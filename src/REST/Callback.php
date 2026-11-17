<?php
namespace Wp\Resta\REST;

use BackedEnum;
use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use RuntimeException;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Parameter\BoundParameters;

/**
 * ルートの callback の定義。シグニチャ（引数、戻り値型、docblock）を読むための薄い包み。
 *
 * 静的なルート（callback メソッドを持たず、body プロパティで応答を宣言するルート）も、
 * そのプロパティの値を返す callback として扱うので、実行者は「callback が無い」場合を知らなくてよい。
 */
final class Callback
{
    private readonly ReflectionFunction $reflection;

    /**
     * @param ReflectionProperty|null $property fromProperty() で作ったときの、型と docblock の出どころ
     */
    private function __construct(Closure $closure, private readonly ?ReflectionProperty $property = null)
    {
        $this->reflection = new ReflectionFunction($closure);
    }

    public static function fromMethod(object $object, string $method): self
    {
        // Closure::fromCallable() はこのクラスのスコープで可視性を判定するので protected な callback で失敗する。
        // ReflectionMethod::getClosure() なら可視性によらず包める
        return new self((new ReflectionMethod($object, $method))->getClosure($object));
    }

    /**
     * プロパティの値を返す引数なしの callback。プロパティの型と docblock（@var）が、戻り値型と @return の代わりになる
     */
    public static function fromProperty(object $object, string $property): self
    {
        $reflection = new ReflectionProperty($object, $property);
        return new self(fn(): mixed => $reflection->getValue($object), $reflection);
    }

    /** @return ReflectionParameter[] */
    public function getParameters(): array
    {
        return $this->reflection->getParameters();
    }

    public function getReturnType(): ?ReflectionType
    {
        if ($this->property !== null) {
            return $this->property->getType();
        }
        return $this->reflection->getReturnType();
    }

    public function getDocComment(): ?string
    {
        $doc = ($this->property ?? $this->reflection)->getDocComment();
        return $doc === false ? null : $doc;
    }

    /**
     * callback を宣言しているクラス。docblock の型名を use 文で解決するときに使う。
     * fromMethod() で作った callback ならそのメソッドのクラス、fromProperty() ならそのプロパティのクラス
     *
     * @return ReflectionClass<object>|null
     */
    public function getDeclaringClass(): ?ReflectionClass
    {
        if ($this->property !== null) {
            return $this->property->getDeclaringClass();
        }
        $scope = $this->reflection->getClosureScopeClass();
        return $scope === null || $scope->getName() === self::class ? null : $scope;
    }

    /**
     * callback の引数を組み立てる。検証はしない（RouteParameters::validate() が済ませている）。
     *
     * - クラス型は、現在のリクエスト（RestaRequestInterface やその実装）ならそれを、それ以外は DI で解決する。名前は見ない
     * - それ以外は URL パラメータ。束縛済みの値に名前があればその値
     * - 無ければ省略された省略可能なパラメータなので、引数を渡さず PHP のデフォルト値に任せる
     *
     * @return array<string, mixed> 引数名 => 値
     */
    public function resolve(BoundParameters $params, RestaRequestInterface $request, Container $container): array
    {
        $args = [];
        foreach ($this->reflection->getParameters() as $param) {
            $type = $param->getType();
            // クラス型の引数は、同名の URL パラメータがあっても型で解決する。名前を先に見ると文字列が渡って TypeError になる
            $isClass = $type instanceof ReflectionNamedType && !$type->isBuiltin() && !is_subclass_of($type->getName(), BackedEnum::class);
            if (!$isClass) {
                // URL パラメータ。省略されたものは渡さず、デフォルト値を invokeArgs() に任せる
                if ($params->has($param->name)) {
                    $args[$param->name] = $params->get($param->name);
                }
                continue;
            }
            $class = $type->getName();
            if ($request instanceof $class) {
                $args[$param->name] = $request;
                continue;
            }
            if (!class_exists($class) && !interface_exists($class)) {
                throw new RuntimeException('cannot resolve type: ' . $class);
            }
            $args[$param->name] = $container->get($class);
        }
        return $args;
    }

    /**
     * @param array<string, mixed> $args resolve() の結果
     */
    public function invoke(array $args): mixed
    {
        return $this->reflection->invokeArgs($args);
    }
}

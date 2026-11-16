<?php
namespace Wp\Resta\REST;

use BackedEnum;
use Closure;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
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
     * 引数は 2 種類だけ。クラス型は変数名ではなく型で決まるので、パラメータと同じ名前の変数でも取り違えない
     * - Request（RestaRequestInterface か、それを実装するクラス）: 現在のリクエスト
     * - それ以外（ビルトイン型と enum）: パラメータ。束縛済みの値があればその値。無ければ省略された
     *   省略可能なパラメータなので、引数を渡さず PHP のデフォルト値に任せる
     *
     * @return array<string, mixed> 引数名 => 値
     */
    public function resolve(BoundParameters $params, RestaRequestInterface $request): array
    {
        $args = [];
        foreach ($this->reflection->getParameters() as $param) {
            $class = self::requestClass($param);
            if ($class === null) {
                if ($params->has($param->name)) {
                    $args[$param->name] = $params->get($param->name);
                }
                continue;
            }
            if (!($request instanceof $class)) {
                // WpRestaRequest を宣言したルートを TestRestaRequest で実行した、など
                throw new LogicException(sprintf(
                    'argument $%s expects %s, but the current request is %s',
                    $param->name,
                    $class,
                    $request::class,
                ));
            }
            $args[$param->name] = $request;
        }
        return $args;
    }

    /**
     * 引数が Request を受け取る宣言なら、そのクラス名。パラメータの宣言なら null。
     * それ以外のクラス型は callback の引数に書けない
     *
     * @return class-string|null
     */
    public static function requestClass(ReflectionParameter $param): ?string
    {
        $type = $param->getType();
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            // クラスを含む union / intersection は、型で 1 つに決められない。同名の URL_PARAMS があっても
            // 渡るのは文字列なので、呼び出し時に TypeError になる。backed enum も、union の中では変換先を
            // 1 つに決められないので同じ。ビルトイン型だけの union はパラメータの側で扱う
            foreach ($type->getTypes() as $member) {
                if (!($member instanceof ReflectionNamedType) || !$member->isBuiltin()) {
                    throw new LogicException(sprintf(
                        'argument $%s: %s cannot be a callback argument. A union or intersection type can contain only built-in types. Declare a single backed enum, or the request as RestaRequestInterface or one of its implementations',
                        $param->name,
                        $type,
                    ));
                }
            }
            return null;
        }
        if (!($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
            return null;
        }
        $class = $type->getName();
        if (is_subclass_of($class, BackedEnum::class)) {
            return null;
        }
        if (is_a($class, RestaRequestInterface::class, true)) {
            return $class;
        }
        throw new LogicException(sprintf(
            'argument $%s: %s cannot be a callback argument. Arguments are URL parameters (built-in types and backed enums) or the request (RestaRequestInterface). %s',
            $param->name,
            $class,
            $class === 'WP_REST_Request'
                ? 'To use WP_REST_Request, declare the argument as WpRestaRequest and call getWpRequest()'
                : 'Receive services in the constructor of the route',
        ));
    }

    /**
     * @param array<string, mixed> $args resolve() の結果
     */
    public function invoke(array $args): mixed
    {
        return $this->reflection->invokeArgs($args);
    }
}

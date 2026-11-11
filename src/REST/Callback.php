<?php
namespace Wp\Resta\REST;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use RuntimeException;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Parameter\BoundParameters;

/**
 * ルートの callback の定義。シグニチャ（引数、戻り値型、docblock）を読むための薄い包み。
 *
 * ルートが callback メソッドを持たない場合も、宣言された body を返す callback として扱うので、
 * 実行者は「callback が無い」場合を知らなくてよい。
 */
final class Callback
{
    private readonly ReflectionFunction $reflection;
    private ?Closure $wrapInvoke = null;

    private function __construct(Closure $closure)
    {
        $this->reflection = new ReflectionFunction($closure);
    }

    /**
     * invoke() の呼び出しを包んだ複製を返す。シグニチャは元の callback のまま。
     * $wrap は callback を実行する closure を受け取り、その戻り値（または例外）をどう扱うかを決める
     *
     * @internal AbstractRoute が非推奨プロパティ（$status）を戻り値の包みに変換するための橋。#59 で消す
     * @param Closure(Closure(): mixed): mixed $wrap
     */
    public function wrapInvoke(Closure $wrap): self
    {
        $clone = clone $this;
        $clone->wrapInvoke = $wrap;
        return $clone;
    }

    public static function fromMethod(object $object, string $method): self
    {
        // Closure::fromCallable() はこのクラスのスコープで可視性を判定するので protected な callback で失敗する。
        // ReflectionMethod::getClosure() なら可視性によらず包める
        return new self((new ReflectionMethod($object, $method))->getClosure($object));
    }

    /** 固定の値を返す引数なしの callback */
    public static function returning(mixed $value): self
    {
        return new self(fn() => $value);
    }

    /** @return ReflectionParameter[] */
    public function getParameters(): array
    {
        return $this->reflection->getParameters();
    }

    public function getReturnType(): ?ReflectionType
    {
        return $this->reflection->getReturnType();
    }

    public function getDocComment(): ?string
    {
        $doc = $this->reflection->getDocComment();
        return $doc === false ? null : $doc;
    }

    /**
     * callback を宣言しているクラス。docblock の型名を use 文で解決するときに使う。
     * fromMethod() で作った callback ならそのメソッドのクラス、returning() なら null
     *
     * @return ReflectionClass<object>|null
     */
    public function getDeclaringClass(): ?ReflectionClass
    {
        $scope = $this->reflection->getClosureScopeClass();
        return $scope === null || $scope->getName() === self::class ? null : $scope;
    }

    /**
     * callback の引数を組み立てる。検証はしない（RouteParameters::validate() が済ませている）。
     *
     * - 束縛済みの値に名前があればその値
     * - 無ければ省略された省略可能なパラメータなので、引数を渡さず PHP のデフォルト値に任せる
     * - クラス型は現在のリクエスト（RestaRequestInterface やその実装）ならそれを、それ以外は DI で解決する
     *
     * @return array<string, mixed> 引数名 => 値
     */
    public function resolve(BoundParameters $params, RestaRequestInterface $request, Container $container): array
    {
        $args = [];
        foreach ($this->reflection->getParameters() as $param) {
            if ($params->has($param->name)) {
                $args[$param->name] = $params->get($param->name);
                continue;
            }
            $type = $param->getType();
            if (!($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
                // 宣言された URL パラメータで、省略されたもの。デフォルト値は invokeArgs() が埋める
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
        $invoke = fn(): mixed => $this->reflection->invokeArgs($args);
        return $this->wrapInvoke === null ? $invoke() : ($this->wrapInvoke)($invoke);
    }
}

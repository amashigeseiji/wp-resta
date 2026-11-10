<?php
namespace Wp\Resta\REST;

use Closure;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionType;

/**
 * ルートの callback の定義。シグニチャ（引数、戻り値型、docblock）を読むための薄い包み。
 *
 * ルートが callback メソッドを持たない場合も、宣言された body を返す callback として扱うので、
 * 実行者は「callback が無い」場合を知らなくてよい。
 */
final class Callback
{
    private readonly ReflectionFunction $reflection;

    private function __construct(Closure $closure)
    {
        $this->reflection = new ReflectionFunction($closure);
    }

    public static function fromMethod(object $object, string $method): self
    {
        return new self(Closure::fromCallable([$object, $method]));
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
}

<?php
namespace Wp\Resta\REST;

use Wp\Resta\REST\Parameter\RouteParameters;

/**
 * ルートの 1 操作: HTTP メソッドに属する宣言（メソッド、callback、callback のシグニチャから導出したパラメータ）。
 *
 * ルート（RouteInterface）は URL に属するものを持ち、リクエストのメソッドで操作を選ぶ。
 * wp-resta 内部の値オブジェクトで、利用者が定義するものではない。利用者はルートクラスに
 * get() / post() などのメソッドか、省略形の callback() を書く。
 */
final class Operation
{
    /** @var list<string> 大文字の HTTP メソッド */
    private readonly array $methodList;

    /**
     * @param string $methods 'GET' や 'GET,POST'（register_rest_route() の methods と同じ形）
     */
    public function __construct(
        private readonly string $methods,
        private readonly Callback $callback,
        private readonly RouteParameters $parameters,
    ) {
        $list = [];
        foreach (explode(',', $methods) as $method) {
            $method = strtoupper(trim($method));
            if ($method !== '') {
                $list[] = $method;
            }
        }
        $this->methodList = $list;
    }

    /** register_rest_route() の methods にそのまま渡せる形 */
    public function getMethods(): string
    {
        return $this->methods;
    }

    /** @return list<string> 大文字の HTTP メソッド */
    public function getMethodList(): array
    {
        return $this->methodList;
    }

    public function handles(string $method): bool
    {
        return in_array(strtoupper($method), $this->methodList, true);
    }

    public function getCallback(): Callback
    {
        return $this->callback;
    }

    /** この操作の URL パラメータの宣言。ROUTE、URL_PARAMS、callback のシグニチャから導出される */
    public function getParameters(): RouteParameters
    {
        return $this->parameters;
    }
}

<?php
namespace Wp\Resta\REST;

use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Parameter\RouteParameters;

interface RouteInterface
{
    public function getNamespace(): string;
    public function setNamespace(string $namespace): void;
    public function getRouteRegex(): string;
    public function getMethods(): string;
    public function invoke(RestaRequestInterface $request): RestaResponseInterface;

    /**
     * URL パラメータの宣言。ROUTE、URL_PARAMS、callback のシグニチャから導出される
     */
    public function getParameters(): RouteParameters;

    /**
     * callback の定義。callback メソッドが無いルートでも宣言された body を返す定義が返る
     */
    public function getCallback(): Callback;

    /**
     * register_rest_route() の args としてそのまま渡せる形のパラメータ定義
     *
     * @return array<string, array<string, mixed>>
     */
    public function getArgs() : array;

    /**
     * JsonSchema
     * @return array<string, mixed>|null
     */
    public function getSchema() : array|null;
    public function getReadableRoute() : string;
}

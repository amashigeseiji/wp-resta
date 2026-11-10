<?php
namespace Wp\Resta\REST;

use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Parameter\BoundParameters;
use Wp\Resta\REST\Parameter\RouteParameters;

interface RouteInterface
{
    public function getNamespace(): string;
    public function setNamespace(string $namespace): void;
    public function getRouteRegex(): string;
    public function getMethods(): string;
    /**
     * 宣言された制約を通った後の、手続き的な検証のフック。拒否は HttpException を投げる
     */
    public function validate(BoundParameters $params): void;

    /**
     * ルート単位の認可。拒否は UnauthorizedException / ForbiddenException を投げる
     */
    public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void;

    /**
     * callback の戻り値（またはエラーボディ）をレスポンスにする。
     * RestaResponseInterface ならそのまま、それ以外はルートに宣言されたステータスとヘッダで包む
     */
    public function respond(mixed $result, ?int $status = null): RestaResponseInterface;

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

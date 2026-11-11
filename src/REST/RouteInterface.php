<?php
namespace Wp\Resta\REST;

use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Parameter\BoundParameters;

/**
 * ルートの宣言。URL に属するもの（namespace、正規表現、既定ヘッダ、スキーマ、フック）を持ち、
 * HTTP メソッドに属するもの（callback とそのパラメータ）は Operation に分かれる。
 */
interface RouteInterface
{
    public function getNamespace(): string;
    public function setNamespace(string $namespace): void;
    public function getRouteRegex(): string;

    /**
     * リクエストの HTTP メソッドに対応する操作。宣言されていなければ null
     */
    public function getOperation(string $method): ?Operation;

    /**
     * 宣言されたすべての操作。登録と OpenAPI の出力に使う
     *
     * @return list<Operation>
     */
    public function getOperations(): array;

    /**
     * 宣言された制約を通った後の、手続き的な検証のフック。拒否は HttpException を投げる
     */
    public function validate(BoundParameters $params): void;

    /**
     * ルート単位の認可。拒否は UnauthorizedException / ForbiddenException を投げる
     */
    public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void;

    /**
     * ルートの既定ヘッダ。どのレスポンス（エラーを含む）にも付き、レスポンス自身の同名ヘッダが優先される
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * JsonSchema
     * @return array<string, mixed>|null
     */
    public function getSchema() : array|null;
    public function getReadableRoute() : string;
}

<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Exception\ForbiddenException;
use Wp\Resta\REST\Exception\UnauthorizedException;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Parameter\BoundParameters;

/**
 * authorize() で認可を宣言するサンプル。ログイン済みで edit_posts を持つユーザーだけが使える
 */
#[RouteMeta(
    description: "認可のサンプルです。edit_posts 権限が必要です",
    tags: ["サンプルAPI"]
)]
class Secret extends AbstractRoute
{
    protected const ROUTE = 'secret';

    public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void
    {
        if (!$principal->isAuthenticated()) {
            throw new UnauthorizedException();
        }
        if (!$principal->can('edit_posts')) {
            throw new ForbiddenException();
        }
    }

    /**
     * @return array{message: string}
     */
    public function callback(): array
    {
        return ['message' => 'This is a secret.'];
    }
}

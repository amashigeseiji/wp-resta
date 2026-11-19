<?php
namespace Test\Resta\E2E\Fixtures\Routes;

use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Authenticated;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Exception\ForbiddenException;

/**
 * リクエストの値を見て認可するサンプル。ログイン済みで、その投稿を編集できるユーザーだけが使える。
 * ログイン済みかどうかはアトリビュートで宣言し、値に依る判定は callback の中に書く
 */
#[RouteMeta(
    description: "認可のサンプルです。ログインと、その投稿の編集権限が必要です",
    tags: ["サンプルAPI"]
)]
class Secret extends AbstractRoute
{
    protected const ROUTE = 'secret/[id]';

    /**
     * @return array{message: string}
     */
    #[Authenticated]
    public function callback(int $id, Principal $principal): array
    {
        if (!$principal->can('edit_post', $id)) {
            throw new ForbiddenException();
        }
        return ['message' => 'This is a secret.'];
    }
}

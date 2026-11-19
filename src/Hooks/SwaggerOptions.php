<?php
namespace Wp\Resta\Hooks;

/**
 * SwaggerHook が OpenAPI のスキーマを「どこで」「誰に」出すかの設定。
 *
 * 変えるときは、設定の dependencies に bind する。bind しなければ既定値で動く。
 *
 * <code>
 * (new Resta)->init([
 *     'hooks' => [SwaggerHook::class],
 *     'dependencies' => [
 *         SwaggerOptions::class => new SwaggerOptions(capability: 'edit_pages'),
 *     ],
 * ]);
 * </code>
 */
class SwaggerOptions
{
    /**
     * @param string $namespace register_rest_route() の第 1 引数。/wp-json/ の直後に来る
     * @param string $route register_rest_route() の第 2 引数。先頭に / を付ける
     * @param string|null $capability スキーマを取得できる capability。null なら誰でも取得できる
     */
    public function __construct(
        public readonly string $namespace = 'resta/v1',
        public readonly string $route = '/schema',
        public readonly ?string $capability = null,
    ) {}
}

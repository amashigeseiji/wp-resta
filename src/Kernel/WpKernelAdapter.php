<?php
namespace Wp\Resta\Kernel;

use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\EventDispatcher\Event;
use Wp\Resta\Hooks\HookProviderInterface;

/**
 * WordPress ライフサイクルをフレームワークに橋渡しする唯一のクラス。
 *
 * WordPress のフック（add_action / add_filter）の呼び出しはここに集約する。
 * WP のアクションを Dispatcher のイベントに変換するだけで、状態は持たない。
 * プロセスの状態（REST サーバーの生成、フックの発火回数）は WP が所有していて
 * wp-resta からは予測できないため、フックが何度発火しても同じことをする。
 *
 * ユーザーが config に登録した HookProvider もここで register() を呼ぶ。
 * HookProvider は移行レイヤーであり、新規実装では Dispatcher を推奨する。
 */
class WpKernelAdapter
{
    /** WP の init アクションに対応するイベント名 */
    public const INIT = 'wp.init';

    /** WP の rest_api_init アクションに対応するイベント名。発火のたびに dispatch される */
    public const REST_API_INIT = 'wp.rest_api_init';

    public function __construct(
        private DispatcherInterface $dispatcher,
        private Config $config,
    ) {}

    /**
     * WordPress フックを登録する。
     * Resta::init() から一度だけ呼ぶ。
     */
    public function install(): void
    {
        // WP の REST API 初期化 → wp.rest_api_init イベント（ルート登録はこのイベントのリスナーが行う）
        \add_action('rest_api_init', fn() =>
            $this->dispatcher->dispatch(new Event(self::REST_API_INIT))
        );

        // WP の init → フレームワーク内の wp.init イベントに変換
        \add_action('init', fn() =>
            $this->dispatcher->dispatch(new Event(self::INIT))
        );

        // URL パラメータをクエリパラメータより優先する
        \add_filter('rest_request_parameter_order', [$this, 'prioritizeUrlParameters'], 10, 2);

        // ユーザー定義の HookProvider を登録（移行レイヤー）
        $container = Container::getInstance();
        foreach ($this->config->hooks as $providerClass) {
            $provider = $container->get($providerClass);
            if (!($provider instanceof HookProviderInterface)) {
                throw new \InvalidArgumentException(
                    sprintf('%s must implement HookProviderInterface', $providerClass)
                );
            }
            $provider->register();
        }
    }

    /**
     * URL パラメータをクエリパラメータより優先する
     *
     * /path/to/123?id=456 のようなリクエストで、埋め込みパラメータ(123)を
     * クエリパラメータ(id=456)より優先させる。
     *
     * このフィルタは WP のすべての WP_REST_Request に掛かるため、適用範囲を絞る。
     * URL スロットが空のリクエスト（dispatch 前の内部リクエスト、[id] のような
     * 名前付きキャプチャを持たないルート）では順序を変えない。
     * WP_REST_Request::set_param() は $order[0] のスロットに書き込み、dispatch 時の
     * set_url_params() は URL スロットを丸ごと代入するので、ここで URL を先頭に
     * 動かすと set_param() した値が消えてしまうため。
     *
     * また、POST / PUT / DELETE や JSON ボディ付きのリクエストでは順序の先頭が
     * 'GET' ではないので、位置に依らず 'URL' を先頭に移動する。
     *
     * @param array<int, string> $order
     * @param \WP_REST_Request|null $request
     * @return array<int, string>
     */
    public function prioritizeUrlParameters(array $order, ?\WP_REST_Request $request = null): array
    {
        if ($request === null || $request->get_url_params() === []) {
            return $order;
        }

        $pos = array_search('URL', $order, true);
        if ($pos === false || $pos === 0) {
            return $order;
        }

        array_splice($order, $pos, 1);
        array_unshift($order, 'URL');

        return $order;
    }
}

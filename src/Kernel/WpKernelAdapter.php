<?php
namespace Wp\Resta\Kernel;

use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\EventDispatcher\Event;
use Wp\Resta\Hooks\HookProviderInterface;
use Wp\Resta\StateMachine\StateMachine;
use Wp\Resta\Kernel\WpRestaServer;

/**
 * WordPress ライフサイクルをフレームワークに橋渡しする唯一のクラス。
 *
 * WordPress のフック（add_action / add_filter）の呼び出しはここに集約する。
 * WP ライフサイクルイベントを Kernel の StateMachine 遷移または
 * Dispatcher イベントに変換することで、フレームワークコアを WP 非依存に保つ。
 *
 * ユーザーが config に登録した HookProvider もここで register() を呼ぶ。
 * HookProvider は移行レイヤーであり、新規実装では Dispatcher を推奨する。
 */
class WpKernelAdapter
{
    public function __construct(
        private Kernel $kernel,
        private StateMachine $sm,
        private DispatcherInterface $dispatcher,
        private Config $config,
    ) {}

    /**
     * WordPress フックを登録する。
     * Resta::init() から一度だけ呼ぶ。
     */
    public function install(): void
    {
        // WP_REST_Server をフレームワーク拡張版に差し替える
        // rest_get_server() の初回呼び出し（rest_api_init の直前）より前に登録する必要がある
        \add_filter('wp_rest_server_class', fn() => WpRestaServer::class, 5);

        // WP の REST API 初期化 → ルート登録の SM 遷移
        \add_action('rest_api_init', fn() =>
            $this->sm->apply($this->kernel, 'registerRoutes')
        );

        // WP の init → フレームワーク内の wp.init イベントに変換
        \add_action('init', fn() =>
            $this->dispatcher->dispatch(new Event('wp.init'))
        );

        // URL パラメータをクエリパラメータより優先する
        \add_filter('rest_request_parameter_order', [$this, 'prioritizeUrlParameters'], 10, 1);

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
     * @param array<int, string> $order
     * @return array<int, string>
     */
    public function prioritizeUrlParameters(array $order): array
    {
        if ($order[0] === 'GET' && $order[1] === 'URL') {
            $order[0] = 'URL';
            $order[1] = 'GET';
        }
        return $order;
    }
}

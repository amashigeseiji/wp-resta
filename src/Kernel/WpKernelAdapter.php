<?php
namespace Wp\Resta\Kernel;

use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\Event;
use Wp\Resta\StateMachine\StateMachine;

/**
 * WordPress ライフサイクルをフレームワークに橋渡しする唯一のクラス。
 *
 * WordPress のフック（add_action / add_filter）の呼び出しはここに集約する。
 * WP ライフサイクルイベントを Kernel の StateMachine 遷移または
 * Dispatcher イベントに変換することで、フレームワークコアを WP 非依存に保つ。
 */
class WpKernelAdapter
{
    public function __construct(
        private Kernel $kernel,
        private StateMachine $sm,
        private Dispatcher $dispatcher,
    ) {}

    /**
     * WordPress フックを登録する。
     * Resta::init() から一度だけ呼ぶ。
     */
    public function install(): void
    {
        // WP の REST API 初期化 → ルート登録の SM 遷移
        \add_action('rest_api_init', fn() =>
            $this->sm->apply($this->kernel, 'registerRoutes')
        );

        // WP の init → フレームワーク内の wp.init イベントに変換
        // Swagger 等、init タイミングで処理したいリスナーはこのイベントを購読する
        \add_action('init', fn() =>
            $this->dispatcher->dispatch(new Event('wp.init'))
        );

        // URL パラメータをクエリパラメータより優先する
        \add_filter('rest_request_parameter_order', [$this, 'prioritizeUrlParameters'], 10, 1);
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

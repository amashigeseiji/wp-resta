<?php
namespace Wp\Resta\Hooks;

use ReflectionClass;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Attributes\AddAction;

/**
 * WordPress フックを宣言的に登録する基底クラス。
 *
 * ## 位置づけ
 *
 * このクラスはレガシー WP コードを wp-resta に移行するための**足場（移行レイヤー）**
 * として提供される。`functions.php` に散在した `add_action`/`add_filter` の呼び出しを
 * オートロード・DI 対応のクラスに引き上げる第一歩として利用できる。
 *
 * **新規実装では {@see \Wp\Resta\EventDispatcher\DispatcherInterface} への
 * リスナー登録を推奨する。** HookProvider の利用はユーザーの自己責任とする。
 *
 * ## 移行パス
 *
 * ```
 * Stage 1（レガシー）
 *   functions.php に require_once × N、グローバル関数
 *
 * Stage 2（HookProvider — このクラス）
 *   オートロード・DI 対応、#[AddAction] / #[AddFilter] で宣言的に登録
 *
 * Stage 3（推奨）
 *   Dispatcher::addListener() でリスナーを登録
 *   WP 非依存、観測可能、テスト容易
 * ```
 *
 * ## 使い方
 *
 * <code>
 * final class MyHookProvider extends HookProvider
 * {
 *     public function __construct(private MyService $service) {}
 *
 *     #[AddAction('init', priority: 10)]
 *     public function onInit(): void
 *     {
 *         $this->service->doSomething();
 *     }
 *
 *     #[AddFilter('the_content', priority: 20, acceptedArgs: 1)]
 *     public function filterContent(string $content): string
 *     {
 *         return $this->service->transform($content);
 *     }
 * }
 * </code>
 *
 * config の `hooks` キーに登録すると DI コンテナ経由でインスタンス化される:
 *
 * <code>
 * (new Resta)->init([
 *     'hooks' => [MyHookProvider::class],
 * ]);
 * </code>
 */
abstract class HookProvider implements HookProviderInterface
{
    public function register(): void
    {
        $reflection = new ReflectionClass($this);

        // public メソッドのみをスキャン（WordPress から呼び出し可能なメソッドに限定）
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // AddFilter アトリビュートの処理
            $filters = $method->getAttributes(AddFilter::class);
            foreach ($filters as $filterAttr) {
                $filter = $filterAttr->newInstance();
                \add_filter(
                    $filter->getHookName(),
                    [$this, $method->getName()],
                    $filter->priority,
                    $filter->acceptedArgs
                );
            }

            // AddAction アトリビュートの処理
            $actions = $method->getAttributes(AddAction::class);
            foreach ($actions as $actionAttr) {
                $action = $actionAttr->newInstance();
                \add_action(
                    $action->getHookName(),
                    [$this, $method->getName()],
                    $action->priority,
                    $action->acceptedArgs
                );
            }
        }
    }
}

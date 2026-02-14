<?php
namespace Wp\Resta\Hooks;

use ReflectionClass;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Attributes\AddAction;

/**
 * Base class for hook providers that use PHP attributes to register
 * WordPress actions and filters.
 *
 * Subclasses declare public methods and annotate them with
 * {@see AddAction} and/or {@see AddFilter}. When {@see HookProvider::register()}
 * is called, this class uses reflection to scan all methods on the subclass,
 * instantiate any attached hook attributes, and call the corresponding
 * WordPress functions (`add_action()` / `add_filter()`) with the settings
 * provided by each attribute.
 *
 * Typical usage:
 *
 * <code>
 * use Wp\Resta\Hooks\HookProvider;
 * use Wp\Resta\Hooks\Attributes\AddAction;
 * use Wp\Resta\Hooks\Attributes\AddFilter;
 *
 * final class MyHookProvider extends HookProvider
 * {
 *     #[AddAction('init', priority: 10)]
 *     public function onInit(): void
 *     {
 *         // Initialization logic.
 *     }
 *
 *     #[AddFilter('the_content', priority: 20, acceptedArgs: 1)]
 *     public function filterContent(string $content): string
 *     {
 *         return $content . ' Extra content.';
 *     }
 * }
 *
 * // During bootstrap:
 * $provider = new MyHookProvider();
 * $provider->register();
 * </code>
 *
 * In most cases, subclasses should not override {@see HookProvider::register()}.
 * If overriding is necessary (for example, to register only a subset of
 * methods or to perform additional setup), the implementation should normally
 * call <code>parent::register()</code> to preserve the attribute-based
 * registration behavior.
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
                    $filter->hook,
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
                    $action->hook,
                    [$this, $method->getName()],
                    $action->priority,
                    $action->acceptedArgs
                );
            }
        }
    }
}

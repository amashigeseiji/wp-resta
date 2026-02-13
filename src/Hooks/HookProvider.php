<?php
namespace Wp\Resta\Hooks;

use ReflectionClass;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Attributes\AddAction;

abstract class HookProvider implements HookProviderInterface
{
    public function register(): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getMethods() as $method) {
            // AddFilter アトリビュートの処理
            $filters = $method->getAttributes(AddFilter::class);
            foreach ($filters as $filterAttr) {
                $filter = $filterAttr->newInstance();
                add_filter(
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
                add_action(
                    $action->hook,
                    [$this, $method->getName()],
                    $action->priority,
                    $action->acceptedArgs
                );
            }
        }
    }
}

<?php
namespace Wp\Resta\EventDispatcher;

use ReflectionClass;
use ReflectionNamedType;
use Wp\Resta\EventDispatcher\Attributes\Listen;

class Dispatcher implements DispatcherInterface
{
    /** @var array<string, EventHandler> */
    private array $listeners = [];

    public function addListener(string $eventName, callable|EventListenerInterface $callback, array $callbackOptions = []): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = new EventHandler($eventName);
        }
        if ($callback instanceof EventListenerInterface) {
            $listener = $callback;
        } else {
            $listener = new EventListener($callback);
        }
        $listener->setOptions($callbackOptions);
        $this->listeners[$eventName]->add($listener);
    }

    /**
     * オブジェクトのパブリックメソッドをスキャンしてリスナーを登録する。
     *
     * 登録ルール（優先順）:
     * 1. #[Listen('event.name')] アトリビュートがあればそのイベント名で登録
     * 2. アトリビュートなし・パラメータが NamedEvent サブクラス → クラス名で自動推論
     * 3. それ以外のメソッドは無視
     */
    public function addSubscriber(object $subscriber): void
    {
        $reflection = new ReflectionClass($subscriber);
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $listenAttrs = $method->getAttributes(Listen::class);
            if (!empty($listenAttrs)) {
                foreach ($listenAttrs as $attr) {
                    $listen = $attr->newInstance();
                    $this->addListener(
                        $listen->eventName,
                        [$subscriber, $method->getName()],
                        ['priority' => $listen->priority]
                    );
                }
                continue;
            }

            // #[Listen] なし → パラメータ型が NamedEvent サブクラスなら自動推論
            $params = $method->getParameters();
            if (count($params) === 1) {
                $type = $params[0]->getType();
                if ($type instanceof ReflectionNamedType
                    && is_subclass_of($type->getName(), NamedEvent::class)
                ) {
                    $this->addListener($type->getName(), [$subscriber, $method->getName()]);
                }
            }
        }
    }

    public function dispatch(Event $event): void
    {
        if (!isset($this->listeners[$event->eventName])) {
            return;
        }
        $this->listeners[$event->eventName]->handle($event);
    }
}

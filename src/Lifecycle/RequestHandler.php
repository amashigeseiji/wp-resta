<?php
namespace Wp\Resta\Lifecycle;

use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\WpRestaRequest;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\RouteInvocationEvent;
use Wp\Resta\StateMachine\TransitionApplier;
use Wp\Resta\StateMachine\TransitionEvent;
use WP_REST_Request;
use WP_REST_Response;

class RequestHandler
{
    public function __construct(
        public TransitionApplier $sm,
        public DispatcherInterface $dispatcher,
    ) {
        $dispatcher->addListener(
            $this->transitionEventName('convert', true),
            [$this, 'onConvert']
        );
        $dispatcher->addListener(
            $this->transitionEventName('invoke', true),
            [$this, 'onInvoke']
        );
        $dispatcher->addListener(
            $this->transitionEventName('invoke'),
            [$this, 'onInvoked']
        );
        $dispatcher->addListener(
            $this->transitionEventName('respond'),
            [$this, 'onRespond']
        );
    }

    public function handle(WP_REST_Request $request, RouteInterface $route): WP_REST_Response
    {
        $req = new Request();
        $req->ctx = new RequestContext($request, $route);
        // アフォーダンスがある限りステートマシンを進める
        while($affordances = $req->affordances()) {
            if (count($affordances) > 1) {
                throw new \InvalidArgumentException('Currently, multiple actions are not supported in request context.');
            }
            $before = $req->currentState();
            $req->doAction($this->sm, $affordances[0]->action);
            $after = $req->currentState();
            // 状態が遷移しなかった場合は合法的な遷移ではない
            if ($before === $after) {
                throw new \RuntimeException('Failed to transition.');
            }
        }
        return $req->ctx->wpResponse;
    }

    public function onConvert(TransitionEvent $event): void
    {
        /** @var Request */
        $subject = $event->subject;
        assert($subject instanceof Request);

        $ctx = $subject->ctx;
        // WordPress Request → RestaRequest
        $restaRequest = WpRestaRequest::fromWpRequest($ctx->wpRequest);
        $ctx->request = $restaRequest;

        // DI container に登録
        Container::getInstance()->bind(WP_REST_Request::class, $ctx->wpRequest);
        Container::getInstance()->bind(RestaRequestInterface::class, $restaRequest);
    }

    public function onInvoke(TransitionEvent $event): void
    {
        /** @var Request */
        $subject = $event->subject;
        assert($subject instanceof Request);
        $ctx = $subject->ctx;
        $response = $ctx->route->invoke($ctx->request);
        $ctx->response = $response;
    }

    public function onInvoked(TransitionEvent $event): void
    {
        /** @var Request */
        $subject = $event->subject;
        assert($subject instanceof Request);
        $ctx = $subject->ctx;

        $invocationEvent = new RouteInvocationEvent($ctx->request, $ctx->route, $ctx->response);
        $this->dispatcher->dispatch($invocationEvent);
        $ctx->response = $invocationEvent->response;
    }

    public function onRespond(TransitionEvent $event): void
    {
        /** @var Request */
        $subject = $event->subject;
        assert($subject instanceof Request);
        $ctx = $subject->ctx;

        // RestaResponse → WordPress REST Response
        $wpResponse = new WP_REST_Response(
            $ctx->response->getData(),
            $ctx->response->getStatusCode()
        );

        foreach ($ctx->response->getHeaders() as $name => $value) {
            $wpResponse->header($name, $value);
        }

        $ctx->wpResponse = $wpResponse;
    }

    private function transitionEventName(string $action, bool $guard = false): string
    {
        $actions = RequestState::actions();

        return $guard
            ? TransitionEvent::guardEventName($actions[$action], $action)
            : TransitionEvent::afterEventName($actions[$action], $action);
    }
}

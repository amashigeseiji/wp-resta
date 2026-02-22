<?php
namespace Wp\Resta\Lifecycle;

use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\WpRestaRequest;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\RouteInvocationEvent;
use Wp\Resta\StateMachine\StateMachine;
use Wp\Resta\StateMachine\TransitionEvent;
use WP_REST_Request;
use WP_REST_Response;

class RequestHandler
{
    private const TRANSTION_EVENTS = [
        RequestState::Received => 'convert',
        RequestState::Prepared => 'invoke',
        RequestState::Invoked => 'respond',
    ];
    public function __construct(
        public StateMachine $stateMachine,
        public DispatcherInterface $dispatcher,
    ) {
        $dispatcher->addListener(
            $this->transitionEventName(RequestState::Received),
            [$this, 'onConvert']
        );

        /*
        $dispatcher->addListener(
            TransitionEvent::guardEventName(RequestState::Prepared, 'invoke'),
            function (TransitionEvent $event) {
                $subject = $event->subject;
                assert($subject instanceof Request);
                if (!$this->auth->check($event->subject->ctx->request)) {
                    $event->stopPropagation(); // invoke をキャンセル → 401 を返す
                }
            }
        );
         */
        $dispatcher->addListener(
            $this->transitionEventName(RequestState::Prepared),
            [$this, 'onInvoke']
        );
        $dispatcher->addListener(
            $this->transitionEventName(RequestState::Invoked),
            [$this, 'onRespond']
        );
    }

    public function handle(WP_REST_Request $request, RouteInterface $route): WP_REST_Response
    {
        $req = new Request();
        $req->ctx = new RequestContext($request, $route);
        $this->stateMachine->apply($req, 'convert');
        $this->stateMachine->apply($req, 'invoke');
        $this->stateMachine->apply($req, 'respond');
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

    public function onRespond(TransitionEvent $event): void
    {
        /** @var Request */
        $subject = $event->subject;
        assert($subject instanceof Request);
        $ctx = $subject->ctx;

        $event = new RouteInvocationEvent($ctx->request, $ctx->route, $ctx->response);
        $this->dispatcher->dispatch($event);
        // RestaResponse → WordPress REST Response
        $wpResponse = new WP_REST_Response(
            $event->response->getData(),
            $event->response->getStatusCode()
        );

        foreach ($event->response->getHeaders() as $name => $value) {
            $wpResponse->header($name, $value);
        }

        $ctx->wpResponse = $wpResponse;
    }

    private function transitionEventName(RequestState $state, bool $guard = false): string
    {
        return $guard
            ? TransitionEvent::guardEventName($state, self::TRANSTION_EVENTS[$state])
            : TransitionEvent::afterEventName($state, self::TRANSTION_EVENTS[$state]);
    }
}

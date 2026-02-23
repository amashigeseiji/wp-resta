<?php
namespace Test\Resta\Unit\StateMachine;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\StateMachine\HasState;
use Wp\Resta\StateMachine\StateMachine;
use Wp\Resta\StateMachine\Transition;
use Wp\Resta\StateMachine\TransitionEvent;
use Wp\Resta\StateMachine\TransitionRegistry;

// テスト用: 複数の遷移先を持つステート
enum CheckState
{
    #[Transition(to: ['to' => CheckState::Approved, 'reject' => CheckState::Rejected], on: 'review')]
    case Pending;

    case Approved;
    case Rejected;
}

class CheckStateSubject implements HasState
{
    private CheckState $state;

    public function __construct(CheckState $initial = CheckState::Pending)
    {
        $this->state = $initial;
    }

    public function currentState(): \UnitEnum
    {
        return $this->state;
    }

    public function applyState(\UnitEnum $state): void
    {
        $this->state = $state;
    }
}

class TransitionTest extends TestCase
{
    // --- Transition::resolve() ---

    public function testResolveSingleEnumReturnsDefaultPath(): void
    {
        $t = new Transition(to: CheckState::Approved, on: 'approve');

        $this->assertSame(CheckState::Approved, $t->resolve('to'));
    }

    public function testResolveSingleEnumThrowsOnNonDefaultPath(): void
    {
        $t = new Transition(to: CheckState::Approved, on: 'approve');

        $this->expectException(\InvalidArgumentException::class);
        $t->resolve('reject');
    }

    public function testResolveArrayReturnsNamedPath(): void
    {
        $t = new Transition(
            to: ['to' => CheckState::Approved, 'reject' => CheckState::Rejected],
            on: 'review'
        );

        $this->assertSame(CheckState::Approved, $t->resolve('to'));
        $this->assertSame(CheckState::Rejected, $t->resolve('reject'));
    }

    public function testResolveArrayThrowsOnUndefinedPath(): void
    {
        $t = new Transition(
            to: ['to' => CheckState::Approved, 'reject' => CheckState::Rejected],
            on: 'review'
        );

        $this->expectException(\InvalidArgumentException::class);
        $t->resolve('unknown');
    }

    // --- TransitionEvent::path でガード内から遷移先を切り替える ---

    public function testGuardCanRedirectTransitionViaPath(): void
    {
        $dispatcher = new Dispatcher();
        $registry   = new TransitionRegistry();
        $registry->registerFromEnum(CheckState::class);
        $sm = new StateMachine($registry, $dispatcher);

        // ガードで path を 'reject' に変更 → Rejected に遷移する
        $dispatcher->addListener(
            TransitionEvent::guardEventName(CheckState::Pending, 'review'),
            function (TransitionEvent $e): void {
                $e->path = 'reject';
            }
        );

        $subject = new CheckStateSubject(CheckState::Pending);
        $sm->apply($subject, 'review');

        $this->assertSame(CheckState::Rejected, $subject->currentState());
    }

    public function testGuardDefaultPathLeadsToDefaultTransition(): void
    {
        $dispatcher = new Dispatcher();
        $registry   = new TransitionRegistry();
        $registry->registerFromEnum(CheckState::class);
        $sm = new StateMachine($registry, $dispatcher);

        // ガードで path を変えない → デフォルト 'to' (Approved) に遷移
        $dispatcher->addListener(
            TransitionEvent::guardEventName(CheckState::Pending, 'review'),
            function (TransitionEvent $e): void {
                // path は 'to' のまま
            }
        );

        $subject = new CheckStateSubject(CheckState::Pending);
        $sm->apply($subject, 'review');

        $this->assertSame(CheckState::Approved, $subject->currentState());
    }

    public function testAfterEventReflectsActualTransitionTarget(): void
    {
        $dispatcher = new Dispatcher();
        $registry   = new TransitionRegistry();
        $registry->registerFromEnum(CheckState::class);
        $sm = new StateMachine($registry, $dispatcher);

        // path を 'reject' にリダイレクト
        $dispatcher->addListener(
            TransitionEvent::guardEventName(CheckState::Pending, 'review'),
            fn(TransitionEvent $e) => ($e->path = 'reject')
        );

        /** @var TransitionEvent|null $afterEvent */
        $afterEvent = null;
        $dispatcher->addListener(
            TransitionEvent::afterEventName(CheckState::Pending, 'review'),
            function (TransitionEvent $e) use (&$afterEvent): void {
                $afterEvent = $e;
            }
        );

        $subject = new CheckStateSubject(CheckState::Pending);
        $sm->apply($subject, 'review');

        // after イベントの to は実際の遷移先 (Rejected)
        $this->assertNotNull($afterEvent);
        $this->assertSame(CheckState::Rejected, $afterEvent->to);
    }
}

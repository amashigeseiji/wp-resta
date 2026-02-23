<?php
namespace Test\Resta\Unit\StateMachine;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\StateMachine\HasState;
use Wp\Resta\StateMachine\StateMachine;
use Wp\Resta\StateMachine\Transition;
use Wp\Resta\StateMachine\TransitionEvent;
use Wp\Resta\StateMachine\TransitionRegistry;

enum DoorState
{
    #[Transition(to: DoorState::Open, on: 'open')]
    case Closed;

    #[Transition(to: DoorState::Closed, on: 'close')]
    #[Transition(to: DoorState::Locked, on: 'lock')]
    case Open;

    #[Transition(to: DoorState::Open, on: 'unlock')]
    case Locked;
}

class Door implements HasState
{
    private DoorState $state;

    public function __construct(DoorState $initial = DoorState::Closed)
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

class StateMachineTest extends TestCase
{
    private StateMachine $sm;

    protected function setUp(): void
    {
        parent::setUp();
        $registry = new TransitionRegistry();
        $registry->registerFromEnum(DoorState::class);
        $this->sm = new StateMachine($registry);
    }

    public function testApplyTransitionsToNextState(): void
    {
        $door = new Door(DoorState::Closed);

        $this->sm->apply($door, 'open');

        $this->assertSame(DoorState::Open, $door->currentState());
    }

    public function testApplyChainedTransitions(): void
    {
        $door = new Door(DoorState::Closed);

        $this->sm->apply($door, 'open');
        $this->sm->apply($door, 'lock');

        $this->assertSame(DoorState::Locked, $door->currentState());
    }

    public function testApplyThrowsOnInvalidActionForCurrentState(): void
    {
        $door = new Door(DoorState::Closed);

        $this->expectException(\InvalidArgumentException::class);

        $this->sm->apply($door, 'lock'); // Closed から lock はできない
    }

    public function testApplyThrowsOnUnknownAction(): void
    {
        $door = new Door(DoorState::Closed);

        $this->expectException(\InvalidArgumentException::class);

        $this->sm->apply($door, 'fly');
    }

    public function testApplyDoesNotChangeStateOnInvalidTransition(): void
    {
        $door = new Door(DoorState::Closed);

        try {
            $this->sm->apply($door, 'lock');
        } catch (\InvalidArgumentException) {}

        // 状態が変わっていないことを確認
        $this->assertSame(DoorState::Closed, $door->currentState());
    }

    // --- Dispatcher 統合 ---

    public function testDispatcherIsOptional(): void
    {
        // Dispatcher なしでも apply() は通常通り動作する
        $door = new Door(DoorState::Closed);
        $this->sm->apply($door, 'open');
        $this->assertSame(DoorState::Open, $door->currentState());
    }

    public function testAfterEventIsDispatchedOnSuccessfulTransition(): void
    {
        $dispatcher = new Dispatcher();
        $registry   = new TransitionRegistry();
        $registry->registerFromEnum(DoorState::class);
        $sm = new StateMachine($registry, $dispatcher);

        /** @var TransitionEvent|null $received */
        $received = null;
        $dispatcher->addListener(
            TransitionEvent::afterEventName(DoorState::Closed, 'open'),
            function (TransitionEvent $e) use (&$received) { $received = $e; }
        );

        $door = new Door(DoorState::Closed);
        $sm->apply($door, 'open');

        $this->assertNotNull($received);
        $this->assertSame(DoorState::Closed, $received->from);
        $this->assertSame(DoorState::Open, $received->to);
        $this->assertSame('open', $received->action);
        $this->assertSame($door, $received->subject);
    }

    public function testGuardEventIsDispatchedBeforeTransition(): void
    {
        $dispatcher = new Dispatcher();
        $registry   = new TransitionRegistry();
        $registry->registerFromEnum(DoorState::class);
        $sm = new StateMachine($registry, $dispatcher);

        $guardFired = false;
        $dispatcher->addListener(
            TransitionEvent::guardEventName(DoorState::Closed, 'open'),
            function (TransitionEvent $e) use (&$guardFired) {
                $guardFired = true;
                // ガード段階ではまだ状態は変わっていない
                $this->assertSame(DoorState::Closed, $e->subject->currentState());
            }
        );

        $door = new Door(DoorState::Closed);
        $sm->apply($door, 'open');

        $this->assertTrue($guardFired);
        $this->assertSame(DoorState::Open, $door->currentState());
    }

    public function testStopPropagationInGuardDoNotCancelsTransition(): void
    {
        $dispatcher = new Dispatcher();
        $registry   = new TransitionRegistry();
        $registry->registerFromEnum(DoorState::class);
        $sm = new StateMachine($registry, $dispatcher);

        $dispatcher->addListener(
            TransitionEvent::guardEventName(DoorState::Closed, 'open'),
            function (TransitionEvent $e) { $e->stopPropagation(); }
        );

        $afterFired = false;
        $dispatcher->addListener(
            TransitionEvent::afterEventName(DoorState::Closed, 'open'),
            function () use (&$afterFired) { $afterFired = true; }
        );

        $door = new Door(DoorState::Closed);
        $sm->apply($door, 'open');

        // ガードで stopPropagation しても状態は変化する
        $this->assertSame(DoorState::Open, $door->currentState());
        $this->assertTrue($afterFired);
    }

    public function testEventNamesFollowClassDoubleColonActionConvention(): void
    {
        $expectedGuard = DoorState::class . '::open.guard';
        $expectedAfter = DoorState::class . '::open';

        $this->assertSame($expectedGuard, TransitionEvent::guardEventName(DoorState::Closed, 'open'));
        $this->assertSame($expectedAfter, TransitionEvent::afterEventName(DoorState::Closed, 'open'));
    }

    public function testKernelStateEnumRegistersCorrectly(): void
    {
        $registry = new TransitionRegistry();
        $registry->registerFromEnum(\Wp\Resta\Kernel\KernelState::class);
        $sm = new StateMachine($registry);

        $kernel = new class implements HasState {
            private \Wp\Resta\Kernel\KernelState $state = \Wp\Resta\Kernel\KernelState::Booting;
            public function currentState(): \UnitEnum { return $this->state; }
            public function applyState(\UnitEnum $state): void { $this->state = $state; }
        };

        $sm->apply($kernel, 'boot');
        $this->assertSame(\Wp\Resta\Kernel\KernelState::Bootstrapped, $kernel->currentState());

        $sm->apply($kernel, 'registerRoutes');
        $this->assertSame(\Wp\Resta\Kernel\KernelState::RoutesRegistered, $kernel->currentState());
    }
}

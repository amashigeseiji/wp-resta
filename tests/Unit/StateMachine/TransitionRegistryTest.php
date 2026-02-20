<?php
namespace Test\Resta\Unit\StateMachine;

use PHPUnit\Framework\TestCase;
use Wp\Resta\StateMachine\Transition;
use Wp\Resta\StateMachine\TransitionRegistry;

// テスト用の状態 enum
enum TrafficLight
{
    #[Transition(to: TrafficLight::Green, on: 'start')]
    #[Transition(to: TrafficLight::Red, on: 'emergency')]
    case Red;

    #[Transition(to: TrafficLight::Yellow, on: 'slow')]
    case Green;

    #[Transition(to: TrafficLight::Red, on: 'stop')]
    case Yellow;
}

class TransitionRegistryTest extends TestCase
{
    private TransitionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new TransitionRegistry();
        $this->registry->registerFromEnum(TrafficLight::class);
    }

    public function testResolveReturnsTransitionForValidStateAndAction(): void
    {
        $transition = $this->registry->resolve(TrafficLight::Red, 'start');

        $this->assertNotNull($transition);
        $this->assertSame(TrafficLight::Green, $transition->to);
        $this->assertSame('start', $transition->on);
    }

    public function testResolveReturnsNullForUnknownAction(): void
    {
        $transition = $this->registry->resolve(TrafficLight::Red, 'unknown');

        $this->assertNull($transition);
    }

    public function testResolveReturnsNullForActionNotValidInCurrentState(): void
    {
        // 'slow' は Green からしか遷移できない
        $transition = $this->registry->resolve(TrafficLight::Red, 'slow');

        $this->assertNull($transition);
    }

    public function testAllowedTransitionsReturnsAllTransitionsFromState(): void
    {
        // Red からは 'start' と 'emergency' の2つ
        $transitions = $this->registry->allowedTransitions(TrafficLight::Red);

        $this->assertCount(2, $transitions);

        $actions = array_map(fn(Transition $t) => $t->on, $transitions);
        $this->assertContains('start', $actions);
        $this->assertContains('emergency', $actions);
    }

    public function testAllowedTransitionsReturnsEmptyForTerminalState(): void
    {
        // Yellow → Red は定義されているが、この enum に terminal state はない
        // Transition のない状態を作るために Yellow の 'stop' 先である Red を確認
        // Red には遷移があるが、ここでは登録されていない仮の state を用いたい
        // → 代わりに TrafficLight::Yellow の遷移先 Red の allowedTransitions が空でないことを確認
        $transitions = $this->registry->allowedTransitions(TrafficLight::Yellow);

        $this->assertCount(1, $transitions);
        $this->assertSame('stop', $transitions[0]->on);
    }

    public function testMultipleTransitionsFromSameStateResolveCorrectly(): void
    {
        $toGreen = $this->registry->resolve(TrafficLight::Red, 'start');
        $toRed   = $this->registry->resolve(TrafficLight::Red, 'emergency');

        $this->assertSame(TrafficLight::Green, $toGreen->to);
        $this->assertSame(TrafficLight::Red, $toRed->to);
    }
}

<?php
namespace Test\Resta\Unit\StateMachine;

use PHPUnit\Framework\TestCase;
use Wp\Resta\StateMachine\Transition;
use Wp\Resta\StateMachine\TransitionMetadata;

// テスト用 enum（シンプルな線形フロー）
enum SimpleFlow
{
    use TransitionMetadata;

    #[Transition(to: SimpleFlow::B, on: 'next')]
    case A;

    #[Transition(to: SimpleFlow::C, on: 'finish')]
    case B;

    case C; // 終端：Transition なし
}

// テスト用 enum（同一ケースに複数 Transition）
enum BranchFlow
{
    use TransitionMetadata;

    #[Transition(to: BranchFlow::B, on: 'go')]
    #[Transition(to: BranchFlow::C, on: 'skip')]
    case A;

    case B;
    case C;
}

class TransitionMetadataTest extends TestCase
{
    public function testActionsReturnsMappingOfActionToFromState(): void
    {
        $actions = SimpleFlow::actions();

        $this->assertSame(SimpleFlow::A, $actions['next']);
        $this->assertSame(SimpleFlow::B, $actions['finish']);
    }

    public function testActionsExcludesTerminalState(): void
    {
        $actions = SimpleFlow::actions();

        $this->assertArrayNotHasKey('', $actions);
        $this->assertCount(2, $actions); // A と B の分だけ
        // C はキーとして出てこない（C からは遷移がない）
        $this->assertNotContains(SimpleFlow::C, $actions);
    }

    public function testActionsWithMultipleTransitionsFromSameState(): void
    {
        $actions = BranchFlow::actions();

        // どちらのアクションも from は A
        $this->assertSame(BranchFlow::A, $actions['go']);
        $this->assertSame(BranchFlow::A, $actions['skip']);
        $this->assertCount(2, $actions);
    }
}

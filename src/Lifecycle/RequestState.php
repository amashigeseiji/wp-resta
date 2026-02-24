<?php
namespace Wp\Resta\Lifecycle;

use Wp\Resta\StateMachine\Transition;
use Wp\Resta\StateMachine\TransitionMetadata;

enum RequestState
{
    use TransitionMetadata;

    #[Transition(to: RequestState::Prepared,  on: 'convert')]
    case Received;

    #[Transition(to: RequestState::Invoked, on: 'invoke')]
    case Prepared;

    #[Transition(to: RequestState::Responded, on: 'respond')]
    case Invoked;

    case Responded;
}

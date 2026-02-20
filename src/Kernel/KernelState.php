<?php
namespace Wp\Resta\Kernel;

use Wp\Resta\StateMachine\Transition;

enum KernelState
{
    #[Transition(to: KernelState::Bootstrapped, on: 'boot')]
    case Booting;

    #[Transition(to: KernelState::RoutesRegistered, on: 'registerRoutes')]
    case Bootstrapped;

    #[Transition(to: KernelState::HandlingRequest, on: 'handleRequest')]
    case RoutesRegistered;

    #[Transition(to: KernelState::ResponseCreated, on: 'createResponse')]
    case HandlingRequest;

    #[Transition(to: KernelState::ResponseSent, on: 'sendResponse')]
    case ResponseCreated;

    case ResponseSent;
}

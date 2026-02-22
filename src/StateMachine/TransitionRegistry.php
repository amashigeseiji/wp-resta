<?php
namespace Wp\Resta\StateMachine;

class TransitionRegistry
{
    /** @var array<string, array<string, Transition>> */
    private array $transitions = [];

    /**
     * @param class-string $enumClass
     */
    public function registerFromEnum(string $enumClass): void
    {
        $reflection = new \ReflectionEnum($enumClass);
        foreach ($reflection->getCases() as $case) {
            /** @var \UnitEnum $from */
            $from = $case->getValue();
            foreach ($case->getAttributes(Transition::class) as $attribute) {
                $transition = $attribute->newInstance();
                $this->transitions[$this->key($from)][$transition->on] = $transition;
            }
        }
    }

    public function resolve(\UnitEnum $from, string $action): ?Transition
    {
        return $this->transitions[$this->key($from)][$action] ?? null;
    }

    /** @return Transition[] */
    public function allowedTransitions(\UnitEnum $from): array
    {
        return array_values($this->transitions[$this->key($from)] ?? []);
    }

    /** @return Affordance[] */
    public function affordancesFrom(\UnitEnum $from): array
    {
        return array_map(
            fn(Transition $t) => new Affordance($t->on, $t->resolve('to')),
            $this->allowedTransitions($from),
        );
    }

    private function key(\UnitEnum $state): string
    {
        return $state::class . '::' . $state->name;
    }
}

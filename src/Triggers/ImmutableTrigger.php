<?php


namespace Umbrellio\Postgres\Triggers;


use JetBrains\PhpStorm\Pure;
use Umbrellio\Postgres\Functions\ImmutableFunction;

class ImmutableTrigger extends BaseTrigger
{
    #[Pure] public function __construct(ImmutableFunction $function)
    {
        parent::__construct($function);
        $this->name = "immutable_{$function->getColumn()}";
        $this->table = $function->getTable();
    }


    public function compile(): string
    {
        return sprintf(
            'create trigger %s %s %s of %s on %s for each row execute function %s()',
            $this->getName(),
            $this->getOrder(),
            implode(' or ', $this->getEvents()),
            $this->getFunction()->getColumn(),
            $this->getTable(),
            $this->getFunction()->getName()
        );
    }
}

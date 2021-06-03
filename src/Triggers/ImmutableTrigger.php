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
}

<?php


namespace Umbrellio\Postgres\Triggers;


use JetBrains\PhpStorm\Pure;
use Umbrellio\Postgres\Functions\OnUpdateFunction;

class OnUpdateTrigger extends BaseTrigger
{
    #[Pure] public function __construct(OnUpdateFunction $function, string $table)
    {
        parent::__construct($function);
        $this->name = $function::NAME . "_table";
        $this->table = $table;
    }
}

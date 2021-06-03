<?php


namespace Umbrellio\Postgres\Triggers;


use JetBrains\PhpStorm\Pure;
use Umbrellio\Postgres\Functions\OnInsertFunction;

class OnInsertTrigger extends BaseTrigger
{
    #[Pure] public function __construct(OnInsertFunction $function, string $table)
    {
        parent::__construct($function);
        $this->name = $function::NAME . "_table";
        $this->table = $table;
    }
}

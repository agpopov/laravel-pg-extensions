<?php


namespace Umbrellio\Postgres\Triggers;


use JetBrains\PhpStorm\Pure;
use Umbrellio\Postgres\Functions\OnDeleteFunction;

class OnDeleteTrigger extends BaseTrigger
{
    #[Pure] public function __construct(OnDeleteFunction $function, string $table)
    {
        parent::__construct($function);
        $this->name = $function::NAME . "_table";
        $this->table = $table;
    }
}

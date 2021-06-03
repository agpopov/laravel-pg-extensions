<?php


namespace Umbrellio\Postgres\Triggers;


use JetBrains\PhpStorm\Pure;
use Umbrellio\Postgres\Functions\TouchFunction;

class TouchTrigger extends BaseTrigger
{
    #[Pure] public function __construct(TouchFunction $function)
    {
        parent::__construct($function);
        $this->name = "touch_parent_by_{$function->getColumnFrom()}";
        $this->table = $function->getTableFrom();
    }
}

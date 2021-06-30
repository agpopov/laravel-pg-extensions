<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Schema\Builders\Indexes\Index;

use Illuminate\Support\Fluent;

class IndexBuilder extends Fluent
{
    public function __call($method, $parameters)
    {
        $command = new IndexPartialBuilder();
        $this->attributes['constraints'] = call_user_func_array([$command, $method], $parameters);
        return $command;
    }
}

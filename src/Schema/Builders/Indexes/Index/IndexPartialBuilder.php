<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Schema\Builders\Indexes\Index;

use Illuminate\Support\Fluent;
use Umbrellio\Postgres\Schema\Builders\WhereBuilderTrait;

class IndexPartialBuilder extends Fluent
{
    use WhereBuilderTrait;
}

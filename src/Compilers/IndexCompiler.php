<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Compilers;

use Illuminate\Database\Schema\Grammars\Grammar;
use Umbrellio\Postgres\Compilers\Traits\WheresBuilder;
use Umbrellio\Postgres\Schema\Blueprint;
use Umbrellio\Postgres\Schema\Builders\Indexes\Index\IndexBuilder;
use Umbrellio\Postgres\Schema\Builders\Indexes\Index\IndexPartialBuilder;

class IndexCompiler
{
    use WheresBuilder;

    public static function compile(
        Grammar $grammar,
        Blueprint $blueprint,
        IndexBuilder $fluent,
        IndexPartialBuilder $command
    ): string {
        $wheres = static::build($grammar, $blueprint, $command);

        return sprintf(
            'create index %s on %s%s (%s) where %s',
            $fluent->get('index'),
            $grammar->wrapTable($blueprint),
            $fluent->get('algorithm') ? ' using ' . $fluent->get('algorithm') : '',
            $grammar->columnize($fluent->get('columns')),
            static::removeLeadingBoolean(implode(' ', $wheres))
        );
    }
}

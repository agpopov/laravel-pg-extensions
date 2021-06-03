<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Umbrellio\Postgres\Functions\ImmutableFunction;
use Umbrellio\Postgres\Triggers\ImmutableTrigger;

class ImmutableCompiler
{

    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $column = ($c = $command->get('column')) instanceof ColumnDefinition ? $c->get('name') : $c;
        $function = new ImmutableFunction($blueprint->getTable(), $column);
        $trigger = new ImmutableTrigger($function);
        $trigger->setOrder('before')->onUpdate();
        return [$function->compile(), $trigger->compile()];
    }

    public static function compileDrop(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $columns = $command->get('columns') ?: (array)$command->get('column');
        $drop = [];
        foreach ($columns as $column) {
            $function = new ImmutableFunction($blueprint->getTable(), $column);
            $trigger = new ImmutableTrigger($function);
            $drop[] = $trigger->compileDrop();
            $drop[] = $function->compileDrop();
        }
        return $drop;
    }

    public static function compileRename(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $rename = [];
        $function = new ImmutableFunction($blueprint->getTable(), $command->get('from'));
        if ($function->exists()) {
            $trigger = new ImmutableTrigger($function);
            $rename[] = $trigger->compileDrop();
            $rename[] = $function->compileDrop();
            $newFunction = new ImmutableFunction($blueprint->getTable(), $command->get('to'));
            $newTrigger = new ImmutableTrigger($newFunction);
            $newTrigger->setOrder('before')->onUpdate();
            $rename[] = $newFunction->compile();
            $rename[] = $newTrigger->compile();
        }

        return $rename;
    }

    public static function compileDropAllFunctions(string $table): array
    {
        $drop = [];
        foreach (ImmutableFunction::getAll($table) as $function) {
            $drop[] = $function->compileDrop();
        }

        return $drop;
    }
}

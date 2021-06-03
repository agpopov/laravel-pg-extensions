<?php


namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Umbrellio\Postgres\Functions\TouchFunction;
use Umbrellio\Postgres\Triggers\TouchTrigger;

class TouchCompiler
{
    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $column = Arr::first($command->get('columns'));
        $function = new TouchFunction($blueprint->getTable(), $command->get('on'), $column, $command->get('references'));
        $trigger = new TouchTrigger($function);
        $trigger->setOrder('after')->onInsert()->onUpdate()->onDelete();
        return [$function->compile(), $trigger->compile()];
    }

    public static function compileDrop(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $columns = $command->get('columns') ?: (array)$command->get('column');
        $drop = [];
        foreach ($columns as $column) {
            foreach (TouchFunction::getAll($blueprint->getTable(), $column) as $function) {
                $trigger = new TouchTrigger($function);
                $drop[] = $trigger->compileDrop();
                $drop[] = $function->compileDrop();
            }
        }
        return $drop;
    }

    public static function compileDropAllFunctions(string $table): array
    {
        $drop = [];
        foreach (TouchFunction::getAll($table) as $function) {
            $drop[] = $function->compileDrop();
        }

        return $drop;
    }

    public static function compileRename(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $rename = [];
        foreach (TouchFunction::getAll($blueprint->getTable(), $command->get('from')) as $function) {
            $trigger = new TouchTrigger($function);
            $rename[] = $trigger->compileDrop();
            $rename[] = $function->compileDrop();
            $newFunction = new TouchFunction($blueprint->getTable(), $function->getTableTo(), $command->get('to'), $function->getColumnTo());
            $newTrigger = new TouchTrigger($newFunction);
            $newTrigger->setOrder('after')->onInsert()->onUpdate()->onDelete();
            $rename[] = $newFunction->compile();
            $rename[] = $newTrigger->compile();
        }

        return $rename;
    }
}

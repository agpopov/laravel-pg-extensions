<?php


namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Umbrellio\Postgres\Functions\OnDeleteFunction;
use Umbrellio\Postgres\Functions\OnInsertFunction;
use Umbrellio\Postgres\Functions\OnUpdateFunction;
use Umbrellio\Postgres\Triggers\OnDeleteTrigger;
use Umbrellio\Postgres\Triggers\OnInsertTrigger;
use Umbrellio\Postgres\Triggers\OnUpdateTrigger;

class WatchCompiler
{


    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command, array $watches = []): array
    {
        $commands = [];
        if ($watches['onUpdate']) {
            $function = OnUpdateFunction::getInstance();
            $trigger = new OnUpdateTrigger($function, $blueprint->getTable());
            $trigger->setOrder('before')->onUpdate();
            if (! $function->exists()) {
                $commands[] = $function->compile();
            }
            $commands[] = $trigger->compile();
        }
        if ($watches['onInsert']) {
            $function = OnInsertFunction::getInstance();
            $trigger = new OnInsertTrigger($function, $blueprint->getTable());
            $trigger->setOrder('before')->onInsert();
            if (! $function->exists()) {
                $commands[] = $function->compile();
            }
            $commands[] = $trigger->compile();
        }
        if ($watches['disallowDelete']) {
            $function = OnDeleteFunction::getInstance();
            $trigger = new OnDeleteTrigger($function, $blueprint->getTable());
            $trigger->setOrder('before')->onDelete();
            if (! $function->exists()) {
                $commands[] = $function->compile();
            }
            $commands[] = $trigger->compile();
        }

        return $commands;
    }

    public static function compileDropInsert(Grammar $grammar, Blueprint $blueprint, Fluent $command): string
    {
        $trigger = new OnInsertTrigger(OnInsertFunction::getInstance(), $blueprint->getTable());
        return $trigger->compileDrop();
    }

    public static function compileDropUpdate(Grammar $grammar, Blueprint $blueprint, Fluent $command): string
    {
        $trigger = new OnUpdateTrigger(OnUpdateFunction::getInstance(), $blueprint->getTable());
        return $trigger->compileDrop();
    }

    public static function compileDropDelete(Grammar $grammar, Blueprint $blueprint, Fluent $command): string
    {
        $trigger = new OnDeleteTrigger(OnDeleteFunction::getInstance(), $blueprint->getTable());
        return $trigger->compileDrop();
    }
}

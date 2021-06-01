<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use JetBrains\PhpStorm\Pure;

class ImmutableCompiler
{
    private static function functionName(string $column, string $table): string
    {
        return "immutable_{$column}_on_$table";
    }

    private static function triggerName(string $column): string
    {
        return "immutable_$column";
    }

    #[Pure] private static function __create(string $column, string $table): array
    {
        return [
            sprintf(
                'create or replace function %s() returns trigger language plpgsql as
                $$
                begin
                    NEW.%s = OLD.%s;
                    return NEW;
                end;
                $$',
                static::functionName($column, $table),
                $column,
                $column,
            ),
            sprintf(
                'create trigger %s before update on %s for each row execute function %s()',
                static::triggerName($column),
                $table,
                static::functionName($column, $table),
            )
        ];
    }

    #[Pure] private static function __drop(string $column, string $table): array
    {
        return [
            sprintf(
                'drop trigger if exists %s on %s',
                static::triggerName($column),
                $table,
            ),
            sprintf(
                'drop function if exists %s cascade',
                static::functionName($column, $table),
            )
        ];
    }

    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $column = ($c = $command->get('column')) instanceof ColumnDefinition ? $c->get('name') : $c;
        return static::__create($column, $blueprint->getTable());
    }

    public static function drop(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $columns = $command->get('columns') ?: (array)$command->get('column');
        $drop = [];
        foreach ($columns as $column) {
            $drop = array_merge($drop, static::__drop($column, $blueprint->getTable()));
        }
        return $drop;
    }

    public static function rename(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $rename = [];
        if (DB::selectOne(
            sprintf(
                "select proname from pg_proc where proname = '%s'",
                static::functionName($command->get('from'), $blueprint->getTable())
            )
        )) {
            $rename = array_merge($rename, static::__drop($command->get('from'), $blueprint->getTable()));
            $rename = array_merge($rename, static::__create($command->get('to'), $blueprint->getTable()));
        }

        return $rename;
    }

    public static function dropTriggerFunctions(string $table): array
    {
        $drop = [];
        foreach (
            DB::select(
                sprintf(
                    "select proname from pg_proc where proname like '%s'",
                    static::functionName('%', $table)
                )
            ) as $function
        ) {
            $drop[] = sprintf('drop function if exists %s cascade', $function->proname);
        }

        return $drop;
    }
}

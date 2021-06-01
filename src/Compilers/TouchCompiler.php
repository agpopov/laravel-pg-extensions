<?php


namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use JetBrains\PhpStorm\Pure;

class TouchCompiler
{
    private static function functionName(string $column, string $childTable, string $parentTable): string
    {
        return "touch_{$parentTable}_by_{$childTable}_$column";
    }

    private static function triggerName(string $parentTable): string
    {
        return "touch_$parentTable";
    }

    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $column = $command->get('columns');
        $column = reset($column);
        $function = static::functionName($column, $blueprint->getTable(), $command->get('on'));
        return [sprintf(
            'create or replace function %s() returns trigger language plpgsql as
                $$
                BEGIN
                   IF row(NEW.*) IS DISTINCT FROM row(OLD.*) THEN
                      UPDATE %s d SET updated_at = LOCALTIMESTAMP WHERE d.%s = NEW.%s;
                   END IF;
                   RETURN NEW;
                END;
                $$',
            $function,
            $grammar->wrapTable($command->get('on')),
            $grammar->wrap($command->get('references')),
            $grammar->wrap($column),
        ),
        sprintf(
            'create trigger %s after update or insert or delete on %s for each row execute function %s()',
            static::triggerName($command->get('on')),
            $grammar->wrapTable($blueprint),
            $function
        )];
    }

    public static function drop(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $columns = $command->get('columns') ?: (array)$command->get('column');
        $drop = [];
        foreach ($columns as $column) {
            $drop = array_merge($drop, static::dropTriggerFunctions($blueprint->getTable(), $column));
        }
        return $drop;
    }

    public static function dropTriggerFunctions(string $table, ?string $column = null): array
    {
        $drop = [];
        foreach (
            DB::select(
                sprintf(
                    "select proname from pg_proc where proname like '%s'",
                    static::functionName($column ?: '%', $table, '%')
                )
            ) as $function
        ) {
            $drop[] = sprintf('drop function if exists %s cascade', $function->proname);
        }

        return $drop;
    }
}

<?php


namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;

class TouchCompiler
{
    private static function functionName(string $childTable, string $parentTable): string
    {
        return "touch_{$parentTable}_from_$childTable";
    }

    private static function triggerName(string $parentTable): string
    {
        return "touch_$parentTable";
    }

    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command): array {
        $function = sprintf(
            'create or replace function %s() returns trigger language plpgsql as
                $$
                BEGIN
                   IF row(NEW.*) IS DISTINCT FROM row(OLD.*) THEN
                      UPDATE %s d SET updated_at = LOCALTIMESTAMP WHERE d.%s = NEW.%s;
                   END IF;
                   RETURN NEW;
                END;
                $$',
            static::functionName($blueprint->getTable(), $command->get('on')),
            $grammar->wrapTable($command->get('on')),
            $grammar->wrap($command->get('references')),
            $grammar->columnize($command->get('columns')),
        );
        $trigger = sprintf(
            'create trigger %s after update or insert or delete on %s for each row execute function %s()',
            static::triggerName($command->get('on')),
            $grammar->wrapTable($blueprint),
            static::functionName($blueprint->getTable(), $command->get('on'))
        );
        return [$function, $trigger];
    }
}

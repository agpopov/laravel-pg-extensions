<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;

class ImmutableCompiler
{
    private static function functionName(string $column, string $table): string
    {
        return "immutable_{$column}_on_$table()";
    }

    private static function triggerName(string $column): string
    {
        return "immutable_$column";
    }

    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $column = ($c = $command->get('column')) instanceof ColumnDefinition ? $c->get('name') : $c;

        return [
            sprintf(
                'create or replace function %s returns trigger AS $$
            begin
                NEW.%s = OLD.%s;
                return NEW;
            end;
            $$ language \'plpgsql\'',
                static::functionName($column, $blueprint->getTable()),
                $column,
                $column,
            ),
            sprintf(
                'create trigger %s before update on %s for each row execute function %s',
                static::triggerName($column),
                $grammar->wrapTable($blueprint),
                static::functionName($column, $blueprint->getTable()),
            )
        ];
    }

    public static function drop(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        return [
            sprintf(
                'drop trigger if exists %s on %s',
                static::triggerName($command->get('column')),
                $grammar->wrapTable($blueprint),
            ),
            sprintf(
                'drop function if exists %s cascade',
                static::functionName($command->get('column'), $blueprint->getTable()),
            )
        ];
    }
}

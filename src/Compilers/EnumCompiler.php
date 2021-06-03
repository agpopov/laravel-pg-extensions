<?php


namespace Umbrellio\Postgres\Compilers;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Umbrellio\Postgres\Schema\Types\PostgresEnumType;

class EnumCompiler
{
    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            "create type %s as enum(%s)",
            PostgresEnumType::getInstance($blueprint->getTable(), $command->get('column'))->getName(),
            $grammar->quoteString($command->get('allowed'))
        );
    }

    public static function drop(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        $columns = $command->get('columns') ?: (array)$command->get('column');
        $drop = [];
        foreach ($columns as $column) {
            $drop[] = sprintf('drop type if exists %s cascade', PostgresEnumType::getInstance($blueprint->getTable(), $column)->getName());
        }
        return $drop;
    }

    public static function rename(Grammar $grammar, Blueprint $blueprint, Fluent $command): array
    {
        if (PostgresEnumType::getInstance($blueprint->getTable(), $command->get('from'))->isExists()) {
            return [
                sprintf(
                    'alter type %s rename to %s',
                    PostgresEnumType::getInstance($blueprint->getTable(), $command->get('from'))->getName(),
                    PostgresEnumType::getInstance($blueprint->getTable(), $command->get('to'))->getName()
                )
            ];
        }

        return [];
    }

    public static function dropAll(string $table): array
    {
        $drop = [];
        /** @var PostgresEnumType $type */
        foreach (PostgresEnumType::getAll($table) as $type) {
            $drop[] = sprintf('drop type if exists %s cascade', $type->getName());
        }

        return $drop;
    }
}

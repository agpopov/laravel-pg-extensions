<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Schema\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar as BasePostgresGrammar;
use Illuminate\Support\Fluent;
use Umbrellio\Postgres\Compilers\AttachPartitionCompiler;
use Umbrellio\Postgres\Compilers\CheckCompiler;
use Umbrellio\Postgres\Compilers\CreateCompiler;
use Umbrellio\Postgres\Compilers\EnumCompiler;
use Umbrellio\Postgres\Compilers\ExcludeCompiler;
use Umbrellio\Postgres\Compilers\ImmutableCompiler;
use Umbrellio\Postgres\Compilers\IndexCompiler;
use Umbrellio\Postgres\Compilers\TouchCompiler;
use Umbrellio\Postgres\Compilers\UniqueCompiler;
use Umbrellio\Postgres\Compilers\WatchCompiler;
use Umbrellio\Postgres\Functions\JsonToArrayFunction;
use Umbrellio\Postgres\Schema\Builders\Constraints\Check\CheckBuilder;
use Umbrellio\Postgres\Schema\Builders\Constraints\Exclude\ExcludeBuilder;
use Umbrellio\Postgres\Schema\Builders\Indexes\Index\IndexBuilder;
use Umbrellio\Postgres\Schema\Builders\Indexes\Index\IndexPartialBuilder;
use Umbrellio\Postgres\Schema\Builders\Indexes\Unique\UniqueBuilder;
use Umbrellio\Postgres\Schema\Builders\Indexes\Unique\UniquePartialBuilder;
use Umbrellio\Postgres\Schema\Types\NumericType;
use Umbrellio\Postgres\Schema\Types\PostgresEnumType;
use Umbrellio\Postgres\Schema\Types\TsRangeType;

class PostgresGrammar extends BasePostgresGrammar
{
    public function compileCreate(Blueprint $blueprint, Fluent $command): array
    {
        $like = $this->getCommandByName($blueprint, 'like');
        $ifNotExists = $this->getCommandByName($blueprint, 'ifNotExists');
        $onUpdate = $this->getCommandByName($blueprint, 'watchUpdate');
        $onInsert = $this->getCommandByName($blueprint, 'watchInsert');
        $disallowDelete = $this->getCommandByName($blueprint, 'disallowDelete');

        $create = CreateCompiler::compile(
            $this,
            $blueprint,
            $this->getColumns($blueprint),
            compact('like', 'ifNotExists')
        );

        $watch = WatchCompiler::compile($this, $blueprint, $command, compact('onUpdate', 'onInsert', 'disallowDelete'));

        return array_merge($create, $watch);
    }

    public function compileDrop(Blueprint $blueprint, Fluent $command): array
    {
        $drop = parent::compileDrop($blueprint, $command);
        return array_merge(
            [$drop],
            TouchCompiler::compileDropAllFunctions($blueprint->getTable()),
            ImmutableCompiler::compileDropAllFunctions($blueprint->getTable()),
            EnumCompiler::dropAll($blueprint->getTable())
        );
    }

    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): array|string
    {
        $drop = parent::compileDropIfExists($blueprint, $command);
        return array_merge(
            [$drop],
            TouchCompiler::compileDropAllFunctions($blueprint->getTable()),
            ImmutableCompiler::compileDropAllFunctions($blueprint->getTable()),
            EnumCompiler::dropAll($blueprint->getTable())
        );
    }

    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection): array
    {
        $rename = parent::compileRenameColumn($blueprint, $command, $connection);
        return array_merge(
            $rename,
            TouchCompiler::compileRename($this, $blueprint, $command),
            ImmutableCompiler::compileRename($this, $blueprint, $command),
            EnumCompiler::rename($this, $blueprint, $command),
        );
    }

    public function compileDropColumn(Blueprint $blueprint, Fluent $command): array
    {
        $drop = parent::compileDropColumn($blueprint, $command);
        return array_merge(
            [$drop],
            TouchCompiler::compileDrop($this, $blueprint, $command),
            ImmutableCompiler::compileDrop($this, $blueprint, $command),
            EnumCompiler::drop($this, $blueprint, $command)
        );
    }

    public function compileAttachPartition(Blueprint $blueprint, Fluent $command): string
    {
        return AttachPartitionCompiler::compile($this, $blueprint, $command);
    }

    public function compileDetachPartition(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s detach partition %s',
            $this->wrapTable($blueprint),
            $command->get('partition')
        );
    }

    public function compileCreateView(/** @scrutinizer ignore-unused */ Blueprint $blueprint, Fluent $command): string
    {
        $materialize = $command->get('materialize') ? 'materialized' : '';
        return implode(
            ' ',
            array_filter(
                [
                    'create',
                    $materialize,
                    'view',
                    $this->wrapTable($command->get('view')),
                    'as',
                    $command->get('select'),
                ]
            )
        );
    }

    public function compileCreateRecursiveView(/** @scrutinizer ignore-unused */ Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create%s view %s(%s) as with recursive %s(%s) as (%s) select %s from %s',
            $command->get('materialize') ? ' materialized' : '',
            $this->wrapTable($command->get('view')),
            $this->columnize($command->get('columns')),
            $this->wrapTable($command->get('view')),
            $this->columnize($command->get('columns')),
            $command->get('select'),
            $this->columnize($command->get('columns')),
            $this->wrapTable($command->get('view'))
        );
    }

    public function compileDropView(/** @scrutinizer ignore-unused */ Blueprint $blueprint, Fluent $command): string
    {
        return 'drop view ' . $this->wrapTable($command->get('view'));
    }

    public function compileViewExists(): string
    {
        return 'select * from information_schema.views where table_schema = ? and table_name = ?';
    }

    public function compileViewDefinition(): string
    {
        return 'select view_definition from information_schema.views where table_schema = ? and table_name = ?';
    }

    public function compileUniquePartial(Blueprint $blueprint, UniqueBuilder $command): string
    {
        $constraints = $command->get('constraints');
        if ($constraints instanceof UniquePartialBuilder) {
            return UniqueCompiler::compile($this, $blueprint, $command, $constraints);
        }
        return $this->compileUnique($blueprint, $command);
    }

    public function compileIndexPartial(Blueprint $blueprint, IndexBuilder $command): string
    {
        $constraints = $command->get('constraints');
        if ($constraints instanceof IndexPartialBuilder) {
            return IndexCompiler::compile($this, $blueprint, $command, $constraints);
        }
        return $this->compileIndex($blueprint, $command);
    }

    public function compileExclude(Blueprint $blueprint, ExcludeBuilder $command): string
    {
        return ExcludeCompiler::compile($this, $blueprint, $command);
    }

    public function compileCheck(Blueprint $blueprint, CheckBuilder $command): string
    {
        return CheckCompiler::compile($this, $blueprint, $command);
    }

    public function compileImmutable(Blueprint $blueprint, Fluent $command): array
    {
        return ImmutableCompiler::compile($this, $blueprint, $command);
    }

    public function compileDropImmutable(Blueprint $blueprint, Fluent $command): array
    {
        return ImmutableCompiler::compileDrop($this, $blueprint, $command);
    }

    public function compileDropWatchInsert(Blueprint $blueprint, Fluent $command): string
    {
        return WatchCompiler::compileDropInsert($this, $blueprint, $command);
    }

    public function compileDropWatchUpdate(Blueprint $blueprint, Fluent $command): string
    {
        return WatchCompiler::compileDropUpdate($this, $blueprint, $command);
    }

    public function compileDropWatchDelete(Blueprint $blueprint, Fluent $command): string
    {
        return WatchCompiler::compileDropDelete($this, $blueprint, $command);
    }

    public function compileCreateJsonToArrayFunction(Blueprint $blueprint, Fluent $command): ?string
    {
        $function = JsonToArrayFunction::getInstance();
        if (! $function->exists()) {
            return $function->compile();
        }

        return null;
    }

    public function compileAddUuidExtension(Blueprint $blueprint, Fluent $command): ?string
    {
        return 'create extension if not exists "uuid-ossp"';
    }

    public function compileForeign(Blueprint $blueprint, Fluent $command): array|string
    {
        $sql = parent::compileForeign($blueprint, $command);

        if ($command->touchParent) {
            $commands = TouchCompiler::compile($this, $blueprint, $command);
            return array_merge([$sql], $commands);
        }

        return $sql;
    }

    public function typeArray(Fluent $column): string
    {
        return "{$column->get('arrayType')}[]";
    }

    protected function typeNumeric(Fluent $column): string
    {
        $type = NumericType::TYPE_NAME;
        $precision = $column->get('precision');
        $scale = $column->get('scale');

        if ($precision && $scale) {
            return "${type}({$precision}, {$scale})";
        }

        if ($precision) {
            return "${type}({$precision})";
        }

        return $type;
    }

    protected function typeTsrange(/** @scrutinizer ignore-unused */ Fluent $column): string
    {
        return TsRangeType::TYPE_NAME;
    }

    protected function typeEnum(Fluent $column): string
    {
        return PostgresEnumType::getInstance($column->get('blueprint')->getTable(), $column->get('name'))->getName();
    }

    public function compileEnum(Blueprint $blueprint, Fluent $command): string
    {
        return EnumCompiler::compile($this, $blueprint, $command);
    }

    public function getFluentCommands(): array
    {
        $fluentCommands = ['Immutable', 'Touch'];
        return array_merge(parent::getFluentCommands(), $fluentCommands);
    }
}

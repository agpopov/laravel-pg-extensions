<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Schema;

use DB;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Umbrellio\Postgres\Schema\Builders\Constraints\Check\CheckBuilder;
use Umbrellio\Postgres\Schema\Builders\Constraints\Exclude\ExcludeBuilder;
use Umbrellio\Postgres\Schema\Builders\Indexes\Index\IndexBuilder;
use Umbrellio\Postgres\Schema\Builders\Indexes\Unique\UniqueBuilder;
use Umbrellio\Postgres\Schema\Definitions\AttachPartitionDefinition;
use Umbrellio\Postgres\Schema\Definitions\CheckDefinition;
use Umbrellio\Postgres\Schema\Definitions\ExcludeDefinition;
use Umbrellio\Postgres\Schema\Definitions\LikeDefinition;
use Umbrellio\Postgres\Schema\Definitions\UniqueDefinition;
use Umbrellio\Postgres\Schema\Definitions\ViewDefinition;
use Umbrellio\Postgres\Schema\Types\PostgresEnumType;

class Blueprint extends BaseBlueprint
{
    protected $commands = [];

    /**
     * @return AttachPartitionDefinition|Fluent
     */
    public function attachPartition(string $partition): Fluent
    {
        return $this->addCommand('attachPartition', compact('partition'));
    }

    public function detachPartition(string $partition): void
    {
        $this->addCommand('detachPartition', compact('partition'));
    }

    /**
     * @return LikeDefinition|Fluent
     */
    public function like(string $table): Fluent
    {
        return $this->addCommand('like', compact('table'));
    }

    public function ifNotExists(): Fluent
    {
        return $this->addCommand('ifNotExists');
    }

    private function filterExistingColumns(array &$columns): void
    {
        foreach ($this->getColumns() as $column) {
            if (array_key_exists($name = $column->get('name'), $columns)) {
                $columns[$name] = false;
            }
        }

        if (in_array(true, $columns)) {
            foreach (Schema::getColumnListing($this->getTable()) as $column) {
                if (array_key_exists($column, $columns)) {
                    $columns[$column] = false;
                }
            }
        }
    }

    public function addWatchColumns(bool $areUserColumnsRequired = true): void
    {
        $columns = [
            'created_at' => true,
            'updated_at' => true,
            'created_by' => true,
            'updated_by' => true,
        ];
        $this->filterExistingColumns($columns);

        if ($columns['created_at']) {
            $this->timestamp('created_at')->useCurrent();
        }
        if ($columns['updated_at']) {
            $this->timestamp('updated_at')->useCurrent();
        }
        if ($columns['created_by']) {
            $column = $this->uuid('created_by');
            if (! $areUserColumnsRequired) {
                $column->nullable();
            }
        }
        if ($columns['updated_by']) {
            $column = $this->uuid('updated_by');
            if (! $areUserColumnsRequired) {
                $column->nullable();
            }
        }
    }

    public function dropWatchColumns(): void
    {
        $this->dropColumn(['created_at', 'updated_at', 'created_by', 'updated_by']);
    }

    public function addSoftDeleteColumns(): void
    {
        $columns = [
            'deleted_at' => true,
            'deleted_by' => true
        ];
        $this->filterExistingColumns($columns);

        if ($columns['deleted_at']) {
            $this->timestamp('deleted_at')->nullable();
        }
        if ($columns['deleted_by']) {
            $this->uuid('deleted_by')->nullable();
        }
    }

    public function dropSoftDeleteColumns(): void
    {
        $this->dropColumn(['deleted_at', 'deleted_by']);
    }

    public function watchUpdate(): Fluent
    {
        $this->addWatchColumns();
        return $this->addCommand('watchUpdate');
    }

    public function watchInsert(): Fluent
    {
        $this->addWatchColumns();
        return $this->addCommand('watchInsert');
    }

    public function watchDelete(): Fluent
    {
        $this->addSoftDeleteColumns();
        return $this->addCommand('disallowDelete');
    }

    public function disallowDelete(): Fluent
    {
        return $this->addCommand('disallowDelete');
    }

    public function dropWatchInsert(): Fluent
    {
        return $this->addCommand('dropWatchInsert');
    }

    public function dropWatchUpdate(): Fluent
    {
        return $this->addCommand('dropWatchUpdate');
    }

    public function dropWatchDelete(): Fluent
    {
        return $this->addCommand('dropWatchDelete');
    }

    public function allowDelete(): Fluent
    {
        return $this->addCommand('dropWatchDelete');
    }

    /**
     * @return UniqueDefinition|UniqueBuilder
     */
    public function uniquePartial(array|string $columns, ?string $index = null, ?string $algorithm = null): Fluent
    {
        $columns = (array)$columns;

        $index = $index ?: $this->createIndexName('unique', $columns);

        return $this->addExtendedCommand(
            UniqueBuilder::class,
            'uniquePartial',
            compact('columns', 'index', 'algorithm')
        );
    }

    /**
     * @return UniqueDefinition|IndexBuilder
     */
    public function indexPartial(array|string $columns, ?string $index = null, ?string $algorithm = null): Fluent
    {
        $columns = (array)$columns;

        $index = $index ?: $this->createIndexName('index', $columns);

        return $this->addExtendedCommand(
            IndexBuilder::class,
            'indexPartial',
            compact('columns', 'index', 'algorithm')
        );
    }

    public function dropUniquePartial($index): Fluent
    {
        return $this->dropIndexCommand('dropIndex', 'unique', $index);
    }

    /**
     * @param array|string $columns
     *
     * @return ExcludeDefinition|ExcludeBuilder
     */
    public function exclude($columns, ?string $index = null): Fluent
    {
        $columns = (array)$columns;

        $index = $index ?: $this->createIndexName('excl', $columns);

        return $this->addExtendedCommand(ExcludeBuilder::class, 'exclude', compact('columns', 'index'));
    }

    /**
     * @param array|string $columns
     *
     * @return CheckDefinition|CheckBuilder
     */
    public function check($columns, ?string $index = null): Fluent
    {
        $columns = (array)$columns;

        $index = $index ?: $this->createIndexName('chk', $columns);

        return $this->addExtendedCommand(CheckBuilder::class, 'check', compact('columns', 'index'));
    }

    public function dropExclude($index): Fluent
    {
        return $this->dropIndexCommand('dropUnique', 'excl', $index);
    }

    public function dropCheck($index): Fluent
    {
        return $this->dropIndexCommand('dropUnique', 'chk', $index);
    }

    public function hasIndex($index, bool $unique = false): bool
    {
        if (is_array($index)) {
            $index = $this->createIndexName($unique === false ? 'index' : 'unique', $index);
        }

        return array_key_exists($index, $this->getSchemaManager()->listTableIndexes($this->getTable()));
    }

    /**
     * @return ViewDefinition|Fluent
     */
    public function createView(string $view, string $select, bool $materialize = false): Fluent
    {
        return $this->addCommand('createView', compact('view', 'select', 'materialize'));
    }

    /**
     * @return ViewDefinition|Fluent
     */
    public function createRecursiveView(string $view, array $columns, string $select, bool $materialize = false): Fluent
    {
        return $this->addCommand('createRecursiveView', compact('view', 'columns', 'select', 'materialize'));
    }

    public function dropView(string $view): Fluent
    {
        return $this->addCommand('dropView', compact('view'));
    }

    /**
     * Almost like 'decimal' type, but can be with variable precision (by default)
     *
     * @return Fluent|ColumnDefinition
     */
    public function numeric(string $column, ?int $precision = null, ?int $scale = null): Fluent
    {
        return $this->addColumn('numeric', $column, compact('precision', 'scale'));
    }

    /**
     * @return Fluent|ColumnDefinition
     */
    public function tsrange(string $column): Fluent
    {
        return $this->addColumn('tsrange', $column);
    }

    /**
     * @return Fluent|ColumnDefinition
     */
    public function array(string $column, string $type): Fluent
    {
        $this->addCommand('createJsonToArrayFunction');
        return $this->addColumn('array', $column, ['arrayType' => $type]);
    }

    /**
     * @return Fluent|ColumnDefinition
     */
    public function primaryUuid(string $column): Fluent
    {
        $this->commands = Arr::prepend($this->commands, $this->createCommand('addUuidExtension'));
        return $this->uuid($column)->primary()->default(DB::raw('uuid_generate_v4()'));
    }

    public function immutable(string $column): Fluent
    {
        return $this->addCommand('immutable', compact('column'));
    }

    public function dropImmutable(string $column): Fluent
    {
        return $this->addCommand('dropImmutable', compact('column'));
    }

    public function enum($column, array $allowed): Fluent
    {
        if (! PostgresEnumType::getInstance($this->getTable(), $column)->exists()) {
            $this->commands = Arr::prepend($this->commands, $this->createCommand('enum', compact('column', 'allowed')));
        }

        return $this->addColumn('enum', $column, ['allowed' => $allowed, 'blueprint' => $this]);
    }

    protected function getSchemaManager(): AbstractSchemaManager
    {
        return Schema::getConnection()->getDoctrineSchemaManager();
    }

    private function addExtendedCommand(string $fluent, string $name, array $parameters = []): Fluent
    {
        $command = new $fluent(array_merge(compact('name'), $parameters));
        $this->commands[] = $command;
        return $command;
    }
}

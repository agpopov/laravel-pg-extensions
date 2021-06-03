<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Schema;

use Closure;
use Illuminate\Database\Schema\PostgresBuilder as BasePostgresBuilder;
use Illuminate\Support\Traits\Macroable;
use Umbrellio\Postgres\Compilers\ImmutableCompiler;
use Umbrellio\Postgres\Compilers\TouchCompiler;
use Umbrellio\Postgres\Functions\JsonToArrayFunction;
use Umbrellio\Postgres\Functions\OnDeleteFunction;
use Umbrellio\Postgres\Functions\OnInsertFunction;
use Umbrellio\Postgres\Functions\OnUpdateFunction;
use Umbrellio\Postgres\Schema\Types\PostgresEnumType;

class Builder extends BasePostgresBuilder
{
    use Macroable;

    public $name;

    public function table($table, Closure $callback)
    {
        foreach (PostgresEnumType::getAll($table) as $type) {
            $this->connection->getSchemaBuilder()->registerCustomDoctrineType($type->getClassName(), $type->getName(), $type->getName());
            $array = clone $type;
            $array->setName('_' . $array->getName());
            $this->connection->getSchemaBuilder()->registerCustomDoctrineType($array->getClassName(), $array->getName(), $array->getName());
        }

        parent::table($table, $callback);
    }

    public function createView(string $view, string $select, $materialize = false): void
    {
        $blueprint = $this->createBlueprint($view);
        $blueprint->createView($view, $select, $materialize);
        $this->build($blueprint);
    }

    public function dropView(string $view): void
    {
        $blueprint = $this->createBlueprint($view);
        $blueprint->dropView($view);
        $this->build($blueprint);
    }

    public function hasView(string $view): bool
    {
        return count(
                $this->connection->selectFromWriteConnection(
                    $this->grammar->compileViewExists(),
                    [
                        $this->connection->getConfig()['schema'],
                        $this->connection->getTablePrefix() . $view,
                    ]
                )
            ) > 0;
    }

    public function getViewDefinition($view): string
    {
        $results = $this->connection->selectFromWriteConnection(
            $this->grammar->compileViewDefinition(),
            [
                $this->connection->getConfig()['schema'],
                $this->connection->getTablePrefix() . $view,
            ]
        );
        return count($results) > 0 ? $results[0]->view_definition : '';
    }

    /**
     * @param string $table
     *
     * @return Blueprint|\Illuminate\Database\Schema\Blueprint
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        return new Blueprint($table, $callback);
    }

    public function dropAllTables()
    {
        $tables = [];

        $excludedTables = $this->connection->getConfig('dont_drop') ?? ['spatial_ref_sys'];

        foreach ($this->getAllTables() as $row) {
            $row = (array)$row;

            $table = reset($row);

            if (! in_array($table, $excludedTables)) {
                $tables[] = $table;

                foreach (TouchCompiler::compileDropAllFunctions($table) as $drop) {
                    $this->connection->statement($drop);
                }

                foreach (ImmutableCompiler::compileDropAllFunctions($table) as $drop) {
                    $this->connection->statement($drop);
                }
            }
        }

        $this->connection->statement(OnUpdateFunction::getInstance()->compileDrop());
        $this->connection->statement(OnDeleteFunction::getInstance()->compileDrop());
        $this->connection->statement(OnInsertFunction::getInstance()->compileDrop());
        $this->connection->statement(JsonToArrayFunction::getInstance()->compileDrop());
        $this->dropAllTypes();

        if (empty($tables)) {
            return;
        }

        $this->connection->statement(
            $this->grammar->compileDropAllTables($tables)
        );
    }
}

<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Schema;

use Closure;
use Illuminate\Database\Schema\PostgresBuilder as BasePostgresBuilder;
use Illuminate\Support\Traits\Macroable;
use Umbrellio\Postgres\Compilers\ImmutableCompiler;
use Umbrellio\Postgres\Compilers\TouchCompiler;

class Builder extends BasePostgresBuilder
{
    use Macroable;

    public $name;

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
            $row = (array) $row;

            $table = reset($row);

            if (! in_array($table, $excludedTables)) {
                $tables[] = $table;

                foreach (TouchCompiler::dropTriggerFunctions($table) as $drop) {
                    $this->connection->statement($drop);
                }

                foreach (ImmutableCompiler::dropTriggerFunctions($table) as $drop) {
                    $this->connection->statement($drop);
                }
            }
        }

        $this->connection->statement('drop function if exists on_update cascade');
        $this->connection->statement('drop function if exists on_delete cascade');
        $this->connection->statement('drop function if exists on_insert cascade');
        $this->connection->statement('drop function if exists json_to_array cascade');

        if (empty($tables)) {
            return;
        }

        $this->connection->statement(
            $this->grammar->compileDropAllTables($tables)
        );
    }
}

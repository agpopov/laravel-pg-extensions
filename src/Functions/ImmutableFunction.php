<?php


namespace Umbrellio\Postgres\Functions;


use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\Pure;

class ImmutableFunction extends BaseFunction
{
    private static function decompileName(string $name, ?string $table = null, ?string $column = null): array
    {
        if (preg_match(
                '/immutable_(' . ($column ?? '[a-z_]+') . ')_on_(' . ($table ?? '[a-z_]+') . ')/',
                $name,
                $matches
            ) !== false) {
            $table = $matches[2];
            $column = $matches[1];

            return [$table, $column];
        }
        return [];
    }

    private static function compileName(string $table, string $column): string
    {
        return "immutable_{$column}_on_$table";
    }

    #[Pure] public function __construct(private string $table, private string $column)
    {
        $this->name = static::compileName($table, $column);
    }

    public function getBody(): string
    {
        return sprintf(
            "NEW.%s = OLD.%s;
                    return NEW;",
            $this->column,
            $this->column
        );
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param string $table
     *
     * @return array|static[]
     */
    public static function getAll(string $table): array
    {
        $functions = [];
        foreach (
            DB::select(
                "select proname from pg_proc where proname like ?",
                [static::compileName($table, '%')]
            ) as $raw
        ) {
            [, $column] = static::decompileName($raw->proname, table: $table);
            $function = new static($table, $column);
            $function->name = $raw->proname;
            $functions[] = $function;
        }

        return $functions;
    }
}

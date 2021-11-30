<?php


namespace Umbrellio\Postgres\Functions;


use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\Pure;

class TouchFunction extends BaseFunction
{
    private static function decompileName(
        string $name,
        ?string $tableFrom = null,
        ?string $tableTo = null,
        ?string $columnFrom = null,
        ?string $columnTo = null
    ): array {
        if (preg_match(
                '/touch_(' . ($tableTo ?? '[a-z_]+') . ')__(' . ($columnTo ?? '[a-z_]+') . ')_from_(' . ($tableFrom ?? '[a-z_]+') . ')__(' . ($columnFrom ?? '[a-z_]+') . ')/',
                $name,
                $matches
            ) > 0) {
            $tableTo = $matches[1];
            $columnTo = $matches[2];
            $tableFrom = $matches[3];
            $columnFrom = $matches[4];
            return [$tableFrom, $tableTo, $columnFrom, $columnTo];
        }
        return [];
    }

    private static function compileName(string $tableFrom, string $tableTo, string $columnFrom, string $columnTo): string
    {
        return "touch_{$tableTo}__{$columnTo}_from_{$tableFrom}__$columnFrom";
    }

    #[Pure] public function __construct(private string $tableFrom, private string $tableTo, private string $columnFrom, private string $columnTo)
    {
        $this->name = static::compileName($tableFrom, $tableTo, $columnFrom, $columnTo);
    }

    public function getBody(): string
    {
        return sprintf(
            "IF row(NEW.*) IS DISTINCT FROM row(OLD.*) THEN
                      UPDATE %s d SET updated_at = LOCALTIMESTAMP WHERE d.%s = NEW.%s;
                   END IF;
                   RETURN NEW;",
            $this->tableTo,
            $this->columnTo,
            $this->columnFrom
        );
    }

    /**
     * @param string $tableFrom
     * @param string|null $columnFrom
     *
     * @return array|static[]
     */
    public static function getAll(string $tableFrom, ?string $columnFrom = null): array
    {
        $functions = [];
        foreach (
            DB::select(
                    "select proname from pg_proc where proname like ?",
                    [static::compileName($tableFrom, '%', $columnFrom ?? '%', '%')]
            ) as $raw
        ) {
            [, $tableTo, $columnFrom, $columnTo] = static::decompileName($raw->proname, tableFrom: $tableFrom, columnFrom: $columnFrom);
            if(is_null($tableTo)) {
                continue;
            }
            $function = new static($tableFrom, $tableTo, $columnFrom, $columnTo);
            $function->name = $raw->proname;
            $functions[] = $function;
        }

        return $functions;
    }

    public function getTableFrom(): string
    {
        return $this->tableFrom;
    }

    public function getTableTo(): string
    {
        return $this->tableTo;
    }

    public function getColumnFrom(): string
    {
        return $this->columnFrom;
    }

    public function getColumnTo(): string
    {
        return $this->columnTo;
    }
}

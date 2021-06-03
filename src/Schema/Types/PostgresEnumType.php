<?php


namespace Umbrellio\Postgres\Schema\Types;


use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostgresEnumType
{
    private string $name;

    public static function getInstance(string $table, string $column): self
    {
        static $types = [];

        if (! isset($types[$table . $column])) {
            $types[$table . $column] = new self($table, $column);
        }

        return $types[$table . $column];
    }

    private static function compileName(string $table = '%', string $column = '%'): string
    {
        return Str::singular($table) . "_{$column}_enum";
    }

    public function __construct(private ?string $table = null, private ?string $column = null)
    {
        $this->name = static::compileName($this->table ?? '%', $this->column ?? '%');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isExists(): bool
    {
        $db = DB::selectOne(
            'select count(*) as cnt from pg_type inner join pg_enum on pg_enum.enumtypid = pg_type.oid where pg_type.typname=?',
            [$this->getName()]
        );

        return $db->cnt > 0;
    }

    public static function getAll(string $table): array
    {
        $types = [];
        foreach (
            DB::select(
                'select pg_type.typname from pg_type inner join pg_enum on pg_enum.enumtypid = pg_type.oid where pg_type.typname like ?',
                [static::compileName($table)]
            ) as $raw
        ) {
            $type = new static();
            $type->setName($raw->typname);
            $types[] = $type;
        }

        return $types;
    }

    public function getClass(): Type
    {
        return eval(
            'return new class extends Doctrine\DBAL\Types\Type {

                public const TYPE_NAME = "' . $this->getName() . '";

                public function getSQLDeclaration(array $fieldDeclaration, Doctrine\DBAL\Platforms\AbstractPlatform $platform): string
                {
                    return self::TYPE_NAME;
                }

                public function getName(): string
                {
                    return self::TYPE_NAME;
                }
            };'
        );
    }

    public function getClassName(): string
    {
        return get_class($this->getClass());
    }

    /**
     * @param string $table
     */
    public function setTable(string $table): void
    {
        $this->table = $table;
        $this->name = static::compileName($this->table, $this->column);
    }

    /**
     * @param string $column
     */
    public function setColumn(string $column): void
    {
        $this->column = $column;
        $this->name = static::compileName($this->table, $this->column);
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

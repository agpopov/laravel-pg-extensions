<?php

declare(strict_types=1);

namespace Umbrellio\Postgres;

use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Events;
use Illuminate\Database\Grammar;
use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Macroable;
use PDO;
use Umbrellio\Postgres\Extensions\AbstractExtension;
use Umbrellio\Postgres\Extensions\Exceptions\ExtensionInvalidException;
use Umbrellio\Postgres\Schema\Builder;
use Umbrellio\Postgres\Schema\Grammars\PostgresGrammar;
use Umbrellio\Postgres\Schema\Subscribers\SchemaAlterTableChangeColumnSubscriber;
use Umbrellio\Postgres\Schema\Types\Int2ArrayType;
use Umbrellio\Postgres\Schema\Types\Int4ArrayType;
use Umbrellio\Postgres\Schema\Types\Int8ArrayType;
use Umbrellio\Postgres\Schema\Types\NumericArrayType;
use Umbrellio\Postgres\Schema\Types\NumericType;
use Umbrellio\Postgres\Schema\Types\TextArrayType;
use Umbrellio\Postgres\Schema\Types\TimestampArrayType;
use Umbrellio\Postgres\Schema\Types\TsRangeType;
use Umbrellio\Postgres\Schema\Types\UuidArrayType;

class PostgresConnection extends BasePostgresConnection
{
    use Macroable;

    public string $name;

    private static array $extensions = [];

    private array $initialTypes = [
        TsRangeType::TYPE_NAME => TsRangeType::class,
        NumericType::TYPE_NAME => NumericType::class,
        UuidArrayType::TYPE_NAME => UuidArrayType::class,
        NumericArrayType::TYPE_NAME => NumericArrayType::class,
        TextArrayType::TYPE_NAME => TextArrayType::class,
        Int2ArrayType::TYPE_NAME => Int2ArrayType::class,
        Int4ArrayType::TYPE_NAME => Int4ArrayType::class,
        Int8ArrayType::TYPE_NAME => Int8ArrayType::class,
        TimestampArrayType::TYPE_NAME => TimestampArrayType::class,
    ];

    /**
     * @throws ExtensionInvalidException
     * @codeCoverageIgnore
     */
    final public static function registerExtension(string $extension): void
    {
        if (!is_subclass_of($extension, AbstractExtension::class)) {
            throw new ExtensionInvalidException(sprintf(
                'Class %s must be implemented from %s',
                $extension,
                AbstractExtension::class
            ));
        }
        self::$extensions[$extension::getName()] = $extension;
    }

    public function getSchemaBuilder(): Builder
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }
        return new Builder($this);
    }

    public function useDefaultPostProcessor(): void
    {
        parent::useDefaultPostProcessor();

        $this->registerExtensions();
        $this->registerInitialTypes();
    }

    public function getDoctrineConnection(): Connection
    {
        $doctrineConnection = parent::getDoctrineConnection();
        $this->overrideDoctrineBehavior($doctrineConnection);
        return $doctrineConnection;
    }

    public function bindValues($statement, $bindings): void
    {
        if ($this->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            foreach ($bindings as $key => $value) {
                $parameter = is_string($key) ? $key : $key + 1;

                $dataType = match (true) {
                    is_bool($value) => PDO::PARAM_BOOL,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };

                $statement->bindValue($parameter, $value, $dataType);
            }
        } else {
            parent::bindValues($statement, $bindings);
        }
    }

    public function prepareBindings(array $bindings): array
    {
        if ($this->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            $grammar = $this->getQueryGrammar();

            foreach ($bindings as $key => $value) {
                if ($value instanceof DateTimeInterface) {
                    $bindings[$key] = $value->format($grammar->getDateFormat());
                }
            }

            return $bindings;
        }

        return parent::prepareBindings($bindings);
    }

    protected function getDefaultSchemaGrammar(): Grammar|PostgresGrammar
    {
        return $this->withTablePrefix(new PostgresGrammar());
    }

    private function registerInitialTypes(): void
    {
        foreach ($this->initialTypes as $type => $typeClass) {
            DB::registerDoctrineType($typeClass, $type, $type);
        }
    }

    /**
     * @codeCoverageIgnore
     */
    private function registerExtensions(): void
    {
        collect(self::$extensions)->each(function ($extension) {
            /** @var AbstractExtension $extension */
            $extension::register();
            foreach ($extension::getTypes() as $type => $typeClass) {
                DB::registerDoctrineType($typeClass, $type, $type);
            }
        });
    }

    private function overrideDoctrineBehavior(Connection $connection): Connection
    {
        $eventManager = $connection->getEventManager();
        if (!$eventManager->hasListeners(Events::onSchemaAlterTableChangeColumn)) {
            $eventManager->addEventSubscriber(new SchemaAlterTableChangeColumnSubscriber());
        }
        $connection
            ->getDatabasePlatform()
            ->setEventManager($eventManager);
        return $connection;
    }
}

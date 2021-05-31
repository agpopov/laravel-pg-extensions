<?php


namespace Umbrellio\Postgres\Compilers;


use Illuminate\Support\Str;

class FunctionsCompiler
{
    public const FUNCTION = [
        'json_to_array' => 'json_to_array',
        'on_update' => 'on_update',
        'on_insert' => 'on_insert',
        'on_delete' => 'on_delete',
    ];

    private static array $created = [];

    public static function compile(string $function): array|string|null
    {
        if (! isset(static::$created[$function])) {
            static::$created[$function] = true;
            $method = "create" . Str::ucfirst($function);
            if (method_exists(static::class, $method)) {
                return static::$method();
            }
        }

        return null;
    }

    public static function createJsonToArrayFunction(): string
    {
        return sprintf(
            'create or replace function %s(_js json)
                    returns text[] language sql immutable parallel safe as
                \'select array(select json_array_elements_text(_js))\'',
            static::FUNCTION['json_to_array']
        );
    }

    public static function createUuidExtension(): string
    {
        return 'create extension if not exists "uuid-ossp"';
    }

    public static function createOnUpdateFunction(): string
    {
        return sprintf(
            "create or replace function %s() returns trigger language plpgsql as
            $$
            BEGIN
                IF row (NEW.*) IS DISTINCT FROM row (OLD.*) THEN
                    NEW.created_at = OLD.created_at;
                    NEW.created_by = OLD.created_by;
                    NEW.updated_at = LOCALTIMESTAMP;

                    IF to_jsonb(NEW) ?? 'deleted_at' AND to_jsonb(NEW) ?? 'deleted_by' THEN
                        IF (NEW.deleted_at IS NOT NULL) THEN
                            IF (OLD.deleted_at IS NULL) THEN

                                IF NEW.deleted_by IS NULL THEN
                                    RAISE EXCEPTION 'null value in column `deleted_by` violates not-null constraint' USING ERRCODE = '23502';
                                END IF;
                                NEW.deleted_at = LOCALTIMESTAMP;

                            ELSE
                                RAISE EXCEPTION 'Can`t change deleted row!' USING ERRCODE = '23001';
                            END IF;

                        ELSE
                            NEW.deleted_by = NULL;
                        END IF;
                    END IF;

                    RETURN NEW;
                ELSE
                    RETURN OLD;
                END IF;
            END;
            $$",
            static::FUNCTION['on_update']
        );
    }

    public static function createOnInsertFunction(): string
    {
        return sprintf(
            "create or replace function %s() returns trigger language plpgsql as
            $$
            BEGIN
                NEW.created_at = LOCALTIMESTAMP;
                NEW.updated_at = LOCALTIMESTAMP;
                IF to_jsonb(NEW) ?? 'deleted_at' AND to_jsonb(NEW) ?? 'deleted_by' THEN
                    IF NEW.deleted_at IS NOT NULL THEN
                        RAISE EXCEPTION 'cant`t create deleted row' USING ERRCODE = '23001';
                    END IF;
                    NEW.deleted_by = NULL;
                END IF;
                RETURN NEW;
            END;
            $$",
            static::FUNCTION['on_insert']
        );
    }

    public static function createOnDeleteFunction(): string
    {
        return sprintf(
            'create or replace function %s() returns trigger language plpgsql as
            $$
            BEGIN
                RAISE EXCEPTION \'Impossible to delete row! Use soft delete.\' USING ERRCODE = \'23001\';
            END;
            $$',
            static::FUNCTION['on_delete']
        );
    }

    public function createTouchParentFunction() {
        \DB::statement(
            'CREATE OR REPLACE FUNCTION on_' . $relationshipTableName . '_update_or_insert()
                RETURNS TRIGGER AS $$
                BEGIN
                   IF row(NEW.*) IS DISTINCT FROM row(OLD.*) THEN
                      UPDATE ' . $dataTableName . ' d SET updated_at = LOCALTIMESTAMP WHERE d.' . $dataKey . ' = NEW.' . $foreignKey . ';
                   END IF;
                   RETURN NEW;
                END;
                $$ language \'plpgsql\';'
        );
    }
}

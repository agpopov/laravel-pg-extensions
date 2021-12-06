<?php


namespace Umbrellio\Postgres\Functions;


class OnInsertFunction extends BaseFunction
{
    use SingletonTrait;

    public const NAME = 'on_insert';

    public function getBody(): string
    {
        return "NEW.created_at = LOCALTIMESTAMP;
            NEW.updated_at = LOCALTIMESTAMP;
            IF to_jsonb(NEW) ?? 'deleted_at' AND to_jsonb(NEW) ?? 'deleted_by' THEN
                IF NEW.deleted_at IS NOT NULL THEN
                    RAISE EXCEPTION 'cant`t create deleted row' USING ERRCODE = '23001';
                END IF;
                NEW.deleted_by = NULL;
            END IF;
            RETURN NEW;";
    }
}

<?php


namespace Umbrellio\Postgres\Functions;


class OnUpdateFunction extends BaseCommonFunction
{
    public const NAME = 'on_update';

    public function getBody(): string
    {
        return "IF row (NEW.*) IS DISTINCT FROM row (OLD.*) THEN
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
        END IF;";
    }
}

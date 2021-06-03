<?php


namespace Umbrellio\Postgres\Functions;


class OnDeleteFunction extends BaseCommonFunction
{
    public const NAME = 'on_delete';

    public function getBody(): string
    {
        return "RAISE EXCEPTION 'Impossible to delete row! Use soft delete.' USING ERRCODE = '23001';";
    }
}

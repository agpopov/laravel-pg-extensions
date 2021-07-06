<?php


namespace Umbrellio\Postgres\Functions;


class OnDeleteFunction extends BaseCommonFunction
{
    public const NAME = 'on_delete';

    public function getBody(): string
    {
        return "raise exception 'Impossible to delete row! Use soft delete.' using errcode = 23001;";
    }
}

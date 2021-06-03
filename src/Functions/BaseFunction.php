<?php


namespace Umbrellio\Postgres\Functions;


use Illuminate\Support\Facades\DB;
use JetBrains\PhpStorm\Pure;

abstract class BaseFunction
{
    protected string $name;

    public function getName(): string
    {
        return $this->name;
    }

    abstract public function getBody(): string;

    #[Pure] public function compileDrop(): string
    {
        return sprintf('drop function if exists %s cascade', $this->getName());
    }

    public function compile(): string
    {
        return sprintf(
            'create or replace function %s() returns trigger language plpgsql as
                $$
                begin
                   %s
                end;
                $$',
            $this->getName(),
            $this->getBody()
        );
    }

    public function exists(): bool
    {
        $raw = DB::selectOne(
            "select count(*) as cnt from pg_proc where proname = ?",
            [$this->getName()]
        );

        return $raw->cnt > 0;
    }
}

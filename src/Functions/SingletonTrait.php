<?php

namespace Umbrellio\Postgres\Functions;

trait SingletonTrait
{
    public static function getInstance(): static
    {
        static $self;

        if (!isset($self)) {
            $self = new static();
        }

        return $self;
    }

    private function __construct()
    {
        $this->name = static::NAME;
    }

    private function __clone()
    {
    }
}
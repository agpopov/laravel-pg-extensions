<?php


namespace Umbrellio\Postgres\Schema\Types;


use Doctrine\DBAL\Platforms\AbstractPlatform;

class ArrayType
{
    public const TYPE_NAME = 'array';

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return static::TYPE_NAME;
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }
}

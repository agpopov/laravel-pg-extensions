<?php

declare(strict_types=1);

namespace Umbrellio\Postgres\Schema\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TsRangeType extends Type
{
    public const TYPE_NAME = 'tsrange';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return static::TYPE_NAME;
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }
}

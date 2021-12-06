<?php


namespace Umbrellio\Postgres\Functions;


use JetBrains\PhpStorm\Pure;

class JsonToArrayFunction extends BaseFunction
{
    use SingletonTrait;

    public const NAME = 'json_to_array';

    #[Pure] public function compile(): string
    {
        return sprintf(
            'create or replace function %s(_js json)
            returns text[] language sql immutable parallel safe as
                   %s',
            $this->getName(),
            $this->getBody()
        );
    }

    public function getBody(): string
    {
        return "'select array(select json_array_elements_text(_js))'";
    }
}

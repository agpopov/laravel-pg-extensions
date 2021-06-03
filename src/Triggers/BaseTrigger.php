<?php


namespace Umbrellio\Postgres\Triggers;


use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use Umbrellio\Postgres\Functions\BaseFunction;

abstract class BaseTrigger
{
    protected string $name;
    protected string $table;

    public function __construct(private BaseFunction $function)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    private string $order;

    private array $events = [
        'update' => false,
        'insert' => false,
        'delete' => false,
    ];

    #[Pure] public function compileDrop(): string
    {
        return sprintf(
            'drop trigger if exists %s on %s',
            $this->getName(),
            $this->getTable(),
        );
    }

    public function compile(): string
    {
        return sprintf(
            'create trigger %s %s %s on %s for each row execute function %s()',
            $this->getName(),
            $this->getOrder(),
            implode(' or ', $this->getEvents()),
            $this->getTable(),
            $this->getFunction()->getName()
        );
    }

    public function getEvents(): array
    {
        return array_keys(array_filter($this->events, fn($value) => $value));
    }

    public function getOrder(): string
    {
        return $this->order;
    }

    public function setOrder(string $order): static
    {
        if (in_array($order, ['after', 'before'])) {
            $this->order = $order;
            return $this;
        }

        throw new InvalidArgumentException('Invalid trigger order.');
    }

    public function onInsert(): static
    {
        $this->events['insert'] = true;
        return $this;
    }

    public function onUpdate(): static
    {
        $this->events['update'] = true;
        return $this;
    }

    public function onDelete(): static
    {
        $this->events['delete'] = true;
        return $this;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getFunction(): BaseFunction
    {
        return $this->function;
    }
}

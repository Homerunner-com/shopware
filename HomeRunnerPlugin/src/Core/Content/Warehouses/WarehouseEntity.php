<?php

namespace HomeRunnerPlugin\Core\Content\Warehouses;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class WarehouseEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $name;

    protected ?string $shorten;


    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): string
    {
        $this->name = $name;
    }

    public function getShorten(): ?string
    {
        return $this->shorten;
    }

    public function setShorten(?string $shorten): void
    {
        $this->shorten = $shorten;
    }
}
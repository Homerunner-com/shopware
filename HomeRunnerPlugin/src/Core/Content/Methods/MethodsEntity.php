<?php

namespace CoolRunnerPlugin\Core\Content\Methods;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MethodsEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $name;

    protected ?string $cps;

    protected ?string $from;

    protected ?string $to;


    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): string
    {
        $this->name = $name;
    }

    public function getCps(): ?string
    {
        return $this->cps;
    }

    public function setCps(?string $cps): void
    {
        $this->cps = $cps;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function setFrom(?string $from): void
    {
        $this->from = $from;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    public function setTo(?string $to): void
    {
        $this->to = $to;
    }

}
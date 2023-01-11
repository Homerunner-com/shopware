<?php

namespace HomeRunnerPlugin\Core\Content\PrintService;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(PrintServiceEntity $entity)
 * @method void               set(string $key, PrintServiceEntity $entity)
 * @method PrintServiceEntity[]    getIterator()
 * @method PrintServiceEntity[]    getElements()
 * @method PrintServiceEntity|null get(string $key)
 * @method PrintServiceEntity|null first()
 * @method PrintServiceEntity|null last()
 */

class PrintServiceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PrintServiceEntity::class;
    }
}
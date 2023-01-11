<?php

namespace HomeRunnerPlugin\Core\Content\Warehouses;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(WarehouseEntity $entity)
 * @method void               set(string $key, WarehouseEntity $entity)
 * @method WarehouseEntity[]    getIterator()
 * @method WarehouseEntity[]    getElements()
 * @method WarehouseEntity|null get(string $key)
 * @method WarehouseEntity|null first()
 * @method WarehouseEntity|null last()
 */

class WarehouseCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WarehouseEntity::class;
    }
}
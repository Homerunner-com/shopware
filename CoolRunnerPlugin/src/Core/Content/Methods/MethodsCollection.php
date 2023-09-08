<?php

namespace CoolRunnerPlugin\Core\Content\Methods;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(MethodsEntity $entity)
 * @method void               set(string $key, MethodsEntity $entity)
 * @method MethodsEntity[]    getIterator()
 * @method MethodsEntity[]    getElements()
 * @method MethodsEntity|null get(string $key)
 * @method MethodsEntity|null first()
 * @method MethodsEntity|null last()
 */

class MethodsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MethodsEntity::class;
    }
}
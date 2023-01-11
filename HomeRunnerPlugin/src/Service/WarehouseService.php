<?php

namespace HomeRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class WarehouseService
{
    private EntityRepositoryInterface $warehouseRepository;

    public function __construct(EntityRepositoryInterface $warehouseRepository)
    {
        $this->warehouseRepository = $warehouseRepository;
    }

    public function getWarehouseById(Context $context, $warehouseId)
    {
        $criteria = new Criteria([$warehouseId]);

        return $this->warehouseRepository->search($criteria, $context)->first();
    }

    public function writeData(Context $context, $name, $shorten)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shorten', $shorten));

        if(empty($this->warehouseRepository->search($criteria, $context)->first())) {
            $this->warehouseRepository->upsert([
                [
                    'name' => $name,
                    'shorten' => $shorten,
                ]
            ], $context);
        }
    }
}
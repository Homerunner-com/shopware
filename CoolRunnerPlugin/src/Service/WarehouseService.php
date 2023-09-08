<?php

namespace CoolRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class WarehouseService
{
    private EntityRepository $warehouseRepository;

    public function __construct(EntityRepository $warehouseRepository)
    {
        $this->warehouseRepository = $warehouseRepository;
    }

    public function getWarehouseById(Context $context, $warehouseId)
    {
        $criteria = new Criteria([$warehouseId]);

        return $this->warehouseRepository->search($criteria, $context)->first();
    }
}
<?php

namespace HomeRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DeliveryService
{
    private EntityRepositoryInterface $deliveryRepository;

    public function __construct(EntityRepositoryInterface $deliveryRepository)
    {
        $this->deliveryRepository = $deliveryRepository;
    }

    public function getDeliveryById($deliveryId, Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $deliveryId));
        $criteria->addAssociation('order');

        return $this->deliveryRepository->search($criteria, $context)->first();
    }

    public function writeData(Context $context, $deliveryId, $package_number)
    {
        $this->deliveryRepository->update([
            [
                'id' => $deliveryId,
                'trackingCodes' => [$package_number]
            ]
        ], $context);
    }
}
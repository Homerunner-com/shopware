<?php

namespace HomeRunnerPlugin\Service;

use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderService
{
    private EntityRepositoryInterface $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function readData(Context $context, $orderId)
    {
        // TODO: Get product id
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('customFields');
        $criteria->addAssociation('lineItems.customFields');
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('deliveries.shippingMethod.customFields');

        return $this->orderRepository->search($criteria, $context)->first();
    }

    public function getOrder($orderId, Context $context)
    {
        $criteria = new Criteria([$orderId]);

        $order = $this->orderRepository->search($criteria, $context)->first();

        if($order instanceof OrderEntity) {
            return $order;
        }

        throw new OrderNotFoundException($orderId);
    }

    public function writeData(Context $context)
    {
//        // TODO: Get product id
//        $this->orderRepository->update([
//            'id' =>  'af41d3a3019147fb8edeabfe99a729b5',
//            'trackingCodes' => 'testtesttest'
//        ], $context);
    }

}
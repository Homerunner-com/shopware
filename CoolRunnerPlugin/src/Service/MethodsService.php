<?php

namespace CoolRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class MethodsService
{
    /** @var EntityRepository $methodsRepository */
    private EntityRepository $methodsRepository;

    public function __construct(EntityRepository $methodsRepository)
    {
        $this->methodsRepository = $methodsRepository;
    }

    public function getMethodById(Context $context, $method_id)
    {
        $criteria = new Criteria([$method_id]);

        return $this->methodsRepository->search($criteria, $context)->first();
    }
}
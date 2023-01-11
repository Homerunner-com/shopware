<?php

namespace HomeRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class MethodsService
{
    /** @var EntityRepositoryInterface $methodsRepository */
    private EntityRepositoryInterface $methodsRepository;

    public function __construct(EntityRepositoryInterface $methodsRepository)
    {
        $this->methodsRepository = $methodsRepository;
    }

    public function getMethodById(Context $context, $method_id)
    {
        $criteria = new Criteria([$method_id]);

        return $this->methodsRepository->search($criteria, $context)->first();
    }

    public function writeData(Context $context, $name, $cps, $from, $to)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('cps', $cps));

        if(empty($this->methodsRepository->search($criteria, $context)->first())) {
            $finalName = explode('(', $name)[0] . '('. $cps .')';
            $this->methodsRepository->upsert([
                [
                    'name' =>  $finalName,
                    'cps' => $cps,
                    'from' => $from,
                    'to' => $to
                ]
            ], $context);
        }
    }
}
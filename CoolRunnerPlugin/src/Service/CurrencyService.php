<?php

namespace CoolRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CurrencyService
{
    private EntityRepository $currencyRepository;

    public function __construct(EntityRepository $currencyRepository)
    {
        $this->currencyRepository = $currencyRepository;
    }

    // Get currency by id
    public function getCurrencyById(Context $context, $currencyId)
    {

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $currencyId));

        return $this->currencyRepository->search($criteria, $context)->first();
    }
}
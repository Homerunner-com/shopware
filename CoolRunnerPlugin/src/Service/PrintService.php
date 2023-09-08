<?php

namespace CoolRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PrintService
{
    /** @var EntityRepository $printersRepository */
    private EntityRepository $printersRepository;

    public function __construct(EntityRepository $printersRepository)
    {
        $this->printersRepository = $printersRepository;
    }

    public function getPrinterById(Context $context, $printerId)
    {
        $criteria = new Criteria([$printerId]);

        return $this->printersRepository->search($criteria, $context)->first();
    }

    public function writeData(Context $context, $name, $alias, $description)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('alias', $alias));

        if(empty($this->printersRepository->search($criteria, $context)->first())) {
            $this->printersRepository->upsert([
                [
                    'name' => $alias,
                    'alias' => $alias,
                    'description' => $description
                ]
            ], $context);
        }
    }
}
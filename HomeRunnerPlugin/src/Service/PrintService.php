<?php

namespace HomeRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PrintService
{
    /** @var EntityRepositoryInterface $printersRepository */
    private EntityRepositoryInterface $printersRepository;

    public function __construct(EntityRepositoryInterface $printersRepository)
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
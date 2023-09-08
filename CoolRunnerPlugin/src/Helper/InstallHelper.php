<?php declare(strict_types=1);

namespace CoolRunnerPlugin\Helper;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class InstallHelper
{
    private $warehouseRepository;
    private $methodsRepository;

    private $printersRepository;

    public function __construct($warehouseRepository, $methodsRepository, $printersRepository)
    {
        $this->warehouseRepository = $warehouseRepository;
        $this->methodsRepository = $methodsRepository;
        $this->printersRepository = $printersRepository;
    }

    public function storeWarehouse(Context $context, $name, $shorten)
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

    public function storeMethod(Context $context, $name, $cps, $from, $to)
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

    public function storePrinter(Context $context, $name, $alias, $description)
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
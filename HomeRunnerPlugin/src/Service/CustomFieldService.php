<?php

namespace HomeRunnerPlugin\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldService
{
    private EntityRepositoryInterface $customFieldRepository;

    public function __construct(EntityRepositoryInterface $customFieldRepository)
    {
        $this->customFieldRepository = $customFieldRepository;
    }

    public function createSWCustomFields(Context $context)
    {
        // Create customs fields
        $this->createCustomFields($context);
    }

    public function createCustomFields(Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'homerunner_customs'));

        if(empty($this->customFieldRepository->search($criteria, $context)->first())) {
            $this->customFieldRepository->create([
                [
                    'name' => 'homerunner_customs',
                    // 'global' => true,
                    'config' => [
                        'label' => [
                            'de-DE' => 'HomeRunner Customs',
                            'en-GB' => 'HomeRunner Customs'
                        ]
                    ],
                    'customFields' => [
                        [
                            'name' => 'homerunner_customs_hscode_from',
                            'label' => "HS Code (From)",
                            'type' => CustomFieldTypes::TEXT,
                            'config' => [
                                'label' => [
                                    'de-DE' => 'HS Code (From)',
                                    'en-GB' => 'HS Code (From)'
                                ]
                            ]
                        ],
                        [
                            'name' => 'homerunner_customs_hscode_to',
                            'label' => "HS Code (To)",
                            'type' => CustomFieldTypes::TEXT,
                            'config' => [
                                'label' => [
                                    'de-DE' => 'HS Code (To)',
                                    'en-GB' => 'HS Code (To)'
                                ]
                            ]
                        ],
                        [
                            'name' => 'homerunner_customs_origin_country',
                            'label' => "Origin Country",
                            'type' => CustomFieldTypes::ENTITY,
                            'config' => [
                                'entity' => 'country',
                                'componentName' => "sw-entity-single-select",
                                'label' => [
                                    'de-DE' => 'Origin Country',
                                    'en-GB' => 'Origin Country'
                                ]
                            ]
                        ]
                    ],
                    'relations' => [[
                        'entityName' => 'product'
                    ]],
                ],
                [
                    'name' => 'homerunner_cps',
                    // 'global' => true,
                    'config' => [
                        'label' => [
                            'de-DE' => 'HomeRunner Methods',
                            'en-GB' => 'HomeRunner Methods'
                        ]
                    ],
                    'customFields' => [
                        [
                            'name' => 'homerunner_methods',
                            'label' => "HomeRunner Methods",
                            'type' => CustomFieldTypes::ENTITY,
                            'config' => [
                                'entity' => 'homerunner_methods',
                                'componentName' => "sw-entity-single-select",
                                'label' => [
                                    'de-DE' => 'HomeRunner Methods',
                                    'en-GB' => 'HomeRunner Methods'
                                ]
                            ]
                        ]
                    ],
                    'relations' => [[
                        'entityName' => 'shipping_method'
                    ]],
                ]
            ], $context);
        }
    }


}
<?php declare(strict_types=1);

namespace CoolRunnerPlugin;

use CoolRunnerPlugin\Service\CustomFieldService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;


class CoolRunnerPlugin extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $check = $customFieldSetRepository->search( (new Criteria())->addFilter(new EqualsFilter('name', 'coolrunner_customs')),$installContext->getContext());

        if($check->getTotal() == 0) {
            $customFieldSetRepository->create([
                [
                    'name' => 'coolrunner_customs',
                    // 'global' => true,
                    'config' => [
                        'label' => [
                            'de-DE' => 'CoolRunner Customs',
                            'en-GB' => 'CoolRunner Customs'
                        ]
                    ],
                    'customFields' => [
                        [
                            'name' => 'coolrunner_customs_hscode_from',
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
                            'name' => 'coolrunner_customs_hscode_to',
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
                            'name' => 'coolrunner_customs_origin_country',
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
                    'name' => 'coolrunner_cps',
                    // 'global' => true,
                    'config' => [
                        'label' => [
                            'de-DE' => 'CoolRunner Methods',
                            'en-GB' => 'CoolRunner Methods'
                        ]
                    ],
                    'customFields' => [
                        [
                            'name' => 'coolrunner_methods',
                            'label' => "CoolRunner Methods",
                            'type' => CustomFieldTypes::ENTITY,
                            'config' => [
                                'entity' => 'coolrunner_methods',
                                'componentName' => "sw-entity-single-select",
                                'label' => [
                                    'de-DE' => 'CoolRunner Methods',
                                    'en-GB' => 'CoolRunner Methods'
                                ]
                            ]
                        ]
                    ],
                    'relations' => [[
                        'entityName' => 'shipping_method'
                    ]],
                ]
            ], $installContext->getContext());
        }
    }
}


<?php

namespace HomeRunnerPlugin\Subscriber;

use HomeRunnerPlugin\Controller\HomeRunnerAPI;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartSubscriber implements EventSubscriberInterface
{
    /** @var SystemConfigService */
    private SystemConfigService $systemConfigService;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var HomeRunnerAPI */
    private HomeRunnerAPI $apiClient;

    public function __construct(SystemConfigService $systemConfigService, LoggerInterface $logger)
    {
        $this->systemConfigService = $systemConfigService;
        $this->logger = $logger;
        $this->apiClient = new HomeRunnerAPI($systemConfigService);
    }

    public static function getSubscribedEvents()
    {
        return [
            CartChangedEvent::class => ['onCartChanged', 10]
        ];
    }

    public function onCartChanged($data) {
        // dd($this->convert($data));
    }

    private function convert($data)
    {
        $finalData = [
            'receiver_name' => '',
            'receiver_address1' => '',
            'receiver_address2' => '',
            'receiver_city' => '',
            'receiver_country' => '',
            'receiver_zip_code' => '',
            'receiver_phone' => '',
            'receiver_email' => '',
            'receiver_company' => '',
            'cart_date' => time(),
            'cart_time' => date('H:i:s'),
            'cart_day' => date('l'),
            'cart_amount' => 0,
            'cart_weight' => 0,
            'cart_currency' => '',
            'cart_subtotal' => 0,
            'cart_items' => []
        ];

        foreach ($data->getCart()->getLineItems() as $lineItem) {
            $finalData['cart_items'][] = [
                'item_name' => $lineItem->getLabel(),
                'item_sku' => $lineItem->getPayload()['productNumber'],
                'item_id' => $lineItem->getId(),
                'item_qty' => $lineItem->getQuantity(),
                'item_price' => $lineItem->getPriceDefinition()->getPrice(),
                'item_weight' => $lineItem->getDeliveryInformation()->getWeight()*1000
            ];

            $finalData['cart_amount'] += $lineItem->getQuantity();
            $finalData['cart_weight'] += ($lineItem->getDeliveryInformation()->getWeight() * $lineItem->getQuantity())*1000;
            $finalData['cart_subtotal'] += ($lineItem->getPriceDefinition()->getPrice() * $lineItem->getQuantity());
        }

        $finalData['cart_weight'] = (int) number_format($finalData['cart_weight'], 0, '', '');

        $response = $this->apiClient->validate($finalData);

        dd($finalData, $response);

        return $response;
    }
}
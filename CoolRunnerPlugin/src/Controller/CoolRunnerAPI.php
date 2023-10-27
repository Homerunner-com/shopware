<?php

namespace CoolRunnerPlugin\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CoolRunnerAPI
{
    /** @var SystemConfigService $systemConfigService */
    private SystemConfigService $systemConfigService;

    /** @var Client $restClient */
    private Client $restClient;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        $this->restClient = new Client();
    }

    public function connect($installation_token, $shop_name, $shop_url)
    {
        $request = new Request(
            'POST',
            'https://api.smartcheckout.coolrunner.dk?activation_token='.$installation_token,
            ['Content-Type' => 'application/json'],
            json_encode([
                'activation_code' => $installation_token,
                'name' => $shop_name,
                'platform' => 'Shopware',
                'version' => '1.0.0',
                'shop_url' => $shop_url,
                'pingback_url' => $shop_url . '/ping'
            ])
        );

        $response = $this->restClient->send($request);

        return json_decode($response->getBody()->getContents());
    }

    public function validate($data)
    {
        $request = new Request(
            'POST',
            'https://api.smartcheckout.coolrunner.dk?shop_token=' . $this->systemConfigService->get('CoolRunnerPlugin.config.shoptoken'),
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );

        $response = $this->restClient->send($request);

        return json_decode($response->getBody()->getContents());
    }

    public function createShipment($order, $country, $shipping_method, $currency, $country_respository, $context, $warehouse = '')
    {
        switch ($this->systemConfigService->get('CoolRunnerPlugin.config.warehouse')) {
            case 'internal':
                return $this->createV3($order, $shipping_method, $currency, $country_respository, $context, $country);
                break;
            case 'external':
                return $this->createWMS($order, $warehouse, $shipping_method, $country_respository, $context, $currency, $country);
                break;
            default:
                return false;
                break;
        };
    }

    private function createV3(OrderEntity $order, $shipping_method, $currency, $country_respository, $context, $country = null)
    {
        $customerAddress = $order->getDeliveries()->first()->getShippingOrderAddress();
        $customerInformation = $order->getOrderCustomer();
        $orderLines = $order->getLineItems();

        $delivery = $order->getDeliveries()->first();

        // Get HomeRunner CPS
        $cps_exploded = explode('_', $shipping_method->cps);
        $carrier = $cps_exploded[0];
        $carrier_product = $cps_exploded[1];
        $carrier_service = $cps_exploded[2];

        $data = [
            'sender' => [
                'name' => $this->systemConfigService->get('CoolRunnerPlugin.config.sendershop'),
                'attention' => '',
                'street1' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderstreet1'),
                'street2' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderstreet2'),
                'zip_code' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderzipcode'),
                'city' => $this->systemConfigService->get('CoolRunnerPlugin.config.sendercity'),
                'country' => $this->systemConfigService->get('CoolRunnerPlugin.config.sendercountry'),
                'phone' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderphone'),
                'email' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderemail')
            ],
            'receiver' => [
                'name' => ($customerAddress->getCompany() != '') ? $customerAddress->getCompany() : $customerAddress->getFirstName() . ' ' . $customerAddress->getLastName(),
                'attention' => ($customerAddress->getCompany() != '') ? $customerAddress->getFirstName() . ' ' . $customerAddress->getLastName() : '',
                'street1' => $customerAddress->getStreet(),
                'street2' => '',
                'zip_code' => $customerAddress->getZipcode(),
                'city' => $customerAddress->getCity(),
                'country' => ($country != null) ? $country->iso : $customerAddress->getCountry()->getIso(),
                'phone' => $customerAddress->getPhoneNumber(),
                'email' => $customerInformation->getEmail(),
                'notify_sms' => $customerAddress->getPhoneNumber(),
                'notify_email' => $customerInformation->getEmail()
            ],
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'weight' => 1000,
            'carrier' => $carrier,
            'carrier_product' => $carrier_product,
            'carrier_service' => $carrier_service,
            'reference' => $order->getOrderNumber(),
            'comment' => '',
            'description' => '',
            'label_format' => $this->systemConfigService->get('CoolRunnerPlugin.config.printformat'),
            'servicepoint_id' => 0,
            'order_lines' => []
        ];

        $totalWeight = 0;
        foreach ($orderLines as $orderLine) {
            /**@var ProductEntity $product*/
            $product = $orderLine->getProduct();

            // Get country
            $country_criteria = new Criteria();
            $country_criteria->addFilter(new EqualsFilter('id', $orderLine->getPayload()['customFields']['coolrunner_customs_origin_country']));
            $country = $country_respository->search($country_criteria, $context)->first();

            $data['order_lines'][] = [
                'qty' => $orderLine->getQuantity(),
                'item_number' => $orderLine->payload['productNumber'],
                'customs' => [
                    'description' => $orderLine->getLabel(),
                    'total_price' => $orderLine->getTotalPrice(),
                    'currency_code' => $currency->isoCode,
                    'sender_tariff' => $orderLine->getPayload()['customFields']['coolrunner_customs_hscode_from'] ?? "",
                    'receiver_tariff' =>  $orderLine->getPayload()['customFields']['coolrunner_customs_hscode_to'] ?? "",
                    'origin_country' =>  $country->iso ?? "",
                    'weight' => ($product->getWeight()*1000)*$orderLine->quantity
                ]
            ];

            $totalWeight += ($product->getWeight()*1000)*$orderLine->quantity;
        }

        if($totalWeight > 0) {
            $data['weight'] = $totalWeight;
        }

        $request = new Request(
            'POST',
            'https://api.coolrunner.dk/v3/shipments',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('CoolRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('CoolRunnerPlugin.config.apitoken'))
            ],
            json_encode($data)
        );

        $response = $this->restClient->send($request);

        return ['body' => json_decode($response->getBody()), 'delivery_id' => $delivery->getId()];
    }

    private function createWMS(OrderEntity $order, $warehouse, $shipping_method, $country_respository, $context, $currency, $country = null)
    {
        $customerAddress = $order->getAddresses()->first();
        $customerInformation = $order->getOrderCustomer();
        $orderLines = $order->getLineItems();

        $delivery = $order->getDeliveries()->first();

        // Get CoolRunner CPS
        $cps_exploded = explode('_', $shipping_method->cps);
        $carrier = $cps_exploded[0];
        $carrier_product = $cps_exploded[1];
        $carrier_service = $cps_exploded[2];

        // TODO: Get phone

        $data = [
            'warehouse' => $warehouse,
            'order_number' => $order->getOrderNumber(),
            'sender' => [
                'name' => $this->systemConfigService->get('CoolRunnerPlugin.config.sendershop'),
                'attention' => '',
                'street1' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderstreet1'),
                'street2' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderstreet2'),
                'zip_code' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderzipcode'),
                'city' => $this->systemConfigService->get('CoolRunnerPlugin.config.sendercity'),
                'country' => $this->systemConfigService->get('CoolRunnerPlugin.config.sendercountry'),
                'phone' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderphone'),
                'email' => $this->systemConfigService->get('CoolRunnerPlugin.config.senderemail')
            ],
            'receiver' => [
                'name' => ($customerAddress->getCompany() != '') ? $customerAddress->getCompany() : $customerAddress->getFirstName() . ' ' . $customerAddress->getLastName(),
                'attention' => ($customerAddress->getCompany() != '') ? $customerAddress->getFirstName() . ' ' . $customerAddress->getLastName() : '',
                'street1' => $customerAddress->getStreet(),
                'street2' => '',
                'zip_code' => $customerAddress->getZipcode(),
                'city' => $customerAddress->getCity(),
                'country' => ($country != null) ? $country->iso : $customerAddress->getCountry()->getIso(),
                'phone' => $customerAddress->getPhoneNumber(),
                'email' => $customerInformation->getEmail(),
                'notify_sms' => $customerAddress->getPhoneNumber(),
                'notify_email' => $customerInformation->getEmail()
            ],
            'length' => 10,
            'width' => 10,
            'height' => 10,
            'weight' => 1000,
            'carrier' => $carrier,
            'carrier_product' => $carrier_product,
            'carrier_service' => $carrier_service,
            'reference' => $order->getOrderNumber(),
            'comment' => '',
            'description' => '',
            'label_format' => $this->systemConfigService->get('CoolRunnerPlugin.config.printformat'),
            'servicepoint_id' => 0,
            'order_lines' => []
        ];

        foreach ($orderLines as $orderLine) {
            /**@var ProductEntity $product*/
            $product = $orderLine->getProduct();

            // Get country
            $country_criteria = new Criteria();
            $country_criteria->addFilter(new EqualsFilter('id', $orderLine->getPayload()['customFields']['coolrunner_customs_origin_country']));
            $country = $country_respository->search($country_criteria, $context)->first();

            $data['order_lines'][] = [
                'qty' => $orderLine->getQuantity(),
                'item_number' => $orderLine->payload['productNumber'],
                'customs' => [
                    'description' => $orderLine->getLabel(),
                    'total_price' => $orderLine->getTotalPrice(),
                    'currency_code' => $currency->isoCode,
                    'sender_tariff' => $orderLine->getPayload()['customFields']['coolrunner_customs_hscode_from'] ?? "",
                    'receiver_tariff' =>  $orderLine->getPayload()['customFields']['coolrunner_customs_hscode_to'] ?? "",
                    'origin_country' =>  $country->iso ?? "",
                    'weight' => ($product->getWeight()*1000)*$orderLine->quantity
                ]
            ];

            $totalWeight += ($product->getWeight()*1000)*$orderLine->quantity;
        }

        $request = new Request(
            'POST',
            'https://api.coolrunner.dk/wms/orders',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('CoolRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('CoolRunnerPlugin.config.apitoken'))
            ],
            json_encode($data)
        );

        $response = $this->restClient->send($request);

        return ['body' => json_decode($response->getBody()), 'delivery_id' => $delivery->getId()];
    }

    public function getPrinters()
    {
        $request = new Request(
            'GET',
            'https://api.coolrunner.dk/printers',
            [
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('CoolRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('CoolRunnerPlugin.config.apitoken'))
            ]
        );

        $response = $this->restClient->send($request);

        return json_decode($response->getBody());
    }

    public function getWarehouses()
    {
        $request = new Request(
            'GET',
            'https://api.coolrunner.dk/wms/warehouses',
            [
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('CoolRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('CoolRunnerPlugin.config.apitoken'))
            ]
        );

        $response = $this->restClient->send($request);

        return json_decode($response->getBody());
    }

    public function printLabel($packageNumber, $printerAlias)
    {
        $data = [
            'package_number' => (string) $packageNumber
        ];

        $request = new Request(
            'POST',
            'https://api.coolrunner.dk/printers/'.$printerAlias.'/print',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('CoolRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('CoolRunnerPlugin.config.apitoken'))
            ],
            json_encode($data)
        );

        $response = $this->restClient->send($request);

        return json_decode($response->getBody());
    }

    public function getMethods()
    {
        $request = new Request(
            'GET',
            'https://api.coolrunner.dk/v3/products/' . $this->systemConfigService->get('CoolRunnerPlugin.config.sendercountry'),
            [
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('CoolRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('CoolRunnerPlugin.config.apitoken'))
            ]
        );

        $response_json = $this->restClient->send($request)->getBody();
        $response = json_decode($response_json);
        $methods = [];

        foreach ($response as $receiver_country => $carriers) {
            foreach ($carriers as $carrier => $carrier_products) {
                foreach ($carrier_products as $product => $carrier_product) {
                    foreach ($carrier_product as $carrier_service) {

                        if(isset($carrier_service->services[0]->code) AND $carrier_service->services[0]->code != "") {
                            $cps = $carrier . '_' . $product . '_' . $carrier_service->services[0]->code;
                        } else {
                            $cps = $carrier . '_' . $product;
                        }

                        $methods[] = [
                            'name' => $carrier_service->title,
                            'cps' => $cps,
                            'from' => $this->systemConfigService->get('CoolRunnerPlugin.config.sendercountry'),
                            'to' => $receiver_country
                        ];
                    }
                }
            }
        }

        return $methods;
    }
}

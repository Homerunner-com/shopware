<?php

namespace HomeRunnerPlugin\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class HomeRunnerAPI
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
            'https://api.smartcheckout.homerunner.com?activation_token='.$installation_token,
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
            'https://api.smartcheckout.homerunner.com?shop_token=' . $this->systemConfigService->get('HomeRunnerPlugin.config.shoptoken'),
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );

        $response = $this->restClient->send($request);

        return json_decode($response->getBody()->getContents());
    }

    public function createShipment($order, $country, $shipping_method, $currency, $warehouse = '')
    {
         switch ($this->systemConfigService->get('HomeRunnerPlugin.config.warehouse')) {
             case 'internal':
                 return $this->createV3($order, $shipping_method, $currency, $country);
                 break;
             case 'external':
                 return $this->createWMS($order, $warehouse, $shipping_method, $currency, $country);
                 break;
            default:
                return false;
                break;
        };
    }

    private function createV3(OrderEntity $order, $shipping_method, $currency, $country = null)
    {
        $customerAddress = $order->getAddresses()->first();
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
                'name' => $this->systemConfigService->get('HomeRunnerPlugin.config.sendershop'),
                'attention' => '',
                'street1' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderstreet1'),
                'street2' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderstreet2'),
                'zip_code' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderzipcode'),
                'city' => $this->systemConfigService->get('HomeRunnerPlugin.config.sendercity'),
                'country' => $this->systemConfigService->get('HomeRunnerPlugin.config.sendercountry'),
                'phone' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderphone'),
                'email' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderemail')
            ],
            'receiver' => [
                'name' => ($customerAddress->company != '') ? $customerAddress->company : $customerAddress->firstName . ' ' . $customerAddress->lastName,
                'attention' => ($customerAddress->company != '') ? $customerAddress->firstName . ' ' . $customerAddress->lastName : '',
                'street1' => $customerAddress->street,
                'street2' => '',
                'zip_code' => $customerAddress->zipcode,
                'city' => $customerAddress->city,
                'country' => ($country != null) ? $country->iso : $customerAddress->country,
                'phone' => 88888888,
                'email' => $customerInformation->email,
                'notify_sms' => '',
                'notify_email' => $customerInformation->email
            ],
            'length' => 15,
            'width' => 15,
            'height' => 15,
            'weight' => 1000,
            'carrier' => $carrier,
            'carrier_product' => $carrier_product,
            'carrier_service' => $carrier_service,
            'reference' => $order->orderNumber,
            'comment' => '',
            'description' => '',
            'label_format' => $this->systemConfigService->get('HomeRunnerPlugin.config.printformat'),
            'servicepoint_id' => 0,
            'order_lines' => []
        ];

        $totalWeight = 0;
        foreach ($orderLines as $orderLine) {
            /**@var ProductEntity $product*/
            $product = $orderLine->getProduct();

            $data['order_lines'][] = [
                'qty' => $orderLine->quantity,
                'item_number' => $orderLine->payload['productNumber'],
                'customs' => [
                    'description' => $orderLine->label,
                    'total_price' => $orderLine->totalPrice,
                    'currency_code' => $currency->isoCode,
                    'sender_tariff' => $orderLine->getPayload()['customFields']['homerunner_customs_hscode_from'],
                    'receiver_tariff' =>  $orderLine->getPayload()['customFields']['homerunner_customs_hscode_to'],
                    'weight' => ($product->getWeight()*1000)*$orderLine->quantity
                ]
            ];

            $totalWeight += ($product->getWeight()*1000)*$orderLine->quantity;
        }

        $data['weight'] = $totalWeight;

        $request = new Request(
            'POST',
            'https://api.homerunner.com/v3/shipments',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('HomeRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('HomeRunnerPlugin.config.apitoken'))
            ],
            json_encode($data)
        );

        $response = $this->restClient->send($request);

        return ['body' => json_decode($response->getBody()), 'delivery_id' => $delivery->getId()];
    }

    private function createWMS(OrderEntity $order, $warehouse, $shipping_method, $currency, $country = null)
    {
        $customerAddress = $order->getAddresses()->first();
        $customerInformation = $order->getOrderCustomer();
        $orderLines = $order->getLineItems();

        $delivery = $order->getDeliveries()->first();

        // Get HomeRunner CPS
        $cps_exploded = explode('_', $shipping_method->cps);
        $carrier = $cps_exploded[0];
        $carrier_product = $cps_exploded[1];
        $carrier_service = $cps_exploded[2];

        // TODO: Get phone

        $data = [
            'warehouse' => $warehouse,
            'order_number' => $order->orderNumber,
            'sender' => [
                'name' => $this->systemConfigService->get('HomeRunnerPlugin.config.sendershop'),
                'attention' => '',
                'street1' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderstreet1'),
                'street2' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderstreet2'),
                'zip_code' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderzipcode'),
                'city' => $this->systemConfigService->get('HomeRunnerPlugin.config.sendercity'),
                'country' => $this->systemConfigService->get('HomeRunnerPlugin.config.sendercountry'),
                'phone' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderphone'),
                'email' => $this->systemConfigService->get('HomeRunnerPlugin.config.senderemail')
            ],
            'receiver' => [
                'name' => ($customerAddress->company != '') ? $customerAddress->company : $customerAddress->firstName . ' ' . $customerAddress->lastName,
                'attention' => ($customerAddress->company != '') ? $customerAddress->firstName . ' ' . $customerAddress->lastName : '',
                'street1' => $customerAddress->street,
                'street2' => '',
                'zip_code' => $customerAddress->zipcode,
                'city' => $customerAddress->city,
                'country' => ($country != null) ? $country->iso : $customerAddress->country,
                'phone' => 88888888,
                'email' => $customerInformation->email,
                'notify_sms' => '',
                'notify_email' => $customerInformation->email
            ],
            'length' => 15,
            'width' => 15,
            'height' => 15,
            'weight' => 1000,
            'carrier' => $carrier,
            'carrier_product' => $carrier_product,
            'carrier_service' => $carrier_service,
            'reference' => $order->orderNumber,
            'comment' => '',
            'description' => '',
            'label_format' => $this->systemConfigService->get('HomeRunnerPlugin.config.printformat'),
            'servicepoint_id' => 0,
            'order_lines' => []
        ];

        foreach ($orderLines as $orderLine) {
            $data['order_lines'][] = [
                'qty' => $orderLine->quantity,
                'item_number' => $orderLine->payload['productNumber'],
                'customs' => [
                    'description' => $orderLine->label,
                    'total_price' => $orderLine->totalPrice,
                    'currency_code' => $currency->isoCode,
                    'sender_tariff' => $orderLine->getPayload()['customFields']['homerunner_customs_hscode_from'],
                    'receiver_tariff' =>  $orderLine->getPayload()['customFields']['homerunner_customs_hscode_to'],
                    'weight' => ''
                ]
            ];
        }

        $request = new Request(
            'POST',
            'https://api.homerunner.com/wms/orders',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('HomeRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('HomeRunnerPlugin.config.apitoken'))
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
            'https://api.homerunner.com/printers',
            [
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('HomeRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('HomeRunnerPlugin.config.apitoken'))
            ]
        );

        $response = $this->restClient->send($request);

        return json_decode($response->getBody());
    }

    public function getWarehouses()
    {
        $request = new Request(
            'GET',
            'https://api.homerunner.com/wms/warehouses',
            [
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('HomeRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('HomeRunnerPlugin.config.apitoken'))
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
            'https://api.homerunner.com/printers/'.$printerAlias.'/print',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('HomeRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('HomeRunnerPlugin.config.apitoken'))
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
            'https://api.homerunner.com/v3/products/' . $this->systemConfigService->get('HomeRunnerPlugin.config.sendercountry'),
            [
                'Authorization' => 'Basic ' . base64_encode($this->systemConfigService->get('HomeRunnerPlugin.config.apiemail').':'.$this->systemConfigService->get('HomeRunnerPlugin.config.apitoken'))
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
                            'from' => $this->systemConfigService->get('HomeRunnerPlugin.config.sendercountry'),
                            'to' => $receiver_country
                        ];
                    }
                }
            }
        }

        return $methods;
    }
}
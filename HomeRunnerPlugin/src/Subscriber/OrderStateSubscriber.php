<?php

namespace HomeRunnerPlugin\Subscriber;

use HomeRunnerPlugin\Controller\HomeRunnerAPI;
use HomeRunnerPlugin\Service\CurrencyService;
use HomeRunnerPlugin\Service\DeliveryService;
use HomeRunnerPlugin\Service\MethodsService;
use HomeRunnerPlugin\Service\OrderService;
use HomeRunnerPlugin\Service\PrintService;
use HomeRunnerPlugin\Service\WarehouseService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateSubscriber implements EventSubscriberInterface
{
    /** @var LoggerInterface */
    private $logger;

    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var HomeRunnerAPI */
    private $apiClient;

    /** @var OrderService */
    private $orderService;

    /** @var DeliveryService */
    private $deliveryService;

    /** @var PrintService */
    private $printService;

    /** @var WarehouseService */
    private $warehouseService;

    /** @var MethodsService */
    private $methodsService;

    /** @var CurrencyService */
    private $currencyService;

    /** @var EntityRepositoryInterface */
    private $countryRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        LoggerInterface $logger,
        OrderService $orderService,
        DeliveryService $deliveryService,
        PrintService $printService,
        WarehouseService $warehouseService,
        MethodsService $methodsService,
        CurrencyService $currencyService,
        EntityRepositoryInterface $countryRepository
    )
    {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->apiClient = new HomeRunnerAPI($systemConfigService);
        $this->orderService = $orderService;
        $this->deliveryService = $deliveryService;
        $this->printService = $printService;
        $this->warehouseService = $warehouseService;
        $this->methodsService = $methodsService;
        $this->currencyService = $currencyService;
        $this->countryRepository = $countryRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            "state_machine.order_delivery.state_changed" => "onDeliveryStateChange"
        ];
    }

    public function onDeliveryStateChange(StateMachineStateChangeEvent $event)
    {
        if($event->getStateEventName() == 'state_enter.order_delivery.state.shipped') {
            /** @var OrderEntity $order */
            $tempOrder = $this->deliveryService->getDeliveryById(
                $event->getTransition()->getEntityId(),
                $event->getContext()
            )->getOrder();

            /** @var OrderEntity $order */
            $order = $this->orderService->readData($event->getContext(), $tempOrder->getId());

            // Get country
            $country_criteria = new Criteria();
            $country_criteria->addFilter(new EqualsFilter('id', $order->addresses->first()->countryId));
            $country = $this->countryRepository->search($country_criteria, $event->getContext())->first();

            // Get shipping method
            $shipping_method = $this->methodsService->getMethodById(
                $event->getContext(),
                $order->getDeliveries()->first()->getShippingMethod()->getCustomFields()['homerunner_methods']
            );

            // Get Currency
            $currency = $this->currencyService->getCurrencyById($event->getContext(), $order->getCurrencyId());

            // Create shipment
            if($this->systemConfigService->get('HomeRunnerPlugin.config.warehouse') == 'internal') {
                $response = $this->apiClient->createShipment($order, $country, $shipping_method, $currency);
            } else {
                $warehouse = $this->warehouseService->getWarehouseById(
                    $event->getContext(),
                    $this->systemConfigService->get('HomeRunnerPlugin.config.externalwarehouse')
                );

                $response = $this->apiClient->createShipment($order, $country, $shipping_method, $currency, $warehouse->getShorten());
            }

            if(isset($response['body']->package_number) AND $response['body']->package_number != "") {
                // Handle V3
                $this->deliveryService->writeData($event->getContext(), $response['delivery_id'], $response['body']->package_number);

                if($this->systemConfigService->get('HomeRunnerPlugin.config.autoprint') AND $this->systemConfigService->get('HomeRunnerPlugin.config.warehouse') == "internal") {
                    $printer = $this->printService->getPrinterById(
                        $event->getContext(),
                        $this->systemConfigService->get('HomeRunnerPlugin.config.printer')
                    );

                    $this->apiClient->printLabel($response['body']->package_number, $printer->getName());
                }
            } elseif (isset($response['body']->shipments[0]->package_number) AND $response['body']->shipments[0]->package_number != "") {
                // Handle WMS
                $this->deliveryService->writeData(
                    $event->getContext(),
                    $response['delivery_id'],
                    $response['body']->shipments[0]->package_number
                );
            }

        }
    }
}
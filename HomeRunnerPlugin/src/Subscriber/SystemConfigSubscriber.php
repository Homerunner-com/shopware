<?php declare(strict_types=1);

namespace HomeRunnerPlugin\Subscriber;

use HomeRunnerPlugin\Controller\HomeRunnerAPI;
use HomeRunnerPlugin\Service\CustomFieldService;
use HomeRunnerPlugin\Service\MethodsService;
use HomeRunnerPlugin\Service\PrintService;
use HomeRunnerPlugin\Service\WarehouseService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigSubscriber implements EventSubscriberInterface
{
    /** @var SystemConfigService */
    private SystemConfigService $systemConfigService;

    /** @var PrintService */
    private PrintService $printService;

    /** @var MethodsService */
    private MethodsService $methodsService;

    /** @var WarehouseService */
    private WarehouseService $warehouseService;

    /** @var CustomFieldService */
    private CustomFieldService $customFieldService;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var HomeRunnerAPI */
    private $apiClient;

    /**
     * @param SystemConfigService $systemConfigService
     * @param LoggerInterface $logger
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        PrintService $printService,
        MethodsService $methodsService,
        WarehouseService $warehouseService,
        CustomFieldService $customFieldService,
        LoggerInterface $logger)
    {
        $this->systemConfigService = $systemConfigService;
        $this->printService = $printService;
        $this->methodsService = $methodsService;
        $this->warehouseService = $warehouseService;
        $this->customFieldService = $customFieldService;
        $this->logger = $logger;
        $this->apiClient = new HomeRunnerAPI($systemConfigService);
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            'system_config.written' => 'onSystemConfigWritten'
        ];
    }

    /**
     * @param EntityWrittenEvent $event
     */
    public function onSystemConfigWritten(EntityWrittenEvent $event)
    {
        //$this->printService->writeData($event->getContext());

        foreach ($event->getPayloads() as $payload) {
            switch ($payload['configurationKey']) {
                case 'HomeRunnerPlugin.config.smartcheckouttoken':
                    $this->connectSmartCheckout($payload['configurationValue']);
                    $this->getPrinters($event->getContext());
                    $this->getShippingMethods($event->getContext());
                    $this->createSWCustomFields($event->getContext());

                    if($this->systemConfigService->get('HomeRunnerPlugin.config.warehouse') == "external") {
                        $this->getWarehouses($event->getContext());
                    }
                    break;
            }
        }
    }

    /**
     * @param $installation_token
     */
    public function connectSmartCheckout($installation_token)
    {
        $install = $this->apiClient->connect(
            $installation_token,
            $this->systemConfigService->get('HomeRunnerPlugin.config.sendershop'),
            $this->systemConfigService->get('HomeRunnerPlugin.config.sendershopurl')
        );

        if(isset($install->status) AND $install->status == "ok") {
            $this->systemConfigService->set('HomeRunnerPlugin.config.apiemail', $install->shop_info->integration_email);
            $this->systemConfigService->set('HomeRunnerPlugin.config.apitoken', $install->shop_info->integration_token);
            $this->systemConfigService->set('HomeRunnerPlugin.config.shoptoken', $install->shop_info->shop_token);
        }
    }

    public function getPrinters($context)
    {
        $printers = $this->apiClient->getPrinters();

        foreach ($printers as $printer) {
            $this->printService->writeData($context, $printer->name, $printer->alias, $printer->description);
        }
    }

    public function getShippingMethods($context)
    {
        $methods = $this->apiClient->getMethods();

        foreach ($methods as $method) {
            $this->methodsService->writeData($context, $method['name'], $method['cps'], $method['from'], $method['to']);
        }
    }

    public function getWarehouses($context)
    {
        $warehouses = $this->apiClient->getWarehouses();

        foreach ($warehouses as $shorten => $warehouse) {
            $this->warehouseService->writeData($context, $warehouse->title, $shorten);
        }
    }

    public function createSWCustomFields($context)
    {
        $this->customFieldService->createSWCustomFields($context);
    }
}
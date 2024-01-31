<?php declare(strict_types=1);

namespace CoolRunnerPlugin\Subscriber;

use CoolRunnerPlugin\Controller\CoolRunnerAPI;
use CoolRunnerPlugin\Helper\InstallHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigSubscriber implements EventSubscriberInterface
{
    /** @var SystemConfigService */
    private SystemConfigService $systemConfigService;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var InstallHelper */
    private InstallHelper $installHelper;


    /** @var CoolRunnerAPI */
    private $apiClient;

    /**
     * @param SystemConfigService $systemConfigService
     * @param InstallHelper $installHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        InstallHelper $installHelper,
        LoggerInterface $logger)
    {
        $this->systemConfigService = $systemConfigService;
        $this->installHelper = $installHelper;
        $this->logger = $logger;
        $this->apiClient = new CoolRunnerAPI($systemConfigService);
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigWritten'
        ];
    }

    public function onSystemConfigWritten(SystemConfigChangedEvent $event)
    {
        switch ($event->getKey()) {
            case 'CoolRunnerPlugin.config.smartcheckouttoken':
                $this->connectSmartCheckout($event->getValue());
                break;
        }
    }

    /**
     * @param $installation_token
     */
    public function connectSmartCheckout($installation_token)
    {
        $install = $this->apiClient->connect(
            $installation_token,
            $this->systemConfigService->get('CoolRunnerPlugin.config.sendershop'),
            $this->systemConfigService->get('CoolRunnerPlugin.config.sendershopurl')
        );

        if(isset($install->status) AND $install->status == "ok") {
            $this->systemConfigService->set('CoolRunnerPlugin.config.apiemail', $install->shop_info->integration_email);
            $this->systemConfigService->set('CoolRunnerPlugin.config.apitoken', $install->shop_info->integration_token);
            $this->systemConfigService->set('CoolRunnerPlugin.config.shoptoken', $install->shop_info->shop_token);

            // Needed because of no context on this event
            $context = Context::createDefaultContext();

            // Create warehouses, shipping methods and printers
            $this->getWarehouses($context);
            $this->getShippingMethods($context);
            $this->getPrinters($context);
        }

        if($this->systemConfigService->get('CoolRunnerPlugin.config.apitoken') !== null) {
            // Needed because of no context on this event
            $context = Context::createDefaultContext();
            
            $this->getShippingMethods($context);
        }

    }

    public function getWarehouses($context)
    {
        $warehouses = $this->apiClient->getWarehouses();

        foreach ($warehouses as $shorten => $warehouse) {
            $this->installHelper->storeWarehouse($context, $warehouse->title, $shorten);
        }
    }

    public function getShippingMethods($context)
    {
        $methods = $this->apiClient->getMethods();

        foreach ($methods as $shorten => $method) {
            $this->installHelper->storeMethod($context, $method['name'], $method['cps'], $method['from'], $method['to']);
        }
    }

    public function getPrinters($context)
    {
        $printers = $this->apiClient->getPrinters();

        foreach ($printers as $printer) {
            $this->installHelper->storePrinter($context, $printer->name, $printer->alias, $printer->description);
        }
    }

}

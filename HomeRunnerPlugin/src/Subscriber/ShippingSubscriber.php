<?php

namespace HomeRunnerPlugin\Subscriber;

use Shopware\Core\Checkout\Shipping\ShippingEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;;

class ShippingSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            ShippingEvents::SHIPPING_METHOD_SEARCH_RESULT_LOADED_EVENT => 'onResultLoaded'
        ];
    }

    public function onResultLoaded(EntitySearchResultLoadedEvent $entityLoadedEvent)
    {
        // dd($entityLoadedEvent->getResult());
    }
}
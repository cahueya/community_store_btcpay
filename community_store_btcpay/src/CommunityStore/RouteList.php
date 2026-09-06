<?php

namespace Concrete\Package\CommunityStoreBtcpay\Src\CommunityStore;

use Concrete\Core\Routing\RouteListInterface;
use Concrete\Core\Routing\Router;
use Concrete\Package\CommunityStoreBtcpay\Src\CommunityStore\Payment\Methods\CommunityStoreBtcpay\CommunityStoreBtcpayPaymentMethod;

class RouteList implements RouteListInterface
{
    public function loadRoutes(Router $router)
    {
        $router->post(
            '/checkout/btcpayresponse',
            CommunityStoreBtcpayPaymentMethod::class . '::validateCompletion'
        );
    }
}

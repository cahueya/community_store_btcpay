<?php

namespace Concrete\Package\CommunityStoreBtcpay;

use Concrete\Core\Package\Package;
use Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as PaymentMethod;
use Concrete\Package\CommunityStoreBtcpay\Src\CommunityStore\RouteList;

class Controller extends Package
{
    protected $pkgHandle = 'community_store_btcpay';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '1.3.0';
    protected $packageDependencies = [
        'community_store' => '2.6.0',
    ];

    protected $pkgAutoloaderRegistries = [
        'src/CommunityStore' => 'Concrete\\Package\\CommunityStoreBtcpay\\Src\\CommunityStore',
    ];

    public function getPackageDescription()
    {
        return t('BTCPay Server payment method for Community Store.');
    }

    public function getPackageName()
    {
        return t('BTCPay Server Payment Method');
    }

    public function install()
    {
        $pkg = parent::install();
        PaymentMethod::add('community_store_btcpay', 'BTCPay Server', $pkg);
    }

    public function upgrade()
    {
        parent::upgrade();

        $method = PaymentMethod::getByHandle('community_store_btcpay');
        if ($method) {
            $displayName = $method->getDisplayName();
            $method->setName('BTCPay Server');

            // Preserve a custom display name, but modernize the legacy default.
            if ($displayName === 'BTC Payserver' || $displayName === '') {
                $method->setDisplayName('BTCPay Server');
            }

            $method->save();
        }
    }

    public function uninstall()
    {
        $method = PaymentMethod::getByHandle('community_store_btcpay');
        if ($method) {
            $method->delete();
        }

        parent::uninstall();
    }

    public function on_start()
    {
        $autoload = $this->getPackagePath() . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        $router = $this->app->make('router');
        (new RouteList())->loadRoutes($router);
    }
}

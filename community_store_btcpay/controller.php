<?php

namespace Concrete\Package\CommunityStoreBtcpay;

use Concrete\Core\Package\Package;
use Concrete\Core\Support\Facade\Route;
use Whoops\Exception\ErrorException;
use Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as PaymentMethod;

class Controller extends Package
{
    protected $pkgHandle = 'community_store_btcpay';
    protected $appVersionRequired = '8.0';
    protected $pkgVersion = '0.81';
    protected $packageDependencies = ['community_store'=>'2.0'];

    protected $pkgAutoloaderRegistries = [
        'src/CommunityStore' => '\Concrete\Package\CommunityStoreBtcpay\Src\CommunityStore',
    ];

    public function getPackageDescription()
    {
        return t("BTC Payserver Payment Method for Community Store");
    }

    public function getPackageName()
    {
        return t("BTC Payserver Payment Method");
    }

    public function install()
    {
        $installed = $this->app->make('Concrete\Core\Package\PackageService')->getInstalledHandles();

        if(!(is_array($installed) && in_array('community_store',$installed)) ) {
            throw new ErrorException(t('This package requires that Community Store be installed'));
        } else {
            $pkg = parent::install();
            $pm = new PaymentMethod();
            $pm->add('community_store_btcpay','BTC Payserver',$pkg);
        }

    }
    public function uninstall()
    {
        $pm = PaymentMethod::getByHandle('community_store_btcpay');
        if ($pm) {
            $pm->delete();
        }
        $pkg = parent::uninstall();
    }

    public function on_start() {
        require $this->getPackagePath() . '/vendor/autoload.php';
        Route::register('/checkout/btcpayresponse','\Concrete\Package\CommunityStoreBtcpay\Src\CommunityStore\Payment\Methods\CommunityStoreBtcpay\CommunityStoreBtcpayPaymentMethod::validateCompletion');
    }
}
?>

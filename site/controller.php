<?php

defined('_JEXEC') or die();

use Expressly\Entity\MerchantType;

use Expressly\Route\BatchCustomer;
use Expressly\Route\BatchInvoice;
use Expressly\Route\CampaignMigration;
use Expressly\Route\CampaignPopup;
use Expressly\Route\Ping;
use Expressly\Route\UserData;

/**
 *
 */
class ExpresslyController extends JControllerLegacy
{
    /**
     * @var null
     */
    public $app = null;

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     * Recognized key values include 'name', 'default_task', 'model_path', and
     * 'view_path' (this list is not meant to be comprehensive).
     *
     * @since   12.2
     */
    public function __construct($config = array())
    {
        parent::__construct($config);

        // TODO: Need to create MerchantType VIRTUEMART
        $client = new \Expressly\Client(MerchantType::WOOCOMMERCE);

        $app = $client->getApp();
        /*$app['merchant.provider'] = $app->share(function () {
            return new WC_Expressly_MerchantProvider();
        });*/

        $this->app = $app;
        $this->dispatcher = $this->app['dispatcher'];
        $this->merchantProvider = $this->app['merchant.provider'];
    }

    /**
     *
     */
    public function display($cachable = false, $urlparams = false)
    {
        $__xly = $this->input->get('__xly', '', 'string');

        $route = $this->app['route.resolver']->process($__xly);

        if ($route instanceof Route) {
            switch ($route->getName()) {
                case Ping::getName():
                    ExpresslyHelper::ping();
                    break;
                /*case UserData::getName():
                    $data = $route->getData();
                    ExpresslyHelper::retrieveUserByEmail($data['email']);
                    break;
                case CampaignPopup::getName():
                    $data = $route->getData();
                    $this->migratestart($data['uuid']);
                    break;
                case CampaignMigration::getName():
                    $data = $route->getData();
                    $this->migratecomplete($data['uuid']);
                    break;
                case BatchCustomer::getName():
                    $this->batchCustomer();
                    break;
                case BatchInvoice::getName():
                    $this->batchInvoice();
                    break;*/
            }
        }
    }
}
/*
class ExpresslyController extends JControllerLegacy
{
    public function __construct($config = array())
    {
        parent::__construct($config);

        // Register Extra tasks
        $this->registerTask('email', 'shout');
    }

    function ping()
    {
        echo new JResponseJson(['sdfasdf' => 43]);
    }
}*/

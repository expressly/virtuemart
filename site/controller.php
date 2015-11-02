<?php

defined('_JEXEC') or die();

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

if (!class_exists ('VmConfig')) {
    if(file_exists(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php')){
        require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
    } else {
        jExit('Install the virtuemart Core first');
    }
}

VmConfig::loadConfig();

use Expressly\Entity\MerchantType;

use Expressly\Route\Ping,
    Expressly\Route\UserData,
    Expressly\Route\CampaignPopup,
    Expressly\Route\CampaignMigration,
    Expressly\Route\BatchCustomer,
    Expressly\Route\BatchInvoice;

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
        $app['merchant.provider'] = $app->share(function () {
            return new ExpresslyMerchantProvider();
        });

        $this->app = $app;
    }

    /**
     *
     */
    public function display($cachable = false, $urlparams = false)
    {
        // Get route
        $route = $this->app['route.resolver']->process($this->input->get('__xly', '', 'string'));

        if ($route instanceof \Expressly\Entity\Route) {

            // Set $app for helper methods
            ExpresslyHelper::setApp($this->app);

            switch ($route->getName()) {
                case Ping::getName():
                    ExpresslyHelper::ping();
                    break;
                case UserData::getName():
                    $data = $route->getData();
                    ExpresslyHelper::retrieveUserByEmail($data['email']);
                    break;
                case CampaignPopup::getName():
                    $data = $route->getData();
                    ExpresslyHelper::migratestart($data['uuid']);
                    break;
                case CampaignMigration::getName():
                    $data = $route->getData();
                    ExpresslyHelper::migratecomplete($data['uuid']);
                    break;
                case BatchCustomer::getName():
                    ExpresslyHelper::batchCustomer();
                    break;
                case BatchInvoice::getName():
                    ExpresslyHelper::batchInvoice();
                    break;
            }
        }

        header('HTTP/1.0 404 Not Found');
        exit();
    }
}

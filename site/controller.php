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

JModelLegacy::addIncludePath(JPATH_SITE . '/components/com_users/models', 'UsersModel');

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
     * Default task
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
                    $this->migratestart($data['uuid']);
                    break;
                case CampaignMigration::getName():
                    $data = $route->getData();
                    $this->migratecomplete($data['uuid']);
                    break;
                case BatchCustomer::getName():
                    ExpresslyHelper::batchCustomer();
                    break;
                case BatchInvoice::getName():
                    ExpresslyHelper::batchInvoice();
                    break;
            }
        }
    }

    /**
     *
     */
    private function migratestart($uuid)
    {
        $merchant = $this->app['merchant.provider']->getMerchant();
        $event    = new \Expressly\Event\CustomerMigrateEvent($merchant, $uuid);

        try {
            $this->app['dispatcher']->dispatch(\Expressly\Subscriber\CustomerMigrationSubscriber::CUSTOMER_MIGRATE_POPUP, $event);

            if (!$event->isSuccessful()) {
                throw new \Expressly\Exception\GenericException(ExpresslyHelper::error_formatter($event));
            }
        } catch (\Exception $e) {
            $this->app['logger']->error(\Expressly\Exception\ExceptionFormatter::format($e));
            JFactory::getApplication()->redirect('/');
        }

        $view = $this->getView('expressly', 'html');
        $view->setProperties([
            'uuid'  => $uuid,
            'popup' => $event->getContent(),
        ]);

        $view->display();
    }

    /**
     *
     */
    private function migratecomplete($uuid)
    {
        if (empty($uuid))
            JFactory::getApplication()->redirect('/');

        $exists   = false;
        $merchant = $this->app['merchant.provider']->getMerchant();
        $event    = new Expressly\Event\CustomerMigrateEvent($merchant, $uuid);

        try {
            $this->app['dispatcher']->dispatch(\Expressly\Subscriber\CustomerMigrationSubscriber::CUSTOMER_MIGRATE_DATA, $event);
            $json = $event->getContent();

            if (!$event->isSuccessful()) {
                if (!empty($json['code']) && $json['code'] == 'USER_ALREADY_MIGRATED') {
                    $exists = true;
                }
                throw new \Expressly\Exception\UserExistsException(ExpresslyHelper::error_formatter($event));
            }

            $email = $json['migration']['data']['email'];
            $user  = ExpresslyHelper::get_user_by_email($email);

            if (null === $user) {

                $customer = $json['migration']['data']['customerData'];

                $model = JModelLegacy::getInstance('Registration', 'UsersModel', array('ignore_request' => true));
                $model->register([
                    'name'      => $customer['firstName'] . ' ' . $customer['lastName'],
                    'username'  => 'user'.time().rand(1000, 9999),
                    'email1'    => $email,
                    'password1' => JUserHelper::genRandomPassword(),
                ]);

                /*

                // Set the role
                $user = new WP_User($user_id);
                $user->set_role('customer');

                $countryCodeProvider = $this->app['country_code.provider'];

                $addAddress = function ($address, $prefix) use ($customer, $user_id, $countryCodeProvider) {
                    $phone = isset($address['phone']) ?
                        (!empty($customer['phones'][$address['phone']]) ? $customer['phones'][$address['phone']] : null) : null;
                    update_user_meta($user_id, $prefix . '_first_name', $address['firstName']);
                    update_user_meta($user_id, $prefix . '_last_name', $address['lastName']);
                    if (!empty($address['address1'])) {
                        update_user_meta($user_id, $prefix . '_address_1', $address['address1']);
                    }
                    if (!empty($address['address2'])) {
                        update_user_meta($user_id, $prefix . '_address_2', $address['address2']);
                    }
                    update_user_meta($user_id, $prefix . '_city', $address['city']);
                    update_user_meta($user_id, $prefix . '_postcode', $address['zip']);
                    if (!empty($phone)) {
                        update_user_meta($user_id, $prefix . '_phone', $phone['number']);
                    }
                    $iso2 = $countryCodeProvider->getIso2($address['country']);
                    update_user_meta($user_id, $prefix . '_state', $address['stateProvince']);
                    update_user_meta($user_id, $prefix . '_country', $iso2);
                };

                if (isset($customer['billingAddress'])) {
                    $addAddress($customer['addresses'][$customer['billingAddress']], 'billing');
                }

                if (isset($customer['shippingAddress'])) {
                    $addAddress($customer['addresses'][$customer['shippingAddress']], 'shipping');
                }

                // Dispatch password creation email
                wp_mail($email, 'Welcome!', 'Your Password: ' . $password);

                // Forcefully log user in
                wp_set_auth_cookie($user_id);

                */
            } else {
                $exists = true;
                $event = new \Expressly\Event\CustomerMigrateEvent($merchant, $uuid, \Expressly\Event\CustomerMigrateEvent::EXISTING_CUSTOMER);
            }
            // Add items (product/coupon) to cart
            /*if (!empty($json['cart'])) {
                WC()->cart->empty_cart();
                if (!empty($json['cart']['productId'])) {
                    WC()->cart->add_to_cart($json['cart']['productId'], 1);
                }
                if (!empty($json['cart']['couponCode'])) {
                    WC()->cart->add_discount(sanitize_text_field($json['cart']['couponCode']));
                }
            }*/
            $this->app['dispatcher']->dispatch(\Expressly\Subscriber\CustomerMigrationSubscriber::CUSTOMER_MIGRATE_SUCCESS, $event);
        } catch (\Exception $e) {
            $this->app['logger']->error(\Expressly\Exception\ExceptionFormatter::format($e));
        }

        if ($exists) {

            $view = $this->getView('expressly', 'html');
            $view->setLayout('exists');
            $view->setProperties([
                'uuid'  => $uuid,
                'popup' => $event->getContent(),
            ]);

            $view->display();

            return;
        }

        JFactory::getApplication()->redirect('/');
    }
}

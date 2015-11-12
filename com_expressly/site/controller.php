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
                    $this->batchCustomer();
                    break;
                case BatchInvoice::getName():
                    $this->batchInvoice();
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

            $session = JFactory::getSession();
            $session->set('__xly', array(
                'action' => 'popup',
                'uuid'   => $uuid,
                'popup'  => $event->getContent(),
            ));

        } catch (\Exception $e) {
            $this->app['logger']->error(\Expressly\Exception\ExceptionFormatter::format($e));
        }

        JFactory::getApplication()->redirect('/');
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

                $model   = JModelLegacy::getInstance('Registration', 'UsersModel', array('ignore_request' => true));
                $user_id = $model->register([
                    'name'      => $customer['firstName'] . ' ' . $customer['lastName'],
                    'username'  => 'user'.time().rand(1000, 9999),
                    'email1'    => $email,
                    'password1' => JUserHelper::genRandomPassword(),
                ]);

                if (isset($customer['billingAddress'])) {
                    $userInfo = VmTable::getInstance('userinfos', 'Table', array('dbo' => JFactory::getDbo()));
                    $userInfo->bindChecknStore(array_merge(array(
                        'virtuemart_userinfo_id' => 0,
                        'virtuemart_user_id'     => $user_id,
                        'address_type'           => 'BT',
                    ), $this->parse_address($customer['addresses'][$customer['billingAddress']], $customer)));
                }

                if (isset($customer['shippingAddress'])) {
                    $userInfo = VmTable::getInstance('userinfos', 'Table', array('dbo' => JFactory::getDbo()));
                    $userInfo->bindChecknStore(array_merge(array(
                        'virtuemart_userinfo_id' => 0,
                        'virtuemart_user_id'     => $user_id,
                        'address_type'           => 'ST',
                    ), $this->parse_address($customer['addresses'][$customer['shippingAddress']], $customer)));
                }

                /*
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
            $session = JFactory::getSession();
            $session->set('__xly', array(
                'action' => 'exists',
            ));
        }

        JFactory::getApplication()->redirect('/');
    }

    /**
     * @param $address
     * @param $customer
     */
    protected function parse_address($address, &$customer)
    {
        $data = array();

        $phone = isset($address['phone']) ?
            (!empty($customer['phones'][$address['phone']]) ? $customer['phones'][$address['phone']] : null) : null;

        $data['first_name'] = $address['firstName'];
        $data['last_name']  = $address['lastName'];

        if (!empty($address['address1']))
            $data['address_1'] = $address['address1'];

        if (!empty($address['address2']))
            $data['address_2'] = $address['address2'];

        $data['city'] = $address['city'];
        $data['zip']  = $address['zip'];

        if (!empty($phone))
            $data['phone_1'] = $phone['number'];

        $data['virtuemart_state_id']   = ExpresslyHelper::get_state_id_by_name($address['stateProvince']);
        $data['virtuemart_country_id'] = ExpresslyHelper::get_country_id_by_iso2($address['country']);

        return $data;
    }

    /**
     *
     */
    public function batchCustomer()
    {
        $json = file_get_contents('php://input');
        $json = json_decode($json);

        $users = array();

        try {
            if (!property_exists($json, 'emails')) {
                throw new GenericException('Invalid JSON request');
            }
            foreach ($json->emails as $email) {
                if (null !== ExpresslyHelper::get_user_by_email($email)) {
                    $users['existing'][] = $email;
                }
            }
            $presenter = new \Expressly\Presenter\BatchCustomerPresenter($users);
            ExpresslyHelper::send_json($presenter->toArray());
        } catch (GenericException $e) {
            $this->app['logger']->error($e);
            ExpresslyHelper::send_json(array());
        }
    }

    /**
     *
     */
    public function batchInvoice()
    {
        $json = file_get_contents('php://input');
        $json = json_decode($json);

        $invoices = array();

        try {
            if (!property_exists($json, 'customers')) {
                throw new \Expressly\Exception\GenericException('Invalid JSON request');
            }

            foreach ($json->customers as $customer) {

                if (!property_exists($customer, 'email')) {
                    continue;
                }

                $user = ExpresslyHelper::get_user_by_email($customer->email);

                if (null !== $user) {

                    $vmOrders = VmModel::getModel('orders')->getOrdersList($user->id);

                    $invoice = new \Expressly\Entity\Invoice();
                    $invoice->setEmail($customer->email);

                    foreach ($vmOrders as $vmOrder) {

                        $vmOrderDetails = VmModel::getModel('orders')->getOrder($vmOrder->virtuemart_order_id);

                        if ($vmOrderDetails['details']['BT']->created_on > $customer->from && $vmOrderDetails['details']['BT']->created_on < $customer->to) {
                            $total = $vmOrderDetails['details']['BT']->order_subtotal;
                            $tax = $vmOrderDetails['details']['BT']->order_tax;
                            $count = count($vmOrderDetails['items']);
                            $order = new \Expressly\Entity\Order();
                            /*foreach ($wpOrder->get_items('line_item') as $lineItem) {
                                $count++;
                                if ($lineItem->tax_class) {
                                    $order->setCurrency($lineItem['tax_class']);
                                }
                            }*/
                            $order
                                ->setId($vmOrderDetails['details']['BT']->virtuemart_order_id)
                                ->setDate(new \DateTime($vmOrderDetails['details']['BT']->created_on))
                                ->setItemCount($count)
                                ->setTotal($total, $tax);
                            /*$coupons = $wpOrder->get_used_coupons();
                            if (!empty($coupons)) {
                                $order->setCoupon($coupons[0]);
                            }*/
                            $invoice->addOrder($order);
                        }
                    }

                    $invoices[] = $invoice;
                }
            }
            $presenter = new \Expressly\Presenter\BatchInvoicePresenter($invoices);
            ExpresslyHelper::send_json($presenter->toArray());
        } catch (\Expressly\Exception\GenericException $e) {
            $this->app['logger']->error($e);
            ExpresslyHelper::send_json(array());
        }
    }
}

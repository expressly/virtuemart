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

            switch ($route->getName()) {
                case Ping::getName():
                    $this->ping();
                    break;
                case UserData::getName():
                    $data = $route->getData();
                    $this->retrieveUserByEmail($data['email']);
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
    private function ping()
    {
        $presenter = new \Expressly\Presenter\PingPresenter();
        ExpresslyHelper::send_json($presenter->toArray());
    }

    /**
     *
     */
    private function retrieveUserByEmail($emailAddr)
    {
        try {
            // get joomla user
            $user = ExpresslyHelper::get_user_by_email($emailAddr);

            if (null !== $user) {

                $customer = new \Expressly\Entity\Customer();

                $email = new \Expressly\Entity\Email();
                $email
                    ->setEmail($emailAddr)
                    ->setAlias('primary');

                $customer->addEmail($email);

                if (!empty($user->id)) {
                    $userInfo_ids = JFactory::getDBO()
                        ->setQuery('SELECT `virtuemart_userinfo_id` FROM `#__virtuemart_userinfos` WHERE `virtuemart_user_id` = "' . intval($user->id) . '" ORDER BY `address_type` ASC')
                        ->loadColumn(0);
                } else {
                    $userInfo_ids  = array();
                }

                foreach ($userInfo_ids as $uid) {

                    $userInfo = VmTable::getInstance('userinfos', 'Table', array('dbo' => JFactory::getDbo()));
                    $userInfo->load($uid);

                    if ('BT' == $userInfo->address_type) {
                        $customer
                            ->setFirstName($userInfo->first_name)
                            ->setLastName($userInfo->last_name);
                    }

                    $address = new \Expressly\Entity\Address();
                    $address
                        ->setFirstName($userInfo->first_name)
                        ->setLastName($userInfo->last_name)
                        ->setAddress1($userInfo->address_1)
                        ->setAddress2($userInfo->address_2)
                        ->setCity($userInfo->city)
                        ->setZip($userInfo->zip);

                    $address->setCountry(ExpresslyHelper::get_country_iso3_by_id($userInfo->virtuemart_country_id));
                    $address->setStateProvince(ExpresslyHelper::get_state_name_by_id($userInfo->virtuemart_state_id));

                    if (!empty($userInfo->phone_1)) {
                        $phone = new \Expressly\Entity\Phone();
                        $phone
                            ->setType(\Expressly\Entity\Phone::PHONE_TYPE_HOME)
                            ->setNumber(strval($userInfo->phone_1));
                        $customer->addPhone($phone);

                        $address->setPhonePosition(intval($customer->getPhoneIndex($phone)));
                    }

                    if (!empty($userInfo->phone_2)) {
                        $phone = new \Expressly\Entity\Phone();
                        $phone
                            ->setType(\Expressly\Entity\Phone::PHONE_TYPE_MOBILE)
                            ->setNumber(strval($userInfo->phone_2));
                        $customer->addPhone($phone);

                        if (empty($userInfo->phone_1))
                            $address->setPhonePosition(intval($customer->getPhoneIndex($phone)));
                    }

                    $customer->addAddress($address, true, (('BT' == $userInfo->address_type)
                        ? \Expressly\Entity\Address::ADDRESS_BILLING
                        : \Expressly\Entity\Address::ADDRESS_SHIPPING
                    ));
                }

                $merchant = $this->app['merchant.provider']->getMerchant();
                $response = new \Expressly\Presenter\CustomerMigratePresenter($merchant, $customer, $emailAddr, $user->id);

                ExpresslyHelper::send_json($response->toArray());
            }
        } catch (\Exception $e) {
            $this->app['logger']->error(ExceptionFormatter::format($e));
            ExpresslyHelper::send_json([]);
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

                // TODO: Need to do programmatically authorize here (if some way exists for Joomla!)

            } else {
                $exists = true;
                $event = new \Expressly\Event\CustomerMigrateEvent($merchant, $uuid, \Expressly\Event\CustomerMigrateEvent::EXISTING_CUSTOMER);
            }

            // *************************************
            // * Add items (product/coupon) to cart
            // *************************************
            if (!empty($json['cart'])) {

                if (!class_exists('VirtueMartCart'))
                    require_once(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');

                $cart = VirtueMartCart::getCart();

                if (!empty($json['cart']['productId'])) {
                    $cartProductsData = array();
                    $cartProductsData[intval($json['cart']['productId'])] = array(
                        'virtuemart_product_id' => intval($json['cart']['productId']),
                        'quantity'              => 1,
                    );
                    $cart->cartProductsData = $cartProductsData;
                }

                if (!empty($json['cart']['couponCode'])) {
                    $cart->setCouponCode(strval($json['cart']['couponCode']));
                }

                $cart->setCartIntoSession();

            }
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

                            // TODO: Need to review this values
                            $total = $vmOrderDetails['details']['BT']->order_subtotal;
                            $tax   = $vmOrderDetails['details']['BT']->order_tax;
                            // ====
                            $count = count($vmOrderDetails['items']);
                            $order = new \Expressly\Entity\Order();
                            $order
                                ->setId($vmOrderDetails['details']['BT']->virtuemart_order_id)
                                ->setDate(new \DateTime($vmOrderDetails['details']['BT']->created_on))
                                ->setItemCount($count)
                                ->setTotal($total, $tax);

                            if (!empty($vmOrderDetails['details']['BT']->coupon_code)) {
                                $order->setCoupon($vmOrderDetails['details']['BT']->coupon_code);
                            }

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

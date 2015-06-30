<?php

//TODO: licence
// no direct access
defined('_JEXEC') or die('Restricted access');
if (!class_exists('VM_Expressly')) :

    require_once('vendor/autoload.php');
    require_once('class-vm-expressly-merchantprovider.php');

    /**
     *
     */
    class VM_Expressly extends JControllerLegacy
    {

        /**
         * @var Silex\Application
         */
        public $app;

        /**
         * @var Symfony\Component\EventDispatcher\EventDispatcher
         */
        public $dispatcher;

        /**
         * Construct the plugin.
         */
        public function __construct()
        {
            // ===== Set app, dispatcher & merchant ===== //
            $client = new Expressly\Client();
            $app = $client->getApp();

            $app['merchant.provider'] = $app->share(function ($app) {
                return new VM_Expressly_MerchantProvider();
            });

            $this->app = $app;
            $this->dispatcher = $this->app['dispatcher'];
            // ===== //
        }

        /**
         *
         */
        public function template_redirect()
        {
            // Get Expressly API call
            $jinput = JFactory::getApplication()->input;
            $__xly = $jinput->get('__xly');

            switch ($__xly):

                case 'utility/ping': {
                    $this->ping();
                }
                    break;

                case 'customer/show': {
                    $this->retrieveUserByEmail($jinput->get('email'));
                }
                    break;

                case "customer/migrate": {
                    $this->migratecomplete($jinput->get('uuid'));
                }
                    break;

                case "customer/popup": {
                    $this->migratestart($jinput->get('uuid'));
                }
                    break;

            endswitch;
        }

        private function migratecomplete($uuid)
        {
            // get key from url
            if (empty($uuid)) {
                die('Undefined uuid');
            }

            // get json
            $merchant = $this->app['merchant.provider']->getMerchant();
            $event = new Expressly\Event\CustomerMigrateEvent($merchant, $uuid);
            $this->dispatcher->dispatch('customer.migrate.complete', $event);

            $json = $event->getResponse();

            if (!empty($json['code'])) {
                die('empty code');
            }

            if (empty($json['migration'])) {
                // record error

                die('empty migration');
            }

            // 'user_already_migrated' should be proper error message, not a plain string
            if ($json == 'user_already_migrated') {
                die('user_already_migrated');
            }

            try {
                $email = $json['migration']['data']['email'];
                // Get a database object
                // Did not find a better way to retrieve a user...
                $db = JFactory::getDbo();
                $query = $db->getQuery(true);

                $query->select('id, password');
                $query->from('#__users');
                $query->where('email=' . $db->quote($email));

                $db->setQuery($query);
                $result = $db->loadObject();

                $user_id = isset($result) && $result ? $result->id : null;
                if (null === $user_id) {

                    $customer = $json['migration']['data']['customerData'];

                    // Generate the password and create the user
                    $salt = JUserHelper::genRandomPassword(32);
                    $crypt = JUserHelper::getCryptedPassword("ght100%2po", $salt);
                    $password = $crypt . ':' . $salt;
                    $model = VmModel::getModel('user');
                    $data_to_save = array(
                        ["name"]      => $customer['firstName'] . ' ' . $customer['lastName'],
                        ["username"]  => $email,
                        ["password1"] => $password,
                        ["password2"] =>
                            $password,
                        ["email1"]    => $email,
                        ["email2"]    => $email
                    );
                    //Not sure about this one
                    $model->setParam('activate', 1);
                    $return = $model->save($data_to_save);
                    $user_id = $return['newId'];


                    $billingAddress = $customer['billingAddress'];
                    $shippingAddress = $customer['shippingAddress'];

                    $countryCodeProvider = $this->app['country_code.provider'];

                    foreach ($customer['addresses'] as $address_key => $address) {
                        if ($address_key == $shippingAddress) {
                            $prefix = 'ship_to';
                        } else {
                            continue;
                        }
                        $address['virtuemart_userinfo_id'] = $user_id;
                        $phone = isset($address['phone']) ?
                            (!empty($customer['phones'][$address['phone']]) ? $customer['phones'][$address['phone']]
                                : null) : null;

                        $model->storeAddress($address);

                    }
                } else {
                    $user = JFactory::getUser($user_id);
                }

                // Forcefully log user in
                $app = JFactory::getApplication();
                $app->triggerEvent('onUserLogin', array((array)$user, $options));

                // Add items (product/coupon) to cart
                if (!empty($json['cart'])) {

                    if (!empty($json['cart']['productId'])) {
                        if (!class_exists('VirtueMartCart')) {
                            require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
                        }
                        $cart = VirtueMartCart::getCart();

                        $cart->add($json['cart']['productId'], false);


                    }

                    if (!empty($json['cart']['couponCode'])) {
                        $cart->setCouponCode($json['cart']['couponCode']);
                    }
                }

                // Dispatch password creation email
                $config = JFactory::getConfig();
                wp_mail($email, 'Welcome!', 'Your Password: ' . $password);
                $data['fromname']	= $config->get('fromname');
		$data['mailfrom']	= $config->get('mailfrom');
		$data['sitename']	= $config->get('sitename');
		$data['siteurl']	= JUri::root();
            } catch (\Exception $e) {
                // TODO: Log
            }

            wp_redirect("/");
        }

        /**
         * @param $uuid
         */
        private function migratestart($uuid)
        {
            $merchant = $this->app['merchant.provider']->getMerchant();
            $event = new Expressly\Event\CustomerMigrateEvent($merchant, $uuid);

            $popup = $this->dispatcher->dispatch('customer.migrate.start', $event)->getResponse();

            wp_enqueue_script('woocommerce_expressly', plugins_url('assets/js/popupbox.js', __FILE__));
            wp_localize_script('woocommerce_expressly', 'XLY', array(
                'uuid' => $uuid,
            ));

            add_action('wp_footer', function () use ($popup) {
                echo $popup;
            });
        }

        /**
         *
         */
        private function ping()
        {
            try {
                $response = $this->dispatcher->dispatch('utility.ping', new Expressly\Event\ResponseEvent());
                wp_send_json($response->getResponse());
            } catch (Exception $e) {
                wp_send_json($e);
            }
        }

        /**
         * @param $emailAddr
         */
        private function retrieveUserByEmail($emailAddr)
        {
            try {
                if (!is_email($emailAddr)) {
                    wp_redirect('/');
                }

                $user = get_user_by('email', $emailAddr);

                if ($user) {

                    echo '<pre>';

                    $customer = new Expressly\Entity\Customer();
                    $customer
                        ->setFirstName($user->first_name)
                        ->setLastName($user->last_name);

                    $email = new Expressly\Entity\Email();
                    $email
                        ->setEmail($emailAddr)
                        ->setAlias('primary');

                    $customer->addEmail($email);

                    $user_id = &$user->ID;
                    $first = true;
                    $prefixes = ['billing', 'shipping'];

                    $countryCodeProvider = $this->app['country_code.provider'];

                    foreach ($prefixes as $prefix) {

                        $address = new Expressly\Entity\Address();
                        $address
                            ->setFirstName(get_user_meta($user_id, $prefix . '_first_name', true))
                            ->setLastName(get_user_meta($user_id, $prefix . '_last_name', true))
                            ->setAddress1(get_user_meta($user_id, $prefix . '_address_1', true))
                            ->setAddress2(get_user_meta($user_id, $prefix . '_address_2', true))
                            ->setCity(get_user_meta($user_id, $prefix . '_city', true))
                            ->setZip(get_user_meta($user_id, $prefix . '_postcode', true));

                        $iso3 = $countryCodeProvider->getIso3(get_user_meta($user_id, $prefix . '_country', true));
                        $address->setCountry($iso3);
                        $address->setStateProvince(get_user_meta($user_id, $prefix . '_state', true));

                        $phoneNumber = get_user_meta($user_id, $prefix . '_phone', true);

                        if (!empty($phoneNumber)) {
                            $phone = new Expressly\Entity\Phone();
                            $phone
                                ->setType(Expressly\Entity\Phone::PHONE_TYPE_HOME)
                                ->setNumber((string)$phoneNumber);

                            $customer->addPhone($phone);
                        }

                        $customer->addAddress($address, $first,
                            ('billing' == $prefix) ? Expressly\Entity\Address::ADDRESS_BILLING
                                : Expressly\Entity\Address::ADDRESS_SHIPPING
                        );
                        $first = false;
                    }

                    $merchant = $this->app['merchant.provider']->getMerchant();
                    $response = new Expressly\Presenter\CustomerMigratePresenter($merchant, $customer, $emailAddr,
                        $user->ID);

                    wp_send_json($response->toArray());
                }
            } catch (\Exception $e) {
                wp_send_json(array(
                    'error' => sprintf('%s - %s::%u', $e->getFile(), $e->getMessage(), $e->getLine())
                ));
            }
        }

    }

    $Expressly = new VM_Expressly();

endif;

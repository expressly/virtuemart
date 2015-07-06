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
    class VM_Expressly extends JControllerLegacy {

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
        public function __construct() {
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
        public function template_redirect() {
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

        private function migratecomplete($uuid) {
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
                    $user = VmModel::getModel('user');
                    $data_to_save = array(
                        ["name"] => $customer['firstName'] . ' ' . $customer['lastName'],
                        ["username"] => $email,
                        ["password1"] => $password,
                        ["password2"] =>
                        $password,
                        ["email1"] => $email,
                        ["email2"] => $email
                    );
                    //Not sure about this one
                    $user->setParam('activate', 1);
                    $return = $user->save($data_to_save);
                    $user_id = $return['newId'];


                    $billingAddress = $customer['billingAddress'];
                    $shippingAddress = $customer['shippingAddress'];

                    $countryCodeProvider = $this->app['country_code.provider'];

                    foreach ($customer['addresses'] as $address_key => $address) {

                        $address['virtuemart_userinfo_id'] = $user_id;

                        $user->storeAddress($address);
                        $user->setParam('activate', 1);
                        if ($user->save()) {
                            $data = $user->getProperties();

                            $emailSubject = JText::sprintf(
                                            'COM_USERS_EMAIL_ACTIVATE_WITH_ADMIN_ACTIVATION_SUBJECT', $data['name'], $data['sitename']
                            );

                            $emailBody = JText::sprintf(
                                            'COM_USERS_EMAIL_ACTIVATE_WITH_ADMIN_ACTIVATION_BODY', $data['sitename'], $data['name'], $data['email'], $data['username'], $data['activate']
                            );
                            $return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);

                            // Check for an error.
                            if ($return !== true) {
                                $this->setError(JText::_('COM_USERS_REGISTRATION_ACTIVATION_NOTIFY_SEND_MAIL_FAILED'));
                                return false;
                            }
                        }
                    }
                } else {
                    $user = JFactory::getUser($user_id);
                }

                // Forcefully log user in
                $app = JFactory::getApplication();
                $app->triggerEvent('onUserLogin', array((array) $user, $options));

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
                $data['fromname'] = $config->get('fromname');
                $data['mailfrom'] = $config->get('mailfrom');
                $data['sitename'] = $config->get('sitename');
                $return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $data['email'], 'Welcome!', 'Your temporary Password: ' . $password);
            } catch (\Exception $e) {
                // TODO: Log
            }

            $this->setRedirect('/');
        }

        /**
         * @param $uuid
         */
        private function migratestart($uuid) {
            $merchant = $this->app['merchant.provider']->getMerchant();
            $event = new Expressly\Event\CustomerMigrateEvent($merchant, $uuid);

            $popup = $this->dispatcher->dispatch('customer.migrate.start', $event)->getResponse();
        }

        /**
         *
         */
        private function ping() {
            try {
                $response = $this->dispatcher->dispatch('utility.ping', new Expressly\Event\ResponseEvent());
            } catch (Exception $e) {
                
            }
        }

        /**
         * @param $emailAddr
         */
        private function retrieveUserByEmail($emailAddr) {
            
        }

    }

    $Expressly = new VM_Expressly();

endif;

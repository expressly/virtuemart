<?php

defined('_JEXEC') or die();

use Expressly\Event\CustomerMigrateEvent;
use Expressly\Exception\ExceptionFormatter;
use Expressly\Exception\GenericException;
use Expressly\Subscriber\CustomerMigrationSubscriber;

/**
 *
 */
abstract class ExpresslyHelper
{
    /**
     * @var null
     */
    protected static $app = null;

    /**
     * @param $app
     */
    public static function setApp($app)
    {
        self::$app = $app;
    }

    /**
     *
     */
    protected static function send_json($data)
    {
        JFactory::getApplication()->setHeader('Content-Type', 'application/json', true);
        JFactory::getApplication()->sendHeaders();

        echo json_encode($data);

        JFactory::getApplication()->close();
    }

    /**
     *
     */
    public static function ping()
    {
        $presenter = new \Expressly\Presenter\PingPresenter();
        self::send_json($presenter->toArray());
    }

    /**
     *
     */
    public static function retrieveUserByEmail($emailAddr)
    {
        try {
            // get joomla user
            $user = self::get_user_by_email($emailAddr);

            // TODO: Need to load virtuemart specific fields (first_name, last_name, etc)

            if (null !== $user) {

                throw new \Exception('Missed required data');

                /*$customer = new \Expressly\Entity\Customer();
                $customer
                    ->setFirstName($user->first_name)
                    ->setLastName($user->last_name);
                $email = new \Expressly\Entity\Email();
                $email
                    ->setEmail($emailAddr)
                    ->setAlias('primary');
                $customer->addEmail($email);
                $user_id =& $user->ID;
                $countryCodeProvider = self::$app['country_code.provider'];
                $createAddress = function ($prefix) use ($user_id, $countryCodeProvider, $customer) {
                    $address = new Address();
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
                        $phone = new \Expressly\Entity\Phone();
                        $phone
                            ->setType(\Expressly\Entity\Phone::PHONE_TYPE_HOME)
                            ->setNumber((string)$phoneNumber);
                        $customer->addPhone($phone);
                        $address->setPhonePosition((int)$customer->getPhoneIndex($phone));
                    }
                    return $address;
                };
                $billingAddress = $createAddress('billing');
                $shippingAddress = $createAddress('shipping');
                if (Address::compare($billingAddress, $shippingAddress)) {
                    $customer->addAddress($billingAddress, true, Address::ADDRESS_BOTH);
                } else {
                    $customer->addAddress($billingAddress, true, Address::ADDRESS_BILLING);
                    $customer->addAddress($shippingAddress, true, Address::ADDRESS_SHIPPING);
                }
                $merchant = self::$app['merchant.provider']->getMerchant();
                $response = new \Expressly\Presenter\CustomerMigratePresenter($merchant, $customer, $emailAddr, $user->ID);
                self::send_json($response->toArray());*/
            }
        } catch (\Exception $e) {
            self::$app['logger']->error(ExceptionFormatter::format($e));
            self::send_json([]);
        }
    }

    /**
     * Get Joomla! user by email
     *
     * @param  string $email
     * @return JUser|null
     */
    protected static function get_user_by_email($email)
    {
        $db = JFactory::getDbo();

        $db->setQuery("SELECT * FROM #__users WHERE email = " . $db->quote($email) . "");
        $result = $db->loadObject();

        return ($result->id) ? JFactory::getUser($result->id) : null;
    }

    /**
     *
     */
    public static function migratestart($uuid)
    {
        $merchant = self::$app['merchant.provider']->getMerchant();
        $event    = new CustomerMigrateEvent($merchant, $uuid);

        try {
            self::$app['dispatcher']->dispatch(CustomerMigrationSubscriber::CUSTOMER_MIGRATE_POPUP, $event);
            var_dump($event);
            if (!$event->isSuccessful()) {
                throw new GenericException(self::error_formatter($event));
            }
        } catch (\Exception $e) {
            //var_dump($e);
            self::$app['logger']->error(ExceptionFormatter::format($e));
            JFactory::getApplication()->redirect('/');
        }

        //wp_enqueue_script('woocommerce_expressly', plugins_url('assets/js/expressly.popup.js', __FILE__));
        //wp_localize_script('woocommerce_expressly', 'XLY', array('uuid' => $uuid));

        $content = $event->getContent();

        /*add_action('wp_footer', function () use ($content) {
            echo $content;
        });*/
    }

    /**
     *
     */
    public static function migratecomplete($uuid)
    {
        if (empty($uuid))
            JFactory::getApplication()->redirect('/');

        $exists = false;
        $merchant = self::$app['merchant.provider']->getMerchant();
        $event = new Expressly\Event\CustomerMigrateEvent($merchant, $uuid);
        try {
            self::$app['dispatcher']->dispatch(CustomerMigrationSubscriber::CUSTOMER_MIGRATE_DATA, $event);
            $json = $event->getContent();
            if (!$event->isSuccessful()) {
                if (!empty($json['code']) && $json['code'] == 'USER_ALREADY_MIGRATED') {
                    $exists = true;
                }
                throw new \Expressly\Exception\UserExistsException(self::error_formatter($event));
            }
            $email = $json['migration']['data']['email'];
            $user_id = email_exists($email);
            if (!$user_id) {
                $customer = $json['migration']['data']['customerData'];
                // Generate the password and create the user
                $password = wp_generate_password(12, false);
                $user_id = wp_create_user($email, $password, $email);
                wp_update_user(array(
                    'ID' => $user_id,
                    'first_name' => $customer['firstName'],
                    'last_name' => $customer['lastName'],
                    'display_name' => $customer['firstName'] . ' ' . $customer['lastName'],
                ));
                // Set the role
                $user = new WP_User($user_id);
                $user->set_role('customer');
                $countryCodeProvider = self::$app['country_code.provider'];
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
            } else {
                $exists = true;
                $event = new CustomerMigrateEvent($merchant, $uuid, CustomerMigrateEvent::EXISTING_CUSTOMER);
            }
            // Add items (product/coupon) to cart
            if (!empty($json['cart'])) {
                WC()->cart->empty_cart();
                if (!empty($json['cart']['productId'])) {
                    WC()->cart->add_to_cart($json['cart']['productId'], 1);
                }
                if (!empty($json['cart']['couponCode'])) {
                    WC()->cart->add_discount(sanitize_text_field($json['cart']['couponCode']));
                }
            }
            self::$app['dispatcher']->dispatch(CustomerMigrationSubscriber::CUSTOMER_MIGRATE_SUCCESS, $event);
        } catch (\Exception $e) {
            self::$app['logger']->error(ExceptionFormatter::format($e));
        }

        if ($exists) {
            wp_enqueue_script('woocommerce_expressly', plugins_url('assets/js/expressly.exists.js', __FILE__));
            return;
        }

        JFactory::getApplication()->redirect('/');
    }

    /**
     *
     */
    public static function batchCustomer()
    {
        $json = file_get_contents('php://input');
        $json = json_decode($json);
        $users = array();
        try {
            if (!property_exists($json, 'emails')) {
                throw new GenericException('Invalid JSON request');
            }
            $merchant = self::$app['merchant.provider']->getMerchant();
            foreach ($json->emails as $email) {
                // user_status is a deprecated column and cannot be depended upon
                if (email_exists($email)) {
                    $users['existing'][] = $email;
                }
            }
            $presenter = new BatchCustomerPresenter($merchant, $users);
            wp_send_json($presenter->toArray());
        } catch (GenericException $e) {
            self::$app['logger']->error($e);
            wp_send_json(array());
        }
    }

    /**
     *
     */
    public static function batchInvoice()
    {
        $json = file_get_contents('php://input');
        $json = json_decode($json);
        $invoices = array();
        try {
            if (!property_exists($json, 'customers')) {
                throw new GenericException('Invalid JSON request');
            }
            $merchant = self::$app['merchant.provider']->getMerchant();
            foreach ($json->customers as $customer) {
                if (!property_exists($customer, 'email')) {
                    continue;
                }
                if (email_exists($customer->email)) {
                    $invoice = new Invoice();
                    $invoice->setEmail($customer->email);
                    $orderPosts = get_posts(array(
                        'meta_key' => '_billing_email',
                        'meta_value' => $customer->email,
                        'post_type' => 'shop_order',
                        'numberposts' => -1
                    ));
                    foreach ($orderPosts as $post) {
                        $wpOrder = new WC_Order($post->ID);
                        if ($wpOrder->order_date > $customer->from && $wpOrder->order_date < $customer->to) {
                            $total = 0.0;
                            $tax = 0.0;
                            $count = 0;
                            $order = new Order();
                            foreach ($wpOrder->get_items('line_item') as $lineItem) {
                                $tax += (double)$lineItem['line_tax'];
                                $total += (double)$lineItem['line_total'] - (double)$lineItem['line_tax'];
                                $count++;
                                if ($lineItem->tax_class) {
                                    $order->setCurrency($lineItem['tax_class']);
                                }
                            }
                            $order
                                ->setId($wpOrder->id)
                                ->setDate(new \DateTime($wpOrder->order_date))
                                ->setItemCount($count)
                                ->setTotal($total, $tax);
                            $coupons = $wpOrder->get_used_coupons();
                            if (!empty($coupons)) {
                                $order->setCoupon($coupons[0]);
                            }
                            $invoice->addOrder($order);
                        }
                    }
                    $invoices[] = $invoice;
                }
            }
            $presenter = new BatchInvoicePresenter($merchant, $invoices);
            wp_send_json($presenter->toArray());
        } catch (GenericException $e) {
            self::$app['logger']->error($e);
            wp_send_json(array());
        }
    }

    /**
     *
     */
    protected static function error_formatter($event)
    {
        $content = $event->getContent();
        $message = array(
            $content['description']
        );
        $addBulletpoints = function ($key, $title) use ($content, &$message) {
            if (!empty($content[$key])) {
                $message[] = '<br>';
                $message[] = $title;
                $message[] = '<ul>';
                foreach ($content[$key] as $point) {
                    $message[] = "<li>{$point}</li>";
                }
                $message[] = '</ul>';
            }
        };
        // TODO: translatable
        $addBulletpoints('causes', 'Possible causes:');
        $addBulletpoints('actions', 'Possible resolutions:');
        return implode('', $message);
    }

}

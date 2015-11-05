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
    public static function send_json($data)
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
    public static function get_user_by_email($email)
    {
        $db = JFactory::getDbo();

        $db->setQuery("SELECT * FROM #__users WHERE email = " . $db->quote($email) . "");
        $result = $db->loadObject();

        return ($result->id) ? JFactory::getUser($result->id) : null;
    }

    /**
     *
     */
    public static function error_formatter($event)
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

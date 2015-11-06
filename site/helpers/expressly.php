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

                    $address->setCountry(self::get_country_iso3_by_id($userInfo->virtuemart_country_id));
                    $address->setStateProvince(self::get_state_name_by_id($userInfo->virtuemart_state_id));

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

                $merchant = self::$app['merchant.provider']->getMerchant();
                $response = new \Expressly\Presenter\CustomerMigratePresenter($merchant, $customer, $emailAddr, $user->id);

                self::send_json($response->toArray());
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

    public static function get_country_iso3_by_id($country_id)
    {
        $result = JFactory::getDBO()
            ->setQuery('SELECT `country_3_code` FROM `#__virtuemart_countries` WHERE virtuemart_country_id = "' . intval($country_id) . '"')
            ->loadColumn(0);

        return $result[0];
    }

    public static function get_state_name_by_id($state_id)
    {
        $result = JFactory::getDBO()
            ->setQuery('SELECT `state_name` FROM `#__virtuemart_states` WHERE virtuemart_state_id = "' . intval($state_id) . '"')
            ->loadColumn(0);

        return $result[0];
    }
}

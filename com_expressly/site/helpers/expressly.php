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

    /**
     * @param $country_id
     * @return mixed
     */
    public static function get_country_iso3_by_id($country_id)
    {
        $result = JFactory::getDBO()
            ->setQuery('SELECT `country_3_code` FROM `#__virtuemart_countries` WHERE virtuemart_country_id = "' . intval($country_id) . '"')
            ->loadColumn(0);

        return $result[0];
    }

    /**
     * @param $iso2
     * @return mixed
     */
    public static function get_country_id_by_iso2($iso2)
    {
        $result = JFactory::getDBO()
            ->setQuery('SELECT `virtuemart_country_id` FROM `#__virtuemart_countries` WHERE country_2_code = "' . strval($iso2) . '"')
            ->loadColumn(0);

        return $result[0];
    }

    /**
     * @param $state_id
     * @return mixed
     */
    public static function get_state_name_by_id($state_id)
    {
        $result = JFactory::getDBO()
            ->setQuery('SELECT `state_name` FROM `#__virtuemart_states` WHERE virtuemart_state_id = "' . intval($state_id) . '"')
            ->loadColumn(0);

        return $result[0];
    }

    /**
     * @param $state_name
     * @return mixed
     */
    public static function get_state_id_by_name($state_name)
    {
        $result = JFactory::getDBO()
            ->setQuery('SELECT `virtuemart_state_id` FROM `#__virtuemart_states` WHERE `state_name` LIKE "' . strval($state_name) . '"')
            ->loadColumn(0);

        return $result[0];
    }
}

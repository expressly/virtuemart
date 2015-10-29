<?php

defined('_JEXEC') or die();

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
        self::send_json(['hello' => 1]);
    }
}

<?php

defined('_JEXEC') or die;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

/**
 * Expressly plugin class.
 *
 * @package     Joomla.plugin
 * @subpackage  System.Expressly
 */
class plgSystemExpressly extends JPlugin
{
    /**
     *
     */
    public function onAfterInitialise()
    {
        JLoader::import('expressly.vendor.autoload');
        JLoader::import('expressly.ExpresslyMerchantProvider');
    }
}
<?php

defined('_JEXEC') or die;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

if (!class_exists ('VmConfig')) {
    if(file_exists(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php')){
        require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
    } else {
        jExit('Install the virtuemart Core first');
    }
}

JLoader::import('expressly.vendor.autoload');
JLoader::import('expressly.ExpresslyMerchantProvider');

$controller = JControllerLegacy::getInstance('Expressly');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();

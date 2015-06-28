<?php

defined('_JEXEC') or die('Restricted access');
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
if (!class_exists('VmConfig')) {
    require(JPATH_ROOT . DS . 'administrator' . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS
        . 'config.php');
}
VmConfig::loadConfig();

if (!class_exists('VmModel')) {
    require(VMPATH_ADMIN . DS . 'helpers' . DS . 'vmmodel.php');
}
//TODO: remove !!!! Do not let this in production !!!!
/*error_reporting(E_ALL);
ini_set("display_errors", 1);*/

//require(__DIR__.'/vendor/autoload.php');

function com_install()
{
    $config = VmModel::getModel('config');
    //TODO: createpassword from buyexpressly
    $expresslyConfig = array(
        'expressly_host'           => sprintf('://%s', $_SERVER['HTTP_HOST']),
        'wc_expressly_destination' => '/',
        'wc_expressly_offer'       => 'yes',
        'wc_expressly_password'    => uniqid(),
        'wc_expressly_path'        => 'index.php'
    );

    if ($config->store($expresslyConfig)) {

        // Load the newly saved values into the session.
        VmConfig::loadConfig();
        echo 'Expressly configuration saved<br/>';
    }

    echo 'All done<br/>';
}

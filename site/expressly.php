<?php

defined('_JEXEC') or die;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

//jimport('expressly.autoload');
JLoader::import('expressly.autoload');

// require helper file
JLoader::register('ExpresslyHelper', dirname(__FILE__) . DS . 'helpers' . DS . 'expressly.php');

$controller = JControllerLegacy::getInstance('Expressly');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();

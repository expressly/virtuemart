<?php

defined('_JEXEC') or die;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// require helper file
JLoader::register('ExpresslyHelper', dirname(__FILE__) . DS . 'helpers' . DS . 'expressly.php');

$controller = JControllerLegacy::getInstance('Expressly');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();

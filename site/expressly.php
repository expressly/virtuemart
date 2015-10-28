<?php

defined('_JEXEC') or die;

$controller = JControllerLegacy::getInstance('Expressly');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();

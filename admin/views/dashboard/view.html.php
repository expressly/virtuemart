<?php

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

if (!class_exists ('VmConfig')) {
    if(file_exists(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php')){
        require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
    } else {
        jExit('Install the virtuemart Core first');
    }
}

VmConfig::loadConfig();

/**
 *
 */
class ExpresslyViewDashboard extends JViewLegacy
{
    /**
     * Display the Expressly/Dashboard view
     *
     * @param  string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return void
     */
    function display($tpl = null)
    {
        parent::display($tpl);
        $this->addToolBar();
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function addToolBar()
    {
        JToolBarHelper::title(JText::_('COM_EXPRESSLY_DASHBOARD'));

        if (true) {
            JToolBarHelper::custom('register', 'publish.png', 'publish_f2.png', 'Register', false);
            JToolBarHelper::divider();
        }

        JToolBarHelper::preferences('com_expressly');
        JToolBarHelper::divider();
        JToolBarHelper::help('JHELP_SITE_MAINTENANCE_GLOBAL_CHECK-IN');
    }
}
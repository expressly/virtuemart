<?php

defined ('_JEXEC') or die('Restricted access');

/**
 * Expressly script file
 * This file is executed during install/upgrade and uninstall
 */

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

if (!class_exists ('VmConfig')) {
    if(file_exists(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php')){
        require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
    } else {
        jExit('Install the virtuemart Core first');
    }
}

VmConfig::loadConfig();

if (!class_exists('VmModel'))
    require_once (VMPATH_ADMIN . DS . 'helpers' . DS . 'vmmodel.php');


// hack to prevent defining these twice in 1.6 installation
if (!defined ('_EXPRESSLY_SCRIPT_INCLUDED')) {

    define('_EXPRESSLY_SCRIPT_INCLUDED', TRUE);

    /**
     *
     */
    class com_expresslyInstallerScript
    {
        /**
         * Method to run before an install/update/uninstall method
         * $parent is the class calling this method
         * $type is the type of change (install, update or discover_install)
         *
         * @return void
         */
        function preflight($type, $parent)
        {
            echo '<p>Anything here happens before the installation/update/uninstallation of the module</p>';
        }

        /**
         * Method to install the extension
         * $parent is the class calling this method
         *
         * @return void
         */
        function install($parent)
        {
            echo '<p>The module has been installed</p>';

            return true;
        }

        /**
         * Method to update the extension
         * $parent is the class calling this method
         *
         * @return void
         */
        function update($parent)
        {
            echo '<p>The module has been updated to version ' . $parent->get('manifest')->version . '</p>';
        }

        /**
         * Method to uninstall the extension
         * $parent is the class calling this method
         *
         * @return void
         */
        function uninstall($parent)
        {
            echo '<p>The module has been uninstalled</p>';
        }

        /**
         * Method to run after an install/update/uninstall method
         * $parent is the class calling this method
         * $type is the type of change (install, update or discover_install)
         *
         * @return void
         */
        function postflight($type, $parent)
        {
            // $parent is the class calling this method
            // $type is the type of change (install, update or discover_install)

            if ($type == 'install') {
                $db = JFactory::getDBO();
                $query = $db->getQuery(true);
                $query->update($db->quoteName('#__extensions'));
                $defaults = json_encode([
                    'expressly_host' => JUri::root(),
                    'expressly_path' => 'index.php?option=com_expressly&__xly='
                ]);
                $query->set($db->quoteName('params') . ' = ' . $db->quote($defaults));
                $query->where($db->quoteName('element') . ' = ' . $db->quote('com_expressly'));
                $db->setQuery($query);
                $db->execute();
            }

            echo '<p>Anything here happens after the installation/update/uninstallation of the module</p>';
        }
    }
}

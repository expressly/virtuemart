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

// hack to prevent defining these twice in 1.6 installation
if (!defined ('_EXPRESSLY_SCRIPT_INCLUDED')) {

    define('_EXPRESSLY_SCRIPT_INCLUDED', TRUE);

    class com_expresslyInstallerScript
    {
        /**
         * Method to install the extension
         * $parent is the class calling this method
         *
         * @return void
         */
        function install($parent)
        {
            echo '<p>The module has been installed</p>';
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
         * Method to update the extension
         * $parent is the class calling this method
         *
         * @return void
         */
        function update($parent)
        {
            echo '<p>The module has been updated to version' . $parent->get('manifest')->version . '</p>';
        }

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
         * Method to run after an install/update/uninstall method
         * $parent is the class calling this method
         * $type is the type of change (install, update or discover_install)
         *
         * @return void
         */
        function postflight($type, $parent)
        {
            echo '<p>Anything here happens after the installation/update/uninstallation of the module</p>';
        }

        public function vmInstall ($dontMove = 0)
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

            echo "<h3>Installation Successful.</h3>";

            return TRUE;
        }

    }

    if (!defined ('_VM_SCRIPT_INCLUDED')) {
        // PLZ look in #vminstall.php# to add your plugin and module
        function com_install () {

            if (!version_compare (JVERSION, '1.6.0', 'ge')) {
                $vmInstall = new com_expresslyInstallerScript();
                $vmInstall->vmInstall ();
            }
            return TRUE;
        }

        function com_uninstall () {

            return TRUE;
        }
    }
} //if defined
// pure php no tag
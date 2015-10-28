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
/*
VmConfig::loadConfig();

if (!class_exists('VmModel'))
    require_once (VMPATH_ADMIN . DS . 'helpers' . DS . 'vmmodel.php');

*/

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
            jimport ('joomla.filesystem.file');
            jimport ('joomla.installer.installer');

            $this->path = JInstaller::getInstance ()->getPath ('extension_administrator');

            // libraries auto move
            $src = $this->path . DS . "libraries";
            $dst = JPATH_ROOT . DS . "libraries";

            var_dump($src, $dst, $this->recurse_copy ($src, $dst)); die();


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

            echo '<p>The module has been installed</p>';

            return false;
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
            echo '<p>Anything here happens after the installation/update/uninstallation of the module</p>';
        }

        /**
         * copy all $src to $dst folder and remove it
         *
         * @author Max Milbers
         * @param String $src path
         * @param String $dst path
         * @param String $type modulesBE, modules, plugins, languageBE, languageFE
         */
        private function recurse_copy ($src, $dst) {

            static $failed = false;
            $dir = opendir ($src);

            if (is_resource ($dir)) {
                while (FALSE !== ($file = readdir ($dir))) {
                    if (($file != '.') && ($file != '..')) {
                        if (is_dir ($src . DS . $file)) {
                            if(!JFolder::create($dst . DS . $file)){
                                $app = JFactory::getApplication ();
                                $app->enqueueMessage ('Couldnt create folder ' . $dst . DS . $file);
                            }
                            $this->recurse_copy ($src . DS . $file, $dst . DS . $file);
                        } else {
                            if (JFile::exists ($dst . DS . $file)) {
                                if (!JFile::delete ($dst . DS . $file)) {
                                    $app = JFactory::getApplication ();
                                    $app->enqueueMessage ('Couldnt delete ' . $dst . DS . $file);
                                    //return false;
                                }
                            }
                            if (!JFile::move ($src . DS . $file, $dst . DS . $file)) {
                                $app = JFactory::getApplication ();
                                $app->enqueueMessage ('Couldnt move ' . $src . DS . $file . ' to ' . $dst . DS . $file);
                                $failed = true;
                                //return false;
                            }
                        }
                    }
                }
                closedir ($dir);
                if (is_dir ($src) and !$failed) {
                    JFolder::delete ($src);
                }
            } else {
                $app = JFactory::getApplication ();
                $app->enqueueMessage ('Couldnt read dir ' . $dir . ' source ' . $src);
                return false;
            }
            return true;
        }
    }
}

<?php

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * General Controller of Expressly component
 *
 * @package     Joomla.Administrator
 * @subpackage  com_helloworld
 * @since       0.2.0
 */
class ExpresslyController extends JControllerLegacy
{
    /**
     * The default view for the display method.
     *
     * @var string
     * @since 12.2
     */
    protected $default_view = 'dashboard';

    /**
     *
     */
    public function register()
    {
        // Check for request forgeries
        JSession::checkToken() or jexit(JText::_('JInvalid_Token'));

        // Do something
        die('register');

        $this->setRedirect('index.php?option=com_expressly');
    }
}
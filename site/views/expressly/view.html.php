<?php

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * HTML View class for the Expressly Component
 *
 * @since  0.1.0
 */
class ExpresslyViewExpressly extends JViewLegacy
{
    /**
     * Display the Expressly view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     */
    function display($tpl = null)
    {
        // Assign data to the view
        $this->msg = 'Expressly';

        // Display the view
        parent::display($tpl);
    }
}

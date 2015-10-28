<?php

defined('_JEXEC') or die();

jimport('expressly.autoload');

use Expressly\Presenter\PingPresenter;

/**
 *
 */
class ExpresslyController extends JControllerLegacy
{
    public function display($cachable = false, $urlparams = false)
    {
        $__xly = JFactory::getApplication()->input->get('__xly', '', 'string');
        //$__xly = JRequest::getVar('__xly');
        $data = [];

        switch ($__xly) {

            case '/expressly/api/ping':

                $presenter = new PingPresenter();
                $data = $presenter->toArray();

                break;
            default:
                die('Nothing here');
                break;

        }

        JResponse::setHeader('Content-Type', 'application/json', true);
        JResponse::sendHeaders();

        echo json_encode($data);

        JFactory::getApplication()->close();

    }
}
/*
class ExpresslyController extends JControllerLegacy
{
    public function __construct($config = array())
    {
        parent::__construct($config);

        // Register Extra tasks
        $this->registerTask('email', 'shout');
    }

    function ping()
    {
        echo new JResponseJson(['sdfasdf' => 43]);
    }
}*/

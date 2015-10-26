<?php

defined('_JEXEC') or die();

//use Expressly\Presenter\PingPresenter;

/**
 *
 */
class ExpresslyController extends JControllerLegacy
{
    public function display($cachable = false, $urlparams = false)
    {
        $__xly = JFactory::getApplication()->input->get('__xly', '', 'string');
        //$__xly = JRequest::getVar('__xly');

        switch ($__xly) {

            case '/expressly/api/ping':
                /*
                $presenter = new PingPresenter();
                wp_send_json($presenter->toArray());
                */
                break;
            default:
                die('Nothing here');
                break;

        }

        JResponse::setHeader('Content-Type', 'application/json', true);
        JResponse::sendHeaders();

        echo json_encode(['__xly' => $__xly]);

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

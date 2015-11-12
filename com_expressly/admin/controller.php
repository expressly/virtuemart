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

use Expressly\Entity\MerchantType,
    Expressly\Event\PasswordedEvent,
    Expressly\Subscriber\MerchantSubscriber;

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
     * @var null
     */
    public $app = null;

    /**
     * The default view for the display method.
     *
     * @var string
     * @since 12.2
     */
    protected $default_view = 'dashboard';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     * Recognized key values include 'name', 'default_task', 'model_path', and
     * 'view_path' (this list is not meant to be comprehensive).
     *
     * @since   12.2
     */
    public function __construct($config = array())
    {
        parent::__construct($config);

        // TODO: Need to create MerchantType VIRTUEMART
        $client = new \Expressly\Client(MerchantType::WOOCOMMERCE);

        $app = $client->getApp();
        $app['merchant.provider'] = $app->share(function () {
            return new ExpresslyMerchantProvider();
        });

        $this->app = $app;
    }

    /**
     *
     */
    public function register()
    {
        // Check for request forgeries
        JSession::checkToken() or jexit(JText::_('JInvalid_Token'));

        $merchant = $this->app['merchant.provider']->getMerchant(true);
        $event    = new PasswordedEvent($merchant);

        try {
            $this->app['dispatcher']->dispatch(MerchantSubscriber::MERCHANT_REGISTER, $event);
            if (!$event->isSuccessful()) {
                throw new \Expressly\Exception\InvalidAPIKeyException($this->error_formatter($event));
            }
        } catch (\Exception $e) {
            $this->app['logger']->error(\Expressly\Exception\ExceptionFormatter::format($e));
            JFactory::getApplication()->enqueueMessage(sprintf(
                '<div id="message" class="error"><p><strong>%s</strong></p></div>',
                $e->getMessage()
            ), 'error');
        }

        $this->setRedirect('index.php?option=com_expressly');
    }

    protected function error_formatter($event)
    {
        $content = $event->getContent();
        $message = array(
            $content['description']
        );
        $addBulletpoints = function ($key, $title) use ($content, &$message) {
            if (!empty($content[$key])) {
                $message[] = '<br>';
                $message[] = $title;
                $message[] = '<ul>';
                foreach ($content[$key] as $point) {
                    $message[] = "<li>{$point}</li>";
                }
                $message[] = '</ul>';
            }
        };
        // TODO: translatable
        $addBulletpoints('causes', 'Possible causes:');
        $addBulletpoints('actions', 'Possible resolutions:');
        return implode('', $message);
    }
}
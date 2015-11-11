<?php
/**
 * Hello World! Module Entry Point
 *
 * @package    Joomla.Tutorials
 * @subpackage Modules
 * @license    GNU/GPL, see LICENSE.php
 * @link       http://docs.joomla.org/J3.x:Creating_a_simple_module/Developing_a_Basic_Module
 * mod_helloworld is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// No direct access
defined('_JEXEC') or die;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

JLoader::import('expressly.vendor.autoload');
JLoader::import('expressly.ExpresslyMerchantProvider');

// require helper file
JLoader::register('ExpresslyHelper', JPATH_SITE . DS . "components" . DS . "com_expressly" . DS . 'helpers' . DS . 'expressly.php');

$request = vRequest::getRequest();
$task    = vRequest::getCmd('task');

if ($task == 'confirm' || isset($request['confirm'])) {
    // TODO: Need to create MerchantType VIRTUEMART
    /*$client = new \Expressly\Client(\Expressly\Entity\MerchantType::WOOCOMMERCE);

    $app = $client->getApp();
    $app['merchant.provider'] = $app->share(function () {
        return new ExpresslyMerchantProvider();
    });
    $app['version'] = $app->share(function () {
        return 'v2';
    });

    $merchant = $app['merchant.provider']->getMerchant();
    $user     = JFactory::getUser();

    $event = new \Expressly\Event\BannerEvent($merchant, $user->email);

    try {
        $app['dispatcher']->dispatch(\Expressly\Subscriber\BannerSubscriber::BANNER_REQUEST, $event);

        if (!$event->isSuccessful()) {
            throw new \Expressly\Exception\GenericException(error_formatter($event));
        }
    } catch (\Expressly\Exception\GenericException $e) {
        $app['logger']->error($e);
    }
    echo \Expressly\Helper\BannerHelper::toHtml($event);
    */
    echo '<img src="https://placeholdit.imgix.net/~text?txtsize=23&txt=this_is_banner432&w=782&h=90" />';
}

<?php

defined('_JEXEC') or die;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

/**
 * Expressly plugin class.
 *
 * @package     Joomla.plugin
 * @subpackage  System.Expressly
 */
class plgSystemExpressly extends JPlugin
{
    /**
     *
     */
    public function onAfterInitialise()
    {
        JLoader::import('expressly.vendor.autoload');
        JLoader::import('expressly.ExpresslyMerchantProvider');
    }

    /**
     *
     */
    public function onBeforeRender()
    {
        $session = JFactory::getSession();

        // Do nothing if it isn't Expressly specified request
        if (!$session->has('__xly')) return;

        $__xly = $session->get('__xly');

        if (isset($__xly['action'])) {

            switch($__xly['action']) {
                case 'popup': {
                    $document = JFactory::getDocument();
                    $document->addScriptDeclaration('
(function () {
    popupContinue = function (event) {
        event.style.display = "none";
        var loader = event.nextElementSibling;
        loader.style.display = "block";
        loader.nextElementSibling.style.display = "none";

        window.location.replace(window.location.origin + "/index.php?option=com_expressly&__xly=/expressly/api/" + "' . $__xly['uuid'] . '" + "/migrate");
    };

    popupClose = function (event) {
        window.location.replace(window.location.origin);
    };

    openTerms = function (event) {
        window.open(event.href, \'_blank\');
    };

    openPrivacy = function (event) {
        window.open(event.href, \'_blank\');
    };

    (function ($) {
        $(document).ready(function(){
            document.body.innerHTML += \'' .  trim(preg_replace('/\s+/', ' ', $__xly['popup'])) . '\';
        });
    })(jQuery);
})();');
                } break;
                case 'exists': {
                    $document = JFactory::getDocument();
                    $document->addScriptDeclaration('
(function() {
    setTimeout(function() {
        var login = confirm("Your email address has already been registered on this store. Please login with your credentials. Pressing OK will redirect you to the login page.");
        if (login) {
            window.location.replace(window.location.origin + "/index.php?option=com_users&view=profile");
        } else {
            window.location.replace(window.location.origin);
        }
    }, 500);
})();');
                } break;
            }

        }

        $session->clear('__xly');

    }
}

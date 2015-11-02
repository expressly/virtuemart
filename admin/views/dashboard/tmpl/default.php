<?php defined('_JEXEC') or die('Restricted Access'); ?>

<form action="<?php echo JRoute::_('index.php?option=com_expressly'); ?>" method="post" name="adminForm" id="adminForm">

    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

    <ul>
        <li><?php echo JComponentHelper::getParams('com_expressly')->get('expressly_api_key'); ?></li>
        <li><?php echo JComponentHelper::getParams('com_expressly')->get('expressly_host'); ?></li>
        <li><?php echo JComponentHelper::getParams('com_expressly')->get('expressly_path'); ?></li>
    </ul>

    <input type="hidden" name="task" value="" />
    <?php echo JHtml::_('form.token'); ?>
</form>
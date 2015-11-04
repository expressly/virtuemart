<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
?><html>
<head>
    <script type="text/javascript">
        (function() {
            setTimeout(function() {
                var login = confirm('Your email address has already been registered on this store. Please login with your credentials. Pressing OK will redirect you to the login page.');
                if (login) {
                    window.location.replace(window.location.origin + '/index.php?option=com_users&view=profile');
                } else {
                    window.location.replace(window.location.origin);
                }
            }, 500);
        })();
    </script>
</head>
<body>
    <!-- Nothing here -->
</body>
</html>
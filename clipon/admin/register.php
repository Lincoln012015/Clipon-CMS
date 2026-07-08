<?php
require_once __DIR__ . '/../lib/Auth.php';

AdminAccess::requireUser($session, 'login.php');

header('Location: pro/users.php');
exit;

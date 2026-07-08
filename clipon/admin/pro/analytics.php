<?php
define('C_PRO_PROXY', true);

require_once __DIR__ . '/../../lib/Auth.php';

if (!$session->has('user')) {
    header('Location: ../login.php');
    exit;
}

if (function_exists('hasPermission') && !hasPermission('view_analytics')) {
    header('Location: ../index.php');
    exit;
}

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '../analytics.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target);
exit;

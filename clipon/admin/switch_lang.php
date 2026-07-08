<?php
require_once __DIR__ . '/../lib/Auth.php';

require_once __DIR__ . '/../lib/Translation.php';
Translation::init();

$supported = Translation::getInterfaceLangs();

$newLang = $request->query('lang');
if ($newLang && in_array($newLang, $supported, true)) {
    Translation::setInterfaceLang($newLang);
}

$referer = $request->server('HTTP_REFERER');
if ($referer) {
    header('Location: ' . $referer);
} else {
    header('Location: index.php');
}
exit;

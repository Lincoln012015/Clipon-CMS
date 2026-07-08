<?php
require_once __DIR__ . '/../lib/Auth.php';

AdminAccess::requireUserApi($session);
AdminResponder::jsonError('Deprecated endpoint. Use api/media.php with action=rename_folder.', 410);


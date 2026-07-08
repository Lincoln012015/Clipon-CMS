<?php
require_once __DIR__ . '/../lib/Auth.php';

AdminAccess::requireUserApi($session);
AdminResponder::jsonError('Deprecated endpoint. Use api/media.php with action=bulk_delete.', 410);

<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

require_once __DIR__ . '/../../mhcleaning_server/myob/myob_utils.php';

$success = refresh_myob_tokens();

echo json_encode(['success' => $success]);
<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

require_once __DIR__ . '/../../mhcleaning_server/myob/myob_utils.php';

$response = get_myob_cf_list();

if ($response === false)
  $response = ['error' => 'Your session has expired. Please login again.'];

echo json_encode($response);
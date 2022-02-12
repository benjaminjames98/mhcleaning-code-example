<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

require_once __DIR__ . '/../../mhcleaning_server/myob/myob_utils.php';

if (!isset($_REQUEST['json'])) {
  echo json_encode(['error' => 'Your session has expired. Please login again.']);
  die();
}

$json = json_decode(urldecode($_REQUEST['json']), true);
if ($json === null) {
  error_log(
    __DIR__ . ", line " . __LINE__
    . ": no json recieved: \$json: $json\n \$_REQUEST: "
    . var_export($_REQUEST, true)
    . "\n \$_REQUEST['json']: "
    . var_export($_REQUEST['json'], true)
  );
  echo json_encode(['success' => false]);
  die();
}
$uid = filter_var($json['cf_uid'], FILTER_SANITIZE_STRING);
$name = filter_var($json['cf_name'], FILTER_SANITIZE_STRING);
$username = filter_var($json['username'], FILTER_SANITIZE_STRING);
$password = filter_var($json['password'], FILTER_SANITIZE_STRING);


$success = set_myob_cf($uid, $name, $username, $password);

echo json_encode(['success' => $success]);
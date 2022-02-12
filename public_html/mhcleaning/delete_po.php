<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

if (!isset($_REQUEST['json'])) {
  echo json_encode(['unable to detect json component of URL']);
  die();
}

$json = json_decode(urldecode($_REQUEST['json']), true);
$job_number = filter_var($json['job_number'], FILTER_SANITIZE_STRING);
if (!$job_number) {
  echo json_encode(['error' => 'unable to decode job number']);
}

require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
$db = get_db();

if ($job_number === '****') {
  $query = "DELETE FROM invoice WHERE invoice_number IS null";
  $stmt = $db->prepare($query);
} else {
  $query = "DELETE FROM invoice WHERE job_number = ? AND invoice_number IS null";
  $stmt = $db->prepare($query);
  $stmt->bind_param('s', $job_number);
}
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
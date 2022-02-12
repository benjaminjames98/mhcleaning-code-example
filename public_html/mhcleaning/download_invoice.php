<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

if (isset($_REQUEST['job_number'])) $job_number = $_REQUEST['job_number'];
else {
  echo json_encode(['error' => 'please indicate which invoice you would like to download']);
  die();
}

require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
$db = get_db();

$query =
  "SELECT invoice_myob_uid, invoice_number FROM invoice WHERE job_number=?";
$stmt = $db->prepare($query);
$stmt->bind_param('s', $job_number);
$stmt->execute();
$stmt->bind_result($inv_uid, $inv_num);
$stmt->fetch();
$stmt->close();

if ($inv_uid === null) {
  echo json_encode(['error' => 'no invoice associated with that purchase order']);
  die();
}

require_once __DIR__ . "/../../mhcleaning_server/myob/myob_utils.php";
require_once __DIR__ . "/../../mhcleaning_imports/curl_utils.php";

if (!refresh_myob_tokens()) {
  echo json_encode(['error' => 'Your session has expired. Please login again.']);
  die();
}

$headers = get_myob_request_headers();
$cf_url = get_myob_cf_url();

$url = $cf_url . "/Sale/Invoice/Professional/$inv_uid/?format=pdf";

$page = get_page($url, 'GET', "", "", $headers);

$length = strlen($page['content']);

header("Content-Length: $length");
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"$inv_num.pdf\"");

echo $page['content'];
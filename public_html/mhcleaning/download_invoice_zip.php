<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
require_once __DIR__ . "/../../mhcleaning_server/myob/myob_utils.php";
require_once __DIR__ . "/../../mhcleaning_imports/curl_utils.php";
require_once __DIR__ . "/../../vendor/autoload.php";

if (isset($_REQUEST['job_numbers'])) $job_numbers = $_REQUEST['job_numbers'];
else {
  echo json_encode(['error' => 'please indicate which invoice you would like to download']);
  die();
}

if ($job_numbers === []) {
  echo json_encode(['error' => 'no invoices found']);
  die();
}

$options = new ZipStream\Option\Archive();
$options->setSendHttpHeaders(true);
$date = date("Y-m-d h-i-sa");
$zip = new \ZipStream\ZipStream("invoices $date.zip", $options);
$_SESSION['gets_done'] = 0;
$_SESSION['posts_done'] = 0;

$db = get_db();
$invoices = [];
$query =
  "SELECT invoice_myob_uid, invoice_number, builder FROM invoice WHERE job_number=?";
$stmt = $db->prepare($query);
$stmt->bind_param('s', $jn);
foreach ($job_numbers as $jn) {
  $stmt->execute();
  $stmt->bind_result($inv_uid, $inv_num, $builder);
  $stmt->fetch();
  $invoices[] = ['job_number' => $jn, 'uid' => $inv_uid, 'number' => $inv_num,
    'builder' => $builder];
}
$stmt->close();
$db->close();

if ($invoices === []) {
  echo json_encode(['error' => 'no invoices found']);
  die();
}

if (!refresh_myob_tokens()) {
  echo json_encode(['error' => 'Your session has expired. Please login again.']);
  die();
}

$headers = get_myob_request_headers();
$cf_url = get_myob_cf_url();
$handles = [];

foreach ($invoices as $inv) {
  $url = $cf_url . "/Sale/Invoice/Professional/${inv['uid']}/?format=pdf";
  $handles[$inv['number']] = get_curl_handle($url, 'GET', "", "", $headers);
}

execute_ch_array($handles, false);

foreach ($invoices as $inv) {
  $page = process_curl_result($handles[$inv['number']]);

  $file_name = "${inv['builder']}/";
  $file_name .= empty($inv['number']) ? $inv['job_number'] : $inv['number'];
  $zip->addFile("/${file_name}.pdf", $page['content']);
}

try {
  $zip->finish();
} catch (\ZipStream\Exception\OverflowException $e) {
  error_log($e);
}

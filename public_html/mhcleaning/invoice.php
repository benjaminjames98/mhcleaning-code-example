<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

if (!isset($_REQUEST['json'])) {
  echo json_encode(['error' => 'No array of purchase orders detected.']);
  die();
}

$json = json_decode(urldecode($_REQUEST['json']), true);
if (!count($json)) {
  echo json_encode(['error' => 'No job numbers selected.']);
  die();
}

$to_invoice = [];
$to_ignore = [];
foreach ($json as $inv) {
  if ($inv['customer_uid'] === 'already_invoiced')
    $to_ignore[] = $inv;
  else $to_invoice[] = $inv;
}

$_SESSION['currently_invoicing'] = count($json);
$_SESSION['posts_done'] = 0;
$_SESSION['gets_done'] = 0;
session_write_close();

require_once __DIR__ .
  '/../../mhcleaning_server/myob/generate_myob_invoices.php';
set_time_limit(900);
$ignored = ignore_myob_invoices($to_ignore);
$result = generate_myob_invoices($to_invoice);
$invoiced = $result['invoiced'];
$failed = $result['failed'];


if (!$invoiced && !$ignored && !$failed) {
  $error = 'Unable to post those invoices. Your error has been logged.'
    . ' If this error persists, please alert your developer/system'
    . ' administrator to this error.';
  $response = ['error' => $error];
} else $response =
  ['invoiced' => $invoiced ?: [],
    'ignored' => $ignored ?: [],
    'failed' => $failed ?: []];

echo json_encode($response);
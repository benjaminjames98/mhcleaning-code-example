<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

if (!isset($_REQUEST['json'])) {
  echo json_encode(['error' => 'No array of purchase orders detected.']);
  die();
}

$json = json_decode(urldecode($_REQUEST['json']), true);
$pos = $json['pos'];

foreach ($pos as $p) {
  push_po_to_db($p);
}

echo json_encode(['success' => true]);
die ();

function push_po_to_db($p) {
  require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
  $db = get_db();

  // add entry to db
  $p['job_number'] = substr($p['job_number'], 0, 6);
  $p['total_cost'] = str_replace('$', '', $p['total_cost']);
  $p['total_cost'] = str_replace(',', '', $p['total_cost']);

  //$str_format = (strlen($p['detail_date']) === 8) ? 'd/m/y' : 'd/m/Y';
  $str_format = (strlen($p['detail_date']) === 8) ? 'y-m-d' : 'Y-m-d';
  $p['detail_date'] = date_format(
    date_create_from_format($str_format, $p['detail_date']),
    'Y-m-d'
  );

  $query = <<<SQL
REPLACE INTO invoice (job_number, po_number, total_cost, builder,
                                  builder_contact, address,
                                  job_type, detail_date, excel_row_num,
                                  invoice_date,
                                  invoice_number, invoice_myob_uid)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, null, null, null)
SQL;
  $stmt = $db->prepare($query);
  $stmt->bind_param('sssssssss',
    $p['job_number'],
    $p['po_number'],
    $p['total_cost'],
    $p['builder'],
    $p['builder_contact'],
    $p['address'],
    $p['job_type'],
    $p['detail_date'],
    $p['excel_row_number']);
  ///* for debugging
  $stmt->execute();
  /*/
  if ($stmt->execute()) echo "\npo to db: " . $p['po_number'] . " || " .
    $p['job_number'] . "\n";
  else echo "\n" . $stmt->error;
  //*/
}
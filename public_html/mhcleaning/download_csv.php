<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
$db = get_db();

if (isset($_REQUEST['q'])) $filter = $_REQUEST['q'];
else $filter = 'all';

if ($filter === 'between') {
  if (isset($_REQUEST['from'], $_REQUEST['to'], $_REQUEST['reference'])) {
    $from = $_REQUEST['from'];
    $to = $_REQUEST['to'];
    if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $from)
      && preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $to)) {
      if (strtotime($from) > strtotime($to)) {
        $temp = $from;
        $from = $to;
        $to = $temp;
      }
      $reference = $_REQUEST['reference'];
      $reference = ($reference === 'detail') ? 'detail_date' : 'invoice_date';
    } else  $filter = 'all';
  } else $filter = 'all';
}

$query = <<<SQL
SELECT job_number, po_number, builder, builder_contact, address, job_type,
       detail_date, total_cost, invoice_number, invoice_date 
FROM invoice
SQL;

if ($filter === 'between')
  $query .= " WHERE ($reference BETWEEN ? AND ?) AND $reference IS NOT NULL ORDER BY $reference DESC";
else if ($filter === 'ready')
  $query .= " WHERE invoice_number IS NULL";
else if ($filter === 'invoiced')
  $query .= " WHERE invoice_number IS NOT NULL";

$stmt = $db->prepare($query);
if ($filter === 'between')
  $stmt->bind_param('ss', $from, $to);
$stmt->execute();
$stmt->bind_result(
  $job_number, $po_number, $builder, $builder_contact, $address, $job_type,
  $detail_date, $total_cost, $invoice_number, $invoice_date
);

$pos = [];
while ($stmt->fetch()) {
  $pos[] = [
    $job_number,
    $po_number,
    $builder,
    $builder_contact,
    $address,
    $job_type,
    $detail_date,
    $total_cost,
    $invoice_number ? "#$invoice_number" : "",
    $invoice_date,
  ];
}
$stmt->close();

$columns = [
  'job_number',
  'po_number',
  'builder',
  'builder_contact',
  'address',
  'job_type',
  'detail_date',
  'total_cost',
  'invoice_number',
  'invoice_date',
];
download_mssafe_csv("invoices.csv", $pos, $columns);

// Downloads microsoft-safe CSV. Based on function from:
// https://www.php.net/manual/en/function.fputcsv.php
function download_mssafe_csv($filename, $data, $headers) {
  if (!($fp = fopen("php://output", 'w'))) return false;

  header('Content-Type: application/csv');
  header('Content-Disposition: attachment; filename="' . $filename . '";');

  reset($headers);
  $first = current($headers);
  if (strpos($first, 'ID') === 0 && !preg_match('/["\\s,]/', $first)) {
    array_shift($headers);
    fwrite($fp, "\"{$first}\",");
  }
  fputcsv($fp, $headers);
  fseek($fp, -1, SEEK_CUR);

  foreach ($data as $line) fputcsv($fp, $line);

  fclose($fp);
  return true;
}
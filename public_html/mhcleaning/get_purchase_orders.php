<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
$db = get_db();

$query = <<<SQL
SELECT job_number,
       po_number,
       total_cost,
       builder,
       builder_contact,
       address,
       job_type,
       detail_date
FROM invoice
WHERE invoice_number IS NULL
SQL;
$stmt = $db->prepare($query);
$stmt->execute();
$stmt->bind_result($job_number,
  $po_number,
  $total_cost,
  $builder,
  $builder_contact,
  $address,
  $job_type,
  $detail_date);

$pos = [];
while ($stmt->fetch()) {
  $pos[] = [
    'job_number' => $job_number,
    'po_number' => $po_number,
    'total_cost' => $total_cost,
    'builder' => $builder,
    'builder_contact' => $builder_contact,
    'address' => $address,
    'job_type' => $job_type,
    'detail_date' => $detail_date
  ];
}
$stmt->close();

echo json_encode($pos);
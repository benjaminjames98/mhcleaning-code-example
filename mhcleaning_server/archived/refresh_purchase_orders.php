<?php

/**
 * This script is used to merge raw purchase orders with calendar events
 */

require_once __DIR__ . "/job_file/load_metricon_jobs.php";
require_once __DIR__ . "/teamup/load_teamup_events.php";

$metricon_jobs = load_metricon_jobs(false);
$calendar_events = load_calendar_events();
$pos = merge_calendar_with_po($calendar_events, $metricon_jobs);

foreach ($pos as $p) push_po_to_db($p);

function push_po_to_db($p) {
  require_once __DIR__ . "/../mhcleaning_imports/db_utils.php";
  $db = get_db();

  // check if entry is already in db
  $query = <<<SQL
SELECT invoice_number
FROM purchase_order
where po_number = ?
SQL;
  $stmt = $db->prepare($query);
  $stmt->bind_param('s', $p['po_number']);
  $stmt->execute();
  $stmt->bind_result($inv_num);
  $stmt->fetch();
  $stmt->close();
  if ($inv_num !== null) return;

  // add entry to db
  $query = <<<SQL
REPLACE INTO purchase_order (po_number, int_job_number, ext_job_number,
                            total_cost, builder, builder_contact, address,
                            squares, job_type, building_type, contractor_type,
                            who, detail_date, note)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
  $stmt = $db->prepare($query);
  $stmt->bind_param('ssssssssssssss', $p['po_number'], $p['int_job_number'],
    $p['ext_job_number'], $p['total_cost'], $p['builder'],
    $p['builder_contact'], $p['address'], $p['squares'], $p['job_type'],
    $p['building_type'], $p['contractor_type'], $p['who'], $p['detail_date'],
    $p['note']);
  ///* for debugging
  $stmt->execute();
  /*/
  if ($stmt->execute()) echo "\npo to db: " . $p['po_number'];
  else echo '\n' . $stmt->error;
  //*/
}
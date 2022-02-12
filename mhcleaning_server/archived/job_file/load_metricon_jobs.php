<?php

require_once __DIR__ . "/job_file_utils.php";

function load_metricon_jobs($refresh_db_from_web = false,
                            $echo_progress = false) {
  if ($refresh_db_from_web) {
    $num_jobs = 100;
    $metricon_owner_id = 8668;
    $pos = get_pos_from_jobfile($metricon_owner_id, $num_jobs, $echo_progress);
    upload_metricon_pos_to_db($pos);
    return $pos;
  }

  return load_metricon_pos_from_db($echo_progress);
}

function upload_metricon_pos_to_db($pos, $echo_progress = false) {
  require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
  $db = get_db();

  foreach ($pos as $p) {
    // if uninvoiced entry already exists, drop entry, then add entry to db
    $query = <<<SQL
REPLACE INTO raw_purchase_order (po_number, ext_job_number, total_cost,
                                 builder, builder_contact, address, squares)
VALUES (?, ?, ?, ?, ?, ?, ?)
SQL;
    $stmt = $db->prepare($query);
    $stmt->bind_param('sssssss',
      $p['po_number'],
      $p['ext_job_number'],
      $p['total_cost'],
      $p['builder'],
      $p['builder_contact'],
      $p['address'],
      $p['squares']
    );
    if ($echo_progress) {
      if ($stmt->execute())
        echo __DIR__ . " Line: " . __LINE__ . "\nRaw po to db: " .
          $p['po_number'] . "\n";
      else echo "\n" . $stmt->error;
    } else $stmt->execute();
  }
}

function load_metricon_pos_from_db($echo_progress = false) {
  require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
  require_once __DIR__ . "/../facade.php";
  $db = get_db();

  $query = <<<SQL
SELECT po_number, ext_job_number, total_cost, builder,
       builder_contact, address, squares
FROM raw_purchase_order
SQL;
  $stmt = $db->prepare($query);
  $stmt->execute();
  $stmt->bind_result($po_number, $ext_job_number,
    $total_cost, $builder, $builder_contact, $address, $squares);

  $pos = [];
  while ($stmt->fetch())
    $pos[] = create_purchase_order_entry($po_number, $total_cost,
      $ext_job_number, $builder, $builder_contact, $address, $squares);

  $stmt->close();

  if ($echo_progress)
    echo __DIR__ . " Line: " . __LINE__ . ":\nPOs from raw_purchase_order: " .
      json_encode($pos) . "\n";

  return $pos;
}
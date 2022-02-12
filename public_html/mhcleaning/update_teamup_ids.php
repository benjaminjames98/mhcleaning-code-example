<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

if (!isset($_REQUEST['json'])) {
  echo json_encode(['error' => 'No array of job_numbers detected.']);
  die();
}

$json = json_decode(urldecode($_REQUEST['json']), true);
$jns = $json['ids'];

foreach ($jns as $jn) {
  update_jn($jn['job_number'], $jn['teamup_id'], $jn['windows']);
}

echo json_encode(['success' => true, 'jns' => $jns]);
die ();

function update_jn($jn, $tid, $is_window) {
  require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
  $db = get_db();

  $teamup_id = $is_window ? "teamup_win_id" : "teamup_int_id";
  $column_value = $tid === 'NULL' ? 'null' : '?';

  $query = <<<MYSQL
INSERT INTO calendar_event (job_number, ${teamup_id})
VALUES (?, ${column_value})
ON DUPLICATE KEY UPDATE $teamup_id = ${column_value}
MYSQL;

  $stmt = $db->prepare($query);
  if ($tid === 'NULL')
    $stmt->bind_param('s', $jn);
  else
    $stmt->bind_param('sss', $jn, $tid, $tid);
  $stmt->execute();
  $stmt->close();
}
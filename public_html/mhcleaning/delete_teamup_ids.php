<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

if (!isset($_REQUEST['json'])) {
  echo json_encode(['error' => 'No array of job_numbers detected.']);
  die();
}

$json = json_decode(urldecode($_REQUEST['json']), true);
$tids = $json['ids'];

foreach ($tids as $tid) {
  delete_jn($tid['teamup_id'], true);
  delete_jn($tid['teamup_id'], false);
}

echo json_encode(['success' => true, 'jns' => $tids]);
die ();

function delete_jn($tid, $is_window) {
  require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
  $db = get_db();

  $teamup_col = $is_window ? "teamup_win_id" : "teamup_int_id";

  $query = <<<MYSQL
UPDATE calendar_event
SET ${teamup_col} = null
WHERE  ${teamup_col} = ?
MYSQL;

  $stmt = $db->prepare($query);
  $stmt->bind_param('s', $tid);
  $stmt->execute();
  $stmt->close();
}
<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

if (!isset($_REQUEST['json'])) {
  echo json_encode(['error' => 'No array of job_numbers detected.']);
  die();
}

$json = json_decode(urldecode($_REQUEST['json']), true);
$jns = $json['job_numbers'];

$ints = [];
$wins = [];
foreach ($jns as $jn) {
  $ids = get_id_from_jn($jn);
  $ints[$jn] = $ids['int'];
  $wins[$jn] = $ids['win'];
}

echo json_encode(['internals' => $ints, 'windows' => $wins]);
die ();

function get_id_from_jn($jn) {
  require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
  $db = get_db();

  // check if entry is already in db
  $query = <<<MYSQL
SELECT teamup_int_id, teamup_win_id
FROM calendar_event
where job_number = ?
MYSQL;
  $stmt = $db->prepare($query);
  $stmt->bind_param('s', $jn);
  $stmt->execute();
  $stmt->bind_result($int, $win);
  $stmt->fetch();
  $stmt->close();
  return ['int' => $int, 'win' => $win];
}
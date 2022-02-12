<?php

require_once __DIR__ . "/../../mhcleaning_imports/curl_utils.php";
require_once __DIR__ . "/../facade.php";

function load_calendar_events($builder_filter = '') {
  // Radacted

  $start_date = strtotime('-29 days');
  // Radacted
  $url =
    "https://api.teamup.com/${calendar_id}/events?modifiedSince=$start_date";
  $page = get_page($url, 'GET', '', '', $headers_array);
  $json = json_decode($page['content'], true);

  /*
  var_export($json['events'][0]);
  echo "<br><br>";
  die();*/

  $subcalendars = get_subcalendars($headers_array, $calendar_id);

  $events = [];
  foreach ($json['events'] as $e) {
    if (!isset($e['custom']['purchase_order_'])) continue;

    if ($e['custom']['invoice'][0] !== 'yes') continue;

    $builder = $e['custom']['builder'][0];
    if ($builder_filter !== '' && strcasecmp($builder, $builder_filter) !== 0)
      continue;

    $note = $e['notes'];
    $note = preg_replace('/<[^>]*>/', "", $note);

    $detail_date = substr($e['start_dt'], 0, 10);

    $subcalendar = $subcalendars[$e['subcalendar_ids'][0]];

    $events[] = create_calendar_entry(
      $e['custom']['job_'],
      $e['custom']['purchase_order_'],
      $builder,
      $e['who'],
      $e['custom']['job_type'][0],
      $e['custom']['building_type'][0],
      $subcalendar['job_type'],
      $subcalendar['who'],
      $detail_date,
      $note
    );
  }

  return $events;
}

function get_subcalendars($headers_array, $calendar_id) {
  $url = "https://api.teamup.com/${calendar_id}/subcalendars";
  $page = get_page($url, 'GET', '', '', $headers_array);
  $json = json_decode($page['content'], true);

  $subcalendars = [];
  foreach ($json['subcalendars'] as $s) {
    $name_parts = preg_split('/\s>\s/', $s['name']);
    if (count($name_parts) === 2) {
      $name_parts[0] = $name_parts[0] === 'Int' ? "Internal" : "Windows";
      $subcalendars [$s['id']] = [
        'job_type' => $name_parts[0],
        'who' => $name_parts[1]
      ];
    } else if (count($name_parts) === 1) {
      $subcalendars [$s['id']] = [
        'job_type' => $name_parts[0],
        'who' => '-'
      ];
    }
  }

  return $subcalendars;
}
<?php

require_once __DIR__ . "/../../mhcleaning_imports/curl_utils.php";
require_once __DIR__ . "/../facade.php";

function get_pos_from_jobfile($job_owner_id, $num_jobs,
                              $echo_progress = false) {

  function get_login_cookies($job_owner_id, $echo_progress = false) {
    // Radacted
  }

  function get_jobs($job_status, $num_jobs, $cookies, $echo_progress = false) {
    $job_status = $job_status === 'active' ? 'A' : 'M';

    $job_list_url = "https://app.jobfile.com.au/Job/AllJobs";
    $jobs_opt =
      "sEcho=2&iColumns=10&sColumns=%2C%2C%2C%2C%2C%2C%2C%2C%2C&iDisplayStart=0&iDisplayLength=${num_jobs}&mDataProp_0=JobId&sSearch_0=&bRegex_0=false&bSearchable_0=true&bSortable_0=false&mDataProp_1=JobNumber&sSearch_1=&bRegex_1=false&bSearchable_1=true&bSortable_1=true&mDataProp_2=LotAddress&sSearch_2=&bRegex_2=false&bSearchable_2=true&bSortable_2=true&mDataProp_3=JobOwnerName&sSearch_3=&bRegex_3=false&bSearchable_3=true&bSortable_3=true&mDataProp_4=ManagerName&sSearch_4=&bRegex_4=false&bSearchable_4=true&bSortable_4=true&mDataProp_5=JobStatus&sSearch_5=&bRegex_5=false&bSearchable_5=true&bSortable_5=true&mDataProp_6=TotalFutureTask&sSearch_6=&bRegex_6=false&bSearchable_6=true&bSortable_6=false&mDataProp_7=TotalRequestedTask&sSearch_7=&bRegex_7=false&bSearchable_7=true&bSortable_7=false&mDataProp_8=TotalBookedInTask&sSearch_8=&bRegex_8=false&bSearchable_8=true&bSortable_8=false&mDataProp_9=TotalCompletedTask&sSearch_9=&bRegex_9=false&bSearchable_9=true&bSortable_9=false&sSearch=&bRegex=false&iSortCol_0=0&sSortDir_0=asc&iSortingCols=1&searchText=&ViewName=_AllJobsBySupervisorPartial&StartDate=&EndDate=&LotSuburb=&JobOwnerId=&JobStatus=${job_status}&ManagerId=";

    $page = get_page($job_list_url, 'POST', $jobs_opt, $cookies, [], $echo_progress);
    $json = json_decode($page['content'], true);

    $jobs = [];
    foreach ($json['aaData'] as $j)
      $jobs[] = [
        'id' => $j['JobId'],
        'number' => $j['JobNumber'],
        'builder' => $j['JobOwnerName'],
        'supervisor' => $j['ManagerName'],
        'address' => $j['LotAddress']
      ];

    return $jobs;
  }

  function get_pos_from_job($job, $cookies, $echo_progress = false) {
    // get purchase orders modal, which contains links to individual purchase_orders
    $url =
      "https://app.jobfile.com.au/PurchaseOrder/AllPurchaseOrdersByContractAjax?jobId=${job['id']}&listType=4";
    $page = get_page($url, 'GET', '', $cookies, [], $echo_progress);
    $content = $page['content'];
    if (strpos($content, 'There are no Purchase Orders'))
      return [];

    // populate purchase_orders array, and return
    $pos = [];
    foreach (preg_split("/((\r?\n)|(\r\n?))/", $content) as $line) {
      $line = trim($line);
      if (strpos($line, '<a id') === false) continue;

      $line_parts = explode('"', $line);
      $num = substr($line_parts[1], 2);
      $id = preg_split("/[\s']+/", $line_parts[5])[3];

      $pos[] = [
        'id' => $id,
        'po_number' => $num,
        'job_number' => $job['number'],
        'builder' => $job['builder'],
        'builder_contact' => $job['supervisor'],
        'address' => $job['address']
      ];
    }

    usort($pos, "compare_strings");

    $squares_tally = 0;
    foreach ($pos as $i => $iValue) {
      $li_info = compile_li_info($iValue['id'], $cookies, $echo_progress);
      if ($li_info['squares'] > 0) $squares_tally = $li_info['squares'];

      $pos[$i]['total_cost'] = $li_info['total_cost'];
      $pos[$i]['squares'] = $squares_tally;
    }

    return $pos;
  }

  function compile_li_info($po_id, $cookies, $echo_progress = false) {
    // get the page, which is a modal
    $url =
      "https://app.jobfile.com.au/JobFile/PurchaseOrder/Details?id=${po_id}";
    $page = get_page($url, 'GET', '', $cookies, [], $echo_progress);

    // filter out line-item table into array of lines
    $content = $page['content'];
    $pos = stripos($content, "<tbody class=\"text-break-word\">");
    $content = substr($content, $pos); // crop to the line-item table
    $lines = preg_split("/<tr>|<td>|<\/td>/", $content);

    // populate line_items array and return
    $cost = 0;
    $squares = 0;
    for ($i = 1, $iMax = count($lines); $i < $iMax; $i += 19)
    { // line-items are 19 lines long
      $qty = trim($lines[$i + 5]);
      $uom = trim($lines[$i + 7]);
      $description = trim($lines[$i + 11]);
      if ($description === 'HOUSE CLEAN' && $uom === 'M2')
        $squares += (float)$qty;
      $price = $lines[$i + 15];
      $cost += price_to_float($price);
    }

    return ['total_cost' => $cost, 'squares' => $squares];
  }

  $cookies = get_login_cookies($job_owner_id, $echo_progress);
  $active_jobs = get_jobs('active', $num_jobs, $cookies, $echo_progress);
  $maintenance_jobs =
    get_jobs('maintenance', $num_jobs, $cookies, $echo_progress);
  $jobs = array_merge($active_jobs, $maintenance_jobs);

  $pos = [];
  foreach ($jobs as $job) {
    $job_pos = get_pos_from_job($job, $cookies, $echo_progress);
    if ($job_pos) $pos = array_merge($pos, $job_pos);
  }

  $standardised_pos = [];
  foreach ($pos as $po) {
    if ($po['total_cost'] === 0.0) continue;
    $standardised_pos[] = create_purchase_order_entry(
      $po['po_number'],
      $po['total_cost'],
      $po['job_number'],
      $po['builder'],
      $po['builder_contact'],
      $po['address'],
      $po['squares']
    );
  }

  if ($echo_progress)
    echo __DIR__ . " Line: " . __LINE__ . ":\nStandardised POs: " . json_encode($standardised_pos) .
      "\n";

  return $standardised_pos;
}

function price_to_float($s) {
  $s = str_replace(array('$', ','), '', $s);
  return (float)$s;
}


function compare_strings($a, $b) {
  return strcmp($a['po_number'], $b['po_number']);
}
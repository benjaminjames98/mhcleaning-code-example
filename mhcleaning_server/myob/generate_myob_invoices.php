<?php

// Note: cf = company file

require_once __DIR__ . "/../../mhcleaning_imports/curl_utils.php";
require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
require_once __DIR__ . "/myob_utils.php";


// takes an array of po_numbers, creates invoices, and then returns list of
// invoiced POs
function generate_myob_invoices($invoice_requests) {
  if (!refresh_myob_tokens()) return false;
  if (!$invoice_requests) return false;

  function get_db_info($job_number) {
    $db = get_db();
    $query = <<<SQL
SELECT builder,
       job_type,
       address,
       po_number,
       total_cost,
       builder_contact,
       detail_date,
       excel_row_num
FROM invoice
WHERE job_number = ?
SQL;
    $stmt = $db->prepare($query);
    $stmt->bind_param('s', $job_number);
    $stmt->execute();
    $stmt->bind_result($builder, $job_type, $address, $po_number,
      $total_cost, $builder_contact, $detail_date, $excel_row_num
    );
    $stmt->fetch();
    $stmt->close();
    $db->close();

    return [
      'job_number' => $job_number,
      'builder' => $builder,
      'job_type' => $job_type,
      'address' => $address,
      'po_number' => $po_number,
      'total_cost' => $total_cost,
      'builder_contact' => $builder_contact,
      'detail_date' => $detail_date,
      'excel_row_number' => $excel_row_num,
    ];
  }

  function get_post_handle($invoice_info, $cf_url, $account_uid, $tax_uid,
                           $headers, $db_info) {
    $po_number = $db_info['po_number'];
    $invoice_date = substr(date(DATE_ATOM), 0, 19);
    $detail_date = $db_info['detail_date'] . 'T00:00:00.000';
    if ($detail_date[0] === '0') $detail_date[0] = '2';
    $customer_uid = $invoice_info['customer_uid'];
    $job_type =
      ucwords(str_replace("_", " ", $db_info['job_type']));
    $description = $job_type . "\n"
      . $db_info['address'] . "\n"
      . (($po_number) ? "Purchase Order: " . $po_number : '');
    $total = $db_info['total_cost'];
    $contact = $db_info['builder_contact'];

    $post_opt = <<< JSON
{
    "CustomerPurchaseOrderNumber" : "$po_number",
    "Date" : "$invoice_date",
    "Customer" : {"UID" : "$customer_uid"},
    "Terms": {
      "PaymentIsDue": "InAGivenNumberOfDays",
      "BalanceDueDate": 14
    },
    "IsTaxInclusive" : false,
    "Lines" : [{
      "Date" : "$detail_date",
      "Description" : "$description",
      "Total" : $total,
      "Account" : {"UID" : "$account_uid"},
      "TaxCode" : {"UID" : "$tax_uid"}
    }],
    "Comment" : "Attn: $contact"
}
JSON;

    $post_url = $cf_url . '/Sale/Invoice/Professional';
    return get_curl_handle($post_url, 'POST', $post_opt, "", $headers);
  }

  $headers = get_myob_request_headers();

  error_log("Begin Invoicing Session");

  // create post handles
  $cf_url = get_myob_cf_url();
  $tax_uid = get_myob_tax_uid($cf_url, $headers);
  $account_uid = get_myob_account_uid($cf_url, $headers);

  $post_handles = [];
  foreach ($invoice_requests as $inv) {
    $jn = $inv['job_number'];
    $post_handles[$jn] = get_post_handle($inv, $cf_url, $account_uid,
      $tax_uid, $headers, get_db_info($jn));
  }

  // generate invoices
  function execute_post_handles($ch_array, $headers) {
    error_log('post requests cycle');
    $post_handles = execute_ch_array($ch_array, true);

    $failed_posts = [];
    $get_handles = [];
    foreach ($post_handles as $jn => $handle) {
      $post_page = process_curl_result($handle);

      $post_headers = explode("\n", $post_page['headers']);
      $get_url = '';
      foreach ($post_headers as $header) {
        if (strpos($header, 'Location') === 0) {
          $get_url = trim(substr($header, 10));
          break;
        }
      }

      if (!$get_url) $failed_posts[$jn] = $handle;
      else {
        error_log($jn . ': ' . $get_url);
        $get_handles[$jn] = get_curl_handle($get_url, 'GET', '', "", $headers);
      }
    }

    if ($failed_posts) {
      $posts = execute_post_handles($failed_posts, $headers);
      foreach ($posts as $jn => $handle) $get_handles[$jn] = $handle;
    }

    return $get_handles;
  }

  $get_handles = execute_post_handles($post_handles, $headers);

  // Get data on created invoices
  function execute_get_handles($ch_array) {
    error_log('get requests cycle');

    $failed_gets = [];
    $get_jsons = [];
    foreach (execute_ch_array($ch_array, false) as $jn => $handle) {
      $get_page = process_curl_result($handle);
      $json = json_decode($get_page['content'], true);

      if (!$json) $failed_gets[$jn] = $handle;
      else $get_jsons[$jn] = $json;
    }

    if ($failed_gets)
      foreach (execute_get_handles($failed_gets) as $jn => $json)
        $get_jsons[$jn] = $json;

    return $get_jsons;
  }

  $get_jsons = execute_get_handles($get_handles);

  // process results
  $invoiced_jobs = [];
  $failed_jobs = [];
  foreach ($get_jsons as $jn => $json) {
    if (isset($json['UID'])) $invoiced_jobs[] = [
      'job_number' => $jn,
      'uid' => $json['UID'],
      'date' => substr($json['Date'], 0, 10),
      'number' => $json['Number'],
      'excel_row_number' => get_db_info($jn)['excel_row_number']
    ];
    else {
      error_log($jn . ' - error: ' . var_export($json, true));
      $failed_jobs[] = ['job_number' => $jn, 'json' => $json];
    }
  }

  // sort Results
  function sort_alg($a, $b) {
    if ($a['job_number'] == $b['job_number']) return 0;
    return ($a['job_number'] < $b['job_number']) ? -1 : 1;
  }


  usort($invoiced_jobs, 'sort_alg');
  usort($failed_jobs, 'sort_alg');

  // record results
  $db = get_db();
  foreach ($invoiced_jobs as $j) {
    if (410000 < $j['number'] && $j['number'] < 419999) continue;
    $query = <<<SQL
UPDATE invoice
SET invoice_number=?, invoice_date=?, invoice_myob_uid=?
WHERE job_number=?
SQL;
    $stmt = $db->prepare($query);
    $stmt->bind_param('ssss', $j['number'], $j['date'],
      $j['uid'], $j['job_number']
    );
    $stmt->execute();
    $stmt->close();
  }
  $db->close();


  return ['invoiced' => $invoiced_jobs, 'failed' => $failed_jobs];
}

function ignore_myob_invoices($jobs) {
  session_start();
  if ($jobs === []) return [];
  $db = get_db();
  $invoice_date = substr(date(DATE_ATOM), 0, 19);
  $ignored_jobs = [];
  foreach ($jobs as $j) {
    $query = <<<SQL
UPDATE invoice
SET invoice_number='-', invoice_date='$invoice_date', invoice_myob_uid='-'
WHERE job_number=?
SQL;
    $stmt = $db->prepare($query);
    $stmt->bind_param('s', $j['job_number']);
    if ($stmt->execute()) {
      $ignored_jobs[] = ['job_number' => $j['job_number']];
      $_SESSION['gets_done']++;
      $_SESSION['posts_done']++;
    }
    $stmt->close();
  }
  session_write_close();
  return $ignored_jobs;
}
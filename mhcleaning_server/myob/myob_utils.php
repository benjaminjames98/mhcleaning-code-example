<?php

require_once __DIR__ . "/../../mhcleaning_imports/curl_utils.php";
require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
require_once __DIR__ . "/myob_credentials.php";

function get_myob_constant($attribute) {
  $info = get_myob_info();
  if (!isset($info['cf_uid'])) return false;
  $db = get_db();

  $query = <<<SQL
SELECT value 
FROM myob_constant
WHERE attribute = ? AND cf_uid = ?
SQL;
  $stmt = $db->prepare($query);
  $stmt->bind_param('ss', $attribute, $info['cf_uid']);
  $stmt->execute();
  if ($stmt->num_rows === 0) {
    $stmt->bind_result($value);
    $stmt->fetch();
    $stmt->close();
    return $value;
  }
  $stmt->close();
  return false;
}

function set_myob_constant($attribute, $value) {
  $info = get_myob_info();
  if (!isset($info['cf_uid'])) return false;

  $db = get_db();
  $query = <<<SQL
INSERT INTO myob_constant (attribute, value, cf_uid)
VALUES (?, ?, ?)
SQL;
  $stmt = $db->prepare($query);
  $stmt->bind_param('sss', $attribute, $value, $info['cf_uid']);
  $success = $stmt->execute();
  $stmt->close();
  return $success;
}

// called before any other call to refresh the myob session
function refresh_myob_tokens() {
  $info = get_myob_info();
  if (!isset($_SESSION['myob_refresh_token']))
    $post_opts = array(
      'client_id' => $info['api_key'],
      'client_secret' => $info['secret'],
      'scope' => 'CompanyFile',
      'code' => $_SESSION['myob_code'],
      'redirect_uri' => $info['redirect_url'],
      'grant_type' => 'authorization_code',
    );
  else
    $post_opts = array(
      'client_id' => $info['api_key'],
      'client_secret' => $info['secret'],
      'refresh_token' => $_SESSION['myob_refresh_token'],
      'grant_type' => 'refresh_token',
    );

  $post_opts = http_build_query($post_opts);
  $token_url = 'https://secure.myob.com/oauth2/v1/authorize';
  $page = get_page($token_url, 'POST', $post_opts);

  $token = json_decode($page['content'], true);

  if (isset($token['error'])) return false;
  $_SESSION['myob_access_token'] = $token['access_token'];
  $_SESSION['myob_refresh_token'] = $token['refresh_token'];
  return true;
}

// used to get list of cf headers at the beginning of the program
function get_myob_cf_list() {
  if (!refresh_myob_tokens()) return false;

  $headers = get_myob_request_headers();
  $url = 'https://api.myob.com/accountright';
  $page = get_page($url, 'GET', "", "", $headers);
  $cf_json_list = json_decode($page['content'], true);

  $cf_list = [];
  foreach ($cf_json_list as $cf)
    $cf_list[] = [
      'uid' => $cf['Id'],
      'name' => $cf['Name']
    ];

  return $cf_list;
}

function set_myob_cf($uid, $name, $username = "", $password = "") {
  $cf_token = ($username === "" && $password === "") ?
    "" : base64_encode("$username:$password");

  $info = get_myob_info();
  $headers = array(
    'Authorization: Bearer ' . $_SESSION['myob_access_token'],
    'x-myobapi-key: ' . $info['api_key'],
    'x-myobapi-version: ' . $info['api_version']
  );
  if ($cf_token !== "") $headers[] = 'x-myobapi-cftoken: ' . $cf_token;

  $url = "https://api.myob.com/accountright/$uid/CurrentUser";

  $page = get_page($url, 'GET', "", "", $headers);

  $content = json_decode($page['content'], true);

  if (isset($content['Message']) && $content['Message'] === 'Access denied') {
    error_log(__LINE__ . ": set myob cf: " . var_export($page, true));
    return false;
  }

  $_SESSION['cf_uid'] = $uid;
  $_SESSION['cf_name'] = $name;
  if ($cf_token !== "") $_SESSION['cf_token'] = $cf_token;

  return true;
}

function get_myob_request_headers() {
  $info = get_myob_info();

  $headers = array(
    'Authorization: Bearer ' . $_SESSION['myob_access_token'],
    'x-myobapi-key: ' . $info['api_key'],
    'x-myobapi-version: ' . $info['api_version']
  );

  if (isset($info['cf_token']))
    $headers[] = 'x-myobapi-cftoken: ' . $info['cf_token'];

  return $headers;
}

function get_myob_cf_url() {
  return "https://api.myob.com/accountright/" . $_SESSION['cf_uid'];
}

function get_myob_customers_from_myob($cf_url = "", $headers = [],
                                      $refresh_tokens = true) {

  if ($refresh_tokens && !refresh_myob_tokens()) return false;
  if ($headers === []) $headers = get_myob_request_headers();
  if ($cf_url === "") $cf_url = get_myob_cf_url();

  $url = $cf_url . '/Contact/Customer';
  $page = get_page($url, 'GET', "", "", $headers);
  $content = json_decode($page['content'], true);

  if (!isset($content['Items'])) {
    error_log("get myob customers from myob: " . var_export($page, true));
    return [];
  }

  $customers = [];
  foreach ($content['Items'] as $customer) {
    if (array_key_exists('CompanyName', $customer))
      $customers[] = [
        'company_name' => $customer['CompanyName'],
        'uid' => $customer['UID'],
        'display_id' => $customer['DisplayID']
      ];
  }

  return $customers;
}


function get_myob_tax_uid($cf_url, $headers) {
  $tax_uid = get_myob_constant('tax_uid');
  if (!$tax_uid) {
    $url = $cf_url . '/GeneralLedger/TaxCode';
    $page = get_page($url, 'GET', "", "", $headers);
    $content = json_decode($page['content'], true);
    $tax_uids = $content['Items'];

    $tax_code = get_myob_info()['tax_code'];
    foreach ($tax_uids as $tax) {
      if ($tax['Code'] === $tax_code)
        $tax_uid = $tax['UID'];
    }

    if ($tax_uid === null) {
      error_log("unable to find $tax_uid in cf accounts: url:$url");
      return false;
    }

    set_myob_constant('tax_uid', $tax_uid);
  }
  return $tax_uid;
}

function get_myob_account_uid($cf_url, $headers) {
  $account_uid = get_myob_constant('account_uid');
  if (!$account_uid) {
    $url = $cf_url . '/GeneralLedger/Account';
    $page = get_page($url, 'GET', "", "", $headers);
    $content = json_decode($page['content'], true);
    $account_uids = $content['Items'];

    $account_id = get_myob_info()['account_id'];
    foreach ($account_uids as $account) {
      if ($account['DisplayID'] === $account_id)
        $account_uid = $account['UID'];
    }

    if ($account_uid === null) {
      error_log("unable to find $account_id in cf accounts: url:$url");
      return false;
    }

    set_myob_constant('account_uid', $account_uid);
  }
  return $account_uid;
}
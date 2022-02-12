<?php

function get_page($url, $request_type, $post_opt, $cookie_opt = '',
                  $headers_array = array(), $echo_progress = false) {

  if ($echo_progress)
    echo "url: " . $url . "\n";
  /*
  echo "\n";
  echo "\n";
  echo "----------------------------";
  echo "CURL STUFF";
  var_export($url);
  echo "\n";
  var_export($request_type);
  echo "\n";
  var_export($post_opt);
  echo "\n";
  var_export($cookie_opt);
  echo "\n";
  echo "----------------------------";
  echo "\n";
  echo "\n";
*/

  $options = array(
    CURLOPT_RETURNTRANSFER => true,     // return web page
    CURLOPT_HEADER => true,     //return headers in addition to content
    CURLOPT_FOLLOWLOCATION => true,     // follow redirects
    CURLOPT_ENCODING => "",       // handle all encodings
    CURLOPT_AUTOREFERER => true,     // set referer on redirect
    CURLOPT_CONNECTTIMEOUT => 90000,      // timeout on connect
    CURLOPT_TIMEOUT => 90000, // 15 mins     // timeout on response
    CURLOPT_MAXREDIRS => 1000,       // stop after 10 redirects
    CURLINFO_HEADER_OUT => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_COOKIE => $cookie_opt
  );

  if ($request_type === 'POST')
    $options += array(
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => $post_opt
    );

  if ($headers_array)
    $options += array(
      CURLOPT_HTTPHEADER => $headers_array
    );

  $ch = curl_init($url);
  curl_setopt_array($ch, $options);
  $rough_content = curl_exec($ch);
  $err = curl_errno($ch);
  $errmsg = curl_error($ch);
  $header = curl_getinfo($ch);
  curl_close($ch);

  $header_content = substr($rough_content, 0, $header['header_size']);
  $body_content = trim(str_replace($header_content, '', $rough_content));
  $pattern = "#Set-Cookie:\\s+(?<cookie>[^=]+=[^;]+)#m";
  preg_match_all($pattern, $header_content, $matches);
  $cookies_out = implode("; ", $matches['cookie']);
  $cookies_out = !$cookie_opt ? $cookies_out : $cookie_opt . ";" . $cookies_out;

  $header['errno'] = $err;
  $header['errmsg'] = $errmsg;
  $header['headers'] = $header_content;
  $header['content'] = $body_content;
  $header['cookies'] = $cookies_out;
  return $header;
}

function get_curl_handle($url, $request_type, $post_opt, $cookie_opt = '',
                         $headers_array = array(), $echo_progress = false) {

  if ($echo_progress)
    echo "url: " . $url . "\n";
  /*
  echo "\n";
  echo "\n";
  echo "----------------------------";
  echo "CURL STUFF";
  var_export($url);
  echo "\n";
  var_export($request_type);
  echo "\n";
  var_export($post_opt);
  echo "\n";
  var_export($cookie_opt);
  echo "\n";
  echo "----------------------------";
  echo "\n";
  echo "\n";
*/

  $options = array(
    CURLOPT_RETURNTRANSFER => true,     // return web page
    CURLOPT_HEADER => true,     //return headers in addition to content
    CURLOPT_FOLLOWLOCATION => true,     // follow redirects
    CURLOPT_ENCODING => "",       // handle all encodings
    CURLOPT_AUTOREFERER => true,     // set referer on redirect
    CURLOPT_CONNECTTIMEOUT => 1200,      // timeout on connect
    CURLOPT_TIMEOUT => 1200,      // timeout on response
    CURLOPT_MAXREDIRS => 10,       // stop after 10 redirects
    CURLINFO_HEADER_OUT => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_COOKIE => $cookie_opt
  );

  if ($request_type === 'POST')
    $options += array(
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => $post_opt
    );

  if ($headers_array)
    $options += array(
      CURLOPT_HTTPHEADER => $headers_array
    );

  $ch = curl_init($url);
  curl_setopt_array($ch, $options);

  return $ch;
}

function process_curl_result($handle) {
  $rough_content = curl_multi_getcontent($handle);
  $err = curl_errno($handle);
  $errmsg = curl_error($handle);
  if ($err || $errmsg) error_log(`err: $err - errmsg: $errmsg`);
  $header = curl_getinfo($handle);

  $header_content = substr($rough_content, 0, $header['header_size']);
  $body_content = trim(str_replace($header_content, '', $rough_content));
  $pattern = "#Set-Cookie:\\s+(?<cookie>[^=]+=[^;]+)#m";
  preg_match_all($pattern, $header_content, $matches);
  $cookies_out = implode("; ", $matches['cookie']);

  $header['errno'] = $err;
  $header['errmsg'] = $errmsg;
  $header['headers'] = $header_content;
  $header['content'] = $body_content;
  $header['cookies'] = $cookies_out;
  return $header;
}

function get_url($handle) {
  $rough_content = curl_multi_getcontent($handle);
  $header = curl_getinfo($handle);
  $header_content = substr($rough_content, 0, $header['header_size']);

  foreach (explode("\n", $header_content) as $h) {
    if (strpos($h, 'Location') === 0) {
      return trim(substr($h, 10));
    }
  }

  return false;
}

function execute_ch_array($ch_array, $post_request) {
  $ch_keys = array_keys($ch_array);
  $mh = curl_multi_init();
  $max_concurrent = 5;
  $num_outstanding = 0;
  $num_completed = 0;

  error_log("opening Multi-Handle. Concurrent: $max_concurrent");

  //start processing the initial request queue
  $num_initial_requests = min($max_concurrent, count($ch_keys));
  for ($i = 0; $i < $num_initial_requests; $i++) {
    curl_multi_add_handle($mh, $ch_array[$ch_keys[$i]]);
    error_log('adding to mh: ' . $ch_keys[$i]);
    $num_outstanding++;
  }

  do {
    do {
      $mh_status = curl_multi_exec($mh, $active);
    } while ($mh_status == CURLM_CALL_MULTI_PERFORM);
    if ($mh_status != CURLM_OK) {
      break;
    }

    //a request is just completed, find out which one
    while ($completed = curl_multi_info_read($mh)) {
      $key = array_search($completed['handle'], $ch_array);
      if ($completed['result'] !== CURLE_OK)
        error_log(
          'error: ' . curl_error($completed['handle']) . ' - Content: ' .
          var_export(curl_getinfo($completed['handle']), true));
      else error_log('url returned: ' . $key);
      $ch = $completed['handle'];
      curl_multi_remove_handle($mh, $ch);
      $num_outstanding--;
      $num_completed++;

      session_start();
      if (!$post_request) $_SESSION['gets_done']++;
      else if (get_url($ch)) $_SESSION['posts_done']++;
      session_write_close();

      //try to add/start a new requests to the request queue
      while (
        $num_outstanding < $max_concurrent && //under the limit
        $i < count($ch_keys) &&
        isset($ch_array[$ch_keys[$i]]) // requests left
      ) {
        usleep(200000);
        curl_multi_add_handle($mh, $ch_array[$ch_keys[$i]]);
        error_log('adding to mh: ' . $ch_keys[$i]);
        $num_outstanding++;
        $i++;
      }
    }

    usleep(150); //save CPU cycles, prevent continuous checking
  } while ($active || $num_completed !== count($ch_keys)); //End do-while

  curl_multi_close($mh);
  return $ch_array;
}
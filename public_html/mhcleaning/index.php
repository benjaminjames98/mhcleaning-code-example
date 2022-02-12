<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in();

require_once __DIR__ . "/../../mhcleaning_imports/db_utils.php";
$db = get_db();

require_once __DIR__ . "/../../mhcleaning_server/myob/myob_utils.php";
if (isset($_REQUEST['code'])) {
  $_SESSION['myob_code'] = $_REQUEST['code'];
  refresh_myob_tokens();
  header("location: index.php");
} else if (!isset($_SESSION['myob_code'])) {
  $error = urlencode("Unable to get required permissions from MYOB");
  header("location: login.php?error=$error");
  die();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="w3.css">

  <title>M&H Cleaning</title>
  <?php $v = 9; ?>
  <script src="utils.js"></script>
  <script src="module_calendar_controller.js?v=<?= $v ?>"></script>
  <script src="module_page_initialiser.js?v=<?= $v ?>"></script>
  <script src="module_po_list.js?v=<?= $v ?>"></script>
  <script src="module_download_controller.js?v=<?= $v ?>"></script>
  <script src="module_upload_controller.js?v=<?= $v ?>"></script>
  <script src="module_invoice_controller.js?v=<?= $v ?>"></script>
  <script src="libraries/csv.js?v=<?= $v ?>"></script>
  <link rel="stylesheet" href="index_styles.css">
</head>
<body>

<!-- Primary Action Buttons -->
<div id='action_btns'>
  <div class="w3-bar">
    <button class="w3-button w3-deep-orange" id="refresh_button"
            onclick="pos_list.refresh_values()">
      Refresh
    </button>
    <input id="teamup_input" class="w3-hide" type="file" accept=".csv">
    <button id="teamup_button" class="w3-button w3-deep-orange">Teamup</button>
    <input id="upload_input" class="w3-hide" type="file" accept=".csv">
    <button id="upload_button" class="w3-button w3-deep-orange">Upload</button>
    <button class="w3-button w3-deep-orange" id="invoice_button"
            onclick="invoicer.update_invoice_list('invoice_list');
            show('invoice_modal', true);"
            disabled>
      Invoice
    </button>
    <button class="w3-button w3-deep-orange"
            onclick="show('download_modal', true)">
      Download
    </button>
    <button class="w3-button w3-red"
            onclick="location.href='logout.php';">
      Logout
    </button>
  </div>
</div>

<!-- PO Table -->
<table id="po_table"
       class="w3-table w3-bordered w3-striped w3-hoverable w3-responsive">
  <thead>
  <tr>
    <th>Sel<br>
      <input type="checkbox" class="w3-check" onchange="check_all(this)">
    </th>
    <th>Del<br>
      <button id="delete_all" class="w3-button" style="padding: 0 8px 2px 8px;">
        <img style="max-height: 22px" src="icons/icons8-delete-bin-48.png">
      </button>
    </th>
    <th onclick="pos_list.sort_by('job_number');">job #</th>
    <th onclick="pos_list.sort_by('po_number');">po #</th>
    <th onclick="pos_list.sort_by('total_cost');">total cost</th>
    <th onclick="pos_list.sort_by('builder');">builder</th>
    <th onclick="pos_list.sort_by('builder_contact');">builder contact</th>
    <th onclick="pos_list.sort_by('address');">address</th>
    <th onclick="pos_list.sort_by('job_type');">job type</th>
    <th onclick="pos_list.sort_by('detail_date');">detail date</th>
  </tr>
  </thead>
  <tbody id="pos_table_body"></tbody>
</table>

<!-- Download Modal -->
<div id="download_modal" class="w3-modal">
  <div class="w3-modal-content w3-animate-top w3-card-4">
    <header class="w3-container w3-green">
        <span
          onclick="show('download_modal', false)"
          class="w3-button w3-xlarge w3-display-topright">&times;</span>
      <h2>Download Invoices</h2>
    </header>
    <div class="w3-container">
      <form>
        <h2>Download:</h2>
        <p>
          <input type="radio" class="w3-radio" name="download_radios"
                 value="ready"
                 onchange="show('download_accordion', !this.checked);"
                 checked>
          <label>uninvoiced jobs</label>
        </p>
        <p>
          <input type="radio" class="w3-radio" name="download_radios"
                 value="invoiced"
                 onchange="show('download_accordion', !this.checked);"
                 checked>
          <label>invoiced jobs</label>
        </p>
        <p>
          <input type="radio" class="w3-radio" name="download_radios"
                 value="all"
                 onchange="show('download_accordion', !this.checked);">
          <label>all jobs (whole database)</label>
        </p>
        <p>
          <input type="radio" class="w3-radio" name="download_radios"
                 value="between"
                 onchange="show('download_accordion', this.checked);">
          <label>between two dates</label>
        </p>
        <div id="download_accordion" class="w3-hide w3-container">
          <p>
            <label for="download_from">from: </label>
            <input type="date" name="download_from">
          </p>
          <p>
            <label for="download_to">to: </label>
            <input type="date" name="download_to">
          </p>
          <p>
            <label for="date_reference">dates to use: </label>
            <select name="date_reference">
              <option value="detail" selected>detail Date</option>
              <option value="invoice">invoice Date</option>
            </select>
          </p>
        </div>
      </form>
      <div class="w3-right">
        <div class="w3-bar">
          <button class="w3-button w3-deep-orange"
                  onclick="downloader.download_pos();
                  show('download_modal', false)">
            Download
          </button>
          <button class="w3-button w3-red"
                  onclick="show('download_modal', false)">
            Cancel
          </button>
        </div>
      </div>
    </div>
    <footer class="w3-container w3-green">
      <p>Note: All invoices are downloaded in CSV format</p>
    </footer>
  </div>
</div>

<!-- Invoice Modal -->
<div id="invoice_modal" class="w3-modal">
  <div class="w3-modal-content w3-animate-top w3-card-4">
    <header class="w3-container w3-green">
        <span
          onclick="show('invoice_modal', false)"
          class="w3-button w3-xlarge w3-display-topright">&times;</span>
      <h2>Push Invoices to MYOB</h2>
    </header>
    <div class="w3-container">

      <div id="invoice_list"></div>

      <div class="w3-right">
        <div class="w3-bar">
          <button class="w3-button w3-deep-orange"
                  onclick="invoicer.send_invoices('invoice_list',
                  'invoice_info_modal','invoice_info_list',
                  'close_invoice_info');">
            Invoice
          </button>
          <button class="w3-button w3-red"
                  onclick="show('invoice_modal', false)">
            Cancel
          </button>
        </div>
      </div>
    </div>
    <footer class="w3-container w3-green">
      <p>Note: Only invoices shown will be invoiced</p>
    </footer>
  </div>
</div>

<!-- Invoice Information Modal -->
<div id="invoice_info_modal" class="w3-modal">
  <div class="w3-modal-content w3-animate-top w3-card-4">
    <header class="w3-container w3-green">
      <h2>Downloaded Invoices</h2>
    </header>
    <div class="w3-container">

      <div id="invoice_info_list"
           style="max-height: 70vh; overflow-y: scroll; margin-bottom: 16px">
      </div>

      <div class="w3-right">
        <div class="w3-bar">
          <button id="close_invoice_info" class="w3-button w3-red w3-right"
                  onclick="show('invoice_info_modal', false);">
            Close
          </button>
          <button id="download_invoices_button"
                  class="w3-button w3-orange w3-right w3-margin-right w3-hide">
            Download Invoices
          </button>
          <button id="download_jns_button"
                  class="w3-button w3-orange w3-right w3-margin-right w3-hide">
            Download CSV File
          </button>
        </div>
      </div>
    </div>
    <footer class="w3-container w3-green">
      <p><!-- notes? --></p>
    </footer>
  </div>
</div>

<!-- Company File Modal -->
<div id="cf_modal" class="w3-modal">
  <div class="w3-modal-content w3-animate-top w3-card-4">
    <header class="w3-container w3-green">
      <h2>Select Company File</h2>
    </header>
    <div class="w3-container">

      <div id="cf_chooser">
        <p>Fetching list of Company Files for you to choose from.</p>
        <p>This should only take a few seconds.</p>
      </div>

      <div class="w3-right">
        <div class="w3-bar">
          <button class="w3-button w3-deep-orange" disabled
                  onclick="initialiser.choose_cf(pos_list, invoicer);">
            Submit
          </button>
        </div>
      </div>
    </div>
    <footer class="w3-container w3-green">
      <p>Note: Only one company file may be used at a time. In order to use
        another one, please logout and then login again.</p>
    </footer>
  </div>
</div>

<!-- Calendar Modal -->
<div id="calendar_modal" class="w3-modal">
  <div class="w3-modal-content w3-animate-top w3-card-4">
    <header class="w3-container w3-green">
      <h2>Update Calendar</h2>
    </header>
    <div class="w3-container">

      <div id="calendar_div"></div>

      <div class="w3-right">
        <div class="w3-bar">
          <button id="close_calendar_modal" class="w3-button w3-red"
                  onclick="show('calendar_modal', false);">
            Close
          </button>
        </div>
      </div>
    </div>
    <footer class="w3-container w3-green">
      <p><!-- notes? --></p>
    </footer>
  </div>
</div>

<script>
  const calendar = get_calendar_controller('calendar_modal', 'calendar_div');
  const initialiser = get_page_initialiser('cf_modal', 'cf_chooser',
    'invoice_button', calendar);
  const pos_list = get_po_list('pos_table_body', 'delete_all');
  const downloader = get_download_controller('download_modal');
  const uploader = get_upload_controller(pos_list, 'upload_input',
    'upload_button', 'teamup_input', 'teamup_button', calendar);
  const invoicer = get_invoice_controller('invoice_modal', 'invoice_button',
    'download_invoices_button', 'download_jns_button', pos_list);

  initialiser.load_cfs();
</script>
</html>
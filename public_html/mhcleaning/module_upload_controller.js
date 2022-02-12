function get_upload_controller(pos_list, upload_input_id, upload_btn_id,
                               teamup_input_id, teamup_btn_id, calendar_controller) {
  let upload_input = el(upload_input_id);
  let upload_btn = el(upload_btn_id);
  let teamup_input = el(teamup_input_id);
  let teamup_btn = el(teamup_btn_id);

  let upload_csv = function (input, should_invoice, should_teamup) {
    // This will allow the user to upload a csv, and will push all the invoices
    // which are ready to invoice to the db
    let file = input.files[0];
    let fr = new FileReader();
    fr.onload = e => {
      let csv_rows = CSV.parse(e.target.result);

      const h_titles = ['PO No.', 'Job No.', 'Builder', 'Contact', 'Address',
        'Actual Task', 'Date', 'Price', 'Inv #', 'Invoice', "SQ's", "Notes", "Windows",
        "Internals", "Task", "Street Address", "Sgl / Dbl"];
      const h_indexes = h_titles.map(
        title => csv_rows[0].indexOf(title)
      );
      const num_header_rows = 2 + 1;

      csv_rows = csv_rows.slice(2).map(
        (row, index) => {
          try {
            return {
              'excel_row_number': (index + num_header_rows).toString(),
              'po_number': row[h_indexes[0]].toString(),
              'job_number': row[h_indexes[1]].toString().split(/\r?\n/)[0].toString(),
              'time': (row[h_indexes[1]].toString().split(/\r?\n/).length > 1 ?
                row[h_indexes[1]].toString().split(/\r?\n/)[1] : '').toString(),
              'builder': row[h_indexes[2]].toString(),
              'builder_contact': row[h_indexes[3]].toString(),
              'address': row[h_indexes[4]].toString(),
              'job_type': row[h_indexes[5]].toString(),
              'detail_date': row[h_indexes[6]].toString(),
              'total_cost': row[h_indexes[7]].toString(),
              'invoice_number': row[h_indexes[8]].toString(),
              'invoice': row[h_indexes[9]].toString(),
              'notes': row[h_indexes[11]].toString(),
              /*'squares': row[h_indexes[11]].toString().match(/\d{1,4}\.\d{1,3}m2/) ?
                         row[h_indexes[11]].toString().match(/\d{1,4}\.\d{1,3}m2/)[0] : "",*/
              'windows_who': row[h_indexes[12]].toString(),
              'internal_who': row[h_indexes[13]].toString(),
              'teamup_job_type': row[h_indexes[14]].toString(),
              'street_address': row[h_indexes[15]].toString(),
              'sgl_dbl': (row[h_indexes[16]].toLowerCase().includes("dbl") ?
                'dbl' : 'sgl').toString()
            };
          } catch (e) {
            alert('Error loading row' + row[h_indexes[0]]);
            return null;
          }
        });

      csv_rows = csv_rows.filter(row => is_valid_row(row));

      if (should_invoice) send_to_invoice(csv_rows);

      if (should_teamup) send_to_teamup(csv_rows);

    };

    fr.readAsText(file);
  };

  upload_btn.addEventListener('click', () => {
    if (upload_input) upload_input.click();
  }, false);

  upload_input.addEventListener("change", () => {
    upload_csv(upload_input, true, false);
  }, false);

  teamup_btn.addEventListener('click', () => {
    if (teamup_input) teamup_input.click();
  }, false);

  teamup_input.addEventListener("change", () => {
    upload_csv(teamup_input, false, true);
  }, false);

  // ---- API ----
  return {
    upload_csv: upload_csv
  };

  // ---- UTILITY FUNCTIONS ----

  function send_to_invoice(csv_rows) {
    let invoice_rows = csv_rows.filter(
      row => row['invoice_number'] === ""
        && row['job_number'] !== ""
        && (row['invoice'] === 'Y' || row['invoice'] === 'y')
        && checkDate(row['detail_date'], 'Date', row)
    );

    invoice_rows.forEach((row, i) =>
      invoice_rows[i]['detail_date'] = calendar.get_date_string(row['detail_date'])
    );


    if (invoice_rows.length === 0)
      alert("Looks like we weren't able to find any jobs to invoice.");
    else
      postJSON('update_pos.php', {pos: invoice_rows}, pos_list.refresh_values);
  }

  function send_to_teamup(csv_rows) {
    let event_rows = csv_rows.filter(
      row => {
        if (row['job_number'] === ""
          || row['detail_date'] === ""
          || row['address'] === "")
          return false;

        let detail_date = new Date(calendar_controller.get_date_string(row['detail_date']));
        let cutoff_date = new Date();
        cutoff_date.setMonth(cutoff_date.getMonth() - 2);
        let add_to_teamup = detail_date > cutoff_date;
        if (add_to_teamup) {
        }
        return add_to_teamup;
      }
    );

    let duplicates = get_duplicate_job_numbers(event_rows);
    if (duplicates) alert(`The following Job Numbers are duplicates:\n ${duplicates}`);

    calendar.show_modal(event_rows);
  }

  function get_duplicate_job_numbers(event_rows) {
    let duplicates = [];
    for (let row of event_rows) {
      let jn = row['job_number'];
      if (duplicates.includes(jn)) continue;
      let jn_occurrences = event_rows.filter(row => row['job_number'] === jn);
      if (jn_occurrences.length > 1)
        duplicates.push(`Job Number ${jn} at Row ${row['excel_row_number']}`);
    }
    return duplicates.join(', ');
  }

  function checkNumber(num, column, should_be_number, row) {
    // let is_number = !isNaN(parseInt(num));
    let is_number = !/[^-$,.\d]/.test(num);
    if (is_number === should_be_number) return true;
    else if (should_be_number)
      alert(`Expecting number in ${column} in the following row:
              Excel Row Number: ${row['excel_row_number']}
              Job Number: ${row['job_number']}
              PO No.: ${row['po_number']}
              ${column} Value: ${num}`);
    else if (!should_be_number)
      alert(`Unexpected number in ${column} in the following row:
              Excel Row Number: ${row['excel_row_number']}
              Job Number ${row['job_number']}
              PO No.: ${row['po_number']}
              ${column} Value: ${num}`);
    return false;
  }

  function checkDate(date, column, row) {
    // let is_number = !isNaN(parseInt(num));
    let is_date = /\d{1,2}\/\d{1,2}\/\d{2,4}/.test(date);
    if (is_date) return true;
    alert(`Expecting date in ${column} in the following row:
              Excel Row Number: ${row['excel_row_number']}
              Job Number: ${row['job_number']}
              PO No.: ${row['po_number']}
              ${column} Value: ${date}`);
    return false;
  }

  function checkTime(time, column, row) {
    // let is_number = !isNaN(parseInt(num));
    let is_time = /\d{0,2}([aA]|[pP])[mM]/.test(time);
    if (is_time) return true;
    alert(`Expecting time in ${column} in the following row:
              Excel Row Number: ${row['excel_row_number']}
              Job Number: ${row['job_number']}
              PO No.: ${row['po_number']}
              ${column} Value: ${time}`);
    return false;
  }

  function is_valid_row(row) {
    return row !== null
      && row['job_number'] !== ""
      && checkNumber(row['job_number'], 'Job No.', true, row)
      && (row['job_type'] === ""
        || checkNumber(row['job_type'], 'Job Type', false, row)
      ) && (
        row['detail_date'] === "" || checkDate(row['detail_date'], 'Date', row)
      )/* && (
            row['invoice_number'] === ""
            || checkNumber(row['invoice_number'], 'Inv #', true)
          )*/ && (
        row['builder'] === ""
        || checkNumber(row['builder'], 'Builder', false, row)
      ) && (
        row['builder_contact'] === ""
        || checkNumber(row['builder_contact'], 'Contact', false, row)
      ) && (
        row['windows_who'] === ""
        || row['windows_who'] === "-"
        || checkNumber(row['windows_who'], 'Windows', false, row)
      ) && (
        row['internal_who'] === ""
        || row['internal_who'] === "-"
        || checkNumber(row['internal_who'], 'Internal', false, row)
      ) && (
        row['address'] === ""
        || checkNumber(row['address'], 'Street Address', false, row)
      ) && (
        row['time'] === ""
        || checkTime(row['time'], 'Job No.', row)
      );
  }
}
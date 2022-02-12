function get_invoice_controller(invoice_modal_id, invoice_button_id,
                                download_invoices_btn_id,
                                download_jns_btn_id, pos_list) {
  let job_numbers = [];
  let customers = [];
  const invoice_button = el(invoice_button_id);
  const invoice_modal = el(invoice_modal_id);
  const download_invoices_btn = el(download_invoices_btn_id);
  const download_jns_btn = el(download_jns_btn_id);
  let invoice_content_div;

  let refresh_customers = function () {
    invoice_button.disabled = true;
    getJSON('get_customers.php', obj => {
      if (obj.length === 0) {
        alert('Unable to find any customers linked to that company file.'
          + ' Please choose another company file, or try again.');
        window.location.href = 'index.php';
      }
      customers = obj.sort(
        (c1, c2) => c1["company_name"].localeCompare(c2["company_name"])
      );
      invoice_button.disabled = false;
    });
  };

  let update_invoice_modal_list = function (invoice_list_id) {
    refresh_job_numbers_array();

    let options = get_options_from_customers(customers);
    let head_selector = `
      <p>
        <label>set all to</label>
        <select onchange="invoicer.update_selects(this.value, 'invoice_list');">
        ${options}</select>
      </p>`;

    let rows = generate_jn_rows_for_invoice_modal(options);

    el(invoice_list_id).innerHTML =
      rows !== '' ? head_selector + rows : '<p>Please select purchase orders '
        + 'to invoice using the checkboxes provided</p>';
  };

  let update_selects = function (selector_value, invoice_list_id) {
    let selectors = document.querySelectorAll(`#${invoice_list_id} > p > select`);
    selectors.forEach(i => i.value = selector_value);
  };

  let send_invoices = function (invoice_list_id, invoice_info_modal_id,
                                invoice_content_div_id, close_invoice_info_id) {

    invoice_content_div = el(invoice_content_div_id);
    let close_invoice_info_btn = el(close_invoice_info_id);

    let invoices = selected_for_invoicing(invoice_list_id);
    if (invoices.length === 0) {
      alert('Please select some purchase orders to be invoiced');
      return;
    }

    show(invoice_modal, false);
    show(download_invoices_btn, false);
    show(download_jns_btn, false);
    invoice_content_div.innerHTML = "<p>Sending those invoices through now</p>";
    close_invoice_info_btn.disabled = true;
    show(invoice_info_modal_id, true);

    let progress_interval = setInterval(update_invoice_progress(false), 3000);

    fetch('invoice.php', {
      'method': 'POST',
      'headers': {
        'Content-type': 'application/x-www-form-urlencoded'
      },
      'body': 'json=' + encodeURIComponent(JSON.stringify(invoices))
    }).then(r => r.json())
      .then(obj => {
        clearInterval(progress_interval);
        if (obj.error) {
          invoice_content_div.innerHTML = obj.error;
          return;
        }

        invoice_content_div.innerHTML = get_html_from_invoice_results(obj);

        if (obj["invoiced"].length > 0) {
          download_invoices_btn.disabled = false;
          download_invoices_btn.onclick =
            download_invoices_and_show_modal(obj["invoiced"],
              close_invoice_info_btn, invoice_info_modal_id);
          show(download_invoices_btn, true);

          download_jns_btn.disabled = false;
          download_jns_btn.onclick = download_jns_csv(obj["invoiced"]);
          show(download_jns_btn, true);
        }
      })
      .then(() => {
        close_invoice_info_btn.disabled = false;
        pos_list.refresh_values();
      });
  };

  // ---- API ----
  return {
    send_invoices: send_invoices,
    refresh_customers: refresh_customers,
    update_invoice_list: update_invoice_modal_list,
    update_selects: update_selects
  };

  // --- UTILITY FUNCTIONS ----

  function generate_jn_rows_for_invoice_modal(options) {
    return job_numbers.reduce((rows, jn) => {
        let customer = customers.find(c =>
          c["company_name"] === pos_list.get_builder(jn)
        );
        let row_options = `${options}`; // This forces js to copy by value
        if (customer !== undefined)
          row_options = row_options.replace(
            `'${customer["uid"]}'`,
            `'${customer["uid"]}' selected`
          );

        return rows + `
      <p>
        <label>${jn} (${pos_list.get_builder(jn)})</label>
        <select name="${jn}">${row_options}</select>
      </p>`;
      },
      ""
    );
  }

  function refresh_job_numbers_array() {
    let checked_boxes =
      document.querySelectorAll("input[name='po_check']:checked");

    job_numbers = [];
    checked_boxes.forEach(i => job_numbers.push(i.value));
  }

  function get_options_from_customers(customers) {
    return customers.reduce((a, c) =>
        `${a}
         <option value='${c["uid"]}'>
           ${c["company_name"]} (${c["display_id"]})
         </option>`,
      `<option value='already_invoiced'>Already Invoiced</option>`
    );
  }

  function compare_job_numbers(a, b) {
    return a["job_number"].toString().localeCompare(b["job_number"].toString());
  }

  function selected_for_invoicing(invoice_list_id) {
    let selectors = document.querySelectorAll(`#${invoice_list_id} > p > select`);

    let invoices = [];
    Array.from(selectors).forEach(i => {
      if (i.name) invoices.push({job_number: i.name, customer_uid: i.value});
    });
    invoices.sort(compare_job_numbers);
    return invoices;
  }

  function update_invoice_progress(downloading) {
    return function () {
      postJSON('get_invoice_progress.php', {}, (obj) => {
        if (downloading) {
          if (obj["gets_done"] === obj["currently_invoicing"]) {
            invoice_content_div.innerHTML = `<p>Downloading invoices complete</p>`;
          } else {
            invoice_content_div.innerHTML = `
          <p>Downloading invoices now</p>
          <p>downloaded: ${obj["gets_done"]} / ${obj["currently_invoicing"]}</p>
        `;
          }
        } else {
          invoice_content_div.innerHTML = `
          <p>Sending those invoices through now</p>
          <p>generated: ${obj["posts_done"]} / ${obj["currently_invoicing"]}</p>
          <p>confirmed: ${obj["gets_done"]} / ${obj["currently_invoicing"]}</p>
        `;
        }

      });
    };
  }

  function get_html_from_invoice_results(obj) {
    let html = '';
    if (obj["invoiced"].length > 0) {
      obj["invoiced"].sort(compare_job_numbers);
      html += obj["invoiced"].reduce((a, po) => a +
        `<p>${po["job_number"]} | ${po["number"]}  
            (<a href="download_invoice.php?job_number=${po["job_number"]}" 
            target="_blank">download</a>)
            </p>`,
        "<p>Success! The following invoices have been generated:</p>");
    }

    if (obj["failed"].length > 0)
      html += obj["failed"].reduce((a, po) => a
        + `${po["job_number"]}, `,
        "<p>The following invoices failed to be invoiced:</p><p>")
        + '</p>';

    if (obj["ignored"].length > 0)
      html += obj["ignored"].reduce((a, po) => a +
        `<p>${po["job_number"]}</p>`,
        "<p>Success! The following invoices have been set as 'already invoiced':</p>");

    return html;
  }

  function download_invoices_and_show_modal(invoiced, close_invoice_info_btn,
                                            invoice_info_modal_id) {
    return () => {
      download_invoices_in_zip(invoiced);

      download_invoices_btn.disabled = true;
      invoice_content_div.innerHTML = "<p>Downloading invoices now</p>";
      let progress_interval = setInterval(update_invoice_progress(true), 3000);
      close_invoice_info_btn.onclick = () => {
        clearInterval(progress_interval);
        show(invoice_info_modal_id, false);
        close_invoice_info_btn.disabled = true;
      };
    };
  }

  function download_invoices_in_zip(invoiced) {
    let f = document.createElement('form');
    f.method = 'POST';
    f.action = 'download_invoice_zip.php';
    invoiced.forEach(inv => f.innerHTML +=
      "<input type='hidden' name='job_numbers[]' value='"
      + encodeURIComponent(JSON.stringify(inv["job_number"]))
      + "'>"
    );
    document.body.appendChild(f);
    f.submit();
  }

  function download_jns_csv(invoiced) {
    // TODO invoiced now has the 'excel_row_number' property
    let highest_row_number = invoiced.reduce((p, c) => {
      return Math.max(c["excel_row_number"], p);
    }, 0);
    let csv_rows = Array.from({length: highest_row_number - 2}, () => {
      return {date: '', job_number: '', number: ''};
    });
    csv_rows.unshift({date: 'Date', job_number: 'Job No.', number: ''});
    csv_rows.unshift({date: 'Date', job_number: 'Job No.', number: ''});
    invoiced.forEach(inv => csv_rows[inv["excel_row_number"] - 1] = inv);

    return () => {
      download_jns_btn.disabled = true;
      let csvContent = "data:text/csv;charset=utf-8,"
        + csv_rows.map(
          i => [i["date"], i["job_number"], i["number"]].join(",")
        ).join("\n");

      let encodedUri = encodeURI(csvContent);
      let link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", "job_numbers.csv");
      document.body.appendChild(link); // Required for FF

      link.click();

      download_jns_btn.disabled = false;
    };
  }


}
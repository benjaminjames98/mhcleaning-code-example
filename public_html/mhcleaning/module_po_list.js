function get_po_list(table_body_id, delete_all_btn_id) {
  let pos = [];
  let last_sorted_attribute = "";
  let table_body = el(table_body_id);

  (() => {
    let btn = el(delete_all_btn_id);
    btn.addEventListener("click", () => {
        if (confirm('Clear all invoices from system?')) {
          btn.disabled = true;
          postJSON(
            'delete_po.php',
            {'job_number': '****'},
            () => {
              refresh_values();
              btn.disabled = false;
            });

        }
      }
    );
  })();

  let refresh_values = function () {
    getJSON('get_purchase_orders.php', obj => {
      pos = obj;
      last_sorted_attribute = "";
      update_table();
    });
  };

  let update_table = function () {
    let table_rows = "";
    pos.forEach(item =>
      table_rows += `
<tr>
  <td>
    <input type="checkbox" class="w3-check" name="po_check" value="${item.job_number}">
  </td>
  <td>
    <button id="delete_${item.job_number}" class="w3-button" style="padding: 0 8px 2px 8px;">
      <img style="max-height: 22px" src="icons/icons8-delete-bin-48.png">
    </button>
  </td>
  <td>${item.job_number}</td>
  <td>${item.po_number}</td>
  <td>${item.total_cost}</td>
  <td>${item.builder}</td>
  <td>${item.builder_contact}</td>
  <td>${item.address}</td>
  <td>${item.job_type}</td>
  <td>${item.detail_date}</td>
</tr>`
    );
    table_rows = table_rows || "<p>There are no uninvoiced purchase orders left</p>";
    table_body.innerHTML = table_rows;

    pos.forEach(item => {
      let btn = el(`delete_${item.job_number}`);

      btn.addEventListener("click", () => {
        btn.disabled = true;
        postJSON(
          'delete_po.php',
          {'job_number': item.job_number},
          refresh_values);
      });
    });
  };

  let sort_by = function (attribute) {
    if (attribute === last_sorted_attribute)
      pos.reverse();
    else {
      let numerical_attributes = ['total_cost'];
      let date_attributes = ['detail_date'];

      let sort_function = function (po1, po2) {
        let x = po1[attribute].toLowerCase(), y = po2[attribute].toLowerCase();
        return (x < y) ? -1 : (x > y) ? 1 : 0;
      };
      if (numerical_attributes.includes(attribute))
        sort_function = (po1, po2) => Number(po1[attribute]) - Number(po2[attribute]);
      else if (date_attributes.includes(attribute))
        sort_function = (po1, po2) => new Date(po1[attribute]) - new Date(po2[attribute]);

      pos.sort(sort_function);
    }
    last_sorted_attribute = attribute;
    update_table(table_body);
  };

  let get_builder = function (job_number) {
    let job = pos.find(po => po['job_number'] === job_number);
    return job['builder'];
  };

// ---- API ----
  return {
    refresh_values: refresh_values,
    sort_by: sort_by,
    get_builder: get_builder
  };
}

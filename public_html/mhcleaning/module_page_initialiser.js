function get_page_initialiser(cf_modal_id, cf_chooser_id, inv_btn_id) {
  const cf_modal = el(cf_modal_id);
  const cf_chooser = el(cf_chooser_id);
  const cf_button = cf_modal.getElementsByTagName('button')[0];
  const inv_button = el(inv_btn_id);

  const load_cfs = function () {
    show(cf_modal, true);

    getJSON('get_company_files.php', cfs => {
      if (cfs.length === 0) {
        alert('Unable to find any company files in this account. Logging out.');
        window.location.href = 'logout.php';
        return;
      }

      const options = cfs.reduce((a, c) =>
        a + `<option value='${c.uid}'>${c.name}</option>`,
        ""
      );

      cf_chooser.innerHTML = `
      <h4>Company File Authentication Info</h4>
      <p>
        <label>Company file to use: </label>
        <select>${options}</select>
      </p>
      <p>
        <label>Username</label>
        <input class="w3-input w3-border" style="max-width: 400px" type="text" name="username">
        <label>Password</label>
        <input class="w3-input w3-border" style="max-width: 400px" type="password" name="password">
      </p>`;

      cf_button.disabled = false;
      inv_button.disabled = false;

      const cf_inputs = cf_modal.querySelectorAll('Input');
      const listener = ev => {
        if (ev.key === 'Enter' && !cf_button.disabled)
          initialiser.choose_cf(pos_list, invoicer);
      };
      cf_inputs.forEach(e => e.addEventListener('keypress', listener));
    });
  };

  const choose_cf = function (pos_list, invoicer) {
    cf_button.disabled = true;

    const cf_select = cf_modal.querySelector(`select`);
    const cf_uid = cf_select.selectedOptions[0].value;
    const cf_name = cf_select.selectedOptions[0].text;
    const username = cf_modal.querySelector(`input[name=username]`).value;
    const password_input = cf_modal.querySelector(`input[name=password]`);
    const password = password_input.value;
    password_input.value = '';

    const obj = {
      cf_uid: cf_uid,
      cf_name: cf_name,
      username: username,
      password: password
    };

    let callback = function (response) {
      cf_button.disabled = false;

      if (!response.success) {
        alert('Incorrect Authentication Info. Please double-check, and try again.');
        return;
      }

      pos_list.refresh_values();
      invoicer.refresh_customers();
      show(cf_modal, false);

      // Initialise Keep Alive
      setInterval(() => {
        let callback = function (response) {
          if (!response.success) {
            alert('Your MYOB session has expired. Please log back into the system.');
            window.location.href = "logout.php";
          }
        };

        postJSON('keepalive.php', {}, callback);
      }, 60 * 1000);
    };

    postJSON('set_company_file.php', obj, callback);
  };

  // ---- API ----
  return {
    load_cfs: load_cfs,
    choose_cf: choose_cf
  };
}
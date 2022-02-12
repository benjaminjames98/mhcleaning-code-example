function get_download_controller(download_modal_id) {
  const download_modal = el(download_modal_id);
  const from_input = download_modal.querySelector('input[name=download_from');
  const to_input = download_modal.querySelector('input[name=download_to');

  (function refresh_dates() {
    to_input.value = new Date().toISOString().substring(0, 10);
    from_input.value = new Date(
      new Date().getFullYear(),
      new Date().getMonth() - 1,
      new Date().getDate()
    ).toISOString().substring(0, 10);
  })();

  let download_pos = function () {
    const filter_type = download_modal
      .querySelector("input[name='download_radios']:checked")
      .value;

    const date_reference = download_modal
      .querySelector("select[name=date_reference]")
      .value;

    let from = from_input.value;
    let to = to_input.value;

    let redirect = 'download_csv.php';
    if (filter_type === 'ready') redirect += "?q=ready";
    if (filter_type === 'invoiced') redirect += "?q=invoiced";
    if (filter_type === 'all') redirect += "?q=all";
    if (filter_type === 'between')
      redirect += `?q=between&from=${from}&to=${to}&reference=${date_reference}`;

    window.location.href = redirect;
  };

  // ---- API ----
  return {
    download_pos: download_pos
  };
}
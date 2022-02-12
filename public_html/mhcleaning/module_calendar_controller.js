function get_calendar_controller(calendar_modal_id, calendar_div_id) {
  const modal = el(calendar_modal_id);
  const div = el(calendar_div_id);
  const close_btn = modal.getElementsByTagName('button')[0];

  const api_key = "da0aa606b5bb4408563322e43e2049f9bab9f32ff1244db31a0a6f9d24593405";
  const cal_id = 'ksrc7rag2w427zreny'; // MHCleaning
  const url = `https://api.teamup.com/${cal_id}`;

  let show_modal = function (csv_rows) {
    // TODO list of things being sent through
    if (!confirm("Push events to teamup?")) return;
    div.innerHTML = "Shooting those through now";
    close_btn.disabled = true;
    show(modal, true);
    create_events(csv_rows);
  };

  let get_subcalendars = async function () {
    return fetch(`${url}/subcalendars`,
      {method: 'get', headers: {'Teamup-Token': api_key}}
    )
      .then(response => {
        if (!response.ok) throw Error(response.statusText);
        else return response.json();
      })
      .catch(er => alert('Error loading subcalendars: ' + er.statusText))
      .then(json => json['subcalendars']);
  };

  let get_teamup_ids = async function (job_numbers) {
    return fetch(`get_teamup_ids.php`,
      {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '&json=' + JSON.stringify({'job_numbers': job_numbers})
      })
      .then(response => {
        if (!response.ok) throw Error(response.statusText);
        else return response.json();
      })
      .catch(er => alert('Error loading teamup ids: ' + er.statusText));
  };


  let update_teamup_ids = async function (ids) {
    return fetch(`update_teamup_ids.php`,
      {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '&json=' + JSON.stringify({'ids': ids})
      })
      .then(response => {
        if (!response.ok) throw Error(response.statusText);
        else return response.json();
      })
      .catch(er => alert('Error teamup ids: ' + er.statusText));
  };


  let delete_teamup_ids = async function (ids) {
    return fetch(`delete_teamup_ids.php`,
      {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '&json=' + JSON.stringify({'ids': ids})
      })
      .then(response => {
        if (!response.ok) throw Error(response.statusText);
        else return response.json();
      })
      .catch(er => alert('Error teamup ids: ' + er.statusText));
  };

  let get_teamup_request = function (row, subcal_id, windows, put_request = false, delete_request = false) {
    let url_end = '/events';
    if (put_request || delete_request) {
      url_end += '/';
      url_end += windows ? row['teamup_win_id'] : row['teamup_int_id'];
    }

    let method = put_request ? 'PUT' :
      delete_request ? 'DELETE' :
        'POST';

    let body = {};
    if (!delete_request) {
      body = {
        'subcalendar_ids': [subcal_id],
        'start_dt': row['start_time_string'],
        'end_dt': row['end_time_string'],
        'all_day': row['all_day'],
        'title': row['address'],
        'who': row['builder_contact'],
        'location': row['street_address'],
        'notes': '<p><a href="https://www.whereis.com/" target="_blank" rel="noreferrer noopener external">https://www.whereis.com/ </a></p>'
          + '<p><a href="http://www.street-directory.com.au/" target="_blank" rel="noreferrer noopener external">http://www.street-directory.com.au/ </a></p>'
          + `<p>${row['notes']} </p>`,
        'custom': {
          // 'purchase_order_': row['po_number'].toString(),
          'builder1': row['builder'],
          'job_': row['job_number'],
          'job_type1': (row['teamup_job_type'] || '-'),
          'status': windows ? ['n_a'] : ['pending'],
          'building_type': row['sgl_dbl'] === 'dbl' ? ['double_story'] : ['single_story'],
          'window_status': windows ? ['pending'] : ['n_a']
          // 'invoice': ['no'],
          // 'squares': row['squares']
        }
      };
      if (put_request)
        body['id'] = windows ? row['teamup_win_id'] : row['teamup_int_id'];
    }

    return fetch(`${url}${url_end}`, {
      method: method,
      headers: {
        'Teamup-Token': api_key,
        'Content-Type': 'application/json'
      },
      body: (!delete_request ? JSON.stringify(body) : '')
    })
      ;
  };

  let get_date_string = function (date) {
    date.replaceAll('-', '/');
    // expecting dates in the form 'dd/mm/yy'
    let parts = date.split('/');
    let d = parts[0], m = parts[1], y = parts[2];
    d = d.length === 1 ? `0${d}` : d;
    m = m.length === 1 ? `0${m}` : m;
    y = y.length === 2 ? `20${y}` : y;

    return `${y}-${m}-${d}`;
  };

  let get_time_string = function (time, end) {
    if (time === '') return '00:00:00';
    let suffix = time.slice(-2).toLowerCase();
    let number = parseInt(time.slice(0, -2));

    if (isNaN(number) && suffix === 'am') number = 7;

    if (isNaN(number) && suffix === 'pm') number = 12;

    if (suffix === 'pm' && number !== 12)
      number += 12;
    if (end) number += 1;

    number = number.toString();
    if (number.length === 1)
      number = '0' + number;

    return number + ':00:00';
  };

  let get_subcalendar_id = function (subcalendars, name, job_type, win = false) {
    // This line is here in case one of the entries in 'job-type' is a number,
    // and not a string
    if (typeof job_type !== "string") job_type = job_type.toString();
    job_type = job_type.toLowerCase();
    if (name === "") return null;

    let calendar_name = win ? 'Win > ' + name : 'Int > ' + name;
    let cal = subcalendars.find(cal => calendar_name === cal.name);

    if (!cal) {
      if (win) calendar_name = 'AA Window Cleans';
      else
        calendar_name = job_type.includes('touch') ? 'A Touchups' :
          job_type.includes('re-') ? 'A Re-Cleans' :
            job_type.includes('win') ? 'AA Window Cleans' :
              'A Internal Clean';

      cal = subcalendars.find(cal => calendar_name === cal.name);
    }

    return {id: cal.id, name: cal.name};
  };

  let create_events = async function (event_rows) {
    let subcalendars = await get_subcalendars();
    let job_numbers = event_rows.map(row => row['job_number']);
    let teamup_ids = await get_teamup_ids(job_numbers);


    // prep rows for promising
    event_rows = event_rows.map(row => {
      // time
      let date = get_date_string(row['detail_date']);
      let start_time = get_time_string(row['time'], false);
      row['start_time_string'] = date + 'T' + start_time;
      let end_time = get_time_string(row['time'], true);
      row['end_time_string'] = date + 'T' + end_time;
      row['all_day'] = start_time === '00:00:00';

      row['teamup_int_id'] = teamup_ids['internals'][row['job_number']];
      if (row['internal_who']) {
        let int_cal = get_subcalendar_id(
          subcalendars, row['internal_who'], row['teamup_job_type'], false
        );
        if (int_cal) {
          row['internal_cal_id'] = int_cal.id;
          row['internal_cal_name'] = int_cal.name;
        }
      }
      row['teamup_win_id'] = teamup_ids['windows'][row['job_number']];
      if (row['windows_who']) {
        let win_cal = get_subcalendar_id(
          subcalendars, row['windows_who'], row['teamup_job_type'], true
        );
        if (win_cal) {
          row['windows_cal_id'] = win_cal.id;
          row['windows_cal_name'] = win_cal.name;
        }
      }
      return row;
    });

    /*
    console.log(subcalendars);
    console.log(event_rows);
    */

    let delete_jns = [];
    let promises = event_rows.reduce(
      (promises, row) => {
        if (row['internal_cal_id']) {
          if (row['teamup_int_id'])
            promises['creates'].push(get_teamup_request(row, row['internal_cal_id'], false, true));
          else
            promises['creates'].push(get_teamup_request(row, row['internal_cal_id'], false));
        } else if (row['teamup_int_id']) {
          promises['deletes'].push(get_teamup_request(row, "", false, false, true));
          delete_jns.push({
            job_number: row['job_number'],
            teamup_id: 'NULL',
            windows: false
          });
        }

        if (row['windows_cal_id']) {
          if (row['teamup_win_id'])
            promises['creates'].push(get_teamup_request(row, row['windows_cal_id'], true, true));
          else
            promises['creates'].push(get_teamup_request(row, row['windows_cal_id'], true));
        } else if (row['teamup_win_id']) {
          promises['deletes'].push(get_teamup_request(row, "", true, false, true));
          delete_jns.push({
            job_number: row['job_number'],
            teamup_id: 'NULL',
            windows: true
          });
        }

        return promises;
      },
      {creates: [], deletes: []}
    );

    if (promises['deletes'])
      Promise.allSettled(promises['deletes'])
        .then(results => {
          update_teamup_ids(delete_jns);
        });

    if (promises['creates'])
      Promise.allSettled(promises['creates'])
        .then(results => {
            let jsons = results.map(r => {
              if (r.value.status === 404) {
                delete_teamup_ids([{
                  teamup_id: r.value.url.slice(-9)
                }]);
                return null;
              } else
                return r.value.json();
            });
            jsons = jsons.filter(j => j !== null);
            return Promise.allSettled(jsons);
          }
        ).then(jsons => {
        let ids = [];
        jsons.forEach(json => {
          if (!json.value.error) ids.push({
            job_number: json.value['event']['custom']['job_'],
            teamup_id: json.value['event']['id'],
            windows: json.value['event']['custom']['window_status'][0] !== 'n_a'
          });
        });
        return update_teamup_ids(ids);
      }).then(ids => {
        console.log(ids);

        let html = "<p><b>The following have been successfully sent:</b></p>";
        html += "<table class='w3-table w3-bordered w3-striped w3-hoverable w3-responsive'" +
          "style='max-height: 60vh'>"
          + "<thead><tr>"
          + "<th>Date (& Time)</th>"
          + "<th>Address</th>"
          + "<th>Job #</th>"
          + "<th>Win/Int</th>"
          + "<th>Who</th>"
          + "<th>Builder</th>"
          + "</tr>"
          + "</thead>";

        html += ids['jns'].reduce((acc, jn) => {
            let event = event_rows.find(
              event => event['job_number'] === jn['job_number']
            );
            let win = jn['windows'];
            return acc +=
              "<tr><td>"
              + event['detail_date'] + (event['time'] ? ', ' + event['time'] : '')
              + "</td><td>"
              + event['address']
              + "</td><td>"
              + jn['job_number']
              + "</td><td>"
              + (win ? "window" : "internal")
              + "</td><td>"
              + (win ? event['windows_who'] : event['internal_who'])
              + "</td><td>"
              + event['builder']
              + "</td>"
              + "</tr>";
          }
          , "");
        html += "</table>";
        div.innerHTML = html;
        close_btn.disabled = false;
      });

  };

// ---- API ----
  return {
    show_modal: show_modal,
    get_date_string: get_date_string
  };
}
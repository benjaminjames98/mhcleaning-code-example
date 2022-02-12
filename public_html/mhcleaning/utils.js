function getJSON(url, fun) {
  let xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function () {
    if (this.readyState === 4 && this.status === 200) {
      let obj = JSON.parse(this.responseText);
      if (obj.error === "Your session has expired. Please login again.") {
        window.location.href = 'logout.php';
        return;
      }
      fun(obj);
    }
  };
  xhr.open('GET', url);
  xhr.send();
}

function postJSON(url, obj, fun) {
  let xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function () {
    if (this.readyState === 4 && this.status === 200) {
      let obj = JSON.parse(this.responseText);
      if (obj.error === "Your session has expired. Please login again.") {
        window.location.href = 'logout.php';
        return;
      }
      fun(obj);
    }
  };

  let par = 't=' + Math.random();
  par += '&json=' + encodeURIComponent(JSON.stringify(obj));
  xhr.open('POST', url, true);
  xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
  xhr.send(par);
}

function el(id) {
  return document.getElementById(id);
}

function show(e, show) {
  if (typeof e === 'string')
    e = el(e);
  if (show && e.className.indexOf("w3-show") === -1)
    e.className += " w3-show";
  else if (!show && e.className.indexOf("w3-show") !== -1)
    e.className = e.className.replace(" w3-show", "");
}

function check_all(source) {
  let checkboxes = document.getElementsByName('po_check');
  let checked = source.checked;
  checkboxes.forEach(e => e.checked = checked);
}
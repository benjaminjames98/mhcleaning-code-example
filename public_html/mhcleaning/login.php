<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
if (!isset($_REQUEST['error'])) {
  require_logged_out();
} else if (is_logged_in()) {
  logout();
}


if (isset($_REQUEST['password'])) {
  // if information is returned, attempt to log in
  $password = $_REQUEST['password'];
  if (!login($password))
    $error = "That doesn't seem to be the correct password";
} else {
  $_REQUEST['password'] = '';
  if (isset($_REQUEST['error'])) $error = $_REQUEST['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="w3.css">
  <title>Login</title>
</head>
<body>

<form id="form" method="post" class="w3-panel">
  <H2>Login</H2>
  Password:<br>
  <input class="w3-input w3-border" style="width: 400px" name="password"
         type="password" required>
  <br>
  <input class="w3-button w3-green" type="submit" value="Login">
  <?php if (isset($error)) echo "<p style='color: red'>$error</p><br>"; ?>
</form>


</body>
</html>
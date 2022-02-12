<?php

function get_db() {
  // TODO update for push
  // Radacted

  if (!$db) die(mysqli_connect_error());
  else return $db;
}

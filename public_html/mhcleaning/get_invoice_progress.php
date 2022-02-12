<?php

require_once __DIR__ . '/../../mhcleaning_imports/permissions.php';
require_logged_in(false);

if (!isset($_SESSION['currently_invoicing']))
  $_SESSION['currently_invoicing'] = 0;
if (!isset($_SESSION['posts_done'])) $_SESSION['posts_done'] = 0;
if (!isset($_SESSION['gets_done'])) $_SESSION['gets_done'] = 0;

echo json_encode([
  'currently_invoicing' => $_SESSION['currently_invoicing'],
  'posts_done' => $_SESSION['posts_done'],
  'gets_done' => $_SESSION['gets_done']
]);
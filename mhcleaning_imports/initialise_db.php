<?php

require_once 'db_utils.php';
$db = get_db();

$queries = [];

$queries[] = <<<SQL
drop table if exists myob_constant;
SQL;

$queries[] = <<<SQL
create table myob_constant (
    attribute varchar(24) null,
    value varchar(100) null,
    cf_uid varchar(100) null
    );
SQL;

foreach ($queries as $q)
  $db->query($q);

$db->close();
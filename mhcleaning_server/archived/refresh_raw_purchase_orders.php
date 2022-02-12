<?php

/**
 * This script is used to load raw_purchase_orders from urls. Attach it to a
 * cron-job
 */

require_once __DIR__ . "/job_file/load_metricon_jobs.php";

$metricon_jobs = load_metricon_jobs(true, true);
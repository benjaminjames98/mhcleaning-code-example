<?php

function create_purchase_order_entry($po_num, $total_cost, $job_number,
                                     $builder_name, $builder_contact, $address,
                                     $squares) {
  return [
    'po_number' => trim($po_num),
    'total_cost' => $total_cost,
    'ext_job_number' => trim($job_number),
    'builder' => trim($builder_name),
    'builder_contact' => trim($builder_contact),
    'address' => trim($address),
    'squares' => $squares
  ];
}

function create_calendar_entry($job_number, $po_number, $builder,
                               $builder_contact, $job_type, $building_type,
                               $contractor_type, $who, $detail_date, $note) {
  return [
    'int_job_number' => $job_number,
    'po_number' => $po_number,
    'builder' => $builder,
    'builder_contact' => $builder_contact,
    'job_type' => $job_type,
    'building_type' => $building_type,
    'contractor_type' => $contractor_type,
    'who' => $who,
    'detail_date' => $detail_date,
    'note' => $note,
  ];
}

function create_merged_entry($int_job_number, $ext_job_number, $po_number,
                             $total_cost, $builder_name, $builder_contact,
                             $address, $squares, $job_type,
                             $building_type, $contractor_type, $who,
                             $detail_date, $note) {
  return [
    'int_job_number' => $int_job_number,
    'ext_job_number' => $ext_job_number,
    'po_number' => $po_number,
    'total_cost' => $total_cost,
    'builder' => $builder_name,
    'builder_contact' => $builder_contact,
    'address' => $address,
    'squares' => $squares,
    'job_type' => $job_type,
    'building_type' => $building_type,
    'contractor_type' => $contractor_type,
    'who' => $who,
    'detail_date' => $detail_date,
    'note' => $note,
  ];
}

function merge_calendar_with_po($calendar_events, $po_events) {
  $merged_array = [];
  foreach ($po_events as $p)
    foreach ($calendar_events as $c)
      if ($c['po_number'] === $p['po_number']) // should only be one of these
        $merged_array[] = create_merged_entry(
          $c['int_job_number'],
          $p['ext_job_number'],
          $c['po_number'],
          $p['total_cost'],
          $p['builder'],
          $p['builder_contact'],
          $p['address'],
          $p['squares'],
          $c['job_type'],
          $c['building_type'],
          $c['contractor_type'],
          $c['who'],
          $c['detail_date'],
          $c['note']);

  return $merged_array;
}
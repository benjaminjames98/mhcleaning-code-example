create schema mhcleaning collate latin1_swedish_ci;

create table myob_constant
(
    attribute varchar(24) null,
    value varchar(100) null,
    cf_uid varchar(100) null
);

CREATE TABLE `invoice`
(
    `job_number` varchar(24) NOT NULL,
    `po_number` varchar(24),
    `builder` varchar(48) DEFAULT NULL,
    `builder_contact` varchar(48) DEFAULT NULL,
    `address` varchar(128) DEFAULT NULL,
    `job_type` varchar(128) DEFAULT NULL,
    `detail_date` date NOT NULL,
    `total_cost` decimal(10, 2) DEFAULT NULL,
    `invoice_number` varchar(24) DEFAULT NULL,
    `invoice_date` date DEFAULT NULL,
    `invoice_myob_uid` varchar(48) DEFAULT NULL,
    `excel_row_num` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`job_number`),
    UNIQUE KEY `temp_purchase_order_job_number_uindex` (`job_number`)
);

CREATE TABLE `calendar_event`
(
    `job_number` varchar(24) NOT NULL,
    `teamup_int_id` varchar(24) DEFAULT NULL,
    `teamup_win_id` varchar(24) DEFAULT NULL,
    PRIMARY KEY (`job_number`)
);
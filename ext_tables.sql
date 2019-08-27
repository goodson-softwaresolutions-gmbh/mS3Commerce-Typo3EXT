CREATE TABLE `fe_users` (
	`ms3commerce_user_rights` VARCHAR(80)
);

CREATE TABLE `fe_groups` (
	`ms3commerce_group_rights` VARCHAR(80)
);

CREATE TABLE `tt_products` (
	`AsimOid` CHAR(36) NOT NULL, 
	UNIQUE INDEX `asimoid` (`AsimOid`)
);

CREATE TABLE `tt_products_stage` (
	`Id` INT(11) NOT NULL,
	 `pid` INT(11) NOT NULL,
	 `AsimOid` char(36) NOT NULL,
	 `title` TINYTEXT NOT NULL
);

/*
-- Needed if OCI is enabled
CREATE TABLE `fe_users` (
	`mS3C_oci_allow` TINYINT DEFAULT 0
);*/

/*
-- Needed if MS3C_SHOP_USE_ORDER_BILLING_ADDRESS == true
-- Stores billing address with orders
CREATE TABLE `sys_products_orders` (
	`bill_name` varchar(80) DEFAULT '' NOT NULL,
	`bill_first_name` varchar(50) DEFAULT '' NOT NULL,
	`bill_last_name` varchar(50) DEFAULT '' NOT NULL
	`bill_gender` int(11) DEFAULT '0' NOT NULL,
	`bill_title` varchar(50) DEFAULT '' NOT NULL,
	`bill_company` varchar(80) DEFAULT '' NOT NULL,
	`bill_address` tinytext,
	`bill_zip` varchar(20) DEFAULT '' NOT NULL,
	`bill_city` varchar(50) DEFAULT '' NOT NULL,
	`bill_country` varchar(60) DEFAULT '' NOT NULL,
	`bill_static_info_country` CHAR(3) DEFAULT '',
	`bill_telephone` varchar(20) DEFAULT '' NOT NULL,
	`bill_email` varchar(80) DEFAULT '' NOT NULL,
	`bill_fax` varchar(20) DEFAULT '' NOT NULL,
	`bill_www` varchar(160) DEFAULT '' NOT NULL,
);
*/

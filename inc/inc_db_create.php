<?php

if (defined('_INC_DB_CREATE')) return;
define('_INC_DB_CREATE', '1');

include_lcm('inc_access');

function log_if_not_duplicate_table($errno) {
	if ($errno) {
		$error = lcm_sql_error();
		// XXX 1- If MySQL set by default in non-English, may not catch the error 
		//        (needs testing, and we can simply add the regexp in _T())
		// XXX 2- PostgreSQL may have different error format.
		if (! preg_match("/.*Table.*already exists.*/", $error)) {
			return lcm_sql_error() . "\n";
		}
	}

	return "";
}

// For details on the various fields, see:
// http://www.lcm.ngo-bg.org/article2.html

function create_database() {
	$log = "";

	//
	// Main objects
	//

	// - DONE lcm_case
	// - DONE lcm_followup
	// - DONE lcm_author
	// - DONE lcm_client 
	// - DONE lcm_org
	// - DONE lcm_client_org
	// + TODO lcm_courtfinal 
	// + TODO lcm_appelation 
	// + TODO lcm_keyword 
	// + TODO lcm_keyword_group 
	// + TODO lcm_client_keywords 
	// - DONE lcm_case_client_org
	// - DONE lcm_case_author

	lcm_log("creating the SQL tables", 'install');

	$query = "CREATE TABLE lcm_case (
		id_case bigint(21) NOT NULL auto_increment,
		title text NOT NULL,
		id_court_archive text NOT NULL,
		date_creation datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		date_assignment datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		legal_reason text NOT NULL,
		alledged_crime text NOT NULL,
		status text NOT NULL,
		public tinyint(1) DEFAULT '0' NOT NULL,
		PRIMARY KEY (id_case))";
	$result = lcm_query($query);
	
	$log .= log_if_not_duplicate_table(lcm_sql_errno());

	$query = "CREATE TABLE lcm_followup (
		id_followup bigint(21) NOT NULL auto_increment,
		id_case bigint(21) DEFAULT '0' NOT NULL,
		date_start datetime NOT NULL,
		date_end datetime NOT NULL,
		type ENUM('assignment', 'suspension', 'delay', 'conclusion', 'consultation', 'correspondance', 'travel', 'other') NOT NULL,
		description text NOT NULL,
		sumbilled decimal(19,4) NOT NULL,
		PRIMARY KEY (id_followup),
		KEY id_case (id_case))";
	$result = lcm_query($query);

	$log .= log_if_not_duplicate_table(lcm_sql_errno());

	// [ML] XXX too many extra fields
	$query = "CREATE TABLE lcm_author (
		id_author bigint(21) NOT NULL auto_increment,
		id_office bigint(21) DEFAULT 1 NOT NULL,
		name_first text NOT NULL,
		name_middle text NOT NULL,
		name_last text NOT NULL,
		date_creation datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		date_update datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		username VARCHAR(255) BINARY NOT NULL,
		password tinytext NOT NULL,
		lang VARCHAR(10) DEFAULT '' NOT NULL,
		prefs tinytext NOT NULL,
		status ENUM('admin', 'normal', 'external') DEFAULT 'normal' NOT NULL,

		low_sec tinytext NOT NULL,
		maj TIMESTAMP,
		pgp BLOB NOT NULL,
		htpass tinyblob NOT NULL,
		imessage VARCHAR(3) NOT NULL,
		messagerie VARCHAR(3) NOT NULL,
		alea_actuel tinytext NOT NULL,
		alea_futur tinytext NOT NULL,
		cookie_oubli tinytext NOT NULL,
		extra longblob NULL,

		PRIMARY KEY (id_author),
		KEY username (username),
		KEY status (status),
		KEY lang (lang))";
	$result = lcm_query($query);

	$log .= log_if_not_duplicate_table(lcm_sql_errno());

	$query = "CREATE TABLE lcm_client (
		id_client bigint(21) NOT NULL auto_increment,
		name_first text NOT NULL,
		name_middle text NOT NULL,
		name_last text NOT NULL,
		date_creation datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		date_update datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		citizen_number text NOT NULL,
		address text NOT NULL,
		civil_status decimal(2) DEFAULT 0 NOT NULL,
		income decimal(2) DEFAULT 0 NOT NULL,
		PRIMARY KEY id_client (id_client))";
	$result = lcm_query($query);

	$log .= log_if_not_duplicate_table(lcm_sql_errno());

	$query = "CREATE TABLE lcm_org (
		id_org bigint(21) NOT NULL auto_increment,
		name text NOT NULL,
		date_creation datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		date_update datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		address text NOT NULL,
		PRIMARY KEY id_org (id_org))";
	$result = lcm_query($query);

	$log .= log_if_not_duplicate_table(lcm_sql_errno());


	//
	// Relations
	//

	lcm_log("creating the tables used for relations between objects", 'install');

	$query = "CREATE TABLE lcm_case_client_org (
		id_case bigint(21) DEFAULT '0' NOT NULL,
		id_client bigint(21) DEFAULT '0' NOT NULL,
		id_org bigint(21) DEFAULT '0' NOT NULL,
		KEY id_case (id_case),
		KEY id_client (id_client),
		KEY id_org (id_org))";
	$result = lcm_query($query);

	$log .= log_if_not_duplicate_table(lcm_sql_errno());

	$query = "CREATE TABLE lcm_case_author (
		id_case bigint(21) DEFAULT '0' NOT NULL,
		id_author bigint(21) DEFAULT '0' NOT NULL,
		ac_read tinyint(1) DEFAULT '1' NOT NULL,
		ac_write tinyint(1) DEFAULT '0' NOT NULL,
		ac_edit tinyint(1) DEFAULT '0' NOT NULL,
		ac_admin tinyint(1) DEFAULT '0' NOT NULL,
		KEY id_case (id_case),
		KEY id_author (id_author))";
	$result = lcm_query($query);

	$log .= log_if_not_duplicate_table(lcm_sql_errno());

	$query = "CREATE TABLE lcm_client_org (
		id_client bigint(21) DEFAULT '0' NOT NULL,
		id_org bigint(21) DEFAULT '0' NOT NULL,
		KEY id_client (id_client),
		KEY id_org (id_org))";
	$result = lcm_query($query);

	$log .= log_if_not_duplicate_table(lcm_sql_errno());

	//
	// Management of the application
	//

	$query = "CREATE TABLE lcm_meta (
		name VARCHAR(255) NOT NULL,
		value VARCHAR(255) DEFAULT '',
		upd TIMESTAMP,
		PRIMARY KEY (name))";
	$result = lcm_query($query);

	$log .= log_if_not_duplicate_table(lcm_sql_errno());

	lcm_log("LCM database initialisation complete", 'install');

	return $log;
}

?>

#
# Table structure for table 'tx_cal_domain_model_event'
#
CREATE TABLE tx_cal_domain_model_event (
	allday tinyint(4) unsigned DEFAULT '0' NOT NULL,
	timezone varchar(5) DEFAULT 'UTC' NOT NULL,
	title varchar(128) DEFAULT '' NOT NULL,
	calendar_id int(11) unsigned DEFAULT '0' NOT NULL,
	category_id int(11) unsigned DEFAULT '0' NOT NULL,
	organizer varchar(128) DEFAULT '' NOT NULL,
	organizer_id int(11) unsigned DEFAULT '0' NOT NULL,
	organizer_pid int(11) DEFAULT '0' NOT NULL,
	organizer_link varchar(255) DEFAULT '' NOT NULL,
	location varchar(128) DEFAULT '' NOT NULL,
	location_id int(11) unsigned DEFAULT '0' NOT NULL,
	location_pid int(11) DEFAULT '0' NOT NULL,
	location_link varchar(255) DEFAULT '' NOT NULL,
	teaser text,
	description text,
	freq varchar(128) DEFAULT 'none',
	until int(11) DEFAULT '0' NOT NULL,
	cnt tinyint(4) unsigned DEFAULT '0',
	byday varchar(128) DEFAULT '',
	bymonthday varchar(128) DEFAULT '',
	bymonth varchar(128) DEFAULT '',
	intrval tinyint(4) unsigned DEFAULT '1',
	rdate text,
	rdate_type varchar(10) DEFAULT 'none' NOT NULL,
	deviation varchar(255) DEFAULT '',
	monitor_cnt int(11) unsigned DEFAULT '0',
	exception_cnt int(11) unsigned DEFAULT '0',
	fe_cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	fe_crgroup_id int(11) unsigned DEFAULT '0' NOT NULL,
	shared_user_cnt int(11) unsigned DEFAULT '0' NOT NULL,
	type tinyint(4) DEFAULT '0' NOT NULL,
	page int(11) DEFAULT '0' NOT NULL,
	ext_url VARCHAR(255) DEFAULT '' NOT NULL,
	isTemp tinyint(1) DEFAULT '0' NOT NULL,
	ics_uid text,
	caldav_data text,
	image VARCHAR(255) DEFAULT '' NOT NULL,
	attachment text,
	ref_event_id int(11) unsigned DEFAULT '0',
	send_invitation tinyint(1) DEFAULT '0' NOT NULL,
	attendee VARCHAR(255) DEFAULT '' NOT NULL,
	status varchar(12) DEFAULT '' NOT NULL,
	priority tinyint(1) DEFAULT '0' NOT NULL,
	completed tinyint(3) DEFAULT '0' NOT NULL
);

CREATE TABLE tx_cal_domain_model_calendar (
	title varchar(128) DEFAULT '' NOT NULL,
	owner int(11) unsigned DEFAULT '0' NOT NULL,
	timezone varchar(255) DEFAULT 'Europe/Berlin',
	activate_fnb tinyint(4) unsigned DEFAULT '0' NOT NULL,
	fnb_user_cnt int(11) unsigned DEFAULT '0' NOT NULL,
	type tinyint(4) DEFAULT '0' NOT NULL,
	ext_url text,
	ext_url_notes text,
	ics_file VARCHAR(255) DEFAULT '' NOT NULL,
	refresh int(11) unsigned DEFAULT '0' NOT NULL,
	md5 varchar(32) DEFAULT '' NOT NULL,
	headerstyle varchar(30) DEFAULT '' NOT NULL,
	bodystyle varchar(30) DEFAULT '' NOT NULL,
	schedulerId int(11) unsigned DEFAULT '0' NOT NULL

);

CREATE TABLE tx_cal_domain_model_fe_user_event_monitor_mm (
	uid_local int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
	tablenames varchar(64) DEFAULT '' NOT NULL,
	sorting int(11) unsigned DEFAULT '0' NOT NULL,
	offset int(11) DEFAULT '0' NOT NULL,
	schedulerId int(11) DEFAULT '0' NOT NULL
);

CREATE TABLE tx_cal_domain_model_unknown_users (
	email varchar(128) DEFAULT '' NOT NULL
);

CREATE TABLE tx_cal_domain_model_exception_event_group (
	title varchar(128) DEFAULT '' NOT NULL,
	exception_event_cnt int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_cal_domain_model_exception_event (
	start_date int(11) DEFAULT '0' NOT NULL,
	end_date int(11) DEFAULT '0' NOT NULL,
	relation_cnt int(11) unsigned DEFAULT '0' NOT NULL,
	title varchar(128) DEFAULT '' NOT NULL,
	freq varchar(128) DEFAULT '',
	until int(11) DEFAULT '0' NOT NULL,
	cnt tinyint(4) unsigned DEFAULT '0',
	byday varchar(128) DEFAULT '',
	bymonthday varchar(128) DEFAULT '',
	bymonth varchar(128) DEFAULT '',
	intrval tinyint(4) unsigned DEFAULT '1',
	rdate text,
	rdate_type varchar(10) DEFAULT 'none' NOT NULL,
	monitor_cnt int(11) unsigned DEFAULT '0'

);

CREATE TABLE tx_cal_domain_model_organizer (
	name varchar(128) DEFAULT '' NOT NULL,
	description text,
	street varchar(128) DEFAULT '' NOT NULL,
	zip varchar(16) DEFAULT '' NOT NULL,
	city varchar(128) DEFAULT '' NOT NULL,
	country_zone varchar(16) DEFAULT '' NOT NULL,
	country varchar(16) DEFAULT '' NOT NULL,
	phone varchar(24) DEFAULT '' NOT NULL,
	fax varchar(24) DEFAULT '' NOT NULL,
	email varchar(64) DEFAULT '' NOT NULL,
	image varchar(64) DEFAULT '' NOT NULL,
	imagecaption text,
	imagealttext text,
	imagetitletext text,
	link varchar(255) DEFAULT '' NOT NULL,
	fe_user_id int(11) unsigned DEFAULT '0' NOT NULL,
	shared_user_cnt int(11) unsigned DEFAULT '0' NOT NULL

);

CREATE TABLE tx_cal_domain_model_location (
	name varchar(128) DEFAULT '' NOT NULL,
	description text,
	street varchar(128) DEFAULT '' NOT NULL,
	zip varchar(16) DEFAULT '' NOT NULL,
	city varchar(128) DEFAULT '' NOT NULL,
	country_zone varchar(16) DEFAULT '' NOT NULL,
	country varchar(16) DEFAULT '' NOT NULL,
	phone varchar(24) DEFAULT '' NOT NULL,
	fax varchar(24) DEFAULT '' NOT NULL,
	email varchar(64) DEFAULT '' NOT NULL,
	image varchar(64) DEFAULT '' NOT NULL,
	imagecaption text,
	imagealttext text,
	imagetitletext text,
	link varchar(255) DEFAULT '' NOT NULL,
	shared_user_cnt int(11) unsigned DEFAULT '0' NOT NULL,
	latitude double default '0',
	longitude double default '0'

);

CREATE TABLE fe_users (
	tx_cal_calendar VARCHAR(255) DEFAULT '' NOT NULL
);

CREATE TABLE tx_cal_domain_model_attendee (
	event_id int(11) unsigned DEFAULT '0' NOT NULL,
	email varchar(128) DEFAULT '',
	fe_user_id int(11) unsigned DEFAULT '0' NOT NULL,
	fe_group_id int(11) unsigned DEFAULT '0' NOT NULL,
	attendance varchar(16) DEFAULT '' NOT NULL,
	status varchar(12) DEFAULT '' NOT NULL

);

CREATE TABLE tx_cal_domain_model_index (
	tablename varchar(30) DEFAULT '' NOT NULL,
	start_datetime bigint(14) NOT NULL DEFAULT '0',
	end_datetime bigint(14) NOT NULL DEFAULT '0',
	event_uid int(11) DEFAULT '-1' NOT NULL,
	event_deviation_uid int(11) DEFAULT '-1' NOT NULL,
	KEY start_datetime (start_datetime),
	KEY event_uid_start_datetime (event_uid, start_datetime)
);

#
# Table structure for table 'tx_cal_domain_model_eventdeviation'
#
CREATE TABLE tx_cal_domain_model_eventdeviation (
    parentid int(11) DEFAULT '0' NOT NULL,
	orig_start_date int(11) DEFAULT '0' NOT NULL,
	orig_start_time int(11) DEFAULT '0' NOT NULL,
	start_date int(11) DEFAULT '0' NOT NULL,
	end_date int(11) DEFAULT '0' NOT NULL,
	start_time int(11) unsigned DEFAULT '0' NOT NULL,
	end_time int(11) unsigned DEFAULT '0' NOT NULL,
	allday tinyint(4) unsigned DEFAULT '0' NOT NULL,
	timezone varchar(5) DEFAULT 'UTC' NOT NULL,
	title varchar(128) DEFAULT '' NOT NULL,
	organizer varchar(128) DEFAULT '' NOT NULL,
	organizer_id int(11) unsigned DEFAULT '0' NOT NULL,
	organizer_pid int(11) DEFAULT '0' NOT NULL,
	organizer_link varchar(255) DEFAULT '' NOT NULL,
	location varchar(128) DEFAULT '' NOT NULL,
	location_id int(11) unsigned DEFAULT '0' NOT NULL,
	location_pid int(11) DEFAULT '0' NOT NULL,
	location_link varchar(255) DEFAULT '' NOT NULL,
	teaser text,
	description text,
	isTemp tinyint(1) DEFAULT '0' NOT NULL,
	ics_uid text,
	image VARCHAR(255) DEFAULT '' NOT NULL,
	imagecaption text,
	imagealttext text,
	imagetitletext text,
	attachment text,
	attachmentcaption text,
	ref_event_id int(11) unsigned DEFAULT '0'

);

#
# Table structure for table 'sys_category': color of the events of a category and its priority
#
CREATE TABLE sys_category (
	tx_cal_color varchar(7) DEFAULT '' NOT NULL,
	tx_cal_color_priority int(11) DEFAULT '0' NOT NULL
);

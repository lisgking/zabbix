<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2011, 2012.  All Rights Reserved
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *       http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 **************************************************************************
 */

/**
 * Upgrade file for OpenAdmin Tool version 3.11
 */
$sql = array();

/*  create the IWA menu entry  */
$sql['iwa'] = "INSERT INTO oat_menu VALUES(NULL,1,'iwa','iwa','index.php?act=iwa','Warehouse','',0,'0','','','true');";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'Server Administration' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu WHERE menu_name = 'Server Administration' ) WHERE menu_name = 'iwa' ";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'iwa' ";

/* add new dashboard panels */
$sql['dashboard'] = "INSERT INTO panels VALUES (7,'toptables_modify','panel_title_toptables_modify','panel_desc_toptables_modify')";
$sql[] = "INSERT INTO panels VALUES (8,'sql_actions_total','panel_title_sql_actions_total','panel_desc_sql_actions_total')";
$sql[] = "INSERT INTO panels VALUES (9,'sql_actions','panel_title_sql_actions','panel_desc_sql_actions')";
$sql[] = "INSERT INTO panels VALUES (10,'num_sessions','panel_title_num_sessions','panel_desc_num_sessions')";
$sql[] = "INSERT INTO panels VALUES (11,'network_reads_writes','panel_title_network_reads_writes','panel_desc_network_reads_writes')";
$sql[] = "INSERT INTO panels VALUES (12,'disk_io','panel_title_disk_io','panel_desc_disk_io')";
$sql[] = "INSERT INTO panels VALUES (13,'os_memory','panel_title_os_memory','panel_desc_os_memory')";

/* upgrading to 3.11 removes existing dashboards and replaces them with the new defaults */
$sql[] = "DELETE FROM dashboards";
$sql[] = "DROP TABLE dashboards";
$sql[] = "CREATE TABLE dashboards(dashboard_id INTEGER PRIMARY KEY AUTOINCREMENT, dashboard_refresh INT, dashboard_name VARCHAR(100), dashboard_description TEXT)";
$sql[] = "INSERT INTO dashboards (dashboard_refresh, dashboard_name, dashboard_description) VALUES (30, 'dashboard_name_perf', 'dashboard_desc_perf')";
$sql[] = "INSERT INTO dashpanels SELECT dashboard_id, 5, 0 FROM dashboards WHERE dashboard_name = 'dashboard_name_perf'";
$sql[] = "INSERT INTO dashpanels SELECT dashboard_id, 7, 1 FROM dashboards WHERE dashboard_name = 'dashboard_name_perf'";
$sql[] = "INSERT INTO dashpanels SELECT dashboard_id, 6, 2 FROM dashboards WHERE dashboard_name = 'dashboard_name_perf'";
$sql[] = "INSERT INTO dashpanels SELECT dashboard_id, 9, 3 FROM dashboards WHERE dashboard_name = 'dashboard_name_perf'";
$sql[] = "INSERT INTO dashboards (dashboard_refresh, dashboard_name, dashboard_description) VALUES (30, 'dashboard_name_resources', 'dashboard_desc_resources')";
$sql[] = "INSERT INTO dashpanels SELECT dashboard_id, 1, 0 FROM dashboards WHERE dashboard_name = 'dashboard_name_resources'";
$sql[] = "INSERT INTO dashpanels SELECT dashboard_id, 4, 1 FROM dashboards WHERE dashboard_name = 'dashboard_name_resources'";
$sql[] = "INSERT INTO dashpanels SELECT dashboard_id, 3, 2 FROM dashboards WHERE dashboard_name = 'dashboard_name_resources'";

/* create the table to cache the server status info for the Dashboard > Group Summary tab */
$sql[] = <<<EOF
create table dashboard_server_status
(
conn_num integer,
group_num integer, 
last_updated int,
status varchar(10),
status_message varchar(128),
server_blocked boolean,
server_version varchar(128),
ha_type integer,
boottime varchar(128),
curtime varchar(128),
uptime varchar(128),
max_users integer,
session_count integer,
alert_count integer, 
error_count integer,
backup_status char(6),
backup_oldest_level0 varchar(128),
backup_max_interval_l0 interger,
cpu_used_percent float,
cpu_duration integer,
memory_status char(6),
memory_not_supported boolean,
memory_red_alarm_threshold float,
memory_yellow_alarm_threshold float,
os_mem_total integer,
os_mem_free integer,
os_mem_free_percent float,
space_status char(6),
space_red_alarm_threshold float,
space_yellow_alarm_threshold float,
spaces_alert_list varchar(128),
io_status char(6),
io_time_status char(6),
io_time_red_alarm_threshold float,
io_time_yellow_alarm_threshold float,
io_time_bad_chunks varchar(128),
io_percent_status char(6),
io_percent_red_alarm_threshold float,
io_percent_yellow_alarm_threshold float,
io_total_ops integer,
io_percent_bad_chunks varchar(128)
)
EOF;
$sql[] = "CREATE TRIGGER conn_del DELETE on connections BEGIN delete from dashboard_server_status where dashboard_server_status.conn_num = old.conn_num;END";

?>

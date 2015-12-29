<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2009.  All Rights Reserved
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
 * upgrade file for OpenAdmin Tool version 2.27
 */
$sql = array();

/* Add protcol  to connections table */
$sql['idsprotocol'] = "ALTER TABLE connections ADD COLUMN idsprotocol char(8);";

/* create the onstat Utility menu entry */
$sql['onstat utility'] = "INSERT INTO oat_menu VALUES(NULL,1,'onstatutil','onstat','index.php?act=onstatutil','onstat Utility','',0,'0','','');";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'onstatutil' ";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'Performance Analysis' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu WHERE menu_name = 'Performance Analysis' ) WHERE menu_name = 'onstatutil' ";
$sql[] = "UPDATE oat_menu_default SET parent = ( SELECT menu_id FROM oat_menu_default WHERE menu_name = 'Performance Analysis' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu_default WHERE menu_name = 'Performance Analysis' ) WHERE menu_name = 'onstatutil' ";

?>

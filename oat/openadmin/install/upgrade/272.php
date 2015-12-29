<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2010, 2011.  All Rights Reserved
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
 * upgrade file for OpenAdmin Tool version 2.72
 */
$sql = array();

/* create the Backup menu entry */
$sql['backup'] = "INSERT INTO oat_menu VALUES(NULL,1,'Backup','backup','index.php?act=backup','Backup','',0,'0','','');";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'Space Administration' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu WHERE menu_name = 'Space Administration' ) WHERE menu_name = 'Backup' ";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'Backup' ";

/* Add visible column to oat_menu table */
$sql['Menu'] = "ALTER TABLE oat_menu ADD COLUMN visible BOOLEAN default true";
?>

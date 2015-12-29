<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2010.  All Rights Reserved
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
 * upgrade file for OpenAdmin Tool version 2.70
 */
$sql = array();

/* Remove the Compression menu (this functionality is now under Storage) */
$sql[] = "DELETE from oat_menu WHERE menu_name='Compression'";
$sql[] = "DELETE from oat_menu_default WHERE menu_name='Compression'";

/* create the storage menu entry (and remove the Dbspaces and Chunks menu items)*/
$sql['Storage'] = "INSERT INTO oat_menu VALUES(NULL,1,'storage','storage','index.php?act=storage','Storage','',0,'0','','');";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'storage' ";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'Space Administration' ) , menu_pos = 1 WHERE menu_name = 'storage' ";
$sql[] = "UPDATE oat_menu_default SET parent = ( SELECT menu_id FROM oat_menu_default WHERE menu_name = 'Space Administration' ) , menu_pos = 1 WHERE menu_name = 'storage' ";
$sql[] = "DELETE FROM oat_menu WHERE menu_name = 'Dbspaces'";
$sql[] = "DELETE FROM oat_menu_default WHERE menu_name = 'Dbspaces'";
$sql[] = "DELETE FROM oat_menu WHERE menu_name = 'Chunks'";
$sql[] = "DELETE FROM oat_menu_default WHERE menu_name = 'Chunks'";

/* create the trusted context menu entry */
$sql['Trusted Context'] = "INSERT INTO oat_menu VALUES(NULL,1,'trustedcontext','trustedcontext','index.php?act=trustedContext','Trusted Context','',0,'0','','');";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'trustedcontext' ";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'Server Administration' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu WHERE menu_name = 'Server Administration' ) WHERE menu_name = 'trustedcontext' ";
$sql[] = "UPDATE oat_menu_default SET parent = ( SELECT menu_id FROM oat_menu_default WHERE menu_name = 'Server Administration' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu WHERE menu_name = 'Server Administration' ) WHERE menu_name = 'trustedcontext' ";

/* create the Replication menu entry */
$sql['Replication'] = "INSERT INTO oat_menu VALUES(NULL,6,'Replication','replication','','','',0,'','','');";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'Replication' ";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'Replication' ) , menu_pos = 1 WHERE menu_name = 'Mach11' ";
$sql[] = "UPDATE oat_menu_default SET parent = ( SELECT menu_id FROM oat_menu_default WHERE menu_name = 'Replication' ) , menu_pos = 1 WHERE menu_name = 'Mach11' ";

?>

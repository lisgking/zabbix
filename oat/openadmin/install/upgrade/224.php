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
 * upgrade file for OpenAdmin Tool version 2.24
 */
$sql = array();
/* create the Compression menu entry */
$sql['compression'] = "INSERT INTO oat_menu VALUES(NULL,1,'Compression','compression','index.php?act=compression','Compression','',0,'0','','');";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'Compression' ";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'Space Administration' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu WHERE menu_name = 'Space Administration' ) WHERE menu_name = 'Compression' ";
$sql[] = "UPDATE oat_menu_default SET parent = ( SELECT menu_id FROM oat_menu_default WHERE menu_name = 'Space Administration' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu_default WHERE menu_name = 'Space Administration' ) WHERE menu_name = 'Compression' ";

/* create the Query By Example menu entry */
$sql['qbe'] = "INSERT INTO oat_menu VALUES(NULL,1,'qbe','qbe','index.php?act=qbe','Query By Example','!\$this-&gt;idsadmin-&gt;isreadonly()',0,'0','','');";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'qbe' ";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'SQLToolBox' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu WHERE menu_name = 'SQLToolBox' ) WHERE menu_name = 'qbe' ";
$sql[] = "UPDATE oat_menu_default SET parent = ( SELECT menu_id FROM oat_menu_default WHERE menu_name = 'SQLToolBox' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu_default WHERE menu_name = 'SQLToolBox' ) WHERE menu_name = 'qbe' ";

?>

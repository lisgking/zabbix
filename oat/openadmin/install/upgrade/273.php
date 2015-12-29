<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2011.  All Rights Reserved
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
 * upgrade file for OpenAdmin Tool version 2.73
 */
$sql = array();

/* Add visible column to oat_menu_default table - this column was added to oat_menu in 272.php */
$sql['Menu'] = "ALTER TABLE oat_menu_default ADD COLUMN visible BOOLEAN default true";

/* Add Menu Manager to the menu */
$sql['Memory Manager'] = "INSERT INTO oat_menu VALUES(NULL,1,'MemoryMgr','MemoryMgr','index.php?act=memory','Memory Manager','',0,'0','','','true');";
$sql[] = "UPDATE oat_menu SET parent = ( SELECT menu_id FROM oat_menu WHERE menu_name = 'Server Administration' ) , menu_pos = ( SELECT max(menu_pos) FROM oat_menu WHERE menu_name = 'Server Administration' ) WHERE menu_name = 'MemoryMgr' ";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu WHERE menu_name = 'MemoryMgr' ";


?>

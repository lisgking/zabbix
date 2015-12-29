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
 * Upgrade file for OpenAdmin Tool version 2.75
 */
$sql = array();

/* Removing Google Maps menu item */
$sql[] = "DELETE FROM oat_menu WHERE menu_name = 'Map'";
$sql[] = "DELETE FROM oat_menu_default WHERE menu_name = 'Map'";

?>

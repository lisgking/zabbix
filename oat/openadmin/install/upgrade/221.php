<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2008, 2012.  All Rights Reserved
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
 * upgrade file for OpenAdmin Tool version 2.21
 */

$sql = array();

/* plugins table */
$sql['plugins'] = <<<EOF
CREATE TABLE plugins 
(
`plugin_id`             INTEGER PRIMARY KEY AUTOINCREMENT ,
`plugin_name`           VARCHAR DEFAULT ''  ,
`plugin_desc`           TEXT    DEFAULT ''  ,
`plugin_author`         VARCHAR DEFAULT ''  ,
`plugin_version`        VARCHAR DEFAULT ''  ,
`plugin_server_version` VARCHAR DEFAULT ''  ,
`plugin_enabled`        INTEGER NOT NULL DEFAULT '0' ,
`plugin_upgrade_url`    VARCHAR DEFAULT '' ,
`plugin_dir`            VARCHAR DEFAULT ''
);

EOF;

/* menu table */
$sql['oat_menu'] = <<<EOF
CREATE TABLE `oat_menu` 
(
`menu_id`      INTEGER PRIMARY KEY AUTOINCREMENT,
`menu_pos`     INTEGER DEFAULT ''  ,
`menu_name`    VARCHAR DEFAULT ''  ,
`lang`         VARCHAR DEFAULT ''  ,
`link`         VARCHAR DEFAULT ''  ,
`title`        VARCHAR DEFAULT ''  ,
`cond`         VARCHAR DEFAULT ''  ,
`parent`       INTEGER DEFAULT '0' ,
`plugin_id`    INTEGER NOT NULL  DEFAULT '0' ,
`expanded`     INTEGER NOT NULL  DEFAULT '0' ,
`linkid`       VARCHAR DEFAULT ''
);
EOF;

/* menu backup table */
$sql['oat_menu_default'] = <<<EOF
CREATE TABLE `oat_menu_default` 
(
`menu_id`      INTEGER PRIMARY KEY AUTOINCREMENT,
`menu_pos`     INTEGER DEFAULT ''  ,
`menu_name`    VARCHAR DEFAULT ''  ,
`lang`         VARCHAR DEFAULT ''  ,
`link`         VARCHAR DEFAULT ''  ,
`title`        VARCHAR DEFAULT ''  ,
`cond`         VARCHAR DEFAULT ''  ,
`parent`       INTEGER DEFAULT '0' ,
`plugin_id`    INTEGER NOT NULL  DEFAULT '0' ,
`expanded`     INTEGER NOT NULL  DEFAULT '0' ,
`linkid`       VARCHAR DEFAULT ''
);
EOF;

/*********
 * `menu_id`     auto generated
 * `menu_pos`    Integer position, can be used to control where within the menu this menu item gets inserted.
 * `menu_name`   internal identifier to refer to this menu
 * `lang`        key into lang_menu.xml
 * `link`        Relative URL for when the user clicks on this menu item.
 * `title`       Hover help for this menu item (only used when the UI language is in English).
 * `cond`        Condition when for this menu item should be visible (e.g. if the menu item should only show
 *               for non-read-only groups: cond="!$this->idsadmin->isreadonly()").  Set to NULL if the menu
 *               item should always appear.
 * `parent`      menu_id for its Parent menu item.  0 if this is a top-level menu item.
 * `plugin_id`   plugin_id associated with this menu item.  NULL for all OAT core functionality.  This field
 *               should only be set to a non-NULL value by the Plug-in Manager code.
 * `expanded`    Not used.  Instead browser cookies are used to determine which menu items are expanded.
 * `linkid`      Used to provide a consistent unique id to certain menu items, e.g. the helpLink
 * `visible` 	 True if the menu item should be displayed.  False if it should not be displayed since it was
 *               put in the trash from the Menu Manager page. Added in OAT version 2.73.
 *******/

/* example plugin */
$sql[] = "INSERT INTO plugins VALUES (1,'IBM Example','An example plugin','IBM','0.1','11.10.UC1',0,'','ibm/example/')";
/* menu data */
$sql[] = "INSERT INTO oat_menu VALUES(1,1,'Home','home','index.php','Home','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(2,2,'Health Center','healthcenter','','','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(3,2,'Alerts','alerts','index.php?act=health&amp;do=showAlerts','Show Alerts','',2,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(4,2,'Dashboard','dashboard','index.php?act=home&amp;do=dashboard','Dashboard','',2,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(5,3,'Logs','logs','','','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(6,3,'Admin Commands','admincommands','index.php?act=show&amp;do=showCommands','Show admin commands','',5,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(7,3,'Online Log','onlinelog','index.php?act=show&amp;do=showOnlineLogTail','Show Online Message Log','',5,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(8,3,'OnBar Act Log','baractlog','index.php?act=show&amp;do=showBarActLogTail','Show ON-Bar Activity Log','',5,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(9,4,'Details','taskscheduler','','','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(10,4,'Scheduler','scheduler','index.php?act=health&amp;do=sched','Scheduler','',9,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(11,4,'Task Details','taskdetails','index.php?act=health&amp;do=tasklist','Task Details','',9,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(12,4,'Task Runtimes','runtimes','index.php?act=health&amp;do=runtimes','Task Runtimes','',9,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(13,5,'Space Administration','space','','','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(14,5,'Dbspaces','dbspaces','index.php?act=space&amp;do=dbspaces','DBSpaces','',13,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(15,5,'Chunks','chunks','index.php?act=chunk&amp;do=show','Chunks','',13,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(16,5,'Recovery Logs','RecoveryLogs','index.php?act=rlogs&amp;do=llogs','Recovery Logs','',13,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(17,6,'Server Administration','serveradmin','','','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(18,6,'Mach11','mach11','index.php?act=ca','High Availability Clusters','',17,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(19,6,'onconfigparam','onconfigparam','index.php?act=onstat&amp;do=config','Configurations','',17,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(20,6,'System Validation','SystemValidation','index.php?act=systemvalidation&amp;do=show','System Validation','!\$this-&gt;idsadmin-&gt;isreadonly() &amp;&amp; (\$this-&gt;idsadmin-&gt;phpsession-&gt;serverInfo instanceof serverInfo &amp;&amp; \$this-&gt;idsadmin-&gt;phpsession-&gt;serverInfo-&gt;isPrimary())',17,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(21,6,'privileges','privileges','index.php?act=privileges&amp;do=database','Manage Privileges','',17,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(22,6,'Virtual Processors','VP','index.php?act=vps&amp;do=global','Virtual Processors','',17,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(23,6,'aus','aus','index.php?act=updstats','Auto Update Statistics','',17,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(24,7,'Performance Analysis','performance','','','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(25,7,'SQL Explorer','sqlexplorer','index.php?act=sqltrace&amp;do=ByType','SQL Explorer','',24,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(26,7,'PerformanceHist','PerformanceHist','index.php?act=performance&amp;do=profilehistory','Performance History','',24,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(27,7,'System Reports','Reports','index.php?act=onstat&amp;do=reports','System Reports','',24,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(28,7,'Session Explorer','sessionexplorer','index.php?act=home&amp;do=sessexplorer','Session Explorer','',24,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(29,8,'SQLToolBox','sqltoolbox','','','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(30,8,'Databases','databases','index.php?act=sqlwin&amp;do=dbtab','Databases','',29,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(31,8,'Schema Browser','schemabrowser','index.php?act=sqlwin&amp;do=schematab','Schema Browser','',29,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(32,8,'SQL Editor','sql','index.php?act=sqlwin&amp;do=sqltab','SQL Editor','!\$this-&gt;idsadmin-&gt;isreadonly()',29,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(34,10,'Help','help','javascript:pop(''{\$this-&gt;idsadmin-&gt;in[''act'']}'',''{\$this-&gt;idsadmin-&gt;in[''do'']}'');','Help','',0,'','','helpLink');";
$sql[] = "INSERT INTO oat_menu VALUES(35,10,'Useful Links','UsefulLinks','index.php?act=info&amp;do=ids','Useful Links','',34,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(36,10,'How Do I','HowDoI','javascript:showDocuments(''HOWTO.html'',''HowDoI''); ','OAT Help','',34,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(37,10,'ReleaseNotes','releasenotes','javascript:showDocuments(''RELEASENOTES.html'',''Release Notes''); ','Release Notes','',34,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(38,10,'Readme','readme','javascript:showDocuments(''README.html'',''readme''); ','Readme','',34,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(39,10,'About','about','index.php?act=info&amp;do=about','About OpenAdmin Tool','',34,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(40,11,'Admin','admin','admin/index.php','Admin','!\$this-&gt;idsadmin-&gt;isreadonly()',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(41,12,'Logout','logout','index.php?act=login&amp;do=logout','Logout','',0,'','','');";
$sql[] = "INSERT INTO oat_menu VALUES(42,1,'IBM Example','ibmexample','index.php?act=/ibm/example/example','Example','',0,'1','','');";
$sql[] = "INSERT INTO oat_menu_default SELECT * FROM oat_menu";
?>

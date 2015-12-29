<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007.  All Rights Reserved
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
 * The RSS class is used to create customizable
 * Database monitoring feeds.
 *
 */
class rss {

    public $idsadmin;

    /**
     * The constructor for the class,
     * Make sure the current language is set
     * and a default title for the page.
     *
     * @return rss
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_lang("rss");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("title"));
        $this->idsadmin->load_template("template_rss");
    }


    # the run function
    # this is what index.php will call
    # the decision of what to actually do is based on
    # the value of 'act' which is either posted or getted

    function run()
    {
        $this->idsadmin->setCurrMenuItem("rss");
        switch($this->idsadmin->in['do'])
        {
            case 'Chunks';
            $this->chunks();
            break;
            case 'Databases';
            $this->databases();
            break;
            case 'Dbspaces';
            $this->dbspaces();
            break;
            case 'LogicalLogs';
            $this->llogs();
            break;
            case 'Onconfig';
            $this->onconfig();
            break;
            case 'OnlineLog';
            $this->online_log();
            break;
            case 'PhysicalLog';
            $this->plog();
            break;
            case 'Sessions';
            $this->sessions();
            break;
            case 'SQL';
            $this->sql_stmts();
            break;
            case 'Environment';
            $this->sys_env();
            break;
            case 'VPs';
            $this->vps();
            break;
            default:
                $this->def();
                break;
        }
    } # end function run

    /**
     * This function create an RSS feed about the
     * database chunks
     *
     */
    function chunks()
    {

        $link = "chunk&amp;do=show";
        $this->print_header("Chunk Info", $link, "IDS Chunk Info", 30);
        $db = $this->idsadmin->get_database("sysmaster");
        $stmt = $db->prepare("select chknum, dbsnum, pagesize/1024 pgsz, " .
        "format_units(chksize*pagesize,'b') CH_SIZE, " .
        "format_units(nfree*pagesize,'b') FREE_SIZE, " .
        "trunc(100 - ((nfree*100) / chksize),2) PCT_FREE, " .
        "is_offline, is_recovering, is_blobchunk, is_sbchunk, " .
        "is_inconsistent from syschunks");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $rss_item_line = "{$row['CHKNUM']}, dbs: {$row['DBSNUM']}, " .
                "{$row['CH_SIZE']} ({$row[PCT_FREE]}% full)";
                if ($row['IS_OFFLINE'] > 0) $rss_item_line .= " Offline";
                if ($row['IS_RECOVERING'] > 0) $rss_item_line .= " Recovering";
                if ($row['IS_BLOBCHUNK'] > 0) $rss_item_line .= " Blob";
                if ($row['IS_SBCHUNK'] > 0) $rss_item_line .= " Smart Blob";
                if ($row['IS_INCONSISTENT']>0) $rss_item_line .= " Inconsistent";

                # To do: update $link with chunk detail URL when implemented
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end chunks

    /**
     * Create an RSS feed about the databases on this server
     *
     */
    function databases()
    {
        $db = $this->idsadmin->get_database("sysmaster");

        $link = "sqlwin&amp;do=dbtab";
        $this->print_header("Database Info", $link, "IDS Databases", 30);

        $stmt = $db->prepare("select trim(name) nam, hex(partnum) part, " .
        "trim(owner) ownr, is_logging, is_buff_log, is_ansi, is_nls " .
        "from sysdatabases order by name");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $rss_item_line = $row['NAM'] . " (" . $row['OWNR'] .  ")";
                if ($row['IS_LOGGING'] > 0) $rss_item_line .= " LOGGED";
                else if ($row['IS_BUFF_LOG'] > 0) $rss_item_line .= " BUFFERED";
                else if ($row['IS_ANSI'] > 0) $rss_item_line .= " ANSI";
                else $rss_item_line .= " NOT LOGGED";
                if ($row['IS_NLS'] > 0) $rss_item_line .= " GLS";
                $rss_item_line .= " " . $row['PART'];
                $link = "sqlwin&amp;do=connect&amp;val=" . $row['NAM'];
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end databases

    /**
     * Create an RSS feed about the list of dbspaces which
     * exist on the server.  It will show how much each dbspaces
     * is used or not.
     *
     */
    function dbspaces()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "space&amp;do=dbspaces";

        $this->print_header("Dbspace Info", $link, "IDS Dbspace Status", 20);

        $stmt =
        $db->prepare("select A.dbsnum, trim(name) name, " .
        "A.pagesize/1024 as pgsize, B.nchunks, " .
        " format_units(sum(chksize*A.pagesize),'b') DBS_SIZE, " .
        " format_units(sum(nfree*A.pagesize),'b') FREE_SIZE, " .
        "TRUNC(100 - (sum(nfree)*100) / (sum(chksize)),2) PCT_FREE, " .
        " B.flags " .
        "FROM syschktab A, sysdbstab B " .
        "WHERE A.dbsnum = B.dbsnum " .
        "GROUP BY A.dbsnum , name, B.nchunks, 3, B.flags " .
        "ORDER BY A.dbsnum");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $dbsnum = $row['DBSNUM'];
                $dbsname = $row['NAME'];
                $pct_usage = $row['PCT_FREE'];
                $dbs_size = $row['DBS_SIZE'];
                $pg_size = $row['PGSIZE'];

                $link = "space&amp;do=dbsdetails&amp;dbsnum=" . $dbsnum .
                "&amp;dbsname=" . $dbsname;
                $rss_item_line = "{$dbsnum}, {$dbsname}, {$dbs_size} " .
                "({$pct_usage}% full)";
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end dbspaces

    /**
     * Create an RSS feed about status of the logical logs
     * and which one are used, full, empty, backed up.
     *
     */
    function llogs()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "rlogs&amp;do=llogs";

        $this->print_header("Logical Logs", $link,
        "Logical Log Information", 30);

        $stmt = $db->prepare("select number, uniqid, size, used, is_current," .
        " is_backed_up, is_archived from syslogs");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $rss_item_line = $row['NUMBER'] . " Id: " .
                $row['UNIQID'] . " Size: " .  $row['SIZE'] . " Used: " .
                $row['USED'] . " (" .  (100 * $row['USED']/$row['SIZE']) .
                "%)";
                if ($row['IS_CURRENT'] > 0) $rss_item_line .= " Current";
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end onconfig

    /**
     * Create an RSS feed showing the database server
     * configuration file.
     *
     */
    function onconfig()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "onstat&amp;do=config";

        $this->print_header("Onconfig Info", $link,
        "IDS configuration Information", 30);

        $stmt = $db->prepare("select trim(cf_name) name, " .
        "trim(cf_effective) value from sysconfig order by 1");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $rss_item_line = $row['NAME'] . " " . $row['VALUE'];
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end onconfig

    /**
     * Show the servers log file.  This will only print the tail
     * of the online.log
     *
     */
    function online_log()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "show&amp;do=showOnlineLogTail";

        $this->print_header("Online Log Messages", $link,
        "IDS Online Log Messages", 30);

        $stmt = $db->prepare("select skip 1 line from sysonlinelog " .
        "where offset > -1024");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $rss_item_line = str_replace("<", "&lt;", rtrim($row['LINE']));
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end online_log

    /**
     * Show general information about the physical log in an
     * RSS feed.
     *
     */
    function plog()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "rlogs&amp;do=plog";

        $this->print_header("Physical Log", $link, "Physical Log Information",
        30);

        $stmt = $db->prepare("select pl_b1used, pl_bufsize, pl_chunk, " .
        "pl_offset, pl_physize, pl_phypos, pl_phyused from sysplog");
        if ($stmt->execute())
        {
            $row = $stmt->fetch();
            $rss_item_line = "bufused: " . $row['PL_B1USED'] . " bufsize: " .
            $row['PL_BUFSIZE'] . " phybegin: " .  $row['PL_CHUNK'] . ":" .
            $row['PL_OFFSET'] . " physize: " . $row['PL_PHYSIZE'] .
            " phypos: " . $row['PL_PHYPOS'] . " phyused: " .
            $row['PL_PHYUSED'] . " (" .
            (100 * $row['PL_PHYUSED']/$row['PL_PHYSIZE']) . "%)";
            $this->print_item($rss_item_line, $link);
        }
        $this->print_footer();
    } # end_plog

    /**
     * In an RSS fee show the list of database sessions.
     *
     */
    function sessions()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "onstat&amp;do=ses";
        $this->print_header("Session Info",$link,"IDS Session Information",5);

        $stmt = $db->prepare("select sid, trim(username) user, uid, pid, " .
        "hostname from syssessions");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $rss_item_line = "{$row['SID']}, {$row['USER']}@" .
                "{$row['HOSTNAME']}, UID {$row['UID']}, PID {$row['PID']}";
                $link .= "&amp;sid={$row['SID']}";
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end sessions

    /**
     * Show the recent sql statement that have been run on the server.
     *
     */
    function sql_stmts()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "sqltrace&amp;do=ByType";
        $this->print_header("Recent SQL Statements", $link,
        "Recent SQL Statements", 20);

        $stmt = $db->prepare("select sqs_sessionid sid, trim(sqs_dbname) db," .
        " sqs_statement stmt from syssqlstat");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $sql_stmt = str_replace("<","&lt;",$row['STMT']);
		$sql_stmt = str_replace(">","&gt;",$sql_stmt);
		$sql_stmt = str_replace("&","&amp;",$sql_stmt);
		$sql_stmt = str_replace("'","&apos;",$sql_stmt);
		$sql_stmt = str_replace("\"","&quot;",$sql_stmt);

		$rss_item_line = "{$row['SID']}, {$row['DB']}, {$sql_stmt}";
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end sql_stmts

    /**
     * Show the online servers environment setting in
     * an RSS feed
     *
     */
    function sys_env()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "onstat&amp;do=config";
        $this->print_header("System Environment Variables", $link,
        "IDS System Environment Variables", 30);

        $stmt = $db->prepare("select env_name, env_value from sysenv " .
        "order by 1");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $rss_item_line = rtrim($row['ENV_NAME']) . " " .
                rtrim($row['ENV_VALUE']);
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end sys_env

    /**
     * In an RSS feed show the list of Virtual processors.
     *
     */
    function vps()
    {
        $db = $this->idsadmin->get_database("sysmaster");
        $link = "onstat&amp;do=glo";
        $this->print_header("Virtual Processor Status", $link,
        "Virtual Processor (VP) Status", 20);

        $stmt = $db->prepare("select vpid, trim(class) cls, usercpu, syscpu " .
        "from sysvpprof");
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch())
            {
                $rss_item_line = "{$row['VPID']} {$row['CLS']}, User: " .
                "{$row['USERCPU']}, Sys: {$row['SYSCPU']}";
                $this->print_item($rss_item_line, $link);
            }
        }
        $this->print_footer();
    } #end vps


    /**
     * The default function which is called
     * when this page is not called by the rss feeds.
     *
     */
    function def()
    {
        $this->add_app_rss("Chunks");
        $this->add_app_rss("Databases");
        $this->add_app_rss("Dbspaces");
        $this->add_app_rss("Environment");
        $this->add_app_rss("LogicalLogs");
        $this->add_app_rss("Onconfig");
        $this->add_app_rss("OnlineLog");
        $this->add_app_rss("PhysicalLog");
        $this->add_app_rss("Sessions");
        $this->add_app_rss("SQL");
        $this->add_app_rss("VPs");

        $this->idsadmin->html->add_to_output($this->idsadmin->template['template_rss']->rss_intro());
        $this->add_rss_link("Chunks", "Chunks RSS feed", "Chunks");
        $this->add_rss_link("Databases","Databases RSS feed","Databases");
        $this->add_rss_link("Dbspaces", "Dbspaces RSS feed", "Dbspaces");
        $this->add_rss_link("Environment","Environment Variables RSS feed",
        "Environment Variables");
        $this->add_rss_link("LogicalLogs",
        "Logical Logs RSS feed", "Logical Logs");
        $this->add_rss_link("Onconfig", "Onconfig RSS feed", "Onconfig");
        $this->add_rss_link("OnlineLog", "Online Log RSS feed", "Online Log");
        $this->add_rss_link("PhysicalLog", "Physical Log RSS feed",
        "Physical Log");
        $this->add_rss_link("Sessions", "Sessions RSS feed", "Sessions");
        $this->add_rss_link("SQL","SQL Stmts RSS feed","SQL Statements");
        $this->add_rss_link("VPs","VPs RSS feed","Virtual Processors");

        $this->idsadmin->html->add_to_output($this->idsadmin->template['template_rss']->rss_conclusion());
    } #end default

    /**
     * Print the rss title
     *
     * @param string $title
     */
    function add_app_rss($title)
    {
        $this->idsadmin->html->add_to_rss ( "<link rel='alternate' type='application/rss+xml' " .
        "title='{$title}' href='{$this->idsadmin->get_config('BASEURL')}/" .
        "index.php?act=rss&amp;do={$title}'/>");
    } #end add_app_rss

    /**
     * Add the rss link to the browser
     *
     * @param string $title
     * @param string $desc
     * @param string $label
     */
    function add_rss_link($title, $desc, $label)
    {
         
        $this->idsadmin->html->add_to_output("<li><a href='javascript:void(0);' onClick='pop_rss(\"$title\")'" .
        "title='{$desc}'><img src='images/rss_feed.gif' border='0' alt='RSS'/> " .
        "{$label}</a></li>");

    } #add_rss_link

    /**
     * Print the RSS header
     *
     * @param string $title
     * @param string $link
     * @param string $description
     * @param string $ttl
     */
    function print_header($title, $link, $description, $ttl)
    {

        header('Content-type: text/xml');
        print <<<EOF
<?xml-stylesheet type="text/xsl" href="{$this->idsadmin->get_config('BASEURL')}/templates/ids_rss.xsl"?>\n
<rss version="2.0">
<channel>
<title>{$title}</title>
<link>{$this->idsadmin->get_config('BASEURL')}/index.php?act={$link}</link>
<description>{$description}</description>
<ttl>{$ttl}</ttl>\n
EOF;
    } #end print_header()

    /**
     * Print the RSS item in the RSS format
     *
     * @param string $rss_item_line
     * @param string $link
     */
    function print_item($rss_item_line, $link)
    {

        print <<<EOF
  <item>
    <title>{$rss_item_line}</title>
    <link>{$this->idsadmin->get_config('BASEURL')}/index.php?act={$link}</link>
  </item>

EOF;
    }

    /**
     * Print the RSS footer
     *
     */
    function print_footer()
    {
        print "</channel>\n";
        print "</rss>\n";
        die();
    }
}
?>

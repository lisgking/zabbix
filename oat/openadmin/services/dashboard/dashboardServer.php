<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007, 2012.  All Rights Reserved
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


class dashboardServer {

    public $conndb;  /* PDO handle to the sqlite connections database */
    public $idsadmin = null;  
    
    const STATUS_RED = 1;
    const STATUS_YELLOW = 2;
    const STATUS_GREEN = 3;

    function __construct()
    {

        define( "ROOT_PATH","../../");
        define( 'IDSADMIN',  "1" );
        define( 'DEBUG', false);
        define( 'SQLMAXFETNUM' , 100 );

        include_once("../serviceErrorHandler.php");
        set_error_handler("serviceErrorHandler");

        require_once("../../services/idsadmin/clusterdb.php");
        $this->conndb = new clusterdb ();
        
        require_once("../../lib/idsadmin.php");
        $this->idsadmin = new idsadmin(true);
        $this->idsadmin->load_lang("home");
    }

    function getDBSpaces()
    {
        $sel = "select sh_pagesize from sysshmvals";
        $res = $this->doDatabaseWork($sel,"sysmaster");
        $defaultPageSize = $res[0]['SH_PAGESIZE'];

        $sel = "select first 5 syschunks.dbsnum as dbsnum, trim(name) as name "
        .",sum(decode(mdsize,-1,nfree,udfree) * {$defaultPageSize}) as nfree "
        .",sum((chksize - decode(mdsize,-1,nfree,udfree)) * {$defaultPageSize}) as nused "
        .",sum(chksize*{$defaultPageSize}) as size "
        ."from syschunks , sysdbspaces "
        ."where syschunks.dbsnum = sysdbspaces.dbsnum "
        ."group by 1,2 "
        ."order by size desc" ;

        return $this->doDatabaseWork($sel,"sysmaster");

    } // end getDBSpaces

    function getDBSpacesIO()
    {
        $sel = "select syschunks.dbsnum, trim(name) as name,sum(pagesread) as read, "
        ." sum(pageswritten) as write "
        ." from syschunks , sysdbspaces ,syschkio "
        ." where syschunks.dbsnum = sysdbspaces.dbsnum "
        ." and syschunks.chknum = syschkio.chunknum "
        ." group by 1,2 "
        ." order by 1 asc" ;

        return $this->doDatabaseWork($sel,"sysmaster");

    } // end getDBSpacesIO

    function getChunksByDBS($dbsnum)
    {
        if ( ! $dbsnum )
        {
            triger_error (" getChunksByDBS " , " Param not found ");
        }

        $sel = "select sh_pagesize from sysshmvals";
        $res = $this->doDatabaseWork($sel,"sysmaster");
        $defaultPageSize = $res[0]['SH_PAGESIZE'];

        $sel = "select trim(fname) as name "
        .",((chksize - decode(mdsize,-1,nfree,udfree)) * {$defaultPageSize}) as nused "
        .",decode(mdsize,-1,nfree,udfree) * {$defaultPageSize} as nfree "
        ." from syschktab where dbsnum = {$dbsnum}" ;
        return $this->doDatabaseWork($sel,"sysmaster");

    } // end getChunksByDBS

    function getDBSpaceById($dbsNum=1)
    {
        if ( ! $dbsNum )
        {
            trigger_error(" getDBSPaceById - Param not found ",E_USER_ERROR);
        }

        $sel = "select dbsnum , trim(name) as name, trim(owner) as owner, pagesize "
        ." ,fchunk ,nchunks ,is_mirrored ,is_blobspace "
        ." ,is_sbspace ,is_temp ,flags "
        ." from sysdbspaces where dbsnum = {$dbsNum}" ;

        $row = $this->doDatabaseWork($sel,"sysmaster");
        return new myDBspace_t($row);

    } // end getDBSpacesIO

    function getMemory()
    {

        $sel = "select current hour to second as dt,sum(seg_blkfree) as free "
        .",sum(seg_blkused) as used from syssegments ";

        $row = $this->doDatabaseWork($sel,"sysmaster");
        return $row;

    } // end getMemory

    function getTransac()
    {
        $sel = "select current hour to second as dt, count(*) as num "
        ." from systrans where bitval(tx_flags,2) > 0 ";

        $row = $this->doDatabaseWork($sel,"sysmaster");
        return $row;

    } // end getTransac

    function getLocks()
    {
        $sel = "select current hour to second as dt, count(*) as num "
        ." from syslocks";

        $row = $this->doDatabaseWork($sel,"sysmaster");
        return $row;

    } // end getLocks
    
    function getNumSessions()
    {
    	$qry = "select current hour to second as dt, count(*) as count "
    	. "from syssessions where syssessions.sid != DBINFO('sessionid')";
    	 
    	$row = $this->doDatabaseWork($qry, "sysmaster");
    	 
    	return $row;
    }
    
    function getDiskIO()
    {
    	$sel = "select current hour to second as dt, * from sysshmhdr where number in ( 288 )";
    
    	$row = $this->doDatabaseWork($sel,"sysmaster");
    	return $row;
    }
    
    function getNetworkReadsWrites()
    {
    	$sel = "select current hour to second as dt, ng_reads as reads, ng_writes as writes from sysnetglobal";
    
    	$row = $this->doDatabaseWork($sel,"sysmaster");
    	return $row;
    }
    
    function getTableReads()
    {
    	require_once ROOT_PATH."lib/feature.php";
    	$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
    	if (Feature::isAvailable(Feature::PANTHER_UC4, $this->idsadmin->phpsession->serverInfo->getVersion()))
    	{
    		// For 11.70.xC4 and above, we can use the string_to_utf8 and therefore are able to 
    		// return top table reads no matter how many locales are in use on the database server.
    		$sel = "select first 25 dbsname , string_to_utf8(tabname, dbs_collate) as tabname, isreads "
    			 . "from sysptprof left outer join sysdbslocale on dbsname = dbs_dbsname "
    			 . "order by isreads desc ";
    		$row = $this->doDatabaseWork($sel,"sysmaster","en_US.UTF8");
    	} else {
    		// For anything earlier, we'll only support the Top Table Reads module on database servers
    		// with a maximum of one non-English locale (enforced by the uniqueNonEnglishLocale function).
    		$sel = "select first 25 dbsname , tabname , isreads "
    			 . " from sysptprof order by isreads desc";
    		
    		$locale = $this->uniqueNonEnglishLocale();
    		$row = $this->doDatabaseWork($sel,"sysmaster",$locale);
    	}
        
        return $row;

    } // end getTableReads
    
    function getTopTablesModified()
    {
    	require_once ROOT_PATH."lib/feature.php";
    	$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
    	if (Feature::isAvailable(Feature::PANTHER_UC4, $this->idsadmin->phpsession->serverInfo->getVersion()))
    	{
    		// For 11.70.xC4 and above, we can use the string_to_utf8 and therefore are able to 
    		// return top table reads no matter how many locales are in use on the database server.
    		$sel = "select first 25 dbsname, "
    			 . "string_to_utf8(tabname, dbs_collate) as tabname, "
    			 . "iswrites as inserts, "
    			 . "isrewrites as updates, "
    			 . "isdeletes as deletes, "
    			 . "iswrites + isrewrites + isdeletes as total "
    			 . "from sysptprof left outer join sysdbslocale on dbsname = dbs_dbsname "
    			 . "order by total desc ";
    		$row = $this->doDatabaseWork($sel,"sysmaster","en_US.UTF8");
    	} else {
    		// For anything earlier, we'll only support the Top Table Modify module on database servers
    		// with a maximum of one non-English locale (enforced by the uniqueNonEnglishLocale function).
    		$sel = "select first 25 dbsname, "
    			 . "tabname, "
    			 . "iswrites as inserts, "
    			 . "isrewrites as updates, "
    			 . "isdeletes as deletes, "
    			 . "iswrites + isrewrites + isdeletes as total "
    			 . "from sysptprof order by total desc";
    		
    		$locale = $this->uniqueNonEnglishLocale();
    		$row = $this->doDatabaseWork($sel,"sysmaster",$locale);
    	}
        return $row;

    } // end getTopTablesModified

    function getCpu()
    {
        $sel = "select current hour to second as dt , TRUNC(sum(usecs_user + usecs_sys ),2) as user "
 				. " FROM sysmaster:sysvplst";
 				
        $row = $this->doDatabaseWork($sel,"sysmaster");
        return $row;

    } // end getCpu
    
    function getOSMemory()
    {
    	$sel = "select current hour to second as dt, os_mem_total as total, os_mem_free free, ( os_mem_total - os_mem_free ) as used  "
        ." from sysmachineinfo ";

        $row = $this->doDatabaseWork($sel,"sysmaster");
        return $row;
    }
    
    function getSQLActionsTotal()
    {
   		$sel = "select trim(name) as name, value "
   			. "from sysshmhdr where number in (53, 54, 55, 56)";

   		$res = $this->doDatabaseWork($sel,"sysmaster");
   		return $res;        

    } // end getSQLActionsTotal
    
    function getSQLActions()
    {
        $sel = "select DBINFO('utc_current') as utc, "
        	 . "current hour to second as dt, "
        	 . "sum(value) as ops "
        	 . "from sysshmhdr "
        	 . "where number in (53, 54, 55, 56)";
        $res = $this->doDatabaseWork($sel,"sysmaster");
        
        return $res;

    } // end getSQLActions
        
    function getDashBoards()
    {
        $qry = "select * from dashboards";
        $res = $this->doConnectionsDBWork($qry);
        
        // Localize default dashboard names and descriptions.
        // These are inserted into the connections.db with a keyword at install time.  
        // If user has not edited these, we want to display the names and descriptions 
        // in the current UI language.
        for($i = 0; $i < count($res); $i++)
        {
            if (preg_match('/dashboard_name_.*/', $res[$i]['dashboard_name']))
            {
                $res[$i]['dashboard_name'] = $this->idsadmin->lang($res[$i]['dashboard_name']);
            }
            if (preg_match('/dashboard_desc_.*/', $res[$i]['dashboard_description']))
            {
                $res[$i]['dashboard_description'] = $this->idsadmin->lang($res[$i]['dashboard_description']);
            }
        }
        
        return $res;
    } // end getDashBoards

    function getPanels($dashboardId=0)
    {
        if ( $dashboardId == 0 )
        {
            $qry = "select * from panels";
        }
        else
        {
            $qry = "select * from panels where panel_id not in ( select panelid from dashpanels where dashid = {$dashboardId} )";
        }

        $res = $this->doConnectionsDBWork($qry);
        
        // Localize panel titles and descriptions.
        // These are inserted into the connections.db with the lang file keyword at install time.
        for($i = 0; $i < count($res); $i++)
        {
            if (preg_match('/panel_title_.*/', $res[$i]['panel_title']))
            {
                $res[$i]['panel_title'] = $this->idsadmin->lang($res[$i]['panel_title']);
            }
            if (preg_match('/panel_desc_.*/', $res[$i]['panel_description']))
            {
                $res[$i]['panel_description'] = $this->idsadmin->lang($res[$i]['panel_description']);
            }
        }
        
        return $res;
    } // end getPanels

    function getPanelsForDashBoard($dashboardId)
    {
        if ( !$dashboardId )
        {
            trigger_error("getPanelsForDashBoard - No Dashboard Id Found",E_USER_ERROR);
        }

        $qry = "select * from dashpanels,panels where dashid = {$dashboardId} and panels.panel_id = panelid  order by pos";

        $res = $this->doConnectionsDBWork($qry);
        
        // Localize panel titles and descriptions.
        // These are inserted into the connections.db with the lang file keyword at install time.  
        for($i = 0; $i < count($res); $i++)
        {
            if (preg_match('/panel_title_.*/', $res[$i]['panel_title']))
            {
                $res[$i]['panel_title'] = $this->idsadmin->lang($res[$i]['panel_title']);
            }
            if (preg_match('/panel_desc_.*/', $res[$i]['panel_description']))
            {
                $res[$i]['panel_description'] = $this->idsadmin->lang($res[$i]['panel_description']);
            }
        }
        
        return $res;
    }

    function putDashBoardPanels($dashid,$dashboardpanels)
    {
        // first delete any dashpanels
        $qry = "delete from dashpanels where dashid = {$dashid}";
        $this->conndb->query($qry);
        $this->checkSQL();
        //now insert the new ones .
        foreach ($dashboardpanels as $k => $v)
        {
             
            if ( is_array ($v) )
            {
                foreach ($v as $p => $c)
                {
                    $qry = "insert into dashpanels values ( {$dashid} ,{$c->panel_id}, {$p} );";
                    $this->conndb->query($qry);
                    $this->checkSQL();
                }
            }
            else
            {
                $qry = "insert into dashpanels values ( {$dashid} ,{$v->panel_id}, 0);";
                $this->conndb->query($qry);
                $this->checkSQL();
            }
        }
        return 0;
    }

    function putDashBoard($dashboard_id , $dashboard_name,$dashboard_description , $dashboard_refresh=30 )
    {
        if ( $dashboard_refresh > 180 )
        {
            $dashboard_refresh = 180;
        }
        
        if ( $dashboard_id > 0)
        {
            $qry =  "update dashboards  set dashboard_name = :dashboard_name ";
            $qry .= ",dashboard_description = :dashboard_description ";
            $qry .= ",dashboard_refresh = :dashboard_refresh ";
            $qry .= " where dashboard_id = :dashboard_id;";
            $stmt = $this->conndb->prepare($qry);
            $this->checkSQL();
            $stmt->bindParam(":dashboard_name", $dashboard_name);
            $stmt->bindParam(":dashboard_description", $dashboard_description);
            $stmt->bindParam(":dashboard_refresh", $dashboard_refresh);
            $stmt->bindParam(":dashboard_id", $dashboard_id);
            $stmt->execute();
            $this->checkStmtSQL($stmt);
            $this->checkSQL();
            return $dashboard_id;
        }
        else
        {
            $qry =  "insert into dashboards (dashboard_name, dashboard_description, dashboard_refresh) ";
            $qry .= "values (:dashboard_name, :dashboard_description, :dashboard_refresh)";
            $stmt = $this->conndb->prepare($qry);
            $this->checkSQL();
            $stmt->bindParam(":dashboard_name", $dashboard_name);
            $stmt->bindParam(":dashboard_description", $dashboard_description);
            $stmt->bindParam(":dashboard_refresh", $dashboard_refresh);
            $stmt->execute();
            $this->checkStmtSQL($stmt);
            $this->checkSQL();
            $qry="select last_insert_rowid() as status";
            $res = $this->doConnectionsDBWork($qry);
            return $res[0]['status'];
        }
    }

    function deleteDashBoard($dashboard_id)
    {
        $qry = "delete from dashboards where dashboard_id = {$dashboard_id}";
        $this->conndb->query($qry);
        $this->checkSQL();
        return 0;
    }
    
    /**
     * Returns a list of all of the servers in the current OAT group.
     */
    function getServersInGroup() 
    {
    	$group_num = $this->idsadmin->phpsession->get_group();
    	if ($group_num == "")
    	{
    		// If not logged into OAT group, return empty arrary
    		return array();
    	}
    	
    	$qry = "select conn_num as CONN_NUM, server as SERVERNAME from connections "
    		 . "where group_num = {$group_num} order by server";
    	$res = $this->doConnectionsDBWork($qry);
        return $res;
    }
    
    /**
     * Gets server status for the group summary tab.
     * 
     * @param $conn_num 
     * @param $force_refresh - if true, don't use the cache; force 
     *                         a refresh directly from the server.
     */
    function getServerStatus($conn_num, $force_refresh = false) 
    {
    	$group_num = $this->idsadmin->phpsession->get_group();
    	if ($group_num == "" || $conn_num == "")
    	{
    		return array();
    	}
    	
    	// Check if the server status is already in the cache
    	$cacheData = $this->getServerStatusFromCache($conn_num);
    	if ($cacheData != null && !$force_refresh)
    	{
    		$cache_refresh_interval = $this->idsadmin->get_config('PINGINTERVAL', 300);
    		if ((time() - $cacheData['LAST_UPDATED']) < $cache_refresh_interval)
    		{
    			return $cacheData;
    		}
    	}
    	
    	$res = array();
    	$res['CONN_NUM'] = $conn_num;
    	$res['FROM_CACHE'] = false;
    	$res['LAST_UPDATED'] = time();
    	    	
    	// Get a connection to the specified server
    	if ($conn_num == $this->idsadmin->phpsession->instance->get_conn_num())
    	{
    		// It is the current OAT server
    		$db = $this->idsadmin->get_database("sysmaster"); 
    		
    	} else {
	    	// Otherwise, it is another server in the OAT group, so we need to 
	    	// get connection info from the connections db.
	    	$qry = "select server, host, port, username, password, idsprotocol from connections "
	    		 . "where group_num = {$group_num} and conn_num = {$conn_num}";
	    	$connInfo = $this->doConnectionsDBWork($qry);
	    	if (count($connInfo) == 0)
	    	{
	    		$res['STATUS'] = "OFFLINE";
	    		$res['STATUS_MESSAGE'] = $this->idsadmin->lang("CouldNotRetrieveConnectionInfo");
	    		$this->cacheServerStatusInConnDB($conn_num, $res, $cacheData);
	    		return $res;
	    	}
    	
	    	$server = $connInfo[0]['server'];
	    	$host = $connInfo[0]['host'];
	    	$port = $connInfo[0]['port'];
	    	$username = $connInfo[0]['username'];
	    	$password = $connInfo[0]['password'];
	    	$protocol = $connInfo[0]['idsprotocol'];
	    	
	    	// Try to connect to the server
	    	try 
	    	{
	    		$db = $this->getServerConnection($server, $host, $port, $username, $password, $protocol);
	    	}
			catch(PDOException $e) 
			{
				// If we can't connect, return now.
				$message=preg_split("/:/", $e->getMessage());
				$statusMessage = $message[sizeof($message)-1];
				$statusMessage = $this->idsadmin->lang("ConnectionFailed", array($statusMessage));
				$res['STATUS'] = "OFFLINE";
				$res['SERVER_BLOCKED'] = false;
				$res['STATUS_MESSAGE'] = $statusMessage;
				$this->cacheServerStatusInConnDB($conn_num, $res, $cacheData);
				return $res;
			}
		}
		
		// If we can connect, make sure the server is not blocked (e.g. by a checkpoint).
		// Note: This data will only be returned by 12.10 servers or higher.  Older 
		// servers will return no rows for this query, so we just have to assume they 
		// are not blocked.
		$qry = "select name, value from sysshmhdr where number in (292, 293, 294)";
		// Note about the query: querying by number instead of name to take advantage of index.
		// 292=ckpt_pending, 293=ckpt_active, 294=blockflag 
		// If any of these are non-zero, some sort of blocking is occurring.
		$ret = $this->doDatbaseWorkForServer($qry, $db);
		if (count($ret) > 0)
		{
			foreach ($ret as $row)
			{
				if ($row['VALUE'] != 0)
				{
					// If the server is blocked, OAT should treat it as the equivalent of
					// an OFFLINE server.  This is because trying to run the rest of the
					// queries to get the server status will hang if the server is blocked. 
					$res['STATUS'] = "OFFLINE";
					$res['SERVER_BLOCKED'] = true;
					$res['STATUS_MESSAGE'] = $this->idsadmin->lang("ServerBlocked");
					$this->cacheServerStatusInConnDB($conn_num, $res, $cacheData);
					return $res;
				}
			}
		}
		
		// If we got this far without returning, the server is up and we have a connection.
		$res['STATUS'] = "ONLINE";
		
		// So figure out if Health Advisor plugin is installed.
		$hadvPluginInstalled = $this->isHealthAdvisorInstalled($db);
		
		// Get the server version, we'll need it for some queries later on.
		$qry = "select DBINFO('version','full') as version from sysdual";
		$versionRet = $this->doDatbaseWorkForServer($qry, $db);
		$version = $versionRet[0]['VERSION'];
		
		// And now get all of the status data for that server.
		$res['ONLINE_INFO'] = $this->getOnlineInfoForServer($db);
		$res['ALERTS'] = $this->getAlertStatusForServer($db);
		$res['ERRORS'] = $this->getErrorStatusForServer($db);
		$res['SESSIONS'] = $this->getSessionsStatusForServer($db);
		$cpuData = $this->getCPUStatusForServer($db);
		$res = array_merge($res, $cpuData);
		$res['BACKUPS'] = $this->getBackupStatusForServer($db);
		$res['MEMORY'] = $this->getMemoryStatusForServer($db, $hadvPluginInstalled, $version);
		$res['SPACE'] = $this->getSpaceStatusForServer($db, $hadvPluginInstalled);
		$res['IO'] = $this->getIOStatusForServer($db, $hadvPluginInstalled, $version);
		
		// Cache this status data in the connections.db
		$this->cacheServerStatusInConnDB($conn_num, $res, $cacheData);
		
		return $res;
    }
    
    
    
    /****************************************************************************************
     * This section contains private functions used to get server status for the group 
     * summary tab of the Dashboad.
     ****************************************************************************************/
    
    /**
     * Get basic online info for a server --
     * the information that will be shown in the status pop-up
     * 
     * @param $db - PDO database connection to the server
     */
    private function getOnlineInfoForServer($db)
    {
    	$data = array();
    	
    	$qry = "SELECT DBINFO('version','full') AS version, ha_type FROM sysha_type";
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	$versionStr = str_word_count($res[0]['VERSION'],1,'.1234567890');
    	$versionStr = $versionStr[sizeof($versionStr)-1];
    	$data['VERSION'] = $versionStr;
    	$data['HA_TYPE'] = $res[0]['HA_TYPE'];
    	switch ($res[0]['HA_TYPE'])
    	{
			case 0:
				$serverType = "Standard";
				break;
			case 1:
				$serverType = "Primary";
				break;
			case 2:
				$serverType = "Secondary";
				break;
			case 3:
				$serverType = "SDS";
				break;
			case 4:
				$serverType = "RSS";
				break;
    	}
    	$this->idsadmin->load_lang("misc_template");
    	$data['SERVER_TYPE'] = $this->idsadmin->lang($serverType);
    	
    	$qry = <<< EOF
SELECT 
dbinfo('UTC_TO_DATETIME', sh_boottime)::datetime year to minute as boottime,
dbinfo('UTC_TO_DATETIME', sh_curtime)::datetime hour to second as curtime,
(sh_curtime - sh_boottime) as uptime,
sh_ovlmaxcons
from sysshmvals
EOF;
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	$data['BOOTTIME'] = $res[0]['BOOTTIME'];
    	$data['CURTIME'] = $res[0]['CURTIME'];
    	$data['UPTIME'] = $this->idsadmin->timedays($res[0]['UPTIME']);
    	$data['MAX_USERS'] = $res[0]['SH_OVLMAXCONS'];
    	
    	return $data;
    }
    
    /**
     * Get number of alerts on a server.
     * 
     * @param $db - PDO database connection to the server
     */
    private function getAlertStatusForServer($db) 
    {
    	$qry = "select count(*) as count from sysadmin:ph_alerts";
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	
    	return $res[0]['COUNT'];
    }
    
    /**
     * Get number of online.log errors on a server.
     * 
     * @param $db - PDO database connection to the server
     */
    private function getErrorStatusForServer($db)
    {
    	$qry = "select trim(line) as line from sysonlinelog where offset > -10000";
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	
    	// Because IDS doesn't do case insensitive search on sysmaster database,
    	// we'll have the query return the lines from the online.log and we'll
    	// use PHP to find the error lines.
    	$errorCnt = 0;
    	foreach ($res as $row)
    	{
    		$val = $row['LINE'];
    		if ( stripos($val, "err") || stripos($val, "assert") || stripos($val, "Exception") || stripos($val, "fail") )
    		{
    			if (!stripos($val,"success"))
				{
					// If if string also has success in it, don't mark it as an error!
					// For example, the message 'R-tree error message conversion completed successfully'
					$errorCnt++;
				}
    		}
    	}
    	return $errorCnt;
    }
    
    /**
     * Get number of sessions connected to a server.
     * 
     * @param $db - PDO database connection to the server
     */
    private function getSessionsStatusForServer($db)
    {
    	$qry = "select count(*) as count "
    		 . "from syssessions where syssessions.sid != DBINFO('sessionid')";
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	
    	return $res[0]['COUNT'];
    }
    
    /**
     * Get backup status for a server.
     * 
     * @param $db - PDO database connection to the server
     */
    private function getBackupStatusForServer($db)
    {
    	$result = array();
    	
    	// Get the oldest level 0 backup across all dbspaces on the database server.
    	$qry = <<< EOF
SELECT 
decode(level0,0, 'NEVER' ,trim((CURRENT - DBINFO('utc_to_datetime',level0))::char(40)) ) as oldest_Level0
FROM 
( 
    SELECT 
    min(level0) as level0 
    FROM sysdbstab 
    WHERE BITAND(flags, '0x2000') = 0 
)
EOF;
    	$oldestBackups = $this->doDatbaseWorkForServer($qry, $db);
    	if (count($oldestBackups) > 0)
    	{
    		$result['OLDEST_LEVEL0'] = $oldestBackups[0]['OLDEST_LEVEL0'];
    	}
    	
    	// Get threshold for how often level 0 backups should run from ph_threshold
    	$result['MAX_INTERVAL_L0'] = 2;  // Default threshold (2 days)
    	$qry = "select value from sysadmin:ph_threshold "
    	     . "where task_name = 'check_backup' and name = 'REQUIRED LEVEL 0 BACKUP'";
    	$reqBackup = $this->doDatbaseWorkForServer($qry, $db);
    	if (count($reqBackup) > 0)
    	{
    		$result['MAX_INTERVAL_L0'] = trim($reqBackup[0]['VALUE']);
    	}
    	
    	// Compute backup status - red, yellow, or green.
    	$result["BACKUP_STATUS"] = $this->determineBackupStatus($result['OLDEST_LEVEL0'], $result['MAX_INTERVAL_L0']);
   		
    	return $result;
    }
    
    /**
     * Determine if backup status should be RED, YELLOW or GREEN 
     * based on threshold and age of the last backup.
     */
    private function determineBackupStatus( $last_backup_datime, $threshold)
    {
    	// Status is red if there has never been a backup
    	if ($last_backup_datime == 'NEVER') 
    	{
    		return self::STATUS_RED;
    	}
    	
    	// Figure out number of days since the last backup
    	$dayComp = preg_split("/ /",$last_backup_datime);
    	$numDaysSinceBackup = $dayComp[0];

    	// Compare to threshold and determine status
    	if ($numDaysSinceBackup <= $threshold)
    	{
    		// If last backup is within threshold, status is GREEN
    		return self::STATUS_GREEN;
    	} else if ($numDaysSinceBackup <= ($threshold + 1)) {
    		// If only one day past threshold, status is YELLOW
    		return self::STATUS_YELLOW;
    	} else {
    		// If more than one day past threshold, status is RED
    		return self::STATUS_RED;
    	}
    }
    
    /**
     * Get cpu usage for a server
     * 
     * @param $db - PDO database connection to the server
     */
    private function getCPUStatusForServer($db)
    {
    	$qry = <<< EOF
SELECT
ROUND((sum( usecs_user + usecs_sys ) - MAX(before_time)) / (MAX(run_duration)) * 100, 2) AS CPU_USED_PERCENT,
ROUND(MAX(run_duration)/60, 0) AS MEASURED_OVER_MINUTES
FROM
(
    SELECT SUM( usecs_user + usecs_sys) AS before_time, MAX( sh_curtime - r.run_mttime ) AS run_duration
    FROM sysadmin:mon_vps, sysadmin:ph_run R, sysmaster:sysshmvals
    WHERE class = 1
    AND id = (SELECT MAX(ID) FROM sysadmin:mon_vps)
    AND id = run_task_seq
    AND run_task_id = (SELECT tk_id FROM sysadmin:ph_task WHERE tk_name = 'mon_vps')
)
, sysmaster:sysvplst
EOF;
    	
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	
    	$data = array();
    	$data['CPU'] = $res[0]['CPU_USED_PERCENT'];
    	$data['CPU_DURATION'] = $res[0]['MEASURED_OVER_MINUTES'];
    	
    	return $data;
    }
    
    /**
     * Get the memory status on a server.  
     * 
     * @param $db - PDO database connection to the server
     * @param $hadvPluginInstalled - true/false indicating whether the
     *              Health Advisor plugin is installed.
     * @param $version - server version
     */
    private function getMemoryStatusForServer($db, $hadvPluginInstalled, $version)
    {
    	$memoryData = array();
    	
    	// The query used by this function requires 11.50.xC1 or higher
    	require_once ROOT_PATH."lib/feature.php";
    	if (!Feature::isAvailable(Feature::CHEETAH2,$version))
    	{
    		$memoryData['NOT_SUPPORTED'] = true;
    		return $memoryData;
    	}

    	$qry = <<< EOF
select os_mem_free, os_mem_total, 
round(os_mem_free/os_mem_total * 100, 2) as os_mem_free_percent 
from sysmachineinfo
EOF;
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	$memoryData = $res[0];
    	$memoryFreePercent = $res[0]['OS_MEM_FREE_PERCENT'];
    	
    	// Default thresholds 
    	$red_alarm_threshold = 5;
    	$yellow_alarm_threshold = 10;
    	
    	if ($hadvPluginInstalled)
    	{
    		$qry = <<< EOF
select trim(red_rvalue) as red_alarm_threshold,
trim(yel_rvalue) as yellow_alarm_threshold
from sysadmin:hadv_gen_prof g, sysadmin:hadv_profiles p 
where g.prof_id = p.prof_id 
and p.name = 'Default'
and g.group = 'OS'
and g.desc = 'OS Free Memory'
and g.enable = 'Y'
EOF;
    		$res = $this->doDatbaseWorkForServer($qry, $db);
    		
    		if (count($res))
    		{
    			if (is_numeric($res[0]['RED_ALARM_THRESHOLD']))
    			{
    				$red_alarm_threshold = $res[0]['RED_ALARM_THRESHOLD'];
    			}
    			if (is_numeric($res[0]['YELLOW_ALARM_THRESHOLD']))
    			{
    				$yellow_alarm_threshold = $res[0]['YELLOW_ALARM_THRESHOLD'];
    			}
    		}
    	}
    	
    	$memoryData['RED_ALARM_THRESHOLD'] = $red_alarm_threshold;
    	$memoryData['YELLOW_ALARM_THRESHOLD'] = $yellow_alarm_threshold;
    	if (floatval($memoryFreePercent) <= floatval($red_alarm_threshold))
    	{
    		$memoryData['MEMORY_STATUS'] = self::STATUS_RED;
    	} 
    	else if (floatval($memoryFreePercent) <= floatval($yellow_alarm_threshold))
    	{
    		$memoryData['MEMORY_STATUS'] = self::STATUS_YELLOW;
    	} else {
    		$memoryData['MEMORY_STATUS'] = self::STATUS_GREEN;
    	}
    	
    	return $memoryData;
    }

    /**
     * Get the space status on a server.
     * 
     * @param $db - PDO database connection to the server
     * @param $hadvPluginInstalled - true/false indicating whether the
     *              Health Advisor plugin is installed.
     */
    private function getSpaceStatusForServer($db, $hadvPluginInstalled)
    {
    	$spaceData = array();
    	
    	// Default thresholds 
    	$red_alarm_threshold = 5;
    	$yellow_alarm_threshold = 10;
    	$exception_list_clause = "";
    	
    	if ($hadvPluginInstalled)
    	{
    		// Get Health Advisor thresholds
    		$qry = <<< EOF
select trim(red_lvalue_param1) as red_alarm_threshold,
trim(yel_lvalue_param1) as yellow_alarm_threshold
from sysadmin:hadv_gen_prof g, sysadmin:hadv_profiles p 
where g.prof_id = p.prof_id 
and p.name = 'Default'
and g.group = 'Storage'
and g.desc = 'Dbspace Free Space'
and g.enable = 'Y'
EOF;
    		$res = $this->doDatbaseWorkForServer($qry, $db);
    		
    		if (count($res))
    		{
    			if (is_numeric($res[0]['RED_ALARM_THRESHOLD']))
    			{
    				$red_alarm_threshold = $res[0]['RED_ALARM_THRESHOLD'];	
    			}
    			if (is_numeric($res[0]['YELLOW_ALARM_THRESHOLD']))
    			{
    				$yellow_alarm_threshold = $res[0]['YELLOW_ALARM_THRESHOLD'];
    			}
    		}
    		
    		// Get Health Advisor exception list (dbspaces not to include when generating status)
    		$qry = <<< EOF
select trim(value) as space_name
from sysadmin:hadv_exception_prof e, sysadmin:hadv_profiles p 
where e.prof_id = p.prof_id 
and p.name = 'Default'
and e.tabname = 'sto_chkspace'
EOF;
    		$res = $this->doDatbaseWorkForServer($qry, $db);
    		
    		if (count($res) > 0)
    		{
	    		foreach ($res as $row)
	    		{
	    			$exception_list .= "'{$row['SPACE_NAME']}',";
	    		}
	    		$exception_list_clause = "and trim(d.name) not in (" . substr($exception_list, 0, strlen($exception_list) - 1) . ")";
    		}
    	}
    	$spaceData['RED_ALARM_THRESHOLD'] = $red_alarm_threshold;
    	$spaceData['YELLOW_ALARM_THRESHOLD'] = $yellow_alarm_threshold;
    	
    	// Query server any spaces that fall below the yellow threshold
    	$qry = <<< EOF
select d.name dbspace, sum(c.chksize) size, sum(decode(C.mdsize,-1,C.nfree,C.udfree)) nfree, 
round((sum(decode(C.mdsize,-1,C.nfree,C.udfree))/sum(c.chksize))*100,2) percent_free 
from sysmaster:sysdbspaces d, sysmaster:syschunks c
where d.dbsnum=c.dbsnum 
$exception_list_clause
group by 1
having ((sum(decode(C.mdsize,-1,C.nfree,C.udfree))/sum(c.chksize)) * 100) <= {$yellow_alarm_threshold}
order by percent_free 
EOF;
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	$spaceData['SPACES_LIST'] = $res;
    	
    	if (count($res) == 0)
    	{
    		// If no spaces are below the yellow threshold, then everything is green.
    		$spaceData['SPACE_STATUS'] = self::STATUS_GREEN;
    	} else {
    		// Otherwise, we need to go through the results to see if we have
    		// any red alarms.
    		$spaceData['SPACE_STATUS'] = self::STATUS_YELLOW;
    		foreach ($res as $row)
    		{
    			if (floatval($row['PERCENT_FREE']) <= floatval($red_alarm_threshold))
    			{
    				$spaceData['SPACE_STATUS'] = self::STATUS_RED;
    				break;
    			}
    		}
    	}
    	
    	return $spaceData;
    }
    
    /**
     * Get the I/O status on a server.
     * 
     * There are two checks for I/O:
     *    (1) Check that I/O reads/writes per second on each chunk
     *        do not exceed thresholds.
     *    (2) Check that no single chunk does too large a percentage
     *        of total I/O activity.
     * 
     * @param $db - PDO database connection to the server
     * @param $hadvPluginInstalled - true/false indicating whether the
     *              Health Advisor plugin is installed.
     * @param $version - server version
     */
    private function getIOStatusForServer($db, $hadvPluginInstalled, $version)
    {
    	$ioData = array();
    	
    	/** Check #1: Check if I/O reads or writes per second on each chunk exceed thresholds. **/
    	$io_time_red_threshold = 0.01;
    	$io_time_yellow_threshold = 0.005;
    	$ioData['IO_TIME_RED_ALARM_THRESHOLD'] = $io_time_red_threshold;
    	$ioData['IO_TIME_YELLOW_ALARM_THRESHOLD'] = $io_time_yellow_threshold;
    	
    	// Query server for any chunks whose read/write I/O per second exceeds yellow threshold
    	require_once ROOT_PATH."lib/feature.php";
    	if (Feature::isAvailable(Feature::PANTHER_UC3,$version))
    	{
    		$chunk_table = "syschktab_fast";
    		$mirror_chunk_table = "sysmchktab_fast";
    	} else {
    		$chunk_table = "syschktab";
    		$mirror_chunk_table = "sysmchktab";
    	}
    	$qry = <<< EOF
SELECT 
chknum,
name as dbspace,
round(read_io_time,5) as read_io_time,
round(write_io_time,5) as write_io_time
FROM 
(
select
chknum,
dbsnum,
decode(reads, 0, 0, (readtime/reads) *(1/1000000)) as read_io_time,
decode(writes, 0, 0, (writetime/writes) *(1/1000000)) as write_io_time
from {$chunk_table}
union
select
chknum,
dbsnum,
decode(reads, 0, 0, (readtime/reads) *(1/1000000)) as read_io_time,
decode(writes, 0, 0, (writetime/writes) *(1/1000000)) as write_io_time
from {$mirror_chunk_table}
) as chunk_io (chknum, dbsnum, read_io_time, write_io_time), sysdbspaces
where chunk_io.dbsnum = sysdbspaces.dbsnum
and (chunk_io.read_io_time > {$io_time_yellow_threshold} OR chunk_io.write_io_time > {$io_time_yellow_threshold})
EOF;

    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	$ioData['IO_TIME_CHUNKS_LIST'] = $res;
    	
    	if (count($res) == 0)
    	{
    		// If no chunks returned, then everything is green
    		$ioData['IO_TIME_STATUS'] = self::STATUS_GREEN;
    	} else {
    		// Otherwise, we need to go through the results to see if we have
    		// any red alarms.
    		$ioData['IO_TIME_STATUS'] = self::STATUS_YELLOW;
    		foreach ($res as $row)
    		{
    			if (floatval($row['READ_IO_TIME']) >= floatval($io_time_red_threshold)
    				|| floatval($row['WRITE_IO_TIME']) >= floatval($io_time_red_threshold))
    			{
    				$ioData['IO_TIME_STATUS'] = self::STATUS_RED;
    				break;
    			}
    		}
    	}
    	
    	    	
    	/** Check #2: Check if any single chunk does too large a percentage of total I/O activity. **/
    	// This check will use the Health Advisor plugin's thresholds, if it's installed.
    	
    	// Default thresholds 
    	$io_percent_red_alarm_threshold = 40;
    	$io_percent_yellow_alarm_threshold = 25;
    	$exception_list_clause = "";
    	
    	if ($hadvPluginInstalled)
    	{
    		// Get Health Advisor thresholds
    		$qry = <<< EOF
select trim(red_lvalue_param1) as red_alarm_threshold,
trim(yel_lvalue_param1) as yellow_alarm_threshold
from sysadmin:hadv_gen_prof g, sysadmin:hadv_profiles p 
where g.prof_id = p.prof_id 
and p.name = 'Default'
and g.group = 'Storage'
and g.desc = 'Chunk IO OPS'
and g.enable = 'Y'
EOF;
    		$res = $this->doDatbaseWorkForServer($qry, $db);
    		
    		if (count($res))
    		{
    			if (is_numeric($res[0]['RED_ALARM_THRESHOLD']))
    			{
    				$io_percent_red_alarm_threshold = $res[0]['RED_ALARM_THRESHOLD'];
    			} 
    			if (is_numeric($res[0]['YELLOW_ALARM_THRESHOLD']))
    			{
    				$io_percent_yellow_alarm_threshold = $res[0]['YELLOW_ALARM_THRESHOLD'];
    			}
    		}
    		
    		// Get Health Advisor exception list (chunks not to include when generating status)
    		$qry = <<< EOF
select trim(value) as chknum
from sysadmin:hadv_exception_prof e, sysadmin:hadv_profiles p 
where e.prof_id = p.prof_id 
and p.name = 'Default'
and e.tabname = 'sto_chkio_ops'
EOF;
    		$res = $this->doDatbaseWorkForServer($qry, $db);
    		
    		if (count($res) > 0)
    		{
	    		foreach ($res as $row)
	    		{
	    			if ($row['CHKNUM'] != "")
	    			{
	    				$exception_list .= $row['CHKNUM'] . ",";
	    			}
	    		}
	    		$exception_list_clause = "and c.chknum not in (" . substr($exception_list, 0, strlen($exception_list) - 1) . ")";
    		}
    	}
    	$ioData['IO_PERCENT_RED_ALARM_THRESHOLD'] = $io_percent_red_alarm_threshold;
    	$ioData['IO_PERCENT_YELLOW_ALARM_THRESHOLD'] = $io_percent_yellow_alarm_threshold;
    	
    	// Query server for total I/O operations
    	$qry = "select sum(pagesread + pageswritten) as total_ops from sysmaster:syschktab";
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	$totalOps = $res[0]['TOTAL_OPS'];
    	$ioData['TOTAL_OPS'] = $totalOps;
    	
    	// Query server any chunks that whose I/O % exceeds yellow threshold
    	$qry = <<< EOF
select d.name dbspace, c.chknum, 
c.pagesread + c.pageswritten ops,
round(((c.pagesread + c.pageswritten)/$totalOps * 100), 2) as percent_total_ops
from sysmaster:syschktab c, sysmaster:sysdbspaces d 
where d.dbsnum = c.dbsnum 
$exception_list_clause
and (((c.pagesread + c.pageswritten)/$totalOps) * 100) >= {$io_percent_yellow_alarm_threshold}
order by ops desc 
EOF;
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	$ioData['IO_PERCENT_CHUNKS_LIST'] = $res;
    	
    	if (count($res) == 0)
    	{
    		// If no chunks are above the yellow threshold, then everything is green.
    		$ioData['IO_PERCENT_STATUS'] = self::STATUS_GREEN;
    	} else {
    		// Otherwise, we need to go through the results to see if we have
    		// any red alarms.
    		$ioData['IO_PERCENT_STATUS'] = self::STATUS_YELLOW;
    		foreach ($res as $row)
    		{
    			if (floatval($row['PERCENT_TOTAL_OPS']) >= floatval($io_percent_red_alarm_threshold))
    			{
    				$ioData['IO_PERCENT_STATUS'] = self::STATUS_RED;
    				break;
    			}
    		}
    	}
    	
    	// Compute overall I/O status
    	$ioData['IO_STATUS'] = min($ioData['IO_TIME_STATUS'], $ioData['IO_PERCENT_STATUS']);
    	
    	return $ioData;
    }
    
    
    /**
     * Determines if the Health Advisor plugin is installed.
     * 
     * @param $db - PDO database connection to the server
     * @return true/false
     */
    private function isHealthAdvisorInstalled($db) 
    {
    	$qry = "select count(*) as HAEXISTS from sysadmin:systables where tabname='hadv_gen_prof'";
    	$res = $this->doDatbaseWorkForServer($qry, $db);
    	if ($res[0]['HAEXISTS'] == 1)
    	{
    		return true;
    	} else {
    		return false;
    	}
    }
    
    /**
     * Cache the server status information used by the Dashboard > Group Summary tab
     * in the connections.db.
     */
    private function cacheServerStatusInConnDB($conn_num, $data, $cacheData)
    {
    	// Figure out if we need to do an INSERT or UPDATE operation
    	if ($cacheData == null)
    	{
	    	$sql = <<< EOF
insert into dashboard_server_status 
(
conn_num, 
group_num, 
last_updated, 
status, 
status_message, 
server_blocked, 
server_version, 
ha_type, 
boottime, 
curtime, 
uptime,
max_users, 
session_count, 
alert_count, 
error_count, 
backup_status, 
backup_oldest_level0,
backup_max_interval_l0,
cpu_used_percent, 
cpu_duration,
memory_status, 
memory_not_supported, 
memory_red_alarm_threshold, 
memory_yellow_alarm_threshold, 
os_mem_total, 
os_mem_free, 
os_mem_free_percent,
space_status, 
space_red_alarm_threshold, 
space_yellow_alarm_threshold, 
spaces_alert_list,
io_status, 
io_time_status, 
io_time_red_alarm_threshold, 
io_time_yellow_alarm_threshold, 
io_time_bad_chunks, 
io_percent_status, 
io_percent_red_alarm_threshold, 
io_percent_yellow_alarm_threshold, 
io_total_ops, 
io_percent_bad_chunks
) 
values 
(
:conn_num, 
:group_num, 
:last_updated, 
:status, 
:status_message, 
:server_blocked, 
:server_version, 
:ha_type, 
:boottime, 
:curtime, 
:uptime,
:max_users, 
:session_count, 
:alert_count, 
:error_count, 
:backup_status, 
:backup_oldest_level0,
:backup_max_interval_l0,
:cpu_used_percent, 
:cpu_duration,
:memory_status, 
:memory_not_supported, 
:memory_red_alarm_threshold, 
:memory_yellow_alarm_threshold, 
:os_mem_total, 
:os_mem_free, 
:os_mem_free_percent,
:space_status, 
:space_red_alarm_threshold, 
:space_yellow_alarm_threshold, 
:spaces_alert_list,
:io_status, 
:io_time_status, 
:io_time_red_alarm_threshold, 
:io_time_yellow_alarm_threshold, 
:io_time_bad_chunks, 
:io_percent_status, 
:io_percent_red_alarm_threshold, 
:io_percent_yellow_alarm_threshold, 
:io_total_ops, 
:io_percent_bad_chunks
) 
EOF;
    	} else {
    			    	$sql = <<< EOF
update dashboard_server_status
set
last_updated = :last_updated,
status = :status,
status_message = :status_message,
server_blocked = :server_blocked,
server_version = :server_version,
ha_type	= :ha_type,
boottime = :boottime,
curtime = :curtime,
uptime = :uptime,
max_users = :max_users,
session_count = :session_count,
alert_count = :alert_count, 
error_count = :error_count, 
backup_status = :backup_status, 
backup_oldest_level0 = :backup_oldest_level0,
backup_max_interval_l0 = :backup_max_interval_l0,
cpu_used_percent = :cpu_used_percent, 
cpu_duration = :cpu_duration,
memory_status = :memory_status, 
memory_not_supported = :memory_not_supported, 
memory_red_alarm_threshold = :memory_red_alarm_threshold, 
memory_yellow_alarm_threshold = :memory_yellow_alarm_threshold, 
os_mem_total = :os_mem_total, 
os_mem_free = :os_mem_free, 
os_mem_free_percent = :os_mem_free_percent,
space_status = :space_status, 
space_red_alarm_threshold = :space_red_alarm_threshold, 
space_yellow_alarm_threshold = :space_yellow_alarm_threshold, 
spaces_alert_list = :spaces_alert_list,
io_status = :io_status, 
io_time_status = :io_time_status, 
io_time_red_alarm_threshold = :io_time_red_alarm_threshold, 
io_time_yellow_alarm_threshold = :io_time_yellow_alarm_threshold, 
io_time_bad_chunks = :io_time_bad_chunks, 
io_percent_status = :io_percent_status, 
io_percent_red_alarm_threshold = :io_percent_red_alarm_threshold, 
io_percent_yellow_alarm_threshold = :io_percent_yellow_alarm_threshold, 
io_total_ops = :io_total_ops, 
io_percent_bad_chunks = :io_percent_bad_chunks
where conn_num = :conn_num
and group_num = :group_num
EOF;
    	}
	    	
	    // Prepare it
    	$stmt = $this->conndb->prepare($sql);
    	$err = $this->conndb->errorInfo();
    	if ( $err[1] != 0 )
    	{
    		// We don't need to show an error to the user if we can't cache the server status,
    		// because the dashboard will still work, it will just have to query the server each time.
    		// So we'll just print an error to the web server error_log, but not throw one to the UI. 
    		error_log("Unable to save dashboard group summary status to the connections.db.  " .
    			"An error occurred while preparing the SQL statement: " . $err[1] . " " . $err[2]);
    		return;
    	}
    	
    	// Bind all the parameters.
    	$stmt->bindParam(":conn_num", $conn_num);
    	$group_num = $this->idsadmin->phpsession->get_group();
    	$stmt->bindParam(":group_num", $group_num);
    	$stmt->bindParam(":last_updated", $data['LAST_UPDATED']);
    	$stmt->bindParam(":status", $data['STATUS']);
    	$stmt->bindParam(":status_message", $data['STATUS_MESSAGE']);
    	$server_blocked = (isset($data['SERVER_BLOCKED']))? $data['SERVER_BLOCKED'] : false;
    	$stmt->bindParam(":server_blocked", $server_blocked);
    	$stmt->bindParam(":server_version", $data['ONLINE_INFO']['VERSION']);
    	$stmt->bindParam(":ha_type", $data['ONLINE_INFO']['HA_TYPE']);
    	$stmt->bindParam(":boottime", $data['ONLINE_INFO']['BOOTTIME']);
    	$stmt->bindParam(":curtime", $data['ONLINE_INFO']['CURTIME']);
    	$stmt->bindParam(":uptime", $data['ONLINE_INFO']['UPTIME']);
    	$stmt->bindParam(":max_users", $data['ONLINE_INFO']['MAX_USERS']);
    	$stmt->bindParam(":session_count", $data['SESSIONS']);
    	$stmt->bindParam(":alert_count", $data['ALERTS']);
    	$stmt->bindParam(":error_count", $data['ERRORS']);
    	$stmt->bindParam(":backup_status", $data['BACKUPS']['BACKUP_STATUS']);
    	$stmt->bindParam(":backup_oldest_level0", $data['BACKUPS']['OLDEST_LEVEL0']);
    	$stmt->bindParam(":backup_max_interval_l0", $data['BACKUPS']['MAX_INTERVAL_L0']);
    	$stmt->bindParam(":cpu_used_percent", $data['CPU']);
    	$stmt->bindParam(":cpu_duration", $data['CPU_DURATION']);
    	$stmt->bindParam(":memory_status", $data['MEMORY']['MEMORY_STATUS']);
    	$mem_not_supported = (isset($data['MEMORY']['NOT_SUPPORTED']))? $data['MEMORY']['NOT_SUPPORTED'] : false;
    	$stmt->bindParam(":memory_not_supported", $mem_not_supported);
    	$stmt->bindParam(":memory_red_alarm_threshold", $data['MEMORY']['RED_ALARM_THRESHOLD']);
    	$stmt->bindParam(":memory_yellow_alarm_threshold", $data['MEMORY']['YELLOW_ALARM_THRESHOLD']);
    	$stmt->bindParam(":os_mem_total", $data['MEMORY']['OS_MEM_TOTAL']);
    	$stmt->bindParam(":os_mem_free", $data['MEMORY']['OS_MEM_FREE']);
    	$stmt->bindParam(":os_mem_free_percent", $data['MEMORY']['OS_MEM_FREE_PERCENT']);
    	$stmt->bindParam(":space_status", $data['SPACE']['SPACE_STATUS']);
    	$stmt->bindParam(":space_red_alarm_threshold", $data['SPACE']['RED_ALARM_THRESHOLD']);
    	$stmt->bindParam(":space_yellow_alarm_threshold", $data['SPACE']['YELLOW_ALARM_THRESHOLD']);
    	$spaces_alert_list = serialize($data['SPACE']['SPACES_LIST']);
    	$stmt->bindParam(":spaces_alert_list", $spaces_alert_list);
    	$stmt->bindParam(":io_status", $data['IO']['IO_STATUS']);
    	$stmt->bindParam(":io_time_status", $data['IO']['IO_TIME_STATUS']);
    	$stmt->bindParam(":io_time_red_alarm_threshold", $data['IO']['IO_TIME_RED_ALARM_THRESHOLD']);
    	$stmt->bindParam(":io_time_yellow_alarm_threshold", $data['IO']['IO_TIME_YELLOW_ALARM_THRESHOLD']);
    	$io_time_bad_chunks = serialize($data['IO']['IO_TIME_CHUNKS_LIST']);
    	$stmt->bindParam(":io_time_bad_chunks", $io_time_bad_chunks);
    	$stmt->bindParam(":io_percent_status", $data['IO']['IO_PERCENT_STATUS']);
    	$stmt->bindParam(":io_percent_red_alarm_threshold", $data['IO']['IO_PERCENT_RED_ALARM_THRESHOLD']);
    	$stmt->bindParam(":io_percent_yellow_alarm_threshold", $data['IO']['IO_PERCENT_YELLOW_ALARM_THRESHOLD']);
    	$stmt->bindParam(":io_total_ops", $data['IO']['TOTAL_OPS']);
    	$io_percent_bad_chunks = serialize($data['IO']['IO_PERCENT_CHUNKS_LIST']);
    	$stmt->bindParam(":io_percent_bad_chunks", $io_percent_bad_chunks);
    	
    	// Execute it
    	$stmt->execute();
    	$err = $this->conndb->errorInfo();
    	if ( $err[1] != 0 )
    	{
    		// We don't need to show an error to the user if we can't cache the server status,
    		// because the dashboard will still work, it will just have to query the servers each time.
    		// So we'll just print an error to the web server error_log, but not throw one to the UI. 
    		error_log("Unable to save dashboard group summary status to the connections.db.  " .
    			"An error occurred while executing the SQL statement: " . $err[1] . " " . $err[2]);
    		return;
    	}
    }


    /**
     * Get the server status information for the Dashboard > Group Summary tab
     * from the connections.db cache.
     * 
     * If this server is in the cache, this function returns that data in the format
     * expected by Flex.  If the specified server doesn't have status info in the 
     * cache, this function returns null.
     */
    private function getServerStatusFromCache($conn_num)
    {
    	$sql = <<< EOF
select
conn_num, 
group_num, 
last_updated, 
status, 
status_message, 
server_blocked, 
server_version, 
ha_type, 
boottime, 
curtime, 
uptime,
max_users, 
session_count, 
alert_count, 
error_count, 
backup_status, 
backup_oldest_level0,
backup_max_interval_l0,
cpu_used_percent, 
cpu_duration,
memory_status, 
memory_not_supported, 
memory_red_alarm_threshold, 
memory_yellow_alarm_threshold, 
os_mem_total, 
os_mem_free, 
os_mem_free_percent,
space_status, 
space_red_alarm_threshold, 
space_yellow_alarm_threshold, 
spaces_alert_list,
io_status, 
io_time_status, 
io_time_red_alarm_threshold, 
io_time_yellow_alarm_threshold, 
io_time_bad_chunks, 
io_percent_status, 
io_percent_red_alarm_threshold, 
io_percent_yellow_alarm_threshold, 
io_total_ops, 
io_percent_bad_chunks
from dashboard_server_status
where  
conn_num = {$conn_num} 
and group_num = {$this->idsadmin->phpsession->get_group()} 
order by last_updated desc
EOF;

    	$res = $this->doConnectionsDBWork($sql);
    	
    	if (count($res) == 0)
    	{
    		// Returning null indicates that this server is not yet in the connections.db cache.
    		return null;
    	}
    	
    	// There should only ever be one row per server in the cache. But just in case
    	// somehow we get multiple rows per server, the query above orders by the last_updated time
    	// (descending), the most recent would status the first row returned by the query.
    	$row = $res[0];
    	
    	// Convert status data from the connections.db database format to that expected by Flex.
    	$data = array();
    	$data['CONN_NUM'] = $conn_num;
    	$data['LAST_UPDATED'] = $row['last_updated'];
    	$data['STATUS'] = $row['status'];
    	$data['STATUS_MESSAGE'] = $row['status_message'];
    	$data['SERVER_BLOCKED'] = $row['server_blocked'];
    	$data['ONLINE_INFO'] = array();
    	$data['ONLINE_INFO']['VERSION'] = $row['server_version'];
    	$data['ONLINE_INFO']['HA_TYPE'] = $row['ha_type'];
    	switch ($data['ONLINE_INFO']['HA_TYPE'])
    	{
			case 0:
				$serverType = "Standard";
				break;
			case 1:
				$serverType = "Primary";
				break;
			case 2:
				$serverType = "Secondary";
				break;
			case 3:
				$serverType = "SDS";
				break;
			case 4:
				$serverType = "RSS";
				break;
    	}
    	$this->idsadmin->load_lang("misc_template");
    	$data['ONLINE_INFO']['SERVER_TYPE'] = $this->idsadmin->lang($serverType);
    	$data['ONLINE_INFO']['BOOTTIME'] = $row['boottime'];
    	$data['ONLINE_INFO']['CURTIME'] = $row['curtime'];
    	$data['ONLINE_INFO']['UPTIME'] = $row['uptime'];
    	$data['ONLINE_INFO']['MAX_USERS'] = $row['max_users'];
    	$data['SESSIONS'] = $row['session_count'];
    	$data['ALERTS'] = $row['alert_count'];
    	$data['ERRORS'] = $row['error_count'];
    	$data['BACKUPS'] = array();
    	$data['BACKUPS']['BACKUP_STATUS'] = intval($row['backup_status']);
    	$data['BACKUPS']['OLDEST_LEVEL0'] = $row['backup_oldest_level0'];
    	$data['BACKUPS']['MAX_INTERVAL_L0'] = $row['backup_max_interval_l0'];
    	$data['CPU'] = $row['cpu_used_percent'];
    	$data['CPU_DURATION'] = $row['cpu_duration'];
    	$data['MEMORY'] = array();
    	$data['MEMORY']['MEMORY_STATUS'] = ((intval($row['memory_status']) == 0)? null:intval($row['memory_status']));
    	$data['MEMORY']['NOT_SUPPORTED'] = ($row['memory_not_supported'] == "1");
    	$data['MEMORY']['RED_ALARM_THRESHOLD'] = $row['memory_red_alarm_threshold'];
    	$data['MEMORY']['YELLOW_ALARM_THRESHOLD'] = $row['memory_yellow_alarm_threshold'];
    	$data['MEMORY']['OS_MEM_TOTAL'] = $row['os_mem_total'];
    	$data['MEMORY']['OS_MEM_FREE'] = $row['os_mem_free'];
    	$data['MEMORY']['OS_MEM_FREE_PERCENT'] = $row['os_mem_free_percent'];
    	$data['SPACE'] = array();
    	$data['SPACE']['SPACE_STATUS'] = intval($row['space_status']);
    	$data['SPACE']['RED_ALARM_THRESHOLD'] = $row['space_red_alarm_threshold'];
    	$data['SPACE']['YELLOW_ALARM_THRESHOLD'] = $row['space_yellow_alarm_threshold'];
    	$data['SPACE']['SPACES_LIST'] = unserialize($row['spaces_alert_list']);
    	$data['IO'] = array();
    	$data['IO']['IO_STATUS'] = intval($row['io_status']);
    	$data['IO']['IO_TIME_STATUS'] = intval($row['io_time_status']);
    	$data['IO']['IO_TIME_RED_ALARM_THRESHOLD'] = $row['io_time_red_alarm_threshold'];
    	$data['IO']['IO_TIME_YELLOW_ALARM_THRESHOLD'] = $row['io_time_yellow_alarm_threshold'];
    	$data['IO']['IO_TIME_CHUNKS_LIST'] = unserialize($row['io_time_bad_chunks']);
    	$data['IO']['IO_PERCENT_STATUS'] = intval($row['io_percent_status']);
    	$data['IO']['IO_PERCENT_RED_ALARM_THRESHOLD'] = $row['io_percent_red_alarm_threshold'];
    	$data['IO']['IO_PERCENT_YELLOW_ALARM_THRESHOLD'] = $row['io_percent_yellow_alarm_threshold'];
    	$data['IO']['TOTAL_OPS'] = $row['io_total_ops'];
    	$data['IO']['IO_PERCENT_CHUNKS_LIST'] = unserialize($row['io_percent_bad_chunks']);
    	$data['FROM_CACHE'] = true; 
    	
    	return $data;
    }
    
    
    /****************************************************************************************
     * Private helper functions used for doing database queries
     ****************************************************************************************/
    
    private function doConnectionsDBWork($sel)
    {
        $ret = array();
        $stmt = $this->conndb->query($sel);
        while ($row = $stmt->fetch())
        {
            $ret[] = $row;

        }
        return $ret;
    } // end doConnectionsDBWork

    /**
     * Check the sql was successful , if not trigger an error.
     *
     * @return nothing or error out
     */
    private function checkSQL()
    {
        $err = $this->conndb->errorInfo();
        //error_log(var_export($err,true));
        if ( $err[0]  != "00000" )
        {
             trigger_error($this->idsadmin->lang("SQLError", array($err[1], $err[2])),E_USER_ERROR);    
        }
    }
    
    /**
     * Check the sql was successful on the statement object, if not trigger an error.
     *
     * @return nothing or error out
     */
    private function checkStmtSQL($stmt)
    {
        $err = $stmt->errorInfo();
        //error_log(var_export($err,true));
        if ( $err[0]  != "00000" )
        {
             trigger_error($this->idsadmin->lang("SQLError", array($err[1], $err[2])),E_USER_ERROR);    
        }
    }
    

	/* Tables is system databases like sysmaster, sysadmin could potentially have data in multiple locales.
	 * An example scenario is db A having Japanese char table names and db B having Chinese char table names.
	 * SQL Tracing would create data with these names in the above mentioned system db tables. Hence a table would
	 * have data in multiple locales. The assumption below is that a typical customer scenario would be to have
	 * databases in only one non-English locale.
	 *
	 * return - unique non-English locales
	 */
		 
	private function uniqueNonEnglishLocale()
	{
		$locale = NULL;
		$unique_locale = "select unique(dbs_collate) from sysdbslocale where dbs_collate NOT LIKE 'en_%'";

		$locale_res = $this->doDatabaseWork($unique_locale,"sysmaster");
		
		if ( count($locale_res) > 1 )
		{
			trigger_error($this->idsadmin->lang('NotYetImpl'));
			return $ret;
			
		} else if ( count($locale_res) == 1 )
		{
			$locale = $locale_res[0]['DBS_COLLATE'];
		}
		
		return $locale;
	}
	
	/**
	 * Get server connection.
	 * 
	 * NOTE: This function throws a PDOException if the connection fails,
	 * so the caller should be able to handle such exception with a try/catch block. 
	 */
	function getServerConnection($serverName, $host, $port, $username, $password, $protocol)
	{
		$dbname = "sysmaster";
		// Only connecting to system databases, so only need the en_US.819 locale.
		$locale = "en_US.819";

		require_once(ROOT_PATH."lib/PDO_OAT.php");
		$db = new PDO_OAT($this->idsadmin,$serverName,$host,$port,$protocol,$dbname,$locale,null,$username,$password);
		
		return $db;
	}
	
    function doDatabaseWork($sel,$dbname="sysmaster",$locale=NULL)
    {

		if (is_null($locale)) {
			$db = $this->idsadmin->get_database($dbname);			
		} else {
			require_once(ROOT_PATH."lib/database.php");
			$db = new database($this->idsadmin,$dbname,$locale,"","");
		}
		
		return $this->doDatbaseWorkForServer($sel, $db);
    }
		
	private function doDatbaseWorkForServer($sel, $db)
	{
		$stmt = $db->query($sel);
		
		if ($stmt != null)
		{
			while ($row = $stmt->fetch() )
			{
				$ret[] = $row;
			}
		}
		
		$err = $db->errorInfo();
		if ( $err[2] == 0 )
		{
			if ($stmt != null)
			{
				$stmt->closeCursor();
			}
		}
		else
		{
			$err = $this->idsadmin->lang("SQLError", array($err[2], $err[1]));
			if ($stmt != null)
			{
				$stmt->closeCursor();
			}
			trigger_error($err,E_USER_ERROR);
		}
		return $ret;
	}
}

class myDBSpace_t  {
    public $dbsnum        ;
    public $name          ;
    public $owner         ;
    public $pagesize      ;
    public $fchunk        ;
    public $nchunks       ;
    public $is_mirrored   ;
    public $is_blobspace  ;
    public $is_sbspace    ;
    public $is_temp       ;
    public $flags         ;

    function __construct($arr)
    {
        foreach ($arr as $k => $row )
        {
            $this->dbsnum = $row['DBSNUM'];
            $this->name   = $row['NAME'];
            $this->owner  = $row['OWNER'];
            $this->pagesize = $row['PAGESIZE'];
            $this->fchunk   = $row['FCHUNK'];
            $this->nchunks  = $row['NCHUNKS'];
            $this->is_mirrored = $row['IS_MIRRORED'];
            $this->is_blobspace = $row['IS_BLOBSPACE'];
            $this->is_sbspace = $row['IS_SBSPACE'];
            $this->is_temp    = $row['IS_TEMP'];
            $this->flags      = $row['FLAGS'];
        }
    }

}
?>

<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation, 2011, 2013.  All Rights Reserved
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



class oliteServer {

	var $idsadmin;
	var $timeout;
	var $useSameConnection;

	var $handlingPDOException; /* true if the function calling the database handles the PDO Exception */
	
	// This version of the olite web services support Mobile OAT version 1.x
	const MIN_SUPPORTED_MOBILE_OAT_VERSION = 1;
	const MAX_SUPPORTED_MOBILE_OAT_VERSION = 1;

	function __construct()
	{
		define ("ROOT_PATH","../../");

		include_once("../serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");

		require_once(ROOT_PATH."lib/idsadmin.php");
		$this->idsadmin = new idsadmin(true);
		$this->idsadmin->in = array("act" => "olite");

		require_once(ROOT_PATH."lib/feature.php");

		//putenv("INFORMIXCONTIME=10");
		//putenv("INFORMIXCONRETRY=5");

		$this->timeout = 15;
		$this->handlingPDOException = FALSE;
		$this->idsadmin->load_lang("oatlite");
	}
	
	private function setOATLiteLang($lang="en_US")
	{
		$this->idsadmin->phpsession->set_lang($lang);
		$this->idsadmin->load_lang("oatlite");
	}


	//----------------------------
	// Login Page
	//----------------------------

	/**
	 * Called to verify that the wsdl url is correct.
	 * Also verifies that the Mobile OAT version is supported by these web services.
	 */
	function urlIsValid($mobile_oat_verison, $lang="en_US")
	{
		$this->setOATLiteLang($lang);
		
		$ret = array();
		$ret['IS_SUPPORTED'] = true;
		$ret['MESSAGE'] = "connectionEstablished";
		
		// Validate Mobile OAT version
		$version_array = preg_split("/\./", $mobile_oat_verison);
		$major_version = $version_array[0];
		if (is_numeric($major_version))
		{
			if ($major_version < self::MIN_SUPPORTED_MOBILE_OAT_VERSION)
			{ 
				$ret['IS_SUPPORTED'] = false;
				$ret['MESSAGE'] = $this->idsadmin->lang("mobile_oat_not_supported_min", array($mobile_oat_verison, self::MIN_SUPPORTED_MOBILE_OAT_VERSION));
			} 
			else if ($major_version > self::MAX_SUPPORTED_MOBILE_OAT_VERSION)
			{
				$ret['IS_SUPPORTED'] = false;
				$ret['MESSAGE'] = $this->idsadmin->lang("mobile_oat_not_supported_max", array($mobile_oat_verison, self::MAX_SUPPORTED_MOBILE_OAT_VERSION));
			} 
		}
		
		return $ret;
	}

	function setTimeout($newTimeout)
	{
		settype($newTimeout, 'integer');
		$this->timeout = $newTimeout;
	}

	/**
	 * Gets info for all OAT groups
	 * @return group_name, group_num for all OAT groups
	 */
	function getGroups()
	{
		$sql = "SELECT group_name, group_num FROM groups ORDER BY group_name";
		$rows = $this->doConnectionsDatabaseWork($sql);
		return $rows;
	}

	/**
	 * Checks if the password provided is correct.
	 * @param $groupNum - which group password to check
	 * @param $potentialMatch - the user submitted password
	 * @return True if $potentialMatch == password for group with group_num == $groupNum
	 */
	function passIsCorrect($groupNum, $potentialMatch)
	{
		settype($groupNum, 'integer');
		$sql = "SELECT password FROM groups WHERE group_num = $groupNum";
		$password = $this->doConnectionsDatabaseWork($sql);

		return strcmp($potentialMatch, $password[0]['password']) == 0;
	}

	/**
	 * Verify that a connection to the server can be made.
	 * @return true if a new PDO can be created and server version is >= 11, false otherwise
	 */
	function canConnectToIDS($server, $host, $port, $protocol, $username, $password, $lang="en_US")
	{
		$this->setOATLiteLang($lang);
		
		$sql = "SELECT DBINFO('version','major') AS vers FROM sysha_type ";
		$this->handlingPDOException = TRUE;
		try
		{
			$temp = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $username, $password);
			/* set handlingPDOException back to false in case this is used in a multi call */
			$this->handlingPDOException = FALSE;
		}
		catch(PDOException $e)
		{
			return array("canConnect" => false, "message" => $e->getMessage());
		}
		catch(Exception $e1)
		{
			//error_log("Could not connect, returning false");
			return array("canConnect" => false, "message" => $e1->getMessage());
		}
		//error_log(var_export($temp));
		//error_log("temp: " . var_export($temp[0]['VERS'], true));
		if($temp[0]['VERS'] < 11)
		{
			return array("canConnect" => false, "message" => $this->idsadmin->lang('ServerVersionLessThan11'));
		}
		else
		{
			return array("canConnect" => true, "message" => "");
		}
	}


	//----------------------------
	// ServerList
	//----------------------------

	/**
	 * Gets summary info for all connections within group with group_num == $groupNum
	 */
	function getConnections($groupNum, $timeout, $lang="en_US")
	{
		$this->setOATLiteLang($lang);
		
		// Get conncetion info for all connections within group
		$sql = "SELECT server, host, port, idsprotocol, username, password FROM connections WHERE group_num = " . $groupNum;
		$connectionsInfo = $this->doConnectionsDatabaseWork($sql);

		$this->handlingPDOException = TRUE;
		// Add additional info to each connection
		for($i = 0; $i < count($connectionsInfo); $i++)
		{
			//if no exceptions/errors occur, then status = ONLINE
			$status = array();
			$status['ONLINE'] = false;
			$alerts = null;
			$memAndCpuData = null;
			$sessionsData = null;
			$this->useSameConnection = null;
				
			try
			{
				//error_log ( var_export ($connectionsInfo[$i],true));
				$this->useSameConnection = $this->getDBConnection("sysmaster",
				$connectionsInfo[$i]["server"], //server
				$connectionsInfo[$i]["host"], //host
				$connectionsInfo[$i]["port"], //port
				$connectionsInfo[$i]["idsprotocol"], //idsprotocol
				$connectionsInfo[$i]["username"], //username
				$connectionsInfo[$i]["password"], //password
				$this->timeout);

				$alerts = $this->getAlertCount($connectionsInfo[$i], $timeout);
				$memAndCpuData = $this->getLessMemAndCPUInfo($connectionsInfo[$i], $timeout);
				//$memAndCpuData = array();
				$sessionsData = $this->getLessSessionsInfo($connectionsInfo[$i], $timeout);
				$status['ONLINE'] = true;
			}
			catch(PDOException $e)
			{
				//error_log ( var_export ( $e , true));
				//-908 / -930
				//if(strpos($e->getMessage(), "930") || strpos($e->getMessage(), "951"))
				$errorInfo = $this->parsePDOException($e->getMessage());

				$errorMessage = $this->idsadmin->lang('ServerIsOffline');

				switch ( $errorInfo['code'] )
				{
					// for the following errors use the server errorMessage.
					case "-950":
					case "-951":
					case "-952":
					case "-954":
					case "-956":
					case "-27002":
					case "-27010":
						$errorMessage = $errorInfo['message'];
						break;
					default:
						$errorMessage = $this->idsadmin->lang('ServerIsOffline');
						break;
				}

				$status["ERROR_MESSAGE"] = $errorMessage;

			}
			catch(Exception $e1)
			{
				$status["ERROR_MESSAGE"] = $this->idsadmin->lang('BadConnectionInfo');
			}

			if($status['ONLINE'] == true)
			{
				$connectionsInfo[$i] = array_merge($connectionsInfo[$i], $alerts, $memAndCpuData, $sessionsData, $status);
			}
			else
			{
				$connectionsInfo[$i] = array_merge($connectionsInfo[$i],$status);
			}
		}
		$this->handlingPDOException = FALSE;
		$this->useSameConnection = null;
		return $connectionsInfo;
	}

	/**
	 * Gets number of alerts for one connection
	 * @param $connectionInfo - array containing all necessisary info to connect to server (server name, host name, etc.)
	 * @return $connectionInfo - with additionional "ALERT_COUNT" field
	 */
	function getAlertCount($connectionInfo, $timeout )
	{
		$sql = "SELECT count(*) as alert_count FROM sysadmin:ph_alerts";

		$alerts = $this->doDatabaseWork($sql, "sysadmin", $connectionInfo["server"], $connectionInfo["host"],
		$connectionInfo["port"], $connectionInfo["idsprotocol"], $connectionInfo["username"],
		$connectionInfo["password"], $timeout);

		return $alerts[0];
	}

	/**
	 * Gets brief memory and cpu information from the connection information
	 * @param $connectionInfo - array containing all necessisary info to connect to server (server name, host name, etc.)
	 * @return memory/cpu info
	 */
	function getLessMemAndCPUInfo($connectionInfo, $timeout)
	{

		// total mem, free mem, # cpus
		$sql = " SELECT  "
		//. " format_units(os_mem_total/1024,'k') as mem_total, "
		//. " format_units(os_mem_free/1024,'k')  as mem_free, "
		. " (os_mem_free/os_mem_total) * 100 as mem_free_percentage "
		//. " os_num_procs as num_procs "
		. " from sysmaster:sysmachineinfo ";

		$memAndCpuData = $this->doDatabaseWork($sql, "sysmaster", $connectionInfo["server"], $connectionInfo["host"],
		$connectionInfo["port"], $connectionInfo["idsprotocol"], $connectionInfo["username"],
		$connectionInfo["password"], $timeout);
			
		/* $cpu = 	 $this->getCPUPercentageOfAServer ($connectionInfo["server"], $connectionInfo["host"],
		 $connectionInfo["port"], $connectionInfo["idsprotocol"], $connectionInfo["username"],
		 $connectionInfo["password"], $timeout);*/
		//error_log ( var_export ( $cpu , true ));
		return $memAndCpuData[0];

	}

	function getCPUPercentage($connections, $timeout)
	{
		$connectionsInfo = unserialize($connections);

		$cpus = array();
		// Save CPU percentage information for each server
		for($i = 0; $i < count($connectionsInfo); $i++)
		{
			if($connectionsInfo[$i][0] == "true")//get CPU information for online servers only
			{
				try
				{
					$result = $this->getCPUPercentageOfAServer(
					$connectionsInfo[$i][1], //server
					$connectionsInfo[$i][2], //host
					$connectionsInfo[$i][3], //port
					$connectionsInfo[$i][4], //idsprotocol
					$connectionsInfo[$i][5], //username
					$connectionsInfo[$i][6], //password
					$timeout);
					$cpus[] = $result;
				}
				catch(Exception $e)
				{
					error_log("ERROR: " . var_export($e->getMessage(), true));
				}
			}
		}

		return $cpus;
	}

	function getCPUPercentageOfAServer ($server, $host, $port, $idsprotocol, $username, $password, $timeout)
	{
		$sql = "SELECT
    		(sum( usecs_user + usecs_sys ) - max(before_time)) / (max(run_duration))
    		AS CPU_USED,
    		max(run_duration)  MEASURED_OVER_SECONDS
			from
			(
				select  sum( usecs_user + usecs_sys) before_time, max( sh_curtime - r.run_mttime ) run_duration
				from sysadmin:mon_vps, sysadmin:ph_run  R, sysmaster:sysshmvals
				WHERE  class = 1
				and id = (select MAX(ID) from sysadmin:mon_vps)
				and id = run_task_seq
				and run_task_id = (select tk_id from sysadmin:ph_task where tk_name = 'mon_vps')
			)
			, sysmaster:sysvplst";
			
		$result = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $idsprotocol, $username, $password, $timeout);
		$result[0]["server"] = $server;
		return $result[0];
	}

	/**
	 * Gets brief sessions information
	 * @param $connectionInfo - array containing all necessisary info to connect to server (server name, host name, etc.)
	 * @return sessions info
	 */
	function getLessSessionsInfo($connectionInfo, $timeout){

		$sql = " SELECT  count(*) as sessions "
		." from  syssessions "
		." WHERE syssessions.sid != DBINFO('sessionid') ";

		$sessionsData = $this->doDatabaseWork($sql, "sysmaster", $connectionInfo["server"], $connectionInfo["host"],
		$connectionInfo["port"], $connectionInfo["idsprotocol"], $connectionInfo["username"],
		$connectionInfo["password"], $timeout);
		return $sessionsData[0];
	}

	/**
	 * Gets information about server
	 * @return server_type, version, server_time, boot_time, up_time, sessions, max_users, total_mem, free_mem, #_of_cpus
	 */
	function getServerInfo($server, $host, $port, $idsprotocol, $username, $password, $lang="en_US")
	{
		$this->setOATLiteLang($lang);
		
		// version and type
		$sql = "SELECT DBINFO('version','major') || '.' || DBINFO('version', 'minor') || '.' || DBINFO('version', 'os') ||
				DBINFO('version', 'level') AS vers , ha_type FROM sysha_type ";
		$temp0 = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $idsprotocol, $username, $password);

		// Convert server type from int to string
		switch ($temp0[0]['HA_TYPE'])
		{
			case 0:
				$temp0[0]['HA_TYPE'] = $this->idsadmin->lang('Standard');
				break;
			case 1:
				$temp0[0]['HA_TYPE'] = $this->idsadmin->lang('Primary');
				break;
			case 2:
				$temp0[0]['HA_TYPE'] = $this->idsadmin->lang('Secondary');
				break;
			case 3:
				$temp0[0]['HA_TYPE'] = $this->idsadmin->lang('SDS');
				break;
			case 4:
				$temp0[0]['HA_TYPE'] = $this->idsadmin->lang('RSS');
				break;
		}

		// total mem, free mem, # cpus
		$sql = " SELECT  "
		. " format_units(os_mem_total/1024,'k') as mem_total, "
		. " format_units(os_mem_free/1024,'k')  as mem_free, "
		. " os_num_procs as num_procs"
		. " from sysmaster:sysmachineinfo ";
		$temp1 = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $idsprotocol, $username, $password);

		// boot time, current time, uptime, max users, sessions
		$sql = " SELECT  count(*) as sessions "
		." ,dbinfo('UTC_TO_DATETIME', sh_boottime)::datetime year to second as boottime "
		." ,dbinfo('UTC_TO_DATETIME', sh_curtime)::datetime hour to second as curtime "
		." ,(sh_curtime - sh_boottime) as uptime "
		." ,sh_ovlmaxcons as max_users"
		." ,(sh_pagesize / 1024) as pagesize"
		." from sysshmvals  , syssessions "
		." WHERE syssessions.sid != DBINFO('sessionid') "
		." GROUP BY 2,3,4,5,6";
		$temp2 = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $idsprotocol, $username, $password);

		// Convert uptime from sec to days hours:min:sec
		$temp2[0]['UPTIME'] = $this->idsadmin->timedays($temp2[0]['UPTIME']);

		// Merge query results into one result
		$info = array_merge($temp0[0], $temp1[0], $temp2[0]);

		//error_log(var_export($info,true));
		return $info;

	}

	/**
	 * Gets alerts for specified server, can filter based on alert severity and alert type.
	 * @param $showAllAlerts - True to return all alerts, false to limit to $numAlertsToShow
	 * @param $numAlertsToShow - Limits to most recent alerts, overriden if $showAllAlerts is true
	 */
	function getAlerts($serverName, $host, $port, $protocol, $user, $password,
	$green, $yellow, $red, $info, $warning, $error, $showAllAlerts, $numAlertsToShow)
	{

		settype($numAlertsToShow, 'integer');

		$sql = "alert_type, alert_color, '[' || alert_time || ']' as alert_time, alert_message " .
					" FROM sysadmin:ph_alerts";

		if($showAllAlerts){  // show all alerts
			$sql = "SELECT " . $sql;
		}
		else{  // show most recent numRows
			$sql = "SELECT FIRST $numAlertsToShow " . $sql;
		}

		// Filter based on severity and type
		$where = " WHERE " .
	      "   UPPER(alert_color) IN " .
	      "   ( " .
		( ($red) ? " 'RED', " : "' ', " ) .
		( ($yellow) ? " 'YELLOW', " : "' ', " ) .
		( ($green) ? " 'GREEN' " : "' ' " ) .
	      "   ) " .
	      "   AND " .
	      "   UPPER(alert_type) IN " .
	      "   ( " .
		( ($error) ? " 'ERROR', " : "' ', " ) .
		( ($warning) ? " 'WARNING', " : "' ', " ) .
		( ($info) ? " 'INFO' " : "' ' " ) . ")";
		 
		 
		$sql .= $where . " ORDER BY alert_time DESC";

		$alertInfo = $this->doDatabaseWork($sql, "sysadmin", $serverName, $host, $port, $protocol, $user, $password);

		return $alertInfo;
	}

	/**
	 * Gets online logs.  Only shows most recent $numLinesToShow records.
	 * @param $onlyShowErrors - True to only return error log messages, false to return all messages up to limit
	 * @param $numLinesToShow - Limits the number of log messages
	 */
	function getOnlineLog($serverName, $host, $port, $protocol, $user, $password, $onlyShowErrors, $numLinesToShow)
	{
		settype($numLinesToShow, 'integer');

		$sql = "select first {$numLinesToShow} B.offset, B.line from ( " 
			 . "select * " 
			 . "from sysonlinelog "
			 . "where offset > -10240 "
			 . ") as A, sysonlinelog B "
			 . "where A.offset = B.offset "
			 . "and B.line != '' "
			 . "order by B.offset DESC";

		$log = $this->doDatabaseWork($sql, "sysmaster", $serverName, $host, $port, $protocol, $user, $password);
		
		$i = 0;

		foreach($log as $msg)
		{
			if ( (stripos( $msg['LINE'], "err") || stripos( $msg['LINE'], "assert") ||  // is an error
			stripos( $msg['LINE'], "Exception ") || stripos( $msg['LINE'], "fail")) && !stripos( $msg['LINE'],"success") )
			{

				$result[$i]['LINE'] = $msg['LINE'];
				$result[$i]['ERROR'] = true;
			}
			else //if(!$onlyShowErrors){  // is not an error, but only add it if !$onlyShowErrors
			{
				$result[$i]['LINE'] = $msg['LINE'];
				$result[$i]['ERROR'] = false;
			}

			$i++;
		}

		return $result;
	}

	/**
	 * Gets free space info for specified server
	 * @param $showAllSpaces - True to return all spaces, false to limit to $numSpacesToShow
	 * @param $numSpacesToShow - Limits number of spaces
	 * @return space_name, size, free_size, percent_used
	 */
	function getFreeSpace($server, $host, $port, $protocol, $user, $password, $showAllSpaces, $numSpacesToShow){

		$sql = "SELECT sh_pagesize from sysshmvals";

		$pagesize = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $user, $password);
		$pagesize = $pagesize[0]['SH_PAGESIZE'];

		settype($numSpacesToShow, 'integer');

		$sql = "trim(B.name) as space_name, size, free_size, " .
				"100 - percent_used as percent_free, " . 
				"CASE " .
				" WHEN (bitval(flags,'0x10')>0 AND bitval(flags,'0x2')>0)" .
				"   THEN 'mirrored_blobspace' " .
				" WHEN bitval(flags,'0x10')>0 " .
				"   THEN 'blobspace' " .
				" WHEN bitval(flags,'0x2000')>0 AND bitval(flags,'0x8000')>0" .
				"   THEN 'temp_sbspace' " .
				" WHEN bitval(flags,'0x2000')>0 " .
				"   THEN 'temp_dbspace' " .
				" WHEN (bitval(flags,'0x8000')>0 AND bitval(flags,'0x2')>0)" .
				"   THEN 'mirrored_sbspace' " .
				" WHEN bitval(flags,'0x8000')>0 " .
				"   THEN 'sbspace' " .
				" WHEN bitval(flags,'0x2')>0 " .
				"   THEN 'mirrored_dbspace' " .
				" ELSE " .
				"   'dbspace' " .
				" END  as dbstype " .
				"FROM " . 
				"(SELECT dbsnum, sum(chksize*$pagesize) as size , sum(decode(mdsize,-1,nfree,udfree) * $pagesize) as free_size, " . 
				"TRUNC(100-sum(decode(mdsize,-1,nfree,udfree))*100/ sum(chksize),2) as percent_used FROM syschktab " .
				"GROUP by dbsnum) AS A, sysdbstab B WHERE A.dbsnum = B.dbsnum ORDER BY percent_used DESC";

		if($showAllSpaces)
		{
			$sql = "SELECT " . $sql;
		}
		else
		{
			$sql = "SELECT FIRST $numSpacesToShow " . $sql;
		}

		$info = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $user, $password);

		return $info;
	}


	/**
	 * Gets I/O activity for specfied server
	 * @return io_ops, io_time (in seconds), io_per_sec
	 */
	function getIOActivity($server, $host, $port, $protocol, $username, $password)
	{
		$sql = "select chknum, reads + writes  as io_ops, round((readtime + writetime)/1000000,6) as io_time, " .
				"round((reads + writes) / ((readtime + writetime)/1000000) ,6) as io_per_sec from syschktab " . 
				"union " .
				"select chknum, reads +  writes as io_ops, round((readtime + writetime)/1000000,6) as io_time, " . 
				"round((reads + writes) / ((readtime + writetime)/1000000),6) as io_per_sec from sysmchktab " .
				"order by 4 desc";

		$info = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $username, $password);

		return $info;
	}


	/**
	 * Gets memory info for specified server
	 * @return total_mem, used_mem, os_mem_total, os_mem_free, max_ses_mem, avg_ses_mem
	 */
	function getMemory($server, $host, $port, $protocol, $user, $password)
	{
		$sql = "select decode(cf_effective,0,os_mem_total,cf_effective*1024) as TOTAL_MEM, " .
			"(select sum( seg_blkused)*4096 from sysseglst) as USED_MEM from syscfgtab, " . 
			"sysmachineinfo where cf_name = 'SHMTOTAL'"; 

		$temp0 = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $user, $password);

		$sql = "SELECT  (100 * (os_mem_free / os_mem_total)) as os_percent_free, os_mem_total as os_mem_total,  " .
			"os_mem_free as os_mem_free from sysmaster:sysmachineinfo";
		$temp1 = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $user, $password);

		$sql = "SELECT COUNT(*) as session_count, format_units(MAX(memtotal)) as max_ses_mem, " .
			"format_units(ROUND(AVG(memtotal))) as avg_ses_mem FROM sysscblst a, sysrstcb b, systcblst c " . 
			"WHERE a.address = b.scb AND a.sid != DBINFO('sessionid') AND b.tid = c.tid ";
		$temp2 = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $user, $password);

		$info = array_merge($temp0[0], $temp1[0], $temp2[0]);

		return $info;
	}

	/**
	 * Gets CPU information for specified server
	 * @return dbs_threads_waiting, dbs_load_percentage
	 */
	function getCPUInfo($server, $host, $port, $protocol, $username, $password)
	{
		$sql = "select classname, sum(num_ready) as dbs_threads_waiting , " .
		       	"trunc( 100 * sum(thread_run) / (sum(thread_run) + sum(thread_idle + thread_poll_idle)),2)  || " .
		       	"' %' as dbs_load_percentage " .
				"from sysvplst " .
				"where classname = 'cpu' " .
				"group by 1";

		$info = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $username, $password);

		return $info[0];
	}


	/**
	 * Gets Users info for specfied server
	 * @return username, upf_iscommit, connect_duration, rows_processed
	 */
	function getUsersInfo($server, $host, $port, $protocol, $username, $password)
	{
		$sql = "select case when (S.hostname != '') then SUBSTR(trim(S.username)||'@'||trim(S.hostname),0, 25) else trim(S.username) end as username, " .
				"upf_iscommit, (DBINFO('utc_current') - connected) as connect_duration, " .
				"(upf_iswrite + upf_isrwrite + upf_isdelete + upf_isread ) rows_processed " .
				"from sysscblst S, sysrstcb R " .
				"where  S.sid =  r.sid and S.sid != DBINFO('sessionid')";

		$info = $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $username, $password);

		// format CONNECT_DURATION as day:hour:min:ses
		for($i = 0; $i < count($info); $i++)
		{
			$info[$i]['CONNECT_DURATION'] = $this->idsadmin->timedays($info[$i]['CONNECT_DURATION']);
		}

		return $info;
	}


	/**
	 * Gets tables info for specified server, ordered by $orderBy
	 * @return n_inserts, n_updates, n_deletes, n_scans, n_rows
	 */
	function getTableInfo($server, $host, $port, $protocol, $username, $password, $orderBy, $database="sysmaster", $timeout = 10, $lang="en_US")
	{
		$this->setOATLiteLang($lang);
		
		$sql = "";
		$info = "";
		
		$this->useSameConnection = null; // just so we dont use the same connection as a previous query because of locale .
		
		if (Feature::isAvailable(Feature::PANTHER_UC4, $this->getServerVersion($server, $host, $port, $protocol, $username, $password)))
		{
			$sql = "SELECT first 25 " .
					"lockid, n_inserts, n_updates, n_deletes, n_scans, n_rows, " .
					"extents, pagesize_value, compressed, partnum, dbsname, owner, " .
					"trim(string_to_utf8(tabname, NVL(dbs_collate, 'en_US.819'))) AS tabname " .
					"FROM ( " .
					"SELECT  P.lockid,sum(P.ninserts) as n_inserts, sum(P.nupdates) as n_updates, " .
					       " sum(P.ndeletes) as n_deletes, " .
					        "max( OP.pf_seqscans ) n_scans, sum( nrows ) as n_rows, " .
					        "sum(nextns) as extents, (pagesize / 1024) as pagesize_value, " .
					        "decode ( sum( decode( bitand(p.flags,'0x08000000'),0,0,1) ), 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed " .
					     	"from sysmaster:sysptnhdr P, sysmaster:sysptntab OP " .
					     	"WHERE P.lockid = OP.tablock and P.partnum = OP.partnum " .
					"group by lockid , pagesize) as PT,  sysmaster:systabnames T LEFT OUTER JOIN sysmaster:sysdbslocale L " .
						"ON T.dbsname = L.dbs_dbsname " .
					"WHERE PT.lockid = T.partnum " .
					"ORDER by $orderBy desc";

			$info = $this->doDatabaseWork($sql,$database, $server, $host, $port, $protocol, $username, $password, $timeout, false,"en_US.UTF8");
		}
		else
		{
			$sql = "SELECT first 25 " .
					"lockid, n_inserts, n_updates, n_deletes, n_scans, n_rows, " .
					"extents, pagesize_value, compressed, partnum, dbsname, owner, " .
					"trim(tabname) as tabname " .
					"FROM ( " .
					"SELECT  P.lockid,sum(P.ninserts) as n_inserts, sum(P.nupdates) as n_updates, " .
					       " sum(P.ndeletes) as n_deletes, " .
					        "max( OP.pf_seqscans ) n_scans, sum( nrows ) as n_rows, " .
					        "sum(nextns) as extents, (pagesize / 1024) as pagesize_value, " .
					        "decode ( sum( decode( bitand(p.flags,'0x08000000'),0,0,1) ), 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed " .
					     	"from sysmaster:sysptnhdr P, sysmaster:sysptntab OP " .
					     	"WHERE P.lockid = OP.tablock and P.partnum = OP.partnum " .
					"group by lockid , pagesize) as PT,  sysmaster:systabnames T LEFT OUTER JOIN sysmaster:sysdbslocale L " .
						"ON T.dbsname = L.dbs_dbsname " .
					"WHERE PT.lockid = T.partnum " .
					"ORDER by $orderBy desc";
				
			$info = $this->doDatabaseWork($sql,$database, $server, $host, $port, $protocol, $username, $password, $timeout);
		}
		
		$i = 0;
		foreach ($info as $row)
		{
			$info[$i]['COMPRESSED'] = $this->idsadmin->lang($row['COMPRESSED']);
			$i++;
		}

		return $info;
	}

	function getDatabases ($server, $host, $port, $protocol, $username, $password)
	{
		$sql = "SELECT name from sysdatabases";
		return $this->doDatabaseWork($sql, "sysmaster", $server, $host, $port, $protocol, $username, $password);

	}

	//----------------------------------
	// Database work functions
	//----------------------------------

	/**
	 * Gets connection to specified database
	 */
	function getDBConnection($dbname, $serverName, $host, $port, $protocol, $user, $password, $timeout = 10, $locale = null)
	{
		//$INFORMIXCONTIME=2;
		$INFORMIXCONRETRY=10;
		settype($timeout, 'integer');

		putenv("INFORMIXCONTIME={$timeout}");
		putenv("INFORMIXCONRETRY={$INFORMIXCONRETRY}");

		$dsn .= "informix:host={$host}";
		$dsn .= ";service={$port}";
		$dsn .= ";database={$dbname}";
		$dsn .= ";protocol={$protocol}";
		$dsn .= ";server={$serverName}";
		$db = null;

		if(substr(PHP_OS,0,3) != "WIN")
		{
			$informixdir = $this->idsadmin->get_config("INFORMIXDIR");
			$libsuffix = (strtoupper(substr(PHP_OS,0,3)) == "DAR") ? "dylib" : "so";
			$dsn .= ";TRANSLATIONDLL={$informixdir}/lib/esql/igo4a304.".$libsuffix;
			$dsn .= ";Driver={$informixdir}/lib/cli/libifdmr.".$libsuffix.";";
		}

		if ( $locale != null )
		{
			$client_locale = substr($locale,0,strrpos($locale,".")) . ".UTF8";
			$dsn .= ";CLIENT_LOCALE={$client_locale};DB_LOCALE={$locale};";
		}

		if ( $this->handlingPDOException === FALSE )
		{
			try {
				$db = new PDO ("{$dsn}",$user,utf8_decode($password) );
			}
			catch ( PDOException $e )
			{
				//error_log(var_export ( $db->errorInfo() , true ) );
				//trigger_error($e->getMessage(),E_USER_ERROR);
				$exception = $this->parsePDOException($e->getMessage());
				throw new SoapFault("{$exception['code']}",$exception['message']);
			}
		}
		else
		{
			$db = new PDO ("{$dsn}",$user,$password);
		}
		return $db;
	}

	private function parsePDOException($exceptionString)
	{
		$ifxError = "";
		$ifxErrorString = "";

		$ret = array();

		if ( strstr($exceptionString , "SQLSTATE=") )
		{
			//split the string on : to remove the SQLSTATE.
			$mess = explode(":",$exceptionString);
			//remove part of the error that has [Informix][xxxx][Informix]
			$ifxErrorString = preg_replace('/(\[[a-zA-Z ]* ?\])/', "", $mess[1]);
			//pull out the error number.
			preg_match("/[-0-9]+/",$ifxErrorString,$ifxError);
		}
		else
		{
			$ifxError = "-1";
			$ifxErrorString = $exceptionString;
		}

		$ret['code'] = $ifxError[0];
		$ret['message'] = $ifxErrorString;

		return $ret;
	}

	/**
	 * Runs query on specified database
	 * @return array containing all selected records
	 */
	private function doDatabaseWork($sel, $dbname="sysmaster", $serverName, $host, $port, $protocol, $user, $password,
	$timeout = 10, $exceptions=false, $locale=NULL)
	{
		$ret = array();
		if ( $this->useSameConnection == null )
		$db = $this->getDBConnection($dbname, $serverName, $host, $port, $protocol, $user, $password, $timeout, $locale);
		else
		$db = $this->useSameConnection;

		while (1 == 1)
		{
			$stmt = $db->query($sel); // not required as this is using the PDO->query not the $idsadmin->db->query ,false,$exceptions,$locale);

			$err = $db->errorInfo();
			if ( $err[1] != 0 )
			{
				trigger_error("{$err[1]} - {$err[2]}",E_USER_ERROR);
			}
			
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC) )
			{
				$ret[] = $row;
			}

			$err = $db->errorInfo();

			if ( $err[2] == 0 )
			{
				$stmt->closeCursor();
				break;
			}
			else
			{
				$err = "Error: {$err[2]} - {$err[1]}";
				$stmt->closeCursor();
				trigger_error($err,E_USER_ERROR);
				continue;
			}
		}
		return $ret;
	}

	function getServerVersion($server, $host, $port, $protocol, $username, $password)
	{
		$sql = "SELECT DBINFO('version','full') AS vers, " .
        	"DBINFO('version','major') AS vers_major FROM systables WHERE tabid = 1";

		$result = $this->doDatabaseWork($sql,"sysmaster", $server, $host, $port, $protocol, $username, $password);
		return $result[0]['VERS'];
	}

	/**
	 * Use this function to execute statements on the OAT connections database
	 */
	function doConnectionsDatabaseWork($qry , $ispasswd = false)
	{
		require_once(ROOT_PATH."lib/connections.php");
		require_once(ROOT_PATH."services/idsadmin/clusterdb.php");

		$ret = array();
		$connectionsDb = new clusterdb(false);
		$stmt = $connectionsDb->query($qry);
		if (! $stmt)
		{
			$err = connectionsDberrorInfo();
			return array(-1,"statement error:{$err[1]} {$err[2]}");
		}

		while ($row = $stmt->fetch() )
		{
			$err = $stmt->errorInfo();
			 
			// "isset($err[1])" added into the following condition to avoid strange crash
			if (isset($err[1]) && $err[1] != 0)
			{
				$ret = array(-1,"fetch error: {$err[2]}");
				break;
			}
			if ( $ispasswd == true && isset( $row['password'] ) )
			{
				$row['password'] = connections::decode_password($row['password']);
			}
			$ret[] = $row;
		}

		return $ret;
	}
}
?>

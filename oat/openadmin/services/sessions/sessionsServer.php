<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007, 2011.  All Rights Reserved
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


class sessionsServer {

	var $idsadmin;

	function __construct()
	{
		define ("ROOT_PATH","../../");
		define( 'IDSADMIN',  "1" );
		define( 'DEBUG', false);
		define( 'SQLMAXFETNUM' , 100 );


		include_once("../serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");

		require_once(ROOT_PATH."lib/idsadmin.php");
		$this->idsadmin = new idsadmin(true);
		$this->idsadmin->load_lang("sessions");
	}
	/**
	 * check that session exists.
	 *
	 */
	function checkSession($sessionId)
	{
		$sessionId = intval($sessionId);
		$sel = "select sid from syssessions where sid = {$sessionId} ";
		$res = $this->doDatabaseWork($sel,"sysmaster");
		if ( sizeof($res) == 0 )
		{
			trigger_error("{$this->idsadmin->lang('ErrorNoLongerExists')}",E_USER_WARNING);
		}
	}
	/**
	 * get the list of sessions
	 *
	 * @param rows_per_page = rows per page
	 * @param page = current page number
	 * @param sort_col = sort column for order by clause
	 */
	function getSessionList($rows_per_page = null, $page = 1, $sort_col = null)
	{
		$result = array();
		
		if ( $this->idsadmin->isreadonly() )
		{
			$killable = " , 0 as is_killable ";
		}
		else
		{
			$killable = ", case (trim(c.name))  when 'sqlexec' then 1 else 0 end as is_killable ";
		}
		$sel = " SELECT "
		." a.sid AS sid"
		.",a.username"
		.",a.pid"
		.",trim(a.hostname)||decode(length(trim(a.ttyerr)),0,'',':'||a.ttyerr) AS hostname"
		.",DBINFO('UTC_TO_DATETIME', a.connected) AS connected"
		.",memtotal"
		.",iowaittime::decimal(16,3) AS iowaittime"
		.",cpu_time::decimal(16,3) AS cpu_time"
		."{$killable} "
		." FROM sysscblst a, sysrstcb b, systcblst c "
		." WHERE a.address = b.scb "
		." AND a.sid != DBINFO('sessionid') "
		." AND  b.tid = c.tid ";
		
		if ($sort_col == null)
		{
			// Default sort order - by session id
			$sort_col = "a.sid";
		}

		$result['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sel, $rows_per_page, $page, $sort_col),"sysmaster");
		
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sel), "sysmaster");
		if (count($temp) > 0)
		{
			$result['COUNT'] = $temp[0]['COUNT'];
		}
		
		return $result;

	} // end getSessionList

	function getSessionInfo($sessionId)
	{
		$this->checkSession($sessionId);

		$sel = "SELECT " .
        " sysscblst.sid, " .
        " trim(sysscblst.username) as user, " .
        " decode(length(hostname),0,'localhost',hostname) as hostname, " .
        " sysscblst.uid as userid, " .
        " sysscblst.gid, " .
        " sysscblst.pid, " .
		" sysscblst.progname, " .
        " dbinfo('UTC_TO_DATETIME',connected)::DATETIME MONTH TO SECOND as con, " .
        " format_units(memtotal,'b') as mtotal, " .
        " format_units(memused,'b') as mused," .
        " nfiles ".
        " FROM sysscblst , sysrstcb " .
        " WHERE sysscblst.address = sysrstcb.scb " .
        " AND bitval(sysrstcb.flags,'0x80000')>0 " .
        " AND sysscblst.sid = {$sessionId} ";
		$ret = $this->doDatabaseWork($sel,"sysmaster");
		
		$sel = "SELECT cbl_stmt[1,1000] AS curr_stmt " 
			 . "FROM sysconblock "
			 . "WHERE cbl_sessionid = {$sessionId} ";
		try {
			$ret2 = $this->doDatabaseWork($sel,"sysmaster",true);
			if (count($ret2) > 0)
			{
				$ret[0]['CURR_STMT'] = $ret2[0]['CURR_STMT'];
			}
		} 
		catch (PDOException $e) 
		{
			 if($e->getCode() == -272)
			 {
			 	// By default only informix has select permissions on the sysconblock table.
			 	// So if the user is logged into OAT with a non-informix userid, they are likely
			 	// to get a -272 'no select permission' error on this query.
			 	// If so, we want to catch this error and just display a message to the user
			 	// that they don't have permssions to see the current statement.
			 	$username = $this->idsadmin->phpsession->instance->get_username();
			 	error_log("Warning in Session Explorer: The user {$username} does not have select permission on the sysmaster:sysconblock table.");
			 	$ret[0]['CURR_STMT'] = $this->idsadmin->lang('NoSelectPermission_sysconblock', array($username)); 
			  } else {
			 	// For any other errors, throw it back to Flex.
			 	$encode_sql = htmlentities($sel);
			 	trigger_error("{$this->idsadmin->lang('DatabaseQueryFailed')} {$this->idsadmin->lang('ErrorF')} {$e->getMessage()} {$this->idsadmin->lang('QueryF')} {$encode_sql}");
			 }
		}
		
		return $ret;

	} // end getSessionInfo

	function getSessionProfile($sessionId)
	{
		$this->checkSession($sessionId);
		$sel = "SELECT " .
        " sysscblst.sid, " .
        " nreads, " .
        " nwrites, " .
        " nfiles, " .
        " upf_rqlock, " .
        " upf_wtlock, " .
        " upf_deadlk, " .
        " upf_lktouts, " .
        " upf_lgrecs, " .
        " upf_isread, " .
        " upf_iswrite, " .
        " upf_isrwrite, " .
        " upf_isdelete, " .
        " upf_iscommit, " .
        " upf_isrollback, " .
        " upf_longtxs, " .
        " upf_bufreads, " .
        " upf_bufwrites, " .
        " format_units(upf_logspuse,'b') as logspuse, " .
        " format_units(upf_logspmax,'b') as logspmax, " .
        " upf_seqscans, " .
        " upf_totsorts, " .
        " upf_dsksorts, " .
        " upf_totsorts - upf_dsksorts as memsorts, " .
        " format_units(upf_srtspmax,'b') as srtspmax, " .
        " nlocks " .
        " FROM sysscblst , sysrstcb " .
        " WHERE sysscblst.address = sysrstcb.scb " .
        " AND bitval(sysrstcb.flags,'0x80000')>0 " .
        " AND sysscblst.sid = {$sessionId} ";

		return $this->doDatabaseWork($sel,"sysmaster");

	} // end getSessionProfile
	
	/**
	 * Get network information for a session
	 * 
	 * @param sessionId = session id
	 * @param rows_per_page = rows per page
	 * @param page = current page number
	 * @param sort_col = sort column for order by clause
	 */
	function getSessionNetwork($sessionId, $rows_per_page = null, $page = 1, $sort_col = null)
	{
		$this->checkSession($sessionId);
		
		$result = array();
		$sel = " SELECT "
		." net_client_name  thread_name "
		.",net_read_bytes as RECEIVED_DATA "
		.",net_write_bytes as SEND_DATA "
		.",decode ( net_read_cnt , 0 , 0 , net_read_bytes/net_read_cnt ) AVERAGE_RECV "
		.",decode ( net_write_cnt , 0 , 0 , net_write_bytes/net_write_cnt ) AVERAGE_SEND "
		.",CURRENT - dbinfo('UTC_TO_DATETIME', net_open_time)  CONNECT_DURATION "
		.",decode ( net_open_time , 0 , null, dbinfo('UTC_TO_DATETIME', net_open_time)) SESSION_START_TIME "
		.",decode ( net_last_read , 0 , null, dbinfo('UTC_TO_DATETIME', net_last_read)) as LAST_READ "
		.",decode ( net_last_write , 0 , null, dbinfo('UTC_TO_DATETIME', net_last_write)) as LAST_WRITE "
		." FROM sysnetworkio "
		." where sid = {$sessionId} ";

		$result['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sel, $rows_per_page, $page, $sort_col),"sysmaster");
		
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sel), "sysmaster");
		if (count($temp) > 0)
		{
			$result['COUNT'] = $temp[0]['COUNT'];
		}
		
		return $result;
	} // end getSessionNetwork
	
	/**
	 * Get the sql for a session
	 *
	 * @param sessionId = session id
	 * @param rows_per_page = rows per page
	 * @param page = current page number
	 * @param sort_col = sort column for order by clause
	 */
	function getSessionSQL($sessionId, $rows_per_page , $page, $sort_col)
	{
		$this->checkSession($sessionId);
		
		$result = array();
		$result['DATA'] = array();
		$result['COUNT'] = 0;
		
		$sel = "select  "
		." sql_id as id "
		." ,sql_stmtname as type "
		." ,trim(sql_statement[1,1000]) as statement "
 		." from syssqltrace a"
		." where a.sql_sid != dbinfo('sessionid') "
		." and a.sql_sid  = {$sessionId} ";
		
		if ($sort_col == null)
		{
			// default sort order
			$sort_col = "a.sql_sid";
		}

		$exception = false;
		try
		{
			$result['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sel, $rows_per_page, $page, $sort_col),"sysmaster",true);
		}
		catch (PDOException $e) 
		{
			$exception = true;
			if($e->getCode() == -272)
			{
				// By default only informix has select permissions on the syssqltrace table.
				// So if the user is logged into OAT with a non-informix userid, they are likely
				// to get a -272 'no select permission' error on this query.
				// If so, we want to catch this error and just display a message to the user
				// that they don't have permssions to see the session's SQL.
				$username = $this->idsadmin->phpsession->instance->get_username();
				error_log("Warning in Session Explorer: The user {$username} does not have select permission on the sysmaster:syssqltrace table.");
				$result['NO_PERMISSION'] = $this->idsadmin->lang('NoSelectPermission_syssqltrace', array($username));
			 } else {
			 	// For any other errors, throw it back to Flex.
			 	$encode_sql = htmlentities($sel);
			 	trigger_error("{$this->idsadmin->lang('DatabaseQueryFailed')} {$this->idsadmin->lang('ErrorF')} {$e->getMessage()} {$this->idsadmin->lang('QueryF')} {$encode_sql}");
			 }
		}
		
		if (!$exception)
		{
			$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sel), "sysmaster");
			if (count($temp) > 0)
			{
				$result['COUNT'] = $temp[0]['COUNT'];
			}
		}
		
		return $result;
	} // end getSessionSQL

	/**
	 * Get the memory for a session
	 *
	 * @param sessionId = session id
	 * @param rows_per_page = rows per page
	 * @param page = current page number
	 * @param sort_col = sort column for order by clause
	 */
	function getSessionMem($sessionId, $rows_per_page = null, $page = 1, $sort_col = null)
	{
		$this->checkSession($sessionId);
		
		$result = array();
		$sel = "SELECT "
		." po_name as name "
		." ,po_usedamt as used "
		." ,po_freeamt as free "
		." FROM syspoollst "
		." WHERE po_name matches '*_{$sessionId}_*' OR po_name = '{$sessionId}' ";
		
		$result['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sel, $rows_per_page, $page, $sort_col),"sysmaster");
		
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sel), "sysmaster");
		if (count($temp) > 0)
		{
			$result['COUNT'] = $temp[0]['COUNT'];
		}
		
		return $result;
	} // end getSessionMem

	function getSessionSQLInfo($id)
	{
		$this->checkSession($sessionId);
		$sel = "select  "
		." * "
		." from syssqltrace a"
		." where a.sql_sid != dbinfo('sessionid') "
		." and a.sql_id  = {$id} ";

		return $this->doDatabaseWork($sel,"sysmaster");

	} // end getSessionSQLInfo

	/**
	 * Get the threads for a session
	 *
	 * @param id = session id
	 * @param rows_per_page = rows per page
	 * @param page = current page number
	 * @param sort_col = sort column for order by clause
	 */
	function getSessionThreads($id, $rows_per_page = null, $page = 1, $sort_col = null)
	{
		$this->checkSession($id);
		
		$result = array();
		$sel = " select "
		." name                NAME "
		.",sysrstcb.tid        THREAD_ID "
		.",wait_reason         WAIT_REASON "
		.",num_sched           NUM_SCHEDULED "
		.",cpu_time            TOTAL_TIME "
		.",cpu_time/num_sched  TIME_SLICE "
		.",vpid                VPID "
		.",priority            THREAD_PRIORITY "
		." from sysrstcb, systcblst "
		." where "
		." systcblst.tid = sysrstcb.tid "
		." AND sysrstcb.sid = {$id} ";

		$result['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sel, $rows_per_page, $page, $sort_col),"sysmaster");
		
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sel), "sysmaster");
		if (count($temp) > 0)
		{
			$result['COUNT'] = $temp[0]['COUNT'];
		}
		
		return $result;
	} // end getSessionThreads

	/**
	 * Get the environment for a session
	 *
	 * @param id = session id
	 * @param rows_per_page = rows per page
	 * @param page = current page number
	 * @param sort_col = sort column for order by clause
	 */
	function getSessionEnv($id, $rows_per_page = null, $page = 1, $sort_col = null)
	{
		$this->checkSession($id);
		
		$result = array();
		$sel = " SELECT  "
		." 'CLIENT' as location"
		." ,envses_name AS name "
		." ,envses_value AS value "
		." FROM sysenvses a"
		." WHERE a.envses_sid != dbinfo('sessionid') "
		." AND a.envses_sid  = {$id} "
		." UNION "
		." SELECT  "
		." 'SERVER' AS location "
		." ,env_name AS name "
		." ,env_value AS value "
		." FROM sysenv a "
		." WHERE env_name NOT IN ( SELECT envses_name FROM sysenvses WHERE envses_sid  = {$id}  ) ";
		
		if ($sort_col == null)
		{
			// default sort order
			$sort_col = "name";
		}

		$result['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sel, $rows_per_page, $page, $sort_col),"sysmaster");
		
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sel), "sysmaster");
		if (count($temp) > 0)
		{
			$result['COUNT'] = $temp[0]['COUNT'];
		}
		
		return $result;
	} // end getSessionEnv

	function getSessionThreadInfo($id)
	{
		$this->checkSession($sessionId);

		$sel = "select  "
		." * "
		." from syssqltrace a"
		." where a.sql_sid != dbinfo('sessionid') "
		." and a.sql_id  = {$id} ";

		return $this->doDatabaseWork($sel,"sysmaster");

	} // end getSessionThreadInfo
	
	/**
	 * Get the locks for the session
	 * 
	 * @param sessionId = session id
	 * @param rows_per_page = rows per page
	 * @param page = current page number
	 * @param sort_col = sort column for order by clause
	 */
	function getSessionLocks($sessionId, $rows_per_page = null, $page = 1, $sort_col = null)
	{
		$this->checkSession($sessionId);

		$result = array();
		$sessionId = intval($sessionId);
		$sel = "select  /*+ORDERED*/ ".
        " trim(dbsname)||':'||trim(b.tabname) AS TABLE_NAME,
		e.txt[1,6]  LOCK_TYPE,
		(CURRENT - DBINFO('UTC_TO_DATETIME', grtime))::INTERVAL HOUR(5) TO SECOND LOCK_HELD,

		decode(a.wtlist,0,'NONE',f.sid||' ') OTHERS_WAITING_FOR_LOCK,
		hex(rowidr) ROWID,
		keynum INDEX_NUMBER,
		hex(rowidn) KEY_ITEM_LOCKED
		from  sysrstcb d
		, systxptab c
		, syslcktab a
		, systabnames b
		, flags_text e
		, outer sysrstcb f
		where a.partnum = b.partnum
		and a.owner = c.address
		and c.owner = d.address
		and a.wtlist = f.address
		and e.tabname = 'syslcktab'
		and e.flags = a.type
		and d.sid = {$sessionId} ";
			
		$result['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sel, $rows_per_page, $page, $sort_col),"sysmaster");
		
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sel), "sysmaster");
		if (count($temp) > 0)
		{
			$result['COUNT'] = $temp[0]['COUNT'];
		}
		
		return $result;
	}
	
	/**
	 * killSession - tries to kill a session
	 * @param int - sessionId
	 */
	function killSession($sessionId=0)
	{
		if ( intval($sessionId) > 0 )
		{
			$qry = "select task ('onmode' , 'z' "
			. ",'{$sessionId}') as res from systables where tabid=1";
			return $this->doDatabaseWork($qry,"sysadmin");
		}
		trigger_error("{$this->idsadmin->lang('ErrorInvalidSessionID')}",E_USER_ERROR);
	}

	/**
	 * do the database work.
	 *
	 */
	function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false)
	{
		$ret = array();

		$db = $this->idsadmin->get_database($dbname);

		while (1 == 1)
		{
			$stmt = $db->query($sel,false,$exceptions);

			while ($row = $stmt->fetch() )
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
				$err = "{$this->idsadmin->lang('ErrorF')} {$err[2]} - {$err[1]}";
				$stmt->closeCursor();
				trigger_error($err,E_USER_ERROR);
				continue;
			}
		}

		return $ret;
	}
}

?>

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

/* Services for Memory Manager feature */

class memoryServer {

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
	}
	
	/**
	 * Get information about server memory usage and configuration
	 * 
	 * @param lmmSupported - true/false, is low memory manager supported on this server version?
	 */
	public function getServerMemoryInfo($lmmSupported) 
	{
		$data = array();

		// If Low Memory Manager is supported, use syslowmemorymgr
		// table to get total/used memory info as well as info
		// about the low memory manager.
		$lmm_qry = "SELECT lmm_memory_limit as total_mem, "
				 . "lmm_memory_used as used_mem, "
				 . "lmm_start_threshold, "
				 . "lmm_stop_threshold, "
				 . "lmm_idle_time, "
				 . "lmm_total_killed, "
				 . "CASE WHEN lmm_kill_last_time = 0 AND lmm_killall_last_time = 0 "
				 . "     THEN null "
				 . "     WHEN lmm_kill_last_time >  lmm_killall_last_time "
				 . "     THEN DBINFO('utc_to_datetime',lmm_kill_last_time) "
				 . "     ELSE DBINFO('utc_to_datetime',lmm_killall_last_time) END as lmm_kill_last_time, "
				 . "CASE WHEN lmm_reduce_last_time = 0 "
				 . "     THEN null "
				 . "     ELSE DBINFO('utc_to_datetime',lmm_reduce_last_time) END as lmm_reduce_last_time,"
				 . "CASE WHEN lmm_restore_last_time = 0 "
				 . "     THEN null "
				 . "     ELSE DBINFO('utc_to_datetime',lmm_restore_last_time) END as lmm_restore_last_time,"
				 . "CASE WHEN (lmm_thread_id is null) THEN 0 ELSE 1 END as lmm_enabled "
				 . "FROM syslowmemorymgr";
		
		// For older server versions or for servers that have never turned
		// Low Memory Manager on, we'll need to get total/used memory other 
		// system catalog tables.
		$nolmm_qry = "select decode(cf_effective,0,os_mem_total,cf_effective*1024) TOTAL_MEM, "
				 . "(select sum( seg_blkused)*4096 from sysseglst) USED_MEM " 
				 . "from syscfgtab, sysmachineinfo "
				 . "where cf_name = 'SHMTOTAL'";	 
				 
		if ($lmmSupported)
		{
			// If LMM supported, run lmm query
			$res = $this->doDatabaseWork($lmm_qry,"sysmaster");
			if (count($res) > 0)
			{
				$data = $res[0];
			}
		} 
		
		if (!$lmmSupported || count($data) == 0)
		{
			// If LMM not supported, or the lmm query returned no rows because
			// it's never been turned on, run the other query.
			$res = $this->doDatabaseWork($nolmm_qry,"sysmaster");
			if (count($res) > 0)
			{
				$data = $res[0];
			}
		}
		
		// Get OS memory info
		$qry = "select os_mem_free, os_mem_total, (os_mem_total - os_mem_free) as os_mem_used "
			 . "from sysmachineinfo";
		$res = $this->doDatabaseWork($qry,"sysmaster");
		if (count($res) > 0)
		{
			$data = array_merge($data, $res[0]);
		}

		// Get server memory statistics for sessions
		$qry = "SELECT COUNT(*) as session_count, "
			 . "MAX(memtotal) as max_ses_mem, "
			 . "ROUND(AVG(memtotal)) as avg_ses_mem "
			 . "FROM sysscblst a, sysrstcb b, systcblst c "
			 . "WHERE a.address = b.scb "
			 . "AND a.sid != DBINFO('sessionid') "
			 . "AND  b.tid = c.tid";
		$res = $this->doDatabaseWork($qry,"sysmaster");
		if (count($res) > 0)
		{
			$data = array_merge($data, $res[0]);
		}
				
		return $data;
	}
	
	/**
	 * Run a Low Memory Manager command (e.g. enable or disable LMM)
	 * 
	 * @param $sql - command to run (can be multiple command separted by a semi-colon)
	 */
	public function runLMMCommand ($sql)
	{
		$return_message = "";
		$return_code = 0;
		
		// Split the sql into seperate statements
		$stmts = preg_split ("/;\n/",$sql);

		// Run each statement
		foreach ( $stmts as $stmt )
		{
			$stmt = trim($stmt);
			if ( $stmt == "" )
			{
				continue;
			}

			// Run the SQL Admin API statement
			$result = $this->executeSQLAdminTask($stmt);
			
			// Append the return message for each statement
			if ($return_message != "")
			{
				$return_message .= "\n\n";
			}
			$return_message .= $result['RESULT_MESSAGE'];
			
			if (intval($result['RETURN_CODE']) < 0)
			{
				// If any statement failed, do not try to run any additional commands
				$return_code = $result['RETURN_CODE'];
				break;
			}
		}
		
		// Re-query for server memory/LMM data
		$data = $this->getServerMemoryInfo(true);
		
		$data['RESULT_MESSAGE'] = $return_message;
		$data['RETURN_CODE'] = $return_code;
		if (isset($result['SQL']))
		{
			$data['SQL'] = $result['SQL'];
		}
		
		return $data;
	}
	
	/**
	 * Execute SQL Admin API Command given in $sql
	 *
	 * Return values:
	 * The result of the command will be stored in the $task array as follows:
	 * 		$task['RESULT_MESSAGE'] --> success or failure message
	 *      $task['RETURN_CODE'] --> return code of the command
	 */
	private function executeSQLAdminTask($sql)
	{
		$res = array();
		$res['SQL'] = $sql;
		$db = $this->idsadmin->get_database("sysadmin");
		$stmt = $db->query($sql);
        
		// Check for success or errors
		$err = $db->errorInfo();
		if (isset($err[1]) && $err[1] != 0)
		{
			$res['RETURN_CODE']  = -1;
			$res['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
			return $res;
		}
        
		// Retreive id from command_history table 
		$row = $stmt->fetch();
		$cmd_num = $row[''];
        
		// Retrieve cmd_ret_status and cmd_ret_msg for SQL Admin API command
		$qry = "select cmd_ret_status, cmd_ret_msg from command_history "
			 . "where cmd_number=" . abs($cmd_num);
		$stmt = $db->query($qry);
		$err = $db->errorInfo();
		if (isset($err[1]) && $err[1] != 0)
		{
			$res['RETURN_CODE']  = -1;
			$res['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
			return $task;
		}
        
		// Retreive cmd_ret_status and cmd_ret_msg 
		$res['RESULT_MESSAGE'] = "Could not determine result. $cmd_num not found in command_history table" ;	
		while($row = $stmt->fetch(PDO::FETCH_ASSOC))
		{
			$res['RETURN_CODE'] = $row['CMD_RET_STATUS'];
			$res['RESULT_MESSAGE'] = $row['CMD_RET_MSG'];
		}
		
		return $res;
	}
	
	/**
	 * do the database work.
	 */
	private function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false)
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
				$err = "Error: {$err[2]} - {$err[1]}";
				$stmt->closeCursor();
				trigger_error($err,E_USER_ERROR);
				continue;
			}
		}
		return $ret;
	}
}

?>

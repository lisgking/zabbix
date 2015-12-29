<?php

/*
 * *************************************************************************
 *  (c) Copyright IBM Corporation, 2010, 2012.  All Rights Reserved
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
 * *************************************************************************
 */

/* Services for privileges and non-OS users (PRIVILEGES) feature */

class privilegesServer {

	var $idsadmin;

	function __construct() {
		define("ROOT_PATH", "../../");
		define('IDSADMIN', "1");
		define('DEBUG', false);
		define('SQLMAXFETNUM', 100);

		include_once("../serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");

		require_once(ROOT_PATH . "lib/idsadmin.php");
		$this->idsadmin = new idsadmin(true);
		$this->idsadmin->in = array("act" => "privileges");
	}

	/**
	 * Look for all mapped users in the current server
	 *
	 * @param $rows_per_page - null or -1 indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause
	 * @param $search_pattern - user name to search by
	 * @return array of mapped users
	 */
	function getMappedUsers($rows_per_page = null, $page =1, $sort_col = null, $search_pattern = null) {

		$res = array();
		/**
		 * A user can have one or more GIDs. If there's more than one GID, the first GID is stored in syssurrogates table and the remaining GIDs are stored in
		 * syssurrogategroups. The first query gets the GIDs alone.
		 */
		$qry = "SELECT surrogate_id, gid, groupname, groupseq "
			. "FROM syssurrogategroups "
			. "ORDER BY surrogate_id";

		$res['GIDS'] = $res = $this->doDatabaseWork($qry, "sysuser");

		/**
		 * Get all the mapped users
		 */
		$qry =
//			"SELECT map.surrogate_id as surrogate_id, map.username as username, sur.os_username as osuser, sur.uid as uid, sur.homedir as homedir, sur.userauth as privilege, sur.gid as gid "
//			. "FROM sysusermap map, syssurrogates sur "
//			. "WHERE map.surrogate_id == sur.surrogate_id "
			" SELECT A.username, A.auth, A.islocked, B.surrogate_id, B.osuser, B.uid, B.homedir, B.privilege, B.gid FROM ( "
			. "SELECT m.username, DECODE (i.hashed_password, NULL, 'OS', 'DATABASE') AS auth, "
			. "MOD (i.flags, 2)  AS islocked "
			. "FROM sysusermap m LEFT OUTER JOIN sysintauthusers i ON m.username = i.username "
			. ") A INNER JOIN ( "
			. "SELECT map.surrogate_id AS surrogate_id, map.username AS username, "
			. "sur.os_username AS osuser, sur.uid AS uid, sur.homedir AS homedir, "
			. "sur.userauth AS privilege, sur.gid AS gid "
			. "FROM sysusermap map, syssurrogates sur "
			. "WHERE map.surrogate_id == sur.surrogate_id "
			. ") B ON A.username == B.username "
			. (($search_pattern != null) ? "AND (A.username like '%{$search_pattern}%' OR B.osuser like '%{$search_pattern}%') " : "" );
		if ($sort_col == null) {
			// default sort order
			$sort_col = "surrogate_id";
		}
		$res['MAPPED_USERS'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col), "sysuser");

		// Get count of all mapped users
		$res['MAPPED_USERS_COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), "sysuser");
		foreach ($temp as $row) {
			$res['MAPPED_USERS_COUNT'] = $row['COUNT'];
		}

		return $res;
	}

	/**
	 * execute sql statements against db users
	 */
	function runUserActions($sql, $actionType) {
		$sql = trim($sql);
		$result = array();
		$result['CODE'] = 0;
		$result['MESSAGE'] = "";
		$result['ACTIONTYPE'] = $actionType;
		try {
			$this->doDatabaseWork($sql, 'sysmaster', true);
		} catch (PDOException $e) {
			$result['CODE'] = $e->getCode();
			$result['MESSAGE'] = $e->getMessage();
		}
		return $result;
	}



	function setUserMapping($mapping) {
		require_once ROOT_PATH . "lib/onconfig_param.php";

		$result = array();
		$result['CODE'] = 0;
		$result['MESSAGE'] = "";

		$dbadmin = $this->idsadmin->get_database("sysadmin");
		$status_msg = "";
		$sql = "execute function task('onmode', 'wf', 'USERMAPPING={$mapping}')";
		$stmt = $dbadmin->query($sql);
		$res = $stmt->fetch(PDO::FETCH_ASSOC);
		$status_msg .= $res[''];
		$stmt->closeCursor();

		return $status_msg;
	}

	function getUserMapping() {
		$sql = "SELECT cf_effective from sysconfig where cf_name = 'USERMAPPING'";
		$result = array();
		$result['CODE'] = 0;
		$result['MESSAGE'] = "";

		try {
			$res = $this->doDatabaseWork($sql, 'sysmaster', true);
			$result['USERMAPPING'] = trim($res[0]['CF_EFFECTIVE']);
		} catch (PDOException $e) {
			$result['CODE'] = $e->getCode();
			$result['MESSAGE'] = $e->getMessage();
		}
		return $result;
	}
	
	/**
	 * Get admin users, i.e. those users that have been granted privileges to the 
	 * SQL Admin API commands.
	 * 
	 * @param $rows_per_page - null or -1 indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause
	 * @param $search_pattern - user name to search by
	 * @return array of admin users
	 */
	function getAdminUsers ($rows_per_page = null, $page =1, $sort_col = null, $search_pattern = null) 
	{
		$res = array();
		
		if ($sort_col == null)
		{
			$sort_col = "name";
		}
		
		$qry = <<<EOF
select name, perms, lastupdated,
(bitand(perms,'0x10FFFFFF') = '0x10FFFFFF') as perm_admin,
(bitand(perms,'0x00FFFFFF') = '0x00FFFFFF') as perm_operator,
(bitand(perms,'0x000001') > 0) as perm_misc,
(bitand(perms,'0x000002') > 0) as perm_bar,
(bitand(perms,'0x000004') > 0) as perm_onstat,
(bitand(perms,'0x000010') > 0) as perm_replication,
(bitand(perms,'0x000020') > 0) as perm_ha,
(bitand(perms,'0x000100') > 0) as perm_storage,
(bitand(perms,'0x000200') > 0) as perm_sql,
(bitand(perms,'0x000400') > 0) as perm_sqltrace,
(bitand(perms,'0x000800') > 0) as perm_file,
(bitand(perms,'0x001000') > 0) as perm_warehouse,
(bitand(perms,'0x01000000') > 0) as perm_readonly,
(bitand(perms,'0x10000000') > 0) as perm_grant
from sysadmin:ph_allow
EOF;

		if ($search_pattern != null)
		{
			$qry .= " WHERE name like '%{$search_pattern}%'";
		} 
		
		$res['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col), "sysmaster");

		$res['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), "sysmaster");
		foreach ($temp as $row) 
		{
			$res['COUNT'] = $row['COUNT'];
		}

		return $res;
	}
	
	/**
	 * Execute privilege action via the SQL Admin API.
	 * 
	 * @param $sql - set of SQL Admin API statments.  
	 *    Separate multiple statements by a semi-colon.
	 * @param $action_type - string that can be used to 
	 *    identify the action when it is returned to Flex.
	 * @param $usser_name - user name, used by the Flex
	 *    result handler.
	 **/
	function executePrivilegesAction ($sql, $action_type, $user_name)
	{
		$res = array();
		$res['ACTION_TYPE'] = $action_type;
		$res['USER'] = $user_name;
		$res['RESULT_MESSAGE'] = "";
		
		// Run each sql statement
		$sql_stmts = preg_split("/;/", $sql);
		foreach ($sql_stmts as $stmt)
		{
			if (strlen(trim($stmt)) == 0)
			{
				continue;
			}
			$stmt_res = $this->executeSQLAdminTask($stmt);
			$res['RETURN_CODE'] = $stmt_res['RETURN_CODE'];
			$res['RESULT_MESSAGE'] .= $stmt_res['RESULT_MESSAGE'] . "\n";
			if ($stmt_res['RETURN_CODE'] != 0)
			{
				// If one statement caused an error, 
				// stop now and result the results.
				return $res; 
			}
		}
		
		return $res;
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
		$db = $this->idsadmin->get_database("sysadmin");
		$stmt = $db->query($sql);
        
		// Check for success or errors
		$err = $db->errorInfo();
		if (isset($err[1]) && $err[1] != 0)
		{
			$res['RETURN_CODE']  = -1;
			$res['RESULT_MESSAGE'] = "{$this->idsadmin->lang("ErrorF")} {$err[2]} - {$err[1]}";
			return $res;
		}
        
		// Retreive id from command_history table 
		$row = $stmt->fetch();
		$cmd_num = $row[''];

		// Again check for errors after the fetch.
		$err = $db->errorInfo();
		if (isset($err[1]) && $err[1] != 0)
		{
			$res['RETURN_CODE']  = -1;
			$res['RESULT_MESSAGE'] = "{$this->idsadmin->lang("ErrorF")} {$err[2]} - {$err[1]}";
			return $res;
		}
		
		// If we ran the command in a grid environment, the return code is wrapped in single quotes
		// so remove those now.
		$cmd_num = str_replace("'","",$cmd_num);
        
		// Retrieve cmd_ret_status and cmd_ret_msg for SQL Admin API command
		$qry = "select cmd_ret_status, cmd_ret_msg from command_history "
			 . "where cmd_number=" . abs($cmd_num);
		$stmt = $db->query($qry);
		$err = $db->errorInfo();
		if (isset($err[1]) && $err[1] != 0)
		{
			$res['RETURN_CODE']  = -1;
			$res['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
			return $res;
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
	 *
	 */
	function doDatabaseWork($sel, $dbname="sysmaster", $exceptions=false) { //,$dirty=true)
		$stime = microtime(true);
		$ret = array();

		$db = $this->idsadmin->get_database($dbname);

		while (1 == 1) {
			$stmt = $db->query($sel, false, $exceptions);
			while ($row = $stmt->fetch()) {
				$ret[] = $row;
			}

			$err = $db->errorInfo();

			if ($err[2] == 0) {
				$stmt->closeCursor();
				break;
			} else {
				$err = "Error: {$err[2]} - {$err[1]}";
				$stmt->closeCursor();
				trigger_error($err, E_USER_ERROR);
				continue;
			}
		}
		//$etime = microtime(true);
		//error_log ("TIME: ".($etime - $stime));
		//error_log($dbname." ".$sel);
		return $ret;
	}

}

?>

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

class historyGraphServer 
{
	public $idsadmin = null; 
	public $conndb = null; 	/* PDO handle to the sqlite connections database */
	
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
		
		$this->idsadmin->load_lang("performance");
	}
		
	/**
	 * Get the list of available servers from the current OAT group.
	 **/
	public function getAvailableServers ()
	{
		$grp_num = $this->idsadmin->phpsession->get_group();
		if ($grp_num == "")
		{
			// $grp_num is empty if user is not logged into an OAT group.
			// If the user is not logged into a group, there are no
			// other available servers.
			return array();
		}
		
		// $current_conn_num is empty if the user is not using a saved connection from the connections.db
		$current_conn_num = $this->idsadmin->phpsession->instance->get_conn_num();
		
		$qry = "select conn_num, server, host from connections "
			 . "where group_num = {$grp_num} "
			 . (($current_conn_num == "")? "":"and conn_num != {$current_conn_num}")
			 . " order by server";
		$result = $this->doConnectionsDatabaseWork($qry);
		return $result;
	}
	
	/**
	 * Get graph data
	 * 
	 * @param $servers - colon(:)-separated list of conn_num for the servers to graph
	 * @param $field_names - colon(:)-separated list of profile fields to graph
	 */
	public function getGraphData($servers, $field_names)
	{
		$servers_arr = explode(":",$servers);
		
		$data = array();
		$data['GRAPH_DATA'] = array();
		$data['ERRORS'] = array();
		$data['WARNINGS'] = array();
		foreach($servers_arr as $server_conn_num)
		{
			$res = $this->getGraphDataForServer($server_conn_num, $field_names);
				
			// $res will either contain the data for the server or a warning or error message
			if (isset($res['ERROR']))
			{
				// Handle warning
				$error = array();
				$error['CONN_NUM'] = $server_conn_num;
				$error['ERROR_MESSAGE'] = $res['ERROR']['ERROR_MESSAGE'];
				$error['ERROR_DETAIL'] = $res['ERROR']['ERROR_DETAIL'];
				$data['ERRORS'][] = $error;
			}
			else if (isset($res['WARNING']))
			{
				// Handle warning
				$warning = array();
				$warning['CONN_NUM'] = $server_conn_num;
				$warning['MESSAGE'] = $res['WARNING'];
				$data['WARNINGS'][] = $warning;
			} else {
				// Or merge the new data into GRAPH_DATA array
				$data['GRAPH_DATA'] = array_merge($data['GRAPH_DATA'],$res['DATA']);
			}
		}
		
		// After retrieving data from each of the selected servers, sort the data.
		// We want the data sorted by datetime across all servers.
		
		// We have an array of rows, but array_multisort() requires an array of columns, 
		// at least for the columns you want to sort by.  So we use the below code to 
		// obtain the datetime values as a single array, then perform the sorting. 
		$dt = array();
		foreach ($data['GRAPH_DATA'] as $key => $row) 
		{
			$dt[$key]  = $row['DT'];
		}

		// Sort the data by the DT (datetime) column.
		array_multisort($dt, SORT_ASC, $data['GRAPH_DATA']);
		
		return $data;
	}

	/**
	 * Get graph data
	 * 
	 * @param $conn_num for the server to query
	 * @param $field_names - colon(:)-separated list of profile fields to graph
	 **/
	private function getGraphDataForServer($conn_num, $field_names)
	{
		if (!empty ($field_names))
		{
			$names = explode(":",$field_names);
			$mon_prof_names = array();
			$mon_mem_names = array();
			$mon_vps_names = array();
			
			// Process the fields to query for.  We first need to figure out
			// which tables each field comes from: mon_prof,  mon_vps, or mon_memory_system?
			foreach ( $names as $k => $v )
			{
				switch ($v)
				{
					case 'allocated_mem':
					case 'used_mem':
					case 'free_mem':
					case 'allocated_mem_virt':
					case 'used_mem_virt':
					case 'free_mem_virt':
						$mon_mem_names[$k] = $v;
						break;
					case 'all_vps_total_time':
					case 'all_vps_sys_time':
					case 'all_vps_user_time':
					case 'cpu_vps_total_time':
					case 'cpu_vps_sys_time':
					case 'cpu_vps_user_time':
					case 'aio_vps_total_time':
					case 'aio_vps_sys_time':
					case 'aio_vps_user_time':
					case 'adm_vps_total_time':
					case 'adm_vps_sys_time':
					case 'adm_vps_user_time':
					case 'msc_vps_total_time':
					case 'msc_vps_sys_time':
					case 'msc_vps_user_time':
					case 'num_ready_threads':
						$mon_vps_names[$k] = $v;
						break;
					default:
						// Add single quotes here to simply our work later for the mon_prof query.
						$mon_prof_names[$k] = "'{$v}'";
						break;
				}
			}
		}
		
		$qry = "";

		// Build query for mon_prof fields
		if (count($mon_prof_names) > 0 )
		{
			$names_str = implode(",",$mon_prof_names);
			$mon_prof_name_clause = " AND A.name in ($names_str) ";
			
			$qry .= " SELECT "
			." trim(A.name) as name "
			." ,A1.run_time::DATETIME YEAR TO SECOND as DT "
			." ,dbinfo('utc_to_datetime',A1.run_ztime) as ztime"
			." ,sum(A.value) as value "
			." FROM mon_profile A, ph_task C, "
			." ph_run A1 "
			." WHERE "
			." C.tk_name='mon_profile' "
			." {$mon_prof_name_clause} "
			." AND A1.run_task_id = C.tk_id "
			." AND A1.run_task_seq = A.id "
			." GROUP BY 1,2,3 " ;
		}
	
		// Build query for mon_memory_system fields
		if (count($mon_mem_names) > 0 )
		{
			// In order to keep in the same format as the mon_prof data, 
			// we need a different query for each field from the mon_memory_system table.
			// And then we'll union all queries together.
			foreach ($mon_mem_names as $key => $field_name)
			{
				if (strlen($qry) > 0)
				{
					$qry .= " UNION ";
				}
				
				$qry .= $this->getMonMemoryQuerySQL($field_name);
			}
		}
		
		// Build query for mon_vps fields
		if (count($mon_vps_names) > 0 )
		{
			// In order to keep in the same format as the mon_prof data, 
			// we need a different query for each field from the mon_vps table.
			// And then we'll union all queries together.
			foreach ($mon_vps_names as $key => $field_name)
			{
				if (strlen($qry) > 0)
				{
					$qry .= " UNION ";
				}
				
				$qry .= $this->getMonVPsQuerySQL($field_name);
			}
		}
		
		// Order all data, across all tables and sub-queries, by the datetime and last ztime
		$qry .= " ORDER BY 2, 1 ";
		
		$ret = $this->doDatabaseWorkDelta($conn_num, $qry,"sysadmin");
		return $ret;
	} 

	/**
	 * Get the query of the mon_memory_system table for a given field name
	 *
	 * @param field name
	 * @return query
	 */
	private function getMonMemoryQuerySQL($field_name)
	{
		$sum = "";
		$class = "";
		switch ($field_name)
		{
			case 'allocated_mem':
				$sum = "sum(size)/1048576"; 
				break;
			case 'used_mem':
				$sum = "sum(used)/256"; 
				break;
			case 'free_mem':
				$sum = "sum(free)/256"; 
				break;
			case 'allocated_mem_virt':
				$sum = "sum(size)/1048576"; 
				$class = "AND A.class = 2";
				break;
			case 'used_mem_virt':
				$sum = "sum(used)/256"; 
				$class = "AND A.class = 2";
				break;
			case 'free_mem_virt':
				$sum = "sum(free)/256"; 
				$class = "AND A.class = 2";
				break;
		}
		
		$qry = " SELECT "
			. " '$field_name' as name "
			. " ,A1.run_time::DATETIME YEAR TO SECOND as DT "
			. " ,dbinfo('utc_to_datetime',A1.run_ztime) as ztime "
			. " ,$sum as value "
			. " FROM mon_memory_system A, ph_task C, "
			. " ph_run A1 "
			. " WHERE "
			. " C.tk_name='mon_memory_system' " 
			. " AND A1.run_task_id = C.tk_id "
			. " AND A1.run_task_seq = A.id " 
			. " {$class} "
			. " GROUP BY 1, 2,3 ";
		return $qry;
	}
	
	/**
	 * Get the query of the mon_vps table for a given field name
	 *
	 * @param field name
	 * @return query
	 */
	private function getMonVPsQuerySQL($field_name)
	{
		$sum_field = "";
		$flags_text_join = "";
		$flags_text_join_cond = "";
		switch ($field_name)
		{
			case 'all_vps_total_time':
				$sum_field = "A.usecs_user+A.usecs_sys"; 
				break;
			case 'all_vps_sys_time':
				$sum_field = "A.usecs_sys"; 
				break;
			case 'all_vps_user_time':
				$sum_field = "A.usecs_user"; 
				break;
			case 'cpu_vps_total_time':
				$sum_field = "A.usecs_user+A.usecs_sys";
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'cpu'";
				break;
			case 'cpu_vps_sys_time':
				$sum_field = "A.usecs_sys"; 
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'cpu'";
				break;
			case 'cpu_vps_user_time':
				$sum_field = "A.usecs_user";
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'cpu'";
				break;
			case 'aio_vps_total_time':
				$sum_field = "A.usecs_user+A.usecs_sys";
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'aio'";
				break;
			case 'aio_vps_sys_time':
				$sum_field = "A.usecs_sys"; 
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'aio'";
				break;
			case 'aio_vps_user_time':
				$sum_field = "A.usecs_user";
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'aio'";
				break;
			case 'adm_vps_total_time':
				$sum_field = "A.usecs_user+A.usecs_sys";
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'adm'";
				break;
			case 'adm_vps_sys_time':
				$sum_field = "A.usecs_sys"; 
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'adm'";
				break;
			case 'adm_vps_user_time':
				$sum_field = "A.usecs_user";
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'adm'";
				break;
			case 'msc_vps_total_time':
				$sum_field = "A.usecs_user+A.usecs_sys";
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'msc'";
				break;
			case 'msc_vps_sys_time':
				$sum_field = "A.usecs_sys"; 
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'msc'";
				break;
			case 'msc_vps_user_time':
				$sum_field = "A.usecs_user";
				$flags_text_join = ", sysmaster:flags_text F ";
				$flags_text_join_cond = " AND F.tabname='sysvplst' AND F.flags = A.class AND F.txt = 'msc'";
				break;
			case 'num_ready_threads':
				$sum_field = "num_ready";
				break;
		}
		
		$qry = " SELECT "
			." '$field_name' as name "
			." ,A1.run_time::DATETIME YEAR TO SECOND as DT "
			." ,dbinfo('utc_to_datetime',A1.run_ztime) as ztime"
			." ,sum($sum_field) as value "
			." FROM mon_vps A, ph_task C, "
			." ph_run A1 {$flags_text_join} "
			." WHERE "
			." C.tk_name='mon_vps' "
			." AND A1.run_task_id = C.tk_id "
			." AND A1.run_task_seq = A.id "
			.$flags_text_join_cond
			." GROUP BY 1,2,3 ";

		return $qry;
	}

	/*private function doDatabaseWork($qry,$dbname="sysmaster")
	{
		$db = $this->idsadmin->get_database($dbname);
		while (1 == 1)
		{
			 
			$stmt = $db->query($qry,true);
			$prev = -1;
			while ($row = $stmt->fetch() )
			{
				$ret .= $row;
			}
			$err = $db->errorInfo();
			if ( $err[2] == 0 )
			{
				$stmt->closeCursor();
				break;
			}
			else
			{
				$stmt->closeCursor();
				continue;
			}
		}
		 
		return $ret;
	}*/

	/**
	 * Do database work, computing the delta values.
	 * 
	 * This function assumes the data returned by the query is in the 
	 * format of one row per metric.  For example:
	 * 		Row 1:   NAME=>pf_dskreads,  VALUE=>1900, DT=>2011-04-15 10:00:00, ZTIME=>2011-01-01 08:00:00
	 * 		Row 2:   NAME=>pf_dskwrites, VALUE=>6400, DT=>2011-04-15 10:00:00, ZTIME=>2011-01-01 08:00:00
	 * 		Row 3:   NAME=>pf_dskreads,  VALUE=>1980, DT=>2011-04-15 14:00:00, ZTIME=>2011-01-01 08:00:00
	 * 		Row 4:   NAME=>pf_dskwrites, VALUE=>6550, DT=>2011-04-15 14:00:00, ZTIME=>2011-01-01 08:00:00
	 *      Row 5:   NAME=>pf_dskreads,  VALUE=>2040, DT=>2011-04-15 18:00:00, ZTIME=>2011-01-01 08:00:00
	 * 		Row 6:   NAME=>pf_dskwrites, VALUE=>6750, DT=>2011-04-15 18:00:00, ZTIME=>2011-01-01 08:00:00
	 * 	
	 * When the delta are computed, the values are combined to one row per datetime (DT) value.
	 * For example:
	 * 		Row 1:   DT=>2011-04-15 14:00:00, ZTIME=>2011-01-01 08:00:00, pf_dskreads=>80, pf_dskwrites=>150
	 * 		Row 2:   DT=>2011-04-15 18:00:00, ZTIME=>2011-01-01 08:00:00, pf_dskreads=>60, pf_dskwrites=>200
	 *      (where pf_dskreads and pf_dskwrites are delta values)
	 */
	private function doDatabaseWorkDelta($conn_num, $qry, $dbname="sysmaster")
	{
		$ret = array();
		$ret['DATA'] = array();
		
		// Get database connection
		if ($conn_num == $this->idsadmin->phpsession->instance->get_conn_num() || $conn_num == -1)
		{
			// current server ($conn_num = -1 is when user manually typed in server login in, instead of using login info from connections.db)
			$db = $this->idsadmin->get_database($dbname);
		} else {
			// another server in the OAT group
			$db = $this->getDatabaseConnection($conn_num,$dbname);
			
			if (is_array($db))
			{
				// If we got an array back, it is because an error occurred 
				// and we could not connect to the database server.
				$ret['ERROR'] = $db; 
				return $ret;
			}
		}
		
		while (1 == 1)
		{
			try {
				$stmt = $db->query($qry ,false,true);
			} catch (PDOException $e) {	
				$err = $e->getMessage() . "\n\n" . $qry;
				trigger_error($err,E_USER_ERROR);
			}
			
			$prevValues = array();
			$prevZTimes = array();
			$dataPointsCount = 0;
			
			// Handle the first row
			$row = $stmt->fetch();
			if ($row == false)  
			{
				$ret['WARNING'] = $this->idsadmin->lang('NotEnoughDataToProfile');
				return $ret;
			}
			$time = $row['DT'];
			$row_name = trim($row['NAME']);
			$prevZTimes[$row_name] = $row['ZTIME'];
            $prevValues[$row_name] = $row['VALUE'];
			
			// Handle the remaining rows.
            // Iterate through and compute the delta values.
			$deltaData = array();
            while ($row = $stmt->fetch() )
			{
				$row_name = trim($row['NAME']);
				if (!isset($prevValues[$row_name]))
				{
					// If this is the first time we've seen this parameter,
					// just store the value as the starting point for the deltas.
					$prevValues[$row_name] = $row['VALUE'];
					$prevZTimes[$row_name] = $row['ZTIME'];
					$time = $row['DT'];
					continue;
				}

				if ($row['DT'] != $time) 
				{
					// New time, so add another row to the $deltaData array
					$deltaDataRow = array();
					$deltaDataRow['DT'] = $row['DT'];
					$deltaDataRow['ZTIME'] = $row['ZTIME'];
					$deltaData[] = $deltaDataRow;
					$dataPointsCount++;
					$time = $row['DT'];
				}

				// Compute the delta and add to the $deltaData array
				if ( $prevZTimes[$row_name] != $row['ZTIME'] )
				{
					$delta = 0;
					$prevZTimes[$row_name] = $row['ZTIME'];
				} else {
					$delta = $row['VALUE'] - $prevValues[$row_name];
				}
				switch ($row_name)
				{
					// For a few specific fields, we don't want to use the delta, but rather the raw value.
					case "acp_dirty_pgs_S":
					case "acp_dskF_per_S":
					case "acp_llogs_per_S": 
					case "acp_longest_dskF":
					case "acp_plogs_per_S":
					case "num_ready_threads":
					case "allocated_mem":
					case "used_mem":
					case "free_mem":
					case "allocated_mem_virt":
					case "used_mem_virt":
					case "free_mem_virt":
						
						$deltaData[$dataPointsCount -1][$row_name . "_" . $conn_num] = floatval($row['VALUE']);
						break;
						
					// For everything else, use the delta
					default:
						$deltaData[$dataPointsCount -1][$row_name . "_" . $conn_num] = $delta;
				}
				$prevValues[$row_name] = $row['VALUE'];
			}
			
			$err = $db->errorInfo();
			if ( $err[2] == 0 )
			{
				$stmt->closeCursor();
				break;
			}
			else
			{
				$stmt->closeCursor();
				continue;
			}
		}
		
		if ( $dataPointsCount < 3 )
		{
			$ret['WARNING'] = $this->idsadmin->lang('NotEnoughDataToProfile');
		} else {
			$ret['DATA'] = $deltaData;
		}
		
		return $ret;
	}
		
	/**
	 * Do work on the connections.db
	 * 
	 * @param $qry to execute
	 * @param $xml - true/false whether to return data as XML
	 */
	private function doConnectionsDatabaseWork($qry)
    {
    	if ($this->conndb == null)
    	{
    		require_once("../../services/idsadmin/clusterdb.php");
    		$this->conndb = new clusterdb ();
    	}
    	
        $ret = array();
        $stmt = $this->conndb->query($qry);
        while ($row = $stmt->fetch())
	    {
            $ret[] = $row;
        }
        return $ret;
    }
    
    /**
     * Get PDO database connection to another server from the connections.db.
     * 
     * @param $conn_num for the database server in the connections.db
     * @param $dbname datbase name to connect to, default is sysadmin
     * @return PDO database connection handle
     **/
    private function getDatabaseConnection($conn_num, $dbname = "sysadmin", $locale = "en_US.819")
    {
    	// Get connection information from connections.db
    	$qry = "select server, host, port, username, password, idsprotocol "
    		 . "from connections where conn_num = {$conn_num}";
    	$result = $this->doConnectionsDatabaseWork($qry);
    	if (count($result) == 0)
    	{
    		trigger_error("Connection information for conn_num {$conn_num} was not found in the OAT connections.db");
    	} 

    	$server = $result[0]['server'];
    	$host = $result[0]['host'];
    	$port = $result[0]['port'];
    	$username = $result[0]['username'];
    	$password = $result[0]['password'];
    	$idsprotocol = $result[0]['idsprotocol'];
    	
		require_once(ROOT_PATH."lib/PDO_OAT.php");
		try {
			$db_conn = new PDO_OAT($this->idsadmin,$server,$host,$port,$idsprotocol,$dbname,$locale,null,$username,$password);
		} catch (PDOException $e) {
			// If we could not connect to this server, return the error.
			$err = array();
			$err['ERROR_MESSAGE'] = $this->idsadmin->lang('DatabaseConnectionFailed',array($server));
			$err['ERROR_DETAIL'] =  $e->getMessage();
			return $err;
		}
		return $db_conn;
    }

} // end class

?>
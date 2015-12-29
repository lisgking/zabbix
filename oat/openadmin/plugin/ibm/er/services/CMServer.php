<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2010, 2011.  All rights reserved.
 ************************************************************************
 */
 
 /***********************************************************************
  * Public and Private functions:
  * The public functions are in the top half of this file. The private
  * functions (functions not directly accessible via SOAP) are in the
  * bottom half of this file.
  ***********************************************************************/
 
class CMServer
{
    private $idsadmin;
    private $servername = null;
    
    const SYSADMIN = "sysadmin";
    const ERROR = -1;
  
    function __construct()
    {
        define ("ROOT_PATH","../../../../");
        define( 'IDSADMIN',  "1" );
        define( 'DEBUG', false);
        define( 'SQLMAXFETNUM' , 100 );
        include_once(ROOT_PATH."/services/serviceErrorHandler.php");
        set_error_handler("serviceErrorHandler");
		
		require_once(ROOT_PATH."lib/idsadmin.php");
        $this->idsadmin = new idsadmin(true);
        
        require_once(ROOT_PATH."lib/feature.php");
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);

    }

    /**************************************************************************
     * SOAP service functions
     * - The functions in this section are exposed to as a service via SOAP.
     *************************************************************************/
	
	/**
	 * Get Connection Managers
	 * 
	 * @param $rows_per_page - null or -1 indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
	 * @param $search_pattern - part of the cm name to search by
	 */
	function getConnectionManagers($rows_per_page = null, $page = 1, $sort_col = null, $search_pattern = null)
	{
		$result = array();
		
		// Process the sort column.  $sort_col might look something like "@name DESC"
		// We need to translate the @name (which comes from the XML) to a column 
		// in the query (e.g. grid_cm_cm_name).
		if ($sort_col == null)
		{
			// default sort order
			$sort_col = "@name";
		}
		$sort_col_array = explode(" ", $sort_col);
		switch ($sort_col_array[0])
		{
			case "@name":
				$sort_col = "grid_cm_cm_name";
				break;
			case "@cmHost":
				$sort_col = "grid_cm_cm_host";
				break;
			case "@numSlas":
				$sort_col = "num_slas";
				break;
		}
		// Re-append the direction (ASC or DESC) to sort_col
		$sort_col .=  " " . $sort_col_array[1];
		
		// Query for Connection Manager info
		$query1 = "SELECT distinct grid_cm_cm_name, grid_cm_cm_host, num_slas "
				. "FROM grid_cm_nodes, "
				. "(SELECT grid_cm_sla_cm_name, count(*) as num_slas FROM grid_cm_sla GROUP BY grid_cm_sla_cm_name) as sla "
				. "WHERE grid_cm_cm_name=grid_cm_sla_cm_name ";
					
		if ($search_pattern != null)
		{
			$query1 .= " AND grid_cm_cm_name like '%{$search_pattern}%'";
		}
		
		$cmInfo = $this->doDatabaseWork($this->idsadmin->transformQuery($query1, $rows_per_page, $page, $sort_col), 'syscdr');
		
		// Transform results to XML format
		if (count($cmInfo) > 0) 
		{
			$cmXml = "<ConnectionManagers>";
			foreach ($cmInfo as $cmEntry) 
			{
				$cmXml .= $this->processCmNode($cmEntry);
				$cmXml .= "<slas cmName=\"" . $cmEntry['GRID_CM_CM_NAME'] . "\">";
				$cmXml .= $this->processSlas($cmEntry);
				$cmXml .= "</slas></CM>";
			}
			$cmXml .= "</ConnectionManagers>";
			
		}
		$result['CM'] = $cmXml;
		
		// Get count of available CMs
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($query1), 'syscdr');
		foreach ($temp as $row)
		{
			$result['COUNT'] = $row['COUNT'];
		}

		return $result;	
	}
	
	/* Function to insert/modify SLA
	 * $query: insert query to post event to sysmaster:sysrepstats
	 * $type: 'TYPE_INSERT_SLA' or 'TYPE_MODIFY_SLA' 
	 */
	 
	function postCMEvent($query, $type)
	{
		try {
			$result = $this->doDatabaseWork($query, 'sysmaster', null, null, true);	
		} catch ( PDOException $e )  {
			$result['RESULT_MESSAGE'] = "{$e->getCode()}\n" . $e->getMessage() . "\n";
			$result['FAIL'] = true;
			return $result;
		}
		
		if (count($result) == 0) {
			$result['SUCCESS'] = true;
		} else {
			$result['FAIL'] = true;
		}
		return $result;
	}
        
   /**************************************************************************
    * Private functions
    * - The functions in this section are all private and not-directly 
    *    accessible via SOAP services.
    *************************************************************************/
	
	private function processCmNode($cmEntry)
	{
		// to get groupnames - each CM could have multiple groups it's registered with
		$query = "SELECT groupname from grid_cm_nodes, hostdef "
				  . "WHERE name = grid_cm_cm_node AND grid_cm_cm_name = '{$cmEntry['GRID_CM_CM_NAME']}'";		
		$cmIDSServerList = $this->doDatabaseWork($query, 'syscdr');
		$groupList = '';
		foreach ($cmIDSServerList as $grp) 
		{
			$groupList .= $grp['GROUPNAME'] . ',';
		}
		$groupList = rtrim($groupList, ",");
		$cmChildXml = "<CM name=\"" 		. $cmEntry['GRID_CM_CM_NAME'] . "\""
					  . " cmHost=\"" 		. $cmEntry['GRID_CM_CM_HOST'] . "\""
					  . " numSlas=\"" 		. $cmEntry['NUM_SLAS']   	  . "\""
					  . " regIDSServers=\"" . $groupList				  . "\""
					  . ">";
		return $cmChildXml;
	}
	
	private function processSlas($cmEntry)
	{
		$query = "SELECT grid_cm_sla_name, grid_cm_sla_rule "
				. "FROM grid_cm_sla "
				. "WHERE grid_cm_sla_cm_name = '{$cmEntry['GRID_CM_CM_NAME']}'";
		$result = $this->doDatabaseWork($query, 'syscdr');
		
		$cmSla = "";
		foreach ($result as $sla)
		{
			$cmSla .= "<sla name=\"" . $sla['GRID_CM_SLA_NAME'] . "\""
				 	. " rule=\"" . htmlentities($sla['GRID_CM_SLA_RULE'],ENT_COMPAT,"UTF-8") . "\""
				 	. "/>" ;
		}
		
		return $cmSla;
	}
	
	/*
	 * Use this function to prepare then execute a given SQL query.
	 * For queries that involve dynamic parameters it is safer to use prepared statements
	 * than to put the parameters directly into the query string. 
	 */
	private function doPreparedDatabaseWork($sel, $params, $dbname = 'sysmaster' ){
		return $this->doDatabaseWork($sel, $dbname, null, $params);
	}
	/**
	 * Use this function to execute statements on the IDS server
	 */
    private function doDatabaseWork($sel,$dbname="sysmaster",$conn=null,$params=array(),$exceptions = false)
    {
        $ret = array();
		
		if ($conn == null)
        {
        	$db = $this->idsadmin->get_database($dbname);
        } else {
        	$db = $conn;
        }

        while (1 == 1)
        {        	
        	/* If we have parameters, we'll prepare then execute the query. */
			if (count($params) == 0)
        		$stmt = $db->query($sel, false, $exceptions);
			else {
				$stmt = $db->prepare($sel);
				$stmt->execute($params);
			}
        		
            $colcount = $stmt->columnCount();
            $colname = array();
            // Following loop identifies the columns with type = "TEXT"
            // Later we need to convert this type of data before sending it back to the flex side
            // Otherwise flex web service will not be able to handle it correctly
            for($cnt = 0; $cnt < $colcount; $cnt++)
            {
                $meta = $stmt->getColumnMeta($cnt);
                if("TEXT" == $meta["native_type"])
                	$colname[] = $meta["name"];
            }
            
            while($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
				// Following loop actually converts the data into stream,
				// for the columns, identified in the previous loop
            	foreach($colname as $name)
            		$row[$name] = stream_get_contents($row[$name]);
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
                $err = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
                $stmt->closeCursor();
                trigger_error($err,E_USER_ERROR);
                continue;
            }
        }
		//error_log(var_export($err,true));
        //error_log(var_export($ret,true));
        return $ret;
    }
       
    /**
     * Execute SQL Admin API Command
     *
     * Execute SQL Admin API Command specified in $task
     * $task will be an array of the following format:
     * 		$task['COMMAND'] --> SQL Admin API command
     * 		$task['PARAMETERS'] --> Parameters for the SQL Admin API Command
     *
     * When $conn_num is -1, use OAT's current server connection.  
	 * When $conn_num is not -1, use this connection from the connenctions.db
	 *
	 * Return values:
	 * The result of the command will be stored in the $task array as follows:
	 * 		$task['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$task['RESULT_MESSAGE'] --> success or failure message
	 *      $task['RETURN_CODE'] --> return code of the command
     */
    private function executeSQLAdminAPICommand($task, $conn_num = -1)
    {
    	// Get database connection.
    	// If conn_num != -1, use that server instead of the one OAT is connected to
		$db = null;
		if ($conn_num != -1)
		{
    		try {
    			$db = $this->getServerConnection($conn_num, self::SYSADMIN);
        	} catch(PDOException $e) {
        		$err = $e->errorInfo();
            	$err = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            	trigger_error($err_str,E_USER_ERROR); 
        	}
        } else {
	        $db = $this->idsadmin->get_database(self::SYSADMIN);
        }

        if (!isset($task['COMMAND']))
        {
        	$task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "Missing SQL Admin API command name.";
            return $task;
        }
    	if (!isset($task['PARAMETERS']))
        {
        	$task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "Missing SQL Admin API command parameters.";
            return $task;
        }
        
        $command = $task['COMMAND'];
        $parameters = $task['PARAMETERS'];
		
		 //error_log ( 'admin ( ' . $command . ', ' . $parameters . ' )' );
        
        // Build up SQL statement
		$sql = "execute function admin ( {$command} {$parameters} )";
		// Execute SQL Admin API command
		$stmt = $db->query($sql);
        
        // Check for success or errors
        $err = $db->errorInfo();
        if (isset($err[1]) && $err[1] != 0)
        {
            $task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            return $task;
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
            $task['SUCCESS'] = false;
            $task['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$err[2]} - {$err[1]}";
            return $task;
        }
        
        // Retreive cmd_ret_status and cmd_ret_msg 
       	$task['SUCCESS'] = false;
		$task['RESULT_MESSAGE'] = "Could not determine result. $cmd_num not found in command_history table" ;	
		while($row = $stmt->fetch(PDO::FETCH_ASSOC))
       	{
        	$task['SUCCESS'] = ($row['CMD_RET_STATUS'] == 0);
        	$task['RETURN_CODE'] = $row['CMD_RET_STATUS'];
			$task['RESULT_MESSAGE'] = $row['CMD_RET_MSG'];
		}
		  			
		return $task;	
    }
}
	
?>

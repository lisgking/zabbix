<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for Informix
 *  Copyright IBM Corporation 2011, 2012.  All rights reserved.
 ************************************************************************
 */
 
 /***********************************************************************
  * Public and Private functions:
  * The public functions are in the top half of this file. The private
  * functions (functions not directly accessible via SOAP) are in the
  * bottom half of this file.
  ***********************************************************************/
 
class ucmServer
{
    private $idsadmin;
    private $servername = null;    
    private $conn_num = null;

    const CM_COMMAND = '1';

    function __construct()
    {
        define ("ROOT_PATH","../../../../");
        define( 'IDSADMIN',  "1" );
        define( 'DEBUG', false);
        define( 'SQLMAXFETNUM' , 100 );
        include_once(ROOT_PATH."/services/serviceErrorHandler.php");
        set_error_handler("serviceErrorHandler");

        require_once(ROOT_PATH."/services/idsadmin/clusterdb.php");
		$this->connectionsDb = new clusterdb(true);

		//this is where the connection.db password hooks are , so we need to include this
		//file so we can call the decode/encode functions.
		require_once(ROOT_PATH."lib/connections.php");
				
		require_once(ROOT_PATH."lib/idsadmin.php");
        $this->idsadmin = new idsadmin(true);
        
        // Multi-byte support for 11.70.xC4.  For lower versions, reset phpsession's
        // DBLOCALE variables to the defaults (en_US.8895-1).
        require_once(ROOT_PATH."lib/feature.php");
        $this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
        if ( ! Feature::isAvailable( Feature::PANTHER_UC4, $this->idsadmin) ) 
        {
        	$this->idsadmin->phpsession->reset_dblocales_to_default();
        }
    }

    /**
     * Get Connection Managers
     * 
     * params = place holder
     */
	public function getConnMgrs($params)
	{
	
		$cmInfo = array();
		
		// Query for Connection Manager info.
		// Note: system catalog information relating to UCM changed in 12.10.xC1, so different queries are needed depending on the server version. 
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
		{
			$query1 = "SELECT trim(cm.name) as name, trim(cm.host) as host, trim(cm.foc) as foc, "
					. "trim(cm.sla_name)||'/'||trim(unit.type)||'/'||trim(cm.unit) as sla_name, "
					. "trim(cm.sla_define) as sla_define, cm.connections "
					. "FROM syscmsm cm, syscmsmunit unit "
					. "WHERE cm.sid = unit.sid "
					. "AND cm.unit = unit.unit "
					. "ORDER BY cm.name";
		} else {
			// For server versions < 12.10, we need to add a WHERE clause for the sla_name format to the query to get only xC3 UCM - different between UCM and pre UCM.
			$query1 = "SELECT trim(cm.name) as name, trim(cm.host) as host, trim(cm.foc) as foc, "
					. "trim(cm.sla_name) as sla_name, trim(cm.sla_define) as sla_define, cm.connections "
					. "FROM syscmsm cm "
					. "WHERE sla_name like '%/%/%' "
					. "ORDER BY cm.name";
		}

		
		
		$cmInfo = $this->doDatabaseWork($query1, 'sysmaster');
		
		// Get the name of the local CM, if it exists
		$query2 = "SELECT value as local_cm FROM sysadmin:ph_threshold th where th.name = 'CMNAME'";
		$res = $this->doDatabaseWork($query2, 'sysadmin');
		$localCMName = "";
		if (count($res) > 0)
		{
			$localCMName = $res[0]['LOCAL_CM'];
		}
		
		$ret = array();
		$ret['CM_INFO'] = $cmInfo;
		$ret['LOCAL_CM_NAME'] = $localCMName;
		
		return $ret;
	}
	
	/* get the unitInfo for a connection manager + optional unit. */
	function getUnitInfoForCm($cmName,$unitName="")
	{
		// Note: system catalog information relating to UCM changed in 12.10.xC1, so different queries are needed depending on the server version. 
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
		{
			$query1 = "SELECT trim(name) as name, trim(host) as host, trim(foc) as foc, "
				. "trim(unit) as unit_name, trim(type) as unit_type, trim(servers) as servers, "
				. "trim(sla_name) as sla_name, trim(sla_define) as sla_define, connections "
				. "FROM syscmsm "
				. "WHERE name = '{$cmName}'";
				if ( $unitName != "" )
				{
					$query1 .= " AND unit MATCHES ('{$unitName}')";
				}
		} else {
			$query1 = "SELECT trim(name) as name, trim(host) as host, trim(foc) as foc, "
				. "trim(sla_name) as sla_name, trim(sla_define) as sla_define, connections "
				. "FROM syscmsm "
				. "WHERE name = '{$cmName}'";
				if ( $unitName != "" )
				{
					$query1 .= " AND sla_name MATCHES ('*/".$unitName."')";
				}
		}
				
		$cmInfo = $this->doDatabaseWork($query1, 'sysmaster');
		$unitServers = array();
		$cmUnits = array();
		foreach ( $cmInfo as $k => $v)
		{
			$sla = $v['SLA_NAME'];
			if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
			{
				$unitType = $v['UNIT_TYPE'];
				$unitName = $v['UNIT_NAME'];
			} else {
				$brokenApart = preg_split ('/\//',$sla);
				$unitType = $brokenApart[1];
				$unitName = $brokenApart[2];
			}
			
			$servers = sscanf(preg_replace("/[)(]/","",$v['SLA_DEFINE']),"DBSERVERS=%s");
			$serversArray = preg_split ("/,/",$servers[0]) ;

			if (isset($v['SERVERS']))
			{
				// Servers data (INFORMIXSERVER from the CM config file) is only available for 12.10 or higher.
				// So if we have this info, also add it to the servers array.
				$serversArray = array_merge($serversArray, preg_split ("/,/",$v['SERVERS'])) ;
			}
			
			foreach ( $serversArray as $p => $serverName )
			{
				$unitServers[$v['NAME']][$unitName][] =  $serverName;
			}
		    
			$unitServers[$v['NAME']][$unitName] = array_unique ($unitServers[$v['NAME']][$unitName]  );
			$cmUnits[$v['NAME']][$unitName] = array ( "unitType"=>$unitType,"unitName"=>$unitName , "servers"=>$unitServers[$v['NAME']][$unitName]);		
		}
		
		if (count($cmUnits) == 0)
		{
			$this->idsadmin->load_lang("ucm");
			$result = array();
			if ($unitName == "")
			{
				$result['ERROR'] = $this->idsadmin->lang('NoConnUnitsFound',array($cmName));
			} else {
				$result['ERROR'] = $this->idsadmin->lang('ConnUnitNotFound',array($unitName));
			}
			return $result;
		}
		
		foreach ( $cmUnits as $cmName => $units )
		{
			foreach ( $units as $idx => $unitInfo )
			{
				switch ( $unitInfo['unitType'] )
				{
					
					case "GRID":
						$grids[]= array ( "NAME" => $idx , "TYPE" => "GRID" , "DATA" =>$this->getGridMembers($idx) );
						break;
					case "REPLSET":
						$replsets[]= array ( "NAME" => $idx , "TYPE" => "REPLSET" , "DATA" =>$this->getReplSetMembers($idx) , "VIEW" => $this->getERTopologyInfo() );
						break;
					case "SERVERSET":
						$localServerInServerSet = $this->getLocalServerInServerSet($unitInfo['servers']);
						if ($localServerInServerSet == "")
						{
							$serverSetData = array();
						} else {
							$serverSetData = array($localServerInServerSet);
						}
						$serversets[] = array ( "NAME" => $idx , "TYPE" => "SERVERSET" , "DATA" =>$serverSetData  );
						break; 
					case "CLUSTER":
						$clusters[] = array ( "NAME" => $idx , "TYPE" => "CLUSTER" , "DATA" => $this->getCluster($unitInfo['servers']) ) ;
						break;
				}
			}
		
		}
		return array ( array ( "grids" => $grids 
					, "replsets" => $replsets 
					, "clusters" => $clusters 
					, "serversets" => $serversets ) );
	}
	
	/* get the information for a connection unit
	 * optionally using a different server. ( Dis-joint )
	 */
	function getDataForUnit($cmName,$unitName,$unitType,$conn_num="")
	{
		$this->conn_num = $conn_num;
		
		switch ( $unitType)
		{
			
			case "GRID":
				if ( $unitName == "" )
					$data = $this->getGrids();
				else
					$data[]= array ( "NAME" => $unitName , "TYPE" => "GRID" , "DATA" =>$this->getGridMembers($unitName) );
				break;
			case "REPLSET":
				if ( $unitName == "" )
					$data = $this->getReplSets();
				else
					$data[]= array ( "NAME" => $unitName , "TYPE" => "REPLSET" , "DATA" =>$this->getReplSetMembers($unitName), "VIEW" => $this->getERTopologyInfo($conn_num) );
				break;
			case "SERVERSET":
				$data[] = array ( "NAME" => $unitName , "TYPE" => "SERVERSET" , "DATA" => array(" "));
				break; 
			case "CLUSTER":
				if ( $unitName == "" )
				{
					$data = $this->getCluster();
				} else {
					$data[] = array ( "NAME" => $unitName , "TYPE" => "CLUSTER" , "DATA" => $this->getCluster() ) ;
					
					// For server versions < 12.10, working around server defect idsdb00236076 that FOC info is missing for disjoint clusters.
					// So if we are getting detailed for a disjoint cluster, we need to also get the FOC information 
					// from that server explicitly.  (For non-disjoint clusters, we already this has FOC info.)
					if ($conn_num != "" && !Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
					{
						$qry = "select foc from syscmsm where sla_name MATCHES ('*/{$unitName}')";
						$focInfo = $this->doDatabaseWork($qry, 'sysmaster');
						if (count($focInfo) > 0 )
						{
							$data[0]['FOC'] = $focInfo[0]['FOC'];
						}
					}
				}
				
				break;
		}
		
		return $data;
	}
	
	
	function getGrids($serverName=null, $portNum=null, $userName=null, $pswd=null)
	{
		$result = array();
		
		// Get all grids
		$query = "select trim(gd_name) as gridname, gd_id , trim(gd_name) as name from grid_def";
		$result = $this->doDatabaseWork($query, 'syscdr');
		
		return $result;	
	}

/*	see the other getCluster() in this file - keeping this here for now (for dis-joint case)
	function getCluster($serverName=null, $portNum=null, $userName=null, $pswd=null)
	{
		$result = array();
		
		// Get cluster
		$query = "select trim(name) as name, role, trim(nodetype) as nodetype from syscluster";
		$result = $this->doDatabaseWork($query, 'sysmaster');
		
		return $result;	
	}
*/

	function getReplSets($serverName=null, $portNum=null, $userName=null, $pswd=null)
	{
		$result = array();
		
		// Get all replsets that are not grids or ifx_internal_set
		$query = "select trim(replsetname) as replsetname, replsetid , trim(replsetname) as name "
 			   . "from replsetdef "
 			   . "where replsetname NOT IN (select gd_name from grid_def) AND "
			   . "replsetname NOT IN ('ifx_internal_set')";
			   
		$result = $this->doDatabaseWork($query, 'syscdr');
		
		return $result;	
	}		
	
	/**
	 * Get the member servers for a particular grid
	 * 
	 * @param gridName
	 * @param $rows_per_page - null indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
	 * @param $search_pattern - group name to search for
	 */
	function getGridMembers($gridName, $rows_per_page = null, $page = 1, $sort_col = null, $search_pattern = null)
	{
		$result = array();
		
		// Get members of the grid
		$query = "select gp_id, gp_servid, gp_enable, servid, trim(name) as name, groupname "
			  . "from grid_part, hostdef, grid_def "; 
			  if ( $gridName == "" )
			  {
			  	$query .= "where 1=1 ";
			  }
			  else
			  {
			    $query .= "where grid_def.gd_name = '{$gridName}' ";
			  }
			  $query .= "and grid_part.gp_id = grid_def.gd_id "
			  . "and hostdef.servid = grid_part.gp_servid ";
		if ($search_pattern != null)
		{
			$query .= "and groupname like '%{$search_pattern}%'";
		} 
		if ($sort_col == null)
		{
			// default sort order: source servers at top, then ordered by group name
			$sort_col = "gp_enable desc, groupname";
		}
		
		$data = $this->doDatabaseWork($this->idsadmin->transformQuery($query, $rows_per_page, $page, $sort_col), 'syscdr');
		
		// Get members of each server group in the grid
		foreach ($data as $key => $val)
		{
			$groupInfo = $this->getServerGroupInfo($val['GROUPNAME']);
			$data[$key]['DBSVRNM'] = $groupInfo['DBSVRNM'];
			$data[$key]['HOSTNAME'] = $groupInfo['HOSTNAME'];
		}
		
		$result['DATA'] = $data;
		
		// Get total count grid members
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($query), 'syscdr');
		foreach ($temp as $row)
		{
			$result['COUNT'] = $row['COUNT'];
		}
		
		return $result;	
	}
	
	/**
	 * Get the member servers for a particular replset
	 * 
	 * @param replSetName
	 * @param $rows_per_page - null indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
	 * @param $search_pattern - group name to search for
	 */
	function getReplSetMembers($replSetName, $rows_per_page = null, $page = 1, $sort_col = null, $search_pattern = null)
	{
		$result = array();
		
		// Get members of the replset
		$query = "SELECT UNIQUE TRIM(A.replsetname), A.replsetid, D.servid, E.groupname, E.name "
			  . "FROM replsetdef A, replsetpartdef B, repdef C, partdef D, hostdef E "
			  . "WHERE A.replsetname = '{$replSetName}' AND "
			  . "A.replsetid = B.replsetid AND "
			  . "B.repid = C.repid AND "
			  . "C.repid = D.repid AND "
			  . "D.servid = E.servid ";
			  		  
		if ($search_pattern != null)
		{
			$query .= "and groupname like '%{$search_pattern}%'";
		} 
		if ($sort_col == null)
		{
			// default sort order: source servers at top, then ordered by group name
			$sort_col = "groupname";
		}
		
		$data = $this->doDatabaseWork($this->idsadmin->transformQuery($query, $rows_per_page, $page, $sort_col), 'syscdr');
		
		// Get members of each server group in the grid
		foreach ($data as $key => $val)
		{
			$groupInfo = $this->getServerGroupInfo($val['GROUPNAME']);
			$data[$key]['DBSVRNM'] = $groupInfo['DBSVRNM'];
			$data[$key]['HOSTNAME'] = $groupInfo['HOSTNAME'];
		}
		
		$result['DATA'] = $data;
		
		// Get total count grid members
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($query), 'syscdr');
		foreach ($temp as $row)
		{
			$result['COUNT'] = $row['COUNT'];
		}
		
		return $result;	
	}	
	
	/* Function to insert/modify
	 * $query: insert query to post event to sysmaster:sysrepstats
	 * $type: 'ADD_OR_MODIFY' 
	 */
	 
	function postCMEvent($query, $type)
	{	
		if ($type == self::CM_COMMAND) {		
			$cmd = $this->tryQuery($query);
			if (array_key_exists('FAIL', $cmd)) {
			 	  return $cmd;
			}
			$cmd['SUCCESS'] = true;
			return $cmd;
		}
		
		if ($type == 'ack') {
			$query1 = "select * from sysrepstats where repstats_desc like '%FAIL%'";
				
			$checkAck = $this->tryQuery($query1);
			if (array_key_exists('FAIL', $checkAck)) {
				  //$this->tryQuery($purgeQuery);
			 	  return $checkAck;
			}
			
			if (count($checkAck) == 0) {
				$checkAck['SUCCESS'] = true;
				$checkAck['ACK'] = true;
			} else {
				$this->idsadmin->load_lang("ucm");
				$checkAck['RESULT_MESSAGE'] = $this->idsadmin->lang('CMActionFailed');
				$checkAck['FAIL'] = true;
				$checkAck['ACK'] = true;
			}			
				
			return $checkAck;
		}
		
		if ($type == 'purge') {
			$purgeQuery = "insert into sysrepevtreg(evt_bitmap) values (256)";
			$this->tryQuery($purgeQuery);
			$purgeRes['SUCCESS'] = true;
			$purgeRes['PURGE'] = true;
			
			return $purgeRes;
		}
	}
	
	/* Get the servers defined in OAT's connections db for Server Set 
	 */
	function getConnectionsdbServers()
	{
	 	$grpnum = $this->getCurrentOATGroup();
	 	 
	    $query = "SELECT conn_num      		"
               . "     , host          		"
               . "     , server as NAME		"
               . "  FROM connections   		"
               . " WHERE group_num = {$grpnum} " 
               . " ORDER BY server";

        $result = $this->doConnectionsDatabaseWork ( $query ,true );
		return $result;
	}
	
	function getCurrentOATGroup()
	{
		return $this->idsadmin->phpsession->get_group();
	}
	
    function getERTopologyInfo($conn_num = "")
    {
    	$conn = null;
    	require_once 'ERServer.php';
    	$erServer = new ERServer();
    	
    	if ($conn_num != "")
    	{
    		try {
				$conn = $erServer->getServerConnection($this->conn_num ,"sysmaster");
			} 
			catch (PDOException $e)
			{
				 $this->idsadmin->load_lang("er");
				 return array("VALID" => false, 
            			 	"ERROR" => "{$this->idsadmin->lang("ConnectionFailed")}: {$e->getMessage()}");
			}
    	}
    	
    	$ret['servers'] = $erServer->getERTopologyInfo($conn);
        $ret['WORLDVIEW'] = $erServer->getWorldView(true, $conn);
        #error_log ( var_export ( $ret , true ));
        return $ret;
    }

    function getCluster($servers = array() )
    {
        $result = array();
        
        if ( is_array($servers) == TRUE && count($servers) == 0 )
        {
        	$add_to = "";
        }
        else
        {
        	$add_to = "WHERE name in ( ";
        	$addComma = false;
        	foreach ( $servers as $k => $v)
        	{
        		if ( $v == "ANY" )
        		{
        			continue;
        		}
        		if ( $addComma == false )
        		{
        		$add_to .= "'".$v."'";
        		$addComma = true;
        		}
        		else
        		$add_to .= ",'".$v."'";
        	}
        	$add_to .= ")";
        	// if $addComma is still false then
        	// we did not add any servers so remove 
        	// the add_to
            if ( $addComma == false )
            {
            	$add_to = "";
            }
        }
        $qry = "SELECT * FROM syscluster {$add_to} ORDER BY role , nodetype ";
        $data = $this->doDatabaseWork($qry,"sysmaster");
        if ( count($data)  > 0 )
        {
        	// A standalone server with no HDR will have a HDR entry in syscluster with the name field being blank. Exclude this row from the result.
        	$qry = "SELECT * FROM syscluster where length(name) > 0 ORDER BY role , nodetype ";
	        $data = $this->doDatabaseWork($qry,"sysmaster");	
        	return $data;
        }
        else
        {
        	// lets check if syscluster contains this server also..
        	
        }
        
        return $data;
    }

	   
    function runCommand($command, $parameters)
    {
    	$result = array();
		// comma between command and parameters argument
		$command .= ',';
		
		// Set the arguments to pass to SQL Admin API procedure
		$task = array();
		$task["COMMAND"] = $command;
		$task["PARAMETERS"] = $parameters;
		$task["COMMENTS"] = "OAT Connection Manager Command";
		
		// Execute the Admin API Command
		$result = $this->idsadmin->executeSQLAdminAPICommand($task);
		
    	return $result; 
    }
	
   /**************************************************************************
    * Private functions
    * - The functions in this section are all private and not-directly 
    *    accessible via SOAP services.
    *************************************************************************/
    
    /**
     * Get server group information including
     * the list of servers and hostnames in the group.
     **/
    private function getServerGroupInfo($groupname="")
    {
    	$query = "select trim(dbsvrnm) as dbsvrnm, trim(hostname) as hostname "
    		   . "from syssqlhosts where svrgroup = '{$groupname}'";
    	$res = $this->doDatabaseWork($query,"sysmaster");
    	$servers = "";
    	$hostnames = "";
    	foreach ($res as $row)
    	{
    		if ($servers != "")
    		{
    			$servers .= ", ";
    		}
    		$servers .= $row['DBSVRNM'];
    		
    		if ($hostnames != "")
    		{
    			$hostnames .= ", ";
    		}
    		$hostnames .= $row['HOSTNAME'];
    	}
    	
    	$result = array();
    	$result['DBSVRNM'] = $servers;
    	$result['HOSTNAME'] = $hostnames;
    	return $result;
    }
    
    /**
     * For a server set, determine if the local server (the one OAT is connected to) is in the server set
     * 
     * @param $servers - array of servers in the server set
     * @return array - if the local server is in the server set, return that server name
     *                 otherwise, if the local server is not in the server set, return an empty string
     **/
    private function getLocalServerInServerSet($servers)
    {
    	$localServerName = $this->idsadmin->phpsession->instance->get_servername();
    	if (in_array($localServerName, $servers))
    	{
    		return $localServerName;
    	} else {
    		return "";
    	}
    }

    private function getERNodeMembers($groupName)
    {
        $qry = "select svrgroup, dbsvrnm, hostname from syssqlhosts where svrgroup = '$groupName'";
        return $this->doDatabaseWork($qry);
    }    

	/* Run a query and catch an exception if there is one. */
	private function tryQuery($query)
	{
		try {
			$result = $this->doDatabaseWork($query, 'sysmaster', true, null, null);	
		} catch ( PDOException $e )  {
			$result['RESULT_MESSAGE'] = "{$e->getCode()}\n" . $e->getMessage() . "\n";
			$result['FAIL'] = true;
		}
		
		return $result;	
	}
	    
    /* wrapper function for doing database work 
     *   if $this->conn_num == "" then we will 
     *   use the idsadmin database functionality
     *   else
     *   we'll get a db connection to a different server
     *   and execute the query.
     */
    private function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false,$conn=null,$params=array())
    {
    	$locale = $this->idsadmin->phpsession->get_dblcname();
    	
    	if ( $this->conn_num == "" )
    	{
    	 	if ( $dbname == "syscdr" )
                {
                        if ( $this->idsadmin->phpsession->serverInfo->getHasCDR() < 1 )
                        {
                                return  array();
                        }
                }
    		return $this->idsadmin->doDatabaseWork($sel,$dbname,$exceptions,$conn,$params, $locale);
    	}
    	else
    	{
			require_once("ERServer.php");
			$erServer = new ERServer();
			try {
			$conn = $erServer->getServerConnection($this->conn_num , $dbname);
			} 
			catch (PDOException $e)
			{
				 $this->idsadmin->load_lang("er");
				 return array("VALID" => false, 
            			 	"ERROR" => "{$this->idsadmin->lang("ConnectionFailed")}: {$e->getMessage()}");
			}
			
			try {
				return $this->idsadmin->doDatabaseWork($sel,$dbname,true,$conn,$params, $locale);
			} 
			catch (PDOException $e)
			{
				return $e->getMessage();
			}
		}
    }
 
    /**
     * Use this function to execute statements on the OAT connections database
     */
    private function doConnectionsDatabaseWork($qry , $ispasswd = false)
    {
    	$ret = array();

        $stmt = $this->connectionsDb->query($qry);
        if (! $stmt)
        	{
            $err = $this->connectionsDb->errorInfo();
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

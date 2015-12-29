<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2010, 2012.  All rights reserved.
 ************************************************************************
 */
 
 /***********************************************************************
  * Public and Private functions:
  * The public functions are in the top half of this file. The private
  * functions (functions not directly accessible via SOAP) are in the
  * bottom half of this file.
  ***********************************************************************/
 
class GridReplServer
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
        $this->idsadmin->load_lang("er");
        
        require_once(ROOT_PATH."lib/feature.php");
        $this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
        
        // Multi-byte support for 11.70.xC4.  For lower versions, reset phpsession's
        // DBLOCALE variables to the defaults (en_US.8895-1).
        if ( ! Feature::isAvailable( Feature::PANTHER_UC4, $this->idsadmin) ) 
        {
        	$this->idsadmin->phpsession->reset_dblocales_to_default();
        }

    }

    /**************************************************************************
     * SOAP service functions
     * - The functions in this section are exposed to as a service via SOAP.
     *************************************************************************/
	
	/**
	 * Get list of grids + each grid's source servers.
	 * This information will be shown in the Tree.
	 */
	function getGrids()
	{
		$result = array();
		
		// Get all grids
		$query1 = "select trim(gd_name) as gridname, gd_id from grid_def order by gridname";
		$result = $this->doDatabaseWork($query1, 'syscdr');
		
		// For each grid, get source servers
		foreach ( $result as $k => $v )
		{
			$query2 = "select unique groupname "
					  . "from grid_part, hostdef "
					  . "where grid_part.gp_id = {$v['GD_ID']} "
					  . "and hostdef.servid = grid_part.gp_servid "
					  . "and gp_enable = 'y'";

			$part = $this->doDatabaseWork($query2, 'syscdr');
			$result[$k]['GRID_SOURCE_SERVERS'] = $part;
			$result[$k]['GRID_REGIONS'] = array();
		}
		
		// For each grid, get regions
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin)) 
		{
			foreach ( $result as $k => $v )
			{
				$query3 = "select gr_name as region_name, gr_regid as region_id "
				. "from grid_region_tab "
				. "where gr_grid = {$v['GD_ID']} "
				. "order by region_name";
			
				$regions = $this->doDatabaseWork($query3, 'syscdr');
				$result[$k]['GRID_REGIONS'] = $regions;
			}
		}
		return $result;	
	}
	
	/**
	 * Get the member servers for a particular grid or region
	 * 
	 * @param gridid
	 * @param regionid - null indicates all servers in entire grid
	 * @param $rows_per_page - null indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
	 * @param $search_pattern - group name to search for
	 */
	function getGridMembers($gridid, $regionid = null, $rows_per_page = null, $page = 1, $sort_col = null, $search_pattern = null)
	{
		$result = array();
		
		if ($regionid == null || !Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
		{
			// Get all members of the grid
			$query = "select gp_id, gp_servid, gp_enable, servid, name, groupname "
				  . "from grid_part, hostdef "
				  . "where grid_part.gp_id = {$gridid} "
				  . "and hostdef.servid = grid_part.gp_servid ";
		} else {
			// Get all members of a particular grid region
			$query = "select gr_grid as gp_id, grp_partid as gp_servid, servid, name, groupname "
			. "from grid_region_tab r, grid_region_part_tab p, hostdef h "
			. "where r.gr_grid = {$gridid} "
			. "and r.gr_regid = p.grp_regid "
			. "and p.grp_regid = {$regionid} "
			. "and h.servid = p.grp_partid ";
		}
		
		if ($search_pattern != null)
		{
			$query .= "and groupname like '%{$search_pattern}%'";
		} 
		if ($sort_col == null)
		{
			if ($regionid == null || !Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
			{
				// default sort order: source servers at top, then ordered by group name
				$sort_col = "gp_enable desc, groupname";
			} else {
				$sort_col = "groupname";
			}
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
		

	function getGridMemberOutput($gridid, $stmtid, $sourceid, $targetid)
	{
	    $commandtextqry = "SELECT gack_output FROM grid_cmd_ack_tab "
	    	. "WHERE gack_gridid = {$gridid} AND gack_stmtid = {$stmtid} "
	    	. "AND gack_source = {$sourceid} AND gack_target = {$targetid}";
	    $commandtext = $this->doDatabaseWork($commandtextqry, "syscdr");
	   	return $commandtext[0]['GACK_OUTPUT'];
	}

	/**
	 * Get status information for the grid commands
	 * 
	 * @param gridid
	 * @param $rows_per_page - null indicates all rows
	 * @param $page - current page number
	 * @param $sort_col - sort columns for order by clause 
	 * @param $search_pattern - part of the command text to search by
	 * @param $filter -  null, "All", "Completed", "Completed with Errors", 
	 * 			 "Failed", or "Pending"
	 */
	function getGridStatusInfo($gridid, $rows_per_page = null, $page = 1, $sort_col = null, $search_pattern = null, $filter = null)
	{
		$res = array();
		
		// Create a dictionary that maps servid to servername.
		// Also keep track of the total number of servers in the grid and the list of source servers.
		$srvrnameqry = "SELECT servid, servname, gp_enable FROM grid_part p, sysmaster:syscdrs s "
			. "WHERE p.gp_servid = s.servid AND gp_id = {$gridid}";
		$rows = $this->doDatabaseWork($srvrnameqry, 'syscdr');
		$serversDictionary = array();
		$sourceServerList = array();
		foreach ($rows as $row)
		{
			$serversDictionary[$row['SERVID']] = trim($row['SERVNAME']);
			if (strtolower($row['GP_ENABLE']) == "y" )
			{
				$sourceServerList[] = trim($row['SERVNAME']);
			}
		}
		
		// Process the sort column.
		// $sort_col might look something like "@user DESC"
		// We need to translate the @user (which comes from the XML) to a colum in the query (gcmd_user)
		if ($sort_col == null)
		{
			// default sort order
			$sort_col = "@time desc";
		}
		$sort_col_array = explode(" ", $sort_col);
		switch ($sort_col_array[0])
		{
			case "@stmtid":
				$sort_col = "gcmd_stmtid";
				break;
			case "@command":
				$sort_col = "gcmdpart_text";
				break;
			case "@tag":
				$sort_col = "gcmd_tag";
				break;
			case "@user":
				$sort_col = "gcmd_user";
				break;
			case "@time":
				$sort_col = "gcmd_time";
				break;
		}
		// Re-append the direction (ASC or DESC) to sort_col
		$sort_col .=  " " . $sort_col_array[1];
		
		// Get status info
		$xml = "<rows>";
		$query = "SELECT gcmd_source, gcmd_stmtid, gcmd_gridid, gcmd_numpieces, gcmd_textsize, "
				 . "trim(gcmdpart_text) as gcmdpart_text, gcmd_flags, "
				 . "gcmd_time, gcmd_database, gcmd_user, gcmd_locale, gcmd_collation, gcmd_tag "
				 . "FROM grid_cmd_tab, grid_cmd_part_tab "
				 . "WHERE gcmdpart_stmtid=gcmd_stmtid AND gcmdpart_source=gcmd_source "
				 . "AND gcmdpart_ercmd=0 AND gcmd_gridid={$gridid} AND gcmdpart_seq=1";
		if ($search_pattern != null)
		{
			$query .= " AND gcmdpart_text like '%{$search_pattern}%'";
		}
		
		// Add filter clause
		$query .= $this->getStatusFilterClause($filter, $gridid, $sourceServerList);

		// Execute query for status information
		$rows = $this->doDatabaseWork($this->idsadmin->transformQuery($query, $rows_per_page, $page, $sort_col), 'syscdr');
		
		// For each command, get information about the status on each server and convert info to XML
	    foreach ( $rows as $row ) 
	    {
	    	$stmtid = $row['GCMD_STMTID'];
	    	$sourcesrvrid = $row['GCMD_SOURCE'];
	    	$sourcesrvrname = $serversDictionary[$sourcesrvrid];
	    	$commandFull = htmlentities($row['GCMDPART_TEXT'],ENT_COMPAT,"UTF-8");
			
	    	$rxml = "";
	  		// Get errors and successes for servers... 
	  		// union results from grid_cmd_ack_tab (successes) with grid_cmd_errors_tab (failures)
	  		$resultquery = "select gack_target as target, servname as target_name, gack_source as source, "
	  			. "gack_time as time, gack_ackid, gack_output, "
	  			. "-1 as gerr_errid, 0 as gerr_sqlerr, 0 as gerr_isamerr, '' as gerr_text "
	  			. "FROM syscdr@" . $sourcesrvrname . ":grid_cmd_ack_tab, sysmaster:syscdrs s " 
	  			. "WHERE gack_gridid = {$gridid} AND gack_stmtid = {$stmtid} AND gack_source = {$sourcesrvrid} "
	  			. "AND s.servid = gack_target "
	  			. "UNION "
	  			. "select gerr_target as target, servname as target_name, gerr_source as source, "
	  			. "gerr_time as time, -1 as gack_ackid, '' as gack_output, "
	  			. "gerr_errid, gerr_sqlerr, gerr_isamerr, gerr_text "
	  			. "FROM syscdr@" . $sourcesrvrname . ":grid_cmd_errors_tab E, sysmaster:syscdrs s "
	  			. "WHERE gerr_gridid = {$gridid} AND gerr_stmtid = {$stmtid} AND gerr_source = {$sourcesrvrid} "
	  			. "AND s.servid = gerr_target "
	  			// The following part only gets the last error message (in case the command is re-run and it fails
	  			// multiple times.
	  			. "AND gerr_errid = ( SELECT max(gerr_errid) FROM syscdr@" . $sourcesrvrname . ":grid_cmd_errors_tab EI "
	  			. "WHERE gerr_gridid = {$gridid} AND gerr_stmtid = {$stmtid} AND gerr_source = {$sourcesrvrid} " 
	  			. "AND E.gerr_target = EI.gerr_target) ";
	   		$results = $this->doDatabaseWork($resultquery);
	   		$successcount = 0;
	   		$errorcount = 0;
			$generated_endtime = 0;
			$generated_taskstatus = "";	
	    	
			// In addition to the successes and failures, we also want to know on which servers the
			// command is still pending.  So initalize pendingServersList to all servers in the grid. 
			$pendingServersList = $serversDictionary;
			
			foreach ( $results as $srow ) 
			{
	    		// For each command result, remove that server from the pendingServersList
	    		unset($pendingServersList[$srow['TARGET']]);
	    		
	    		// Process the result by converting it to XML
	    		$rxml 	.= 	"<response target=\""    	. $srow['TARGET'] 				. "\""
	    				.  	" gridid=\""     			. $gridid						. "\""
	    				.  	" stmtid=\""     			. $stmtid						. "\""
	    				.  	" cmdText=\""     			. $commandFull					. "\""
	    				.  	" source=\""				. $srow['SOURCE']			 	. "\""
	    				.  	" sourcename=\""     		. $sourcesrvrname				. "\""
	    				.  	" targetname=\""     		. trim($srow['TARGET_NAME'])	. "\""
	    				.  	" tag=\""         		   	. $row['GCMD_TAG']				. "\""
	    				.	" database=\""         		. trim($row['GCMD_DATABASE']) 	. "\""
			    		.  	" user=\""         		   	. trim($row['GCMD_USER']) 		. "\""
			    		.  	" time=\""					. $srow['TIME'] 				. "\""
			    		.  	" locale=\""         		. trim($row['GCMD_LOCALE' ]) 	. "\""
	    				.  	" start=\""					. $row['GCMD_TIME'] 			. "\"";
	    		if ($srow['GACK_ACKID'] != -1)
	    		{
	    			$output = trim(htmlentities($srow['GACK_OUTPUT'],ENT_COMPAT,"UTF-8"));
	    			// For successes
	    			$rxml .= " ackid=\""				. $srow['GACK_ACKID'] 	. "\""
	    				  .  " output=\""				. $output			 	. "\""
	    				  .  " type=\""					. "SUCCESS"				. "\"";
	    			$successcount++;
	    		} else {
	    			// For failures
	    			$rxml .= " errid=\""				. $srow['GERR_ERRID'] 	. "\""
	    		 		  .  " sqlerr=\""				. $srow['GERR_SQLERR'] 	. "\""
	    		 		  .  " isamerr=\""				. $srow['GERR_ISAMERR'] . "\""
	    		 		  .  " errtext=\""				. $srow['GERR_TEXT'] 	. "\""
	    				  .  " type=\""					. "ERROR"				. "\"";
	    			$errorcount++;
	    		}
	    		
	    		// Evaluate task status
	    		$tmp = trim($row['GCMD_TIME']);
	    		if (empty($tmp)) 
	    		{
	    			$rxml .= " status=\"Pending\"";	
	    		} else {
	    			if ($srow['GACK_ACKID'] != -1)
	    			{
	    				$rxml .= " status=\"Completed\"";
	    			} else {
	    				$rxml .=" status=\"Failed\"";
	    			}	 			
	    		}
	    		$rxml	.=	"/>";
	  		}
	  		
	  		// If any servers are left in the pendingServersList, that means we need to 
	  		// add these to the XML as 'Pending'.
	  		if (count($pendingServersList) > 0 )
	  		{
	  			foreach ($pendingServersList as $serverNum => $serverName)
	  			{
		  			$rxml 	.= 	"<response target=\"{$serverNum}\""
		    				.  	" targetname=\"{$serverName}\""
		    				.  	" source=\"{$sourcesrvrid}\""
		    				.  	" sourcename=\"{$sourcesrvrname}\""
		    				.  	" gridid=\"{$gridid}\""
		    				.  	" stmtid=\"{$stmtid}\""
		    				.  	" cmdText=\"{$commandFull}\""
		    				.  	" tag=\"{$row['GCMD_TAG']}\""
		    				.  	" status=\"Pending\""
		    				.  	" type=\"PENDING\""
		    				.	"/>";
	  			}
	  		}
	  		
	    	if (count($pendingServersList) > 0 )
	    	{
	    		$generated_taskstatus = "Pending";
	    	} else if ( $successcount > 0 && $errorcount > 0 ) {
	    		$generated_taskstatus = "Completed with Errors";
	    	} else if ( $successcount > 0 && $errorcount == 0 ) {
	    		$generated_taskstatus = "Completed";
	    	} else {
	    		$generated_taskstatus = "Failed";
	    	} 

	    	$timeStr = trim($srow['TIME']);
	    	if ( $timeStr > $generated_endtime ) 
	    	{
	    		$generated_endtime = $timeStr;
	    	}

	    	$xml .= "<row source=\""           	   . $row['GCMD_SOURCE'           ] . "\""
	    		 .	" stmtid=\""           		   . $row['GCMD_STMTID'           ] . "\""
	    		 .	" gridid=\""           		   . $row['GCMD_GRIDID'           ] . "\""
	    		 .	" command=\""				   . $commandFull				  	. "\""
	    		 .	" numpieces=\""        		   . $row['GCMD_NUMPIECES'     	  ] . "\""
	    		 .	" textsize=\""         		   . $row['GCMD_TEXTSIZE'         ] . "\""
	    		 .	" flags=\""         		   . $row['GCMD_FLAGS'         	  ] . "\""
	    		 .	" time=\""         		   	   . $row['GCMD_TIME'         	  ] . "\""
	    		 .	" database=\""         		   . trim($row['GCMD_DATABASE'    ]). "\""
	    		 .	" user=\""         		   	   . trim($row['GCMD_USER'        ]). "\""
	    		 .	" locale=\""         		   . trim($row['GCMD_LOCALE'      ]). "\""
	    		 .	" collation=\""         	   . trim($row['GCMD_COLLATION'   ]). "\""
				 .  " tag=\""         		   	   . $row['GCMD_TAG'         	  ] . "\""
				 .  " selected=\""				   . "false"						. "\""
				 .	" endtime=\""				   . $generated_endtime				. "\""
				 .	" status=\""				   . $generated_taskstatus			. "\""	
	    		 .  "><responses>";

	    		 $xml .= $rxml . "</responses></row>";
	    }
	    $xml .= "</rows>";
	    
	    $res['DATA'] = $xml;
	    
	    $res['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($query), 'syscdr');
		foreach ($temp as $row)
		{
			$res['COUNT'] = $row['COUNT'];
		}
		return $res;
	}
	
	/**
	 * Get available servers in the domain that can be added to the grid
	 * 
	 * @gridid - If $gridid is not null, get only the available servers 
	 *           that are not yet in the specified grid.  If $gridid = null,
	 *           get all servers.  
	 */
	function getServersInDomain($gridid = null)
	{
		$query = "SELECT TRIM ( servname ) AS name"
		       . "  FROM sysmaster:syscdrs";
		if ($gridid != null)
		{
			$query .= " WHERE servid NOT IN (SELECT gp_servid FROM grid_part WHERE gp_id = {$gridid}) ";
		}
		       
        $servers = $this->doDatabaseWork ( $query, 'syscdr' );
        return $servers;
    }
    
	function getGridUsers($gridid)
	{
		$query = "SELECT gu_user as name"
		       . "  FROM grid_users where gu_id = {$gridid}";
        $users = $this->doDatabaseWork ( $query, 'syscdr' );
        return $users;
    }
    
    /* Execute commands on the server (todo: change name from runCreateGridCmd() to a generic name).
     * If $parameters == 'SQL_STMT' that implies $command contains SQL to be executed,
     * Else, $command and $parameters are used to submit a SQL Admin API command.
     */    
    function runCreateGridCmd($command, $parameters)
    {
    	if ($parameters == 'SQL_STMT') {
    			
    		try
    		{
    			$result = $this->doDatabaseWork ( $command, 'syscdr', true ); // true is to get exceptions, if any
    		}
    		catch ( PDOException $e )  
            {
	            /* the statement may contain '<' chars , this causes a problem when we display the text
	             * back in flex , so we use the htmlspecial chars on the statment.
	             */
	            $command = htmlspecialchars($command,ENT_COMPAT,"UTF-8");
	            $result['RESULT_MESSAGE'] = "{$e->getCode()}\n" . "{$v}\n" . $e->getMessage() . "\n";
	            // store it for use when analyzing results
    			$result['PARAMETERS'] = $command;
	            return $result;
            }
            
    		if (count($result) == 0) {
    			$result['SUCCESS'] = true;
	            // store it for use when analyzing results
    			$result['PARAMETERS'] = $command;    			
    		}
    		return $result;   	
    	} else {
	    	$result = array();
			// comma between command and parameters argument
			$command .= ',';
			
			// Set the arguments to pass to SQL Admin API procedure
			$task = array();
			$task["COMMAND"] = $command;
			$task["PARAMETERS"] = $parameters;
			$task["COMMENTS"] = "OAT create grid command";
			
			// Execute the Admin API Command
			$result = $this->executeSQLAdminAPICommand($task);
			
	    	return $result;
    	} 
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
    private function getServerGroupInfo($groupname)
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
	 * This function generates the SQL for the WHERE clause filter for the status page.
	 * 
	 * @filter - possible values: null, "All", "Completed", "Completed with Errors", 
	 * 			 "Failed", or "Pending"
	 * @gridid - id of the selected grid
	 * @sourceServerList - array of all soruce server names in the grid
	 */
	private function getStatusFilterClause ($filter, $gridid, $sourceServerList) 
	{
		/* Note: It is not simple to filter by status because there is no status column 
		   anywhere on the server.  Instead the status shown in the OAT UI is DERIVED 
		   from data from mutliple tables across the grid's source server as well as 
		   from data that is not present in those tables.  */
		
		$sql = " ";
		
		/* Only source servers have all of the status data.  So in the below filters, we 
		   always want to be running distributed queries on the ack and nack tables on 
		   one of the grid's source servers.  If there are multiple source servers, it 
		   does not matter which is chosen, since this data is replicated across source 
		   servers. */
		$sourceServerStr = "";
		if (count($sourceServerList) > 0)
		{
			$sourceServerStr = "syscdr@" . $sourceServerList[0] . ":";
		}
    	
		if ($filter != null && $filter != "All")
		{
			switch ($filter)
			{
				case 'Completed':
					// To filter for only completed commands, we want to find all commands for which
					// the number of servers in the grid that are NOT in the grid_cmd_ack_tab table
					// (which represent command successes) is zero.
					$sql .= " AND "
						 .  "(SELECT count(*) as count "
					     .  "FROM grid_part "
					     .  "WHERE gp_id={$gridid} "
					     .  "AND gp_servid NOT IN "
					     .  "  (SELECT gack_target FROM {$sourceServerStr}grid_cmd_ack_tab " 
					     .  "   WHERE gack_gridid={$gridid} AND gack_stmtid=gcmd_stmtid AND gack_source=gcmd_source) "
					     .  ") = 0 "; 
					break;
				case 'Completed with Errors':
					// To filter for only 'completed with errors' commands, we want to find all commands 
					// for which ALL of the following are true
					// 	   (1) the number of servers in the grid that are NOT in either the grid_cmd_ack_tab  
					//         or the grid_cmd_errors_tab is equal to zero (meaning there are no pending 
					//         servers for the command).
					//     (2) the count of successes in the grid_cmd_ack_tab for that command is greater than zero
					//		   (meaning it's not a 'Failed' command)
					//     (3) number of servers in the grid that are NOT in the grid_cmd_ack_tab table is 
					//         greater than zero (meaning it's not a 'Completed' command)
					$sql .= "AND "
						 .  "(SELECT count(*) as count "
					     .  "FROM grid_part "
					     .  "WHERE gp_id={$gridid} "
					     .  "AND gp_servid NOT IN "
					     .  "  (SELECT gack_target FROM {$sourceServerStr}grid_cmd_ack_tab " 
					     .  "   WHERE gack_gridid={$gridid} AND gack_stmtid=gcmd_stmtid AND gack_source=gcmd_source) "
					     .  "AND gp_servid NOT IN "
					     .  "   (SELECT gerr_target FROM {$sourceServerStr}grid_cmd_errors_tab "
					     .  "    WHERE gerr_gridid={$gridid} AND gerr_stmtid=gcmd_stmtid AND gerr_source=gcmd_source) " 
					     .  ") = 0 "
					     .  "AND (SELECT count(*) as count FROM {$sourceServerStr}grid_cmd_ack_tab "
					     .  "    WHERE gack_gridid={$gridid} AND gack_stmtid=gcmd_stmtid AND gack_source=gcmd_source) > 0 "
					     .  "AND (SELECT count(*) as count "
					     .  "FROM grid_part "
					     .  "WHERE gp_id={$gridid} "
					     .  "AND gp_servid NOT IN "
						 .  "   (SELECT gack_target FROM {$sourceServerStr}grid_cmd_ack_tab, hostdef " 
						 .  "   WHERE gack_gridid={$gridid} AND gack_stmtid=gcmd_stmtid AND gack_source=gcmd_source)) > 0 ";
					break;
				case 'Failed':
					// To filter for only 'failed' commands, we want to find all commands for which
					// BOTH of the following are true
					// 	   (1) the number of servers in the grid that are NOT in either the grid_cmd_ack_tab  
					//         or the grid_cmd_errors_tab is equal to zero (meaning there are no pending 
					//         servers for the command).
					//     (2) the count of success in the grid_cmd_ack_tab for that command is zero
					$sql .= "AND "
						 .  "(SELECT count(*) as count "
					     .  "FROM grid_part "
					     .  "WHERE gp_id={$gridid} "
					     .  "AND gp_servid NOT IN "
					     .  "  (SELECT gack_target FROM {$sourceServerStr}grid_cmd_ack_tab " 
					     .  "   WHERE gack_gridid={$gridid} AND gack_stmtid=gcmd_stmtid AND gack_source=gcmd_source) "
					     .  "AND gp_servid NOT IN "
					     .  "   (SELECT gerr_target FROM {$sourceServerStr}grid_cmd_errors_tab "
					     .  "    WHERE gerr_gridid={$gridid} AND gerr_stmtid=gcmd_stmtid AND gerr_source=gcmd_source) " 
					     .  ") = 0 "
					     .  "AND (SELECT count(*) as count FROM {$sourceServerStr}grid_cmd_ack_tab "
					     .  "    WHERE gack_gridid={$gridid} AND gack_stmtid=gcmd_stmtid AND gack_source=gcmd_source) = 0 ";
					break;
				case 'Pending':
					// To filter for only pending commands, we want to find all commands for which
					// the number of servers in the grid that are NOT in either the grid_cmd_ack_tab 
					// or the grid_cmd_errors_tab is greater than zero (that is, there is some server
					// in the grid for which we do not have a result).
					$sql .= "AND "
						 .  "(SELECT count(*) as count "
					     .  "FROM grid_part "
					     .  "WHERE gp_id={$gridid} "
					     .  "AND gp_servid NOT IN "
					     .  "  (SELECT gack_target FROM {$sourceServerStr}grid_cmd_ack_tab " 
					     .  "   WHERE gack_gridid={$gridid} AND gack_stmtid=gcmd_stmtid AND gack_source=gcmd_source) "
					     .  "AND gp_servid NOT IN "
					     .  "   (SELECT gerr_target FROM {$sourceServerStr}grid_cmd_errors_tab "
					     .  "    WHERE gerr_gridid={$gridid} AND gerr_stmtid=gcmd_stmtid AND gerr_source=gcmd_source) " 
					     .  ") > 0 "; 
					break;
			}
		}
    	
		return $sql;
	 }
	
	/*
	 * Use this function to prepare then execute a given SQL query.
	 * For queries that involve dynamic parameters it is safer to use prepared statements
	 * than to put the parameters directly into the query string. 
	 */
	private function doPreparedDatabaseWork($sel, $params, $dbname = 'sysmaster' ){
		return $this->doDatabaseWork($sel, $dbname, false, null, $params);
	}
	
	/**
	 * Use this function to execute statements on the IDS server
	 */
    private function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false,$conn=null,$params=array())
    {
        $ret = array();
		
		if ($conn == null)
        {
        	require_once(ROOT_PATH."lib/database.php");
        	$db = new database($this->idsadmin,$dbname,$this->idsadmin->phpsession->get_dblcname());
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
	 * Return values:
	 * The result of the command will be stored in the $task array as follows:
	 * 		$task['SUCCESS'] --> true/false, based on result of Admin API command execution 
	 * 		$task['RESULT_MESSAGE'] --> success or failure message
	 *      $task['RETURN_CODE'] --> return code of the command
     */
    private function executeSQLAdminAPICommand($task)
    {
        require_once(ROOT_PATH."lib/database.php");
        $db = new database($this->idsadmin,self::SYSADMIN,$this->idsadmin->phpsession->get_dblcname());

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
		
        // Build up SQL statement
		$sql = "execute function admin ( {$command} {$parameters} )";
		// Execute SQL Admin API command
		try
		{
			$stmt = $db->query($sql, false, true);
		} 
		catch (PDOException $e)
		{
			$task['SUCCESS'] = false;
			$task['RETURN_CODE'] = $e->getCode(); 
			$task['RESULT_MESSAGE'] = "{$this->idsadmin->lang("Error")}: {$e->getCode()} - {$e->getMessage()}";
			error_log($task['RESULT_MESSAGE']);
			return $task;
		}
		
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

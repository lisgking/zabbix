<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation, 2010, 2013.  All Rights Reserved
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

/* Services for storage feature */

class storageServer {

	const POOR_PAGE_UTILIZATION = 0;           //corresponds to 0% utilization of a page -- free/empty page
	const FAIR_PAGE_UTILIZATION = 20;          //corresponds to 20% utilization of a page -- partially used page
	const GOOD_PAGE_UTILIZATION = 80;          //corresponds to 80% utilization of a page -- mostly used page
	const EXCELLENT_PAGE_UTILIZATION = 100;    //corresponds to 100% utilization of a page -- fully used page
	
	var $idsadmin;
	
	function __construct()
	{
		define ("ROOT_PATH","../../");
		define( 'IDSADMIN',  "1" );
		define( 'DEBUG', false);
		define( 'SQLMAXFETNUM' , 100 );
		
		// Define server types
		define('STANDARD',0);
		define('PRIMARY',1);
		define('SECONDARY',2);
		define('SDS',3);
		define('RSS',4);

		include_once("../serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");

		require_once(ROOT_PATH."lib/idsadmin.php");
		$this->idsadmin = new idsadmin(true);
		$this->idsadmin->in = array("act" => "storage");
		$this->idsadmin->load_lang("storage");
		
		require_once(ROOT_PATH."lib/feature.php");
		
	}
	
	
	
	function getDatabases()
	{
		$sel = "SELECT trim(name) as name ";
		$sel .= " FROM sysdatabases " ;
		$sel .= " order by name " ;
		$ret = $this->doDatabaseWork($sel,"sysmaster");
		return $ret;
	} 
	
	/**
	 * Get the list of spaces for the database server
	 */
	public function getSpaces () 
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		$sql = "SELECT dbsnum, " .
		" trim(name) as space_name, " .
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
		" END  as dbstype, " .
		" 'false' as selected " .
		" FROM sysdbstab " .
		" ORDER BY dbsnum";

		$dbspaces = $this->doDatabaseWork($sql,"sysmaster");
		return $dbspaces;
	}
	
    /**
     * get a list of pending and completed jobs for compression
     */
    function getJobList($rows_per_page, $page=1, $sort_col = null)
    {
        $res = array();
        
        if ($sort_col == null)
        {
        	// default sort order is by job completion time
        	$sort_col = "end DESC"; 
        }
        
        // Get locale
        $locale = $this->uniqueNonEnglishLocale();
        if (is_array($locale))
        {
        	// If the return value is an array, an error occured.
        	// If no error occured, the return value is just a string of the locale.
        	return $locale;
        }
        
        // Get the connection object, so we run all queries on the same connection.
        $conn = $this->getDBConnection("sysadmin", $locale);
        
        $sel = " SET ISOLATION TO DIRTY READ ";
        $this->doDatabaseWork($sel,"sysadmin",false,$locale, $conn);
        
		/* this part gets the running jobs */
		$sel  = "SELECT (smgr_rowsprocessed/smgr_numrows)*100||'' as msg, ";
		$sel .= "0 as js_id, ";
		$sel .= "smgr_id as js_task, ";
		$sel .= "DBINFO('utc_to_datetime',smgr_starttime)::DATETIME year TO second as start, ";
		$sel .= "DBINFO('utc_to_datetime',smgr_starttime+smgr_remainingtime)::DATETIME year TO second as end, ";
		$sel .= "smgr_opdesc as command, ";
		$sel .= "(trim(smgr_dbname)||':'||trim(smgr_owner)||'.'||trim(smgr_tabname))::char(281) as js_comment, ";
		$sel .= "1 as running, ";
		$sel .= "smgr_rowsprocessed as processed, ";
		$sel .= "smgr_elapsedtime as elapsed, ";
		$sel .= "smgr_numrows as numrows ";
		$sel .= "FROM sysmaster:sysstoragemgr ";
		/* this part gets the completed compress, repack, shrink, and defragment jobs */
		$sel .= "UNION ";
		$sel .= "SELECT TRIM(SUBSTR(ph_bgr_retmsg,0,1000)) as msg, ";
		$sel .= "ph_bgr_id as js_id, ";
		$sel .= "ph_bgr_bg_id as js_task, ";
		$sel .= "ph_bgr_starttime as start, ";
		$sel .= "ph_bgr_stoptime as end, ";
		$sel .= "ph_bg_desc as command, ";
		$sel .= "' ' as js_comment, ";
		$sel .= "0 as running, ";
		$sel .= "-1 as processed, -1 as elapsed, -1 as numrows ";
		$sel .= "FROM sysadmin:ph_bg_jobs_results, sysadmin:ph_bg_jobs ";
		$sel .= "WHERE ";
		$sel .= "ph_bgr_tk_id = (select tk_id from ph_task where tk_name='Job Runner') AND ";
		$sel .= "ph_bg_type='STORAGE OPTIMIZATION JOB' AND ";
		$sel .= "ph_bg_id=ph_bgr_bg_id ";
		/* and this part gets the completed estimate compression jobs */
		$sel .= "UNION ";
		$sel .= "SELECT TRIM(SUBSTR(C.cmd_ret_msg,0,1000)) as msg, ";
		$sel .= "J.js_id, ";
		$sel .= "J.js_task, ";
		$sel .= "J.js_start as start, ";
		$sel .= "J.js_done as end, ";
		$sel .= "TRIM(SUBSTR(J.js_comment,0,500)) as js_comment, ";
		$sel .= "TRIM(SUBSTR(J.js_command,0,500)) as command, ";
		$sel .= "0 as running, ";
		$sel .= "-1 as processed, -1 as elapsed, -1 as numrows ";
		$sel .= "FROM job_status J, ph_task, outer command_history C ";
		$sel .= "WHERE ";
		$sel .= "ABS(js_result) = cmd_number ";
		$sel .= "AND js_task = tk_id ";
		$sel .= "AND tk_group = 'COMPRESSION' ";
		$sel .= "AND js_done is NOT NULL ";
		
		// For the purposes of 'transforming' the query using the $rows_per_page, $page, and $sort_col
		// we need the three parts of our union query to be treated as a single result set.
		// So wrap the whole thing within 'SELECT * ...'
		$transformSQL = "SELECT * FROM ($sel)"; 
		
		$res['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($transformSQL, $rows_per_page, $page, $sort_col),
											"sysadmin", false, $locale, $conn);
        
		$res['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sel), "sysadmin", false, $locale, $conn);
		foreach ($temp as $row)
		{
			$res['COUNT'] = $row['COUNT'];
		}
		
		return $res;
	}
	
	/**
	 * Get all information for the server-level view
	 */
	public function getServerViewInfo($rows_per_page)
	{
		// just call the refreshServerViewInfo() function with default pagination parameters.
		return $this->refreshServerViewInfo($rows_per_page,1,null,null,
									 $rows_per_page,1,null,null,
									 $rows_per_page,1,null,null);
	}
	
	/**
	 * Refresh server-level view data.
	 * We need to pass in all of the 'pagination' parameters for each pod,
	 * so we maintain the user's state.
	 */
	public function refreshServerViewInfo
					($spaces_rows_per_page, $spaces_page, $spaces_sort_col, $spaces_dbsname_pattern,
					 $chunks_rows_per_page, $chunks_page, $chunks_sort_col, $chunks_dbsname_pattern,
					 $storage_pool_rows_per_page, $storage_pool_page, $storage_pool_sort_col, $storage_pool_status_filter)
	{
		$res = array();	
		$res['INFORMATION'] = $this->getServerStorageSummaryInfo();
		$res['DBSPACES'] = $this->getDbspacesInfo($spaces_rows_per_page, $spaces_page, $spaces_sort_col, $spaces_dbsname_pattern);
		$res['CHUNKS'] = $this->getChunkInfo(-1, $chunks_rows_per_page, $chunks_page, $chunks_sort_col, $chunks_dbsname_pattern);
		$res['EXTENTS'] = $this->getTablesAndIndexesInfoMinimized(null);
		if (Feature::isAvailable ( Feature::PANTHER , $this->idsadmin->phpsession->serverInfo->getVersion() ))
		{
			$res['STORAGE_POOL'] = $this->getStoragePoolInfo($storage_pool_rows_per_page, $storage_pool_page, $storage_pool_sort_col,$storage_pool_status_filter);
			$res['STORAGE_POOL_CONFIG'] = $this->getStoragePoolConfig();
		} else {
			$res['STORAGE_POOL'] = array();
			$res['STORAGE_POOL_CONFIG'] = null;
		}
		return $res;
	}
	
	/**
	 * Get all information for the space level view
	 */
	public function getSpacesViewInfo($dbsnum,$rows_per_page)
	{
		$dbsnum = trim($dbsnum);
		$res = array();
		$res['INFORMATION'] = $this->getSpaceSummaryInfo($dbsnum) ;
		$res['CHUNKS'] = $this->getChunkInfo($dbsnum,$rows_per_page);

		$res['EXTENTS'] = $this->getTablesAndIndexesInfoMinimized($dbsnum);
		return $res;
	}
	
	/**
	 * Refresh space-level view data.
	 * We need to pass in all of the 'pagination' parameters for each pod,
	 * so we maintain the user's state.
	 */
	public function refreshSpacesViewInfo($dbsnum,
					 $chunks_rows_per_page, $chunks_page, $chunks_sort_col, $chunks_dbsname_pattern)
	{
		$dbsnum = trim($dbsnum);
		$res = array();
		$res['INFORMATION'] = $this->getSpaceSummaryInfo($dbsnum) ;
		$res['CHUNKS'] = $this->getChunkInfo($dbsnum,$chunks_rows_per_page, $chunks_page, $chunks_sort_col, $chunks_dbsname_pattern);

		$res['EXTENTS'] = $this->getTablesAndIndexesInfoMinimized($dbsnum);
		return $res;
	}
	
	/**
	 * Get a summary information for a specific dbspace. 
	 * This information will be used in the information pod in the spaces view.
	 */
	function getSpaceSummaryInfo ($dbsnum)
	{
		$sql = "SELECT nkeys, " .
	        " sum(npused) as npused, " .
	        " sum(npdata) as npdata " .
	        " FROM sysptnhdr" .
	        " WHERE TRUNC(partnum/1048576,0) = " . $dbsnum .
	        " GROUP BY 1 ";
			
		$result = $this->doDatabaseWork($sql,"sysmaster");	 
        $pginfo =  array(
	        "DATA"  => 0,
	        "INDEX" => 0,
	        "OTHER" => 0,
	        "FREE"  => 0,
	        );
			
        foreach  ($result as $res)
        {
            if ( $res['NKEYS'] == 0 )
            {
                $pginfo['DATA']  += $res['NPDATA'];
                $pginfo['OTHER'] += $res['NPUSED'] - $res['NPDATA'];
            }
            else if ( $res['NKEYS'] == 1 && $res['NPDATA'] == 0)
            {
                $pginfo['INDEX']  += $res['NPUSED'];
            }
            else if ( $res['NKEYS'] > 0 )
            {
                $pginfo['DATA']  += $res['NPDATA'];
                $pginfo['INDEX'] += $res['NPUSED'] - $res['NPDATA'];
            }
        }
        $sql =  "SELECT " .
		        " TRUNC( sum(A.nfree * (C.pagesize/ A.pagesize) )  ) as nfree, " .
		        " TRUNC( sum(A.chksize* (C.pagesize/ A.pagesize) ) ) as totalsize " .
		        " FROM syschktab A, sysdbstab B, syschktab C " .
		        " WHERE A.dbsnum = B.dbsnum " .
		        " AND A.dbsnum = " . $dbsnum .
		        " AND C.chknum=1 " .
		        " GROUP BY A.dbsnum ";

		$result = $this->doDatabaseWork($sql,"sysmaster");
        foreach ($result as $res)
        {
            $pginfo['FREE_SPACE'] = $res['NFREE'];
            $tmp=$pginfo['FREE_SPACE']+$pginfo['DATA']+$pginfo['INDEX'];
            if ( ($tmp + $pginfo[ 'OTHER' ]) < $res['TOTALSIZE'] )
            {
            	$pginfo[ 'OTHER' ] = $res['TOTALSIZE'] - $tmp;
			}
        }
        
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		
		$sql = "SELECT B.name, ".
		       "(B.pagesize / 1024) AS pagesize, ";

		if (Feature::isAvailable ( Feature::PANTHER , $this->idsadmin->phpsession->serverInfo->getVersion() ))
		{
			$sql .= "extend_size, ".  
			   "create_size, ";
			$group_by_clause = ", extend_size, create_size ";
		} else {
			$sql .= "0 as extend_size, " .
				"0 AS create_size, ";
			$group_by_clause = "";
		}
		
		$sql .= "sum(A.reads) AS reads, ".
			   "sum(A.writes) AS writes, ".
			   "CASE " .
		       " WHEN bitval(B.flags,'0x4')>0 " .
		       "   THEN 'disabled' " .
		       " WHEN bitand(B.flags,3584)>0 " .
		       "   THEN 'recovering' " .
		       " ELSE " .
		       "   'operational' " .
		       " END  as status, " .
		       "CASE " .
		       " WHEN (bitval(B.flags,'0x10')>0 AND bitval(B.flags,'0x2')>0)" .
		       "   THEN 'mirrored_blobspace' " .
		       " WHEN bitval(B.flags,'0x10')>0 " .
		       "   THEN 'blobspace' " .
		       " WHEN bitval(B.flags,'0x2000')>0 AND bitval(B.flags,'0x8000')>0" .
		       "   THEN 'temp_sbspace' " .
		       " WHEN bitval(B.flags,'0x2000')>0 " .
		       "   THEN 'temp_dbspace' " .
		       " WHEN (bitval(B.flags,'0x8000')>0 AND bitval(B.flags,'0x2')>0)" .
		       "   THEN 'mirrored_sbspace' " .
		       " WHEN bitval(B.flags,'0x8000')>0 " .
		       "   THEN 'sbspace' " .
		       " WHEN bitval(B.flags,'0x2')>0 " .
		       "   THEN 'mirrored_dbspace' " .
		       " ELSE " .
		       "   'dbspace' " .
		       " END  as dbstype, " . 
		       " sum(chksize*{$defPagesize}) as totalsize , " .
		       " sum(decode(mdsize,-1,nfree,udfree) * A.pagesize) AS free, ". // pagesize is $defPagesize for regular dbspaces and blob page size for blobspaces
			   "sum(A.pagesread) AS pagesread, ".
			   "sum(A.pageswritten) AS pageswritten, ".
			   "TRUNC(sum(A.readtime/1000000),3) AS readtime, ".  // readtime in microseconds, so covert to seconds and truncate to 3 decimal places
			   "TRUNC(sum(A.writetime/1000000),3) AS writetime ". // writetime in microseconds, so covert to seconds and truncate to 3 decimal places
			   "FROM syschktab A, sysdbstab B " .
			   "WHERE A.dbsnum = B.dbsnum AND A.dbsnum = {$dbsnum} ".
			   "GROUP BY name, A.dbsnum, B.pagesize, B.flags $group_by_clause";
		
		$temp = $this->doDatabaseWork($sql,"sysmaster");
		
		$growth_rate = 0;
		
		if (Feature::isAvailable ( Feature::PANTHER , $this->idsadmin->phpsession->serverInfo->getVersion() ))
		{
			$total_pages = $temp[0]['TOTALSIZE'] / $defPagesize;

            $sql = "SELECT MAX(id) AS most_recent_measure_id FROM mon_table_profile";
            $maxid = $this->doDatabaseWork($sql,"sysadmin");
            // mon_table_profile is by default setup to get measurements once a day and is purged once a week.
            // So, the expectation is to have a max of 7 measurements per dbspace. But, there has been a customer case
            // where there  were a 1000 measurements per dbspace. So, the query below is updated to use only the last
            // 7 measurements (CQ 242026).
            $mr7th = $maxid[0]['MOST_RECENT_MEASURE_ID'] - 7;

			$sql = "SELECT TRUNC(((avg(pgs) / ((max(tm)::interval day to day)::char(20))::integer) / {$total_pages}) * 100, 1) AS growth_rate " .
				   "FROM ( " .
				   "SELECT b.id, sum(b.npdata - a.npdata) AS pgs, ( select tk_frequency from sysadmin:ph_task where tk_name = 'mon_table_profile') AS tm " .
				   "FROM sysadmin:mon_table_profile  b, sysadmin:mon_table_profile a " .
				   "WHERE b.partnum >= ({$dbsnum} * 1048576) AND b.partnum < ( ({$dbsnum}+1) * 1048576) " .
				   "AND b.id + 1 = a.id and b.id > {$mr7th} " .
				   "GROUP by 1 ) ";
			try {
				$temp2 = $this->doDatabaseWork($sql,"sysadmin", true);
			} catch (Exception $e) {
				if($e->getCode() == -229) 
				{
					// This is the case when rootdbs is full and ODBC failed to create temp space.
					// Just print debug message to error log and then proceed.  We'll not compute 
					// growth rate when the database server is out of space.
					error_log("storageServer: -229 error when running growth rate for dbspace, indicating that the server is probably out of space.  Skipping computation of growth rate.");
					$temp2 = array();
				} else {
					// If it wasn't a -229 error due to space issues, re-run the original query again
					// and this time let trigger_error send the error to be displayed to the user in Flex
					$temp2 = $this->doDatabaseWork($sql,"sysadmin");
				}
			}
			$growth_rate = (count($temp2) == 0 || $temp2[0]['GROWTH_RATE'] == null) ? 0 : $temp2[0]['GROWTH_RATE'];
		}
		
		$result = array_merge($pginfo, $temp[0]);
		$result['GROWTH_RATE'] = $growth_rate;

		return $result;
	}
	
	public function getTablesWithTextOrByteColumns($tables)
	{
		$textByteTables = array();
		$tables = unserialize($tables);

		foreach($tables as $table)
		{
			$sql = "select count(a.colname) as BYTES_OR_BLOBS from syscolumns a, systables b where a.tabid = b.tabid " .
			"  AND b.tabname = '" . $table['tabname'] . 
			"' AND b.owner = '" . $table['owner'] . 
			"' AND  (a.coltype = 11 OR a.coltype = 12) ";

			$result = $this->doDatabaseWork($sql, $table['dbname'], true);
			if($result[0]['BYTES_OR_BLOBS'] > 0)
			{
				$textByteTables[] = $table;
			}
		}
		return $textByteTables;
	}

	public function setOptimizePolicies ($qry)
	{
		$result = array();
		$result['CODE'] = 0;
		try
		{
			$this->doDatabaseWork($qry,"sysadmin", true);
		}
		catch (PDOException $e)
		{
			$result['CODE'] = $e->getCode();
			$result['MESSAGE'] = $e->getMessage();
		}
		
		return $result;
	}
	
	public function getOptimizePolicies () 
	{
		$optPolicies = array();

		$qry = "SELECT tk_start_time, " .
          " NVL(tk_stop_time,'NEVER') as stop_time, " .
          " tk_frequency, " .
          " tk_Monday, tk_Tuesday, tk_Wednesday, " .
          " tk_Thursday, tk_Friday," .
          " tk_Saturday, tk_Sunday, " .
          " tk_enable " .
          " FROM  ph_task " .
          " WHERE tk_name = 'auto_crsd'" .
          " AND tk_type <> 'QUEUEDJOB' " ;

        $res1 = $this->doDatabaseWork($qry,"sysadmin");
		$optPolicies['TASK_INFO'] = $res1[0];
		
		$qry = "SELECT " .
          " name, " .
          " value " .
          " from " .
          " ph_task, ph_threshold " .
          " WHERE tk_name = 'auto_crsd'" .
          " AND task_name = 'auto_crsd'" .
          " AND task_name = tk_name";
		  
		$res2 = $this->doDatabaseWork($qry,"sysadmin");
		$optPolicies['TASK_PARAMETERS'] = $res2;
		
		return $optPolicies;
	}
	
	/**
	 * Get information for the expanded Tables and Indexes pod...
	 * information about tables, indexes, and extents
	 * 
	 * @dbsnum = dbspace number.  if null, queries all dbspaces
	 * @dbname = database name
	 * @rows_per_page = rows per page
	 * @page = current page number
	 * @sort_col = sort column for order by clause
	 * @tabname_pattern = table name to search
	 * @filter = filter for the results.  valid options are 'all', 'tables', 'indexes', 
	 *           'compressed_tables', or 'uncompressed_tables'
	 * @include_catalogs = true/false whether to include system catalog tables.
	 * @is_timeseries = true/false whether currently running in the timeseries view.
	 */
	function getTablesAndIndexesInfo($dbsnum, $dbname, $rows_per_page, $page, $sort_col = null, 
									$tabname_pattern = null, $filter = null, $include_catalogs = false, 
									$is_timeseries = false)
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		
		/* get the locale of the database */
		$locale = $this->getDatabaseLocale($dbname);
		$result = array();
		
		if ($sort_col == null)
		{
			$sort_col = "USED_SIZE DESC";
		}

		$filter = strtolower($filter);	
		
		// Auto compression columns,  for server versions >= 12.10 only
		$auto_compression_columns = "";
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			// Auto compressed is indicated by sysptnhdr.flags2 = '0x00000001'
			$auto_compression_columns = ", decode ( sum(bitand(p.flags2,'0x00000001')), 0, 'no', count(*), 'yes', 'some') as auto_compressed ";
			// Also need to explicitly figure out if any of the table fragments are uncompressed
			$auto_compression_columns .= ", decode ( sum(decode( bitand(p.flags,'0x08000000') + bitand(p.flags2,'0x00000001'),0,1,0) ), 0, 'no', count(*), 'yes', 'some') as uncompressed ";
		}
		
		$result['PAGE_USAGE_DATA_AVAILABLE'] = $this->checkIfMonPageUsageTableExists();
		if ($result['PAGE_USAGE_DATA_AVAILABLE'])
		{
		
			$sql = "SELECT "
				 . "{+INDEX(P sysptnhdridx) } "
				 . "dbsname as dbname, "
				 . "owner, "
				 . "tabname, "
				 . "type, "
				 . "min(run_time) as run_time, "
				 . "min( dbinfo('UTC_TO_DATETIME', P.created) ) as created, "
				 . "sum(U.nextns) as extents, " 
				 . "sum(U.nrows) as nrows, "
				 . "sum(U.nptotal) as nptotal, "
				 . "sum(U.npused) as npused, "
				 . "sum(U.npused * P.pagesize) as used_size, "
				 . "sum(U.free) as free, "
				 . "sum(U.partly_used) as partly_used, "
				 . "sum(U.mostly_used) as mostly_used, "
				 . "sum(U.very_full) as very_full, "
				 . "0 as is_fragment, "
				 . "decode ( sum( decode( bitand(p.flags,'0x08000000'),0,0,1) ), 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed "
				 . $auto_compression_columns
				 . "FROM sysmaster:systabnames T, mon_page_usage U, sysmaster:sysptnhdr P "
				 . "WHERE  U.partnum = T.partnum " 
				 . "AND U.partnum = P.partnum "
				 . "AND U.run_time > dbinfo('UTC_TO_DATETIME', P.created) " // Handles case where table dropped & re-created with same partnum
				 . "AND U.run_time = ( SELECT max(IU.run_time) FROM mon_page_usage IU WHERE U.partnum = IU.partnum ) "
				 . "AND dbsname = '$dbname' "
				 . (($dbsnum != null) ? "AND T.partnum >= 1048576 * {$dbsnum} AND T.partnum < 1048576 * ({$dbsnum} + 1) " : "" )
				 . (($tabname_pattern != null) ? "AND tabname like '%{$tabname_pattern}%' " : "")
				 . (($include_catalogs)? "":"AND tabname not in (select tabname from systables where tabid <= 99) ")
				 . (($filter == 'tables')? "AND U.type = 'T' ":"")
				 . (($filter == 'indexes')? "AND U.type = 'I' ":"")
				 . (($filter == 'compressed_tables')? "AND bitand(p.flags,'0x08000000')>0 ":"")
				 . (($filter == 'uncompressed_tables')? "AND bitand(p.flags,'0x08000000')=0 ":"");
				 
			if ($is_timeseries)
			{
			 	$tsTablesQuery = $this->selectTsTablesQuery($dbname, $tabname_pattern, true);
			 	$sql .= " AND '{$dbname}'||':'||trim(owner)||'.'||trim(tabname) in ($tsTablesQuery) "; 
			}
				 
			$sql .= "GROUP BY dbsname, owner, tabname, type "; 
				 
			// We need to UNION this with a query that gets all tables and indexes that are not in the 
			// mon_page_usage table (e.g. because some tables/indexes may have been created since the 
			// last time the mon_page_usage data was updated).
			$sql .= " UNION "
				 . "SELECT "
				 . "{+INDEX(P sysptnhdridx) } "
				 . "dbsname as dbname, "
				 . "owner, "
				 . "tabname, "
				 . "CASE WHEN P.nkeys = 1 AND P.npused > 1 AND P.npdata = 0 AND P.partnum <> P.lockid AND bitand(P.flags,4) = 0 THEN 'I' ELSE 'T' END as type, "
				 . "CURRENT::DATETIME YEAR TO SECOND as run_time, "
				 . "min( dbinfo('UTC_TO_DATETIME', P.created) ) as created, "
				 . "sum(P.nextns) as extents, " 
				 . "sum(P.nrows) as nrows, "
				 . "sum(P.nptotal) as nptotal, "
				 . "sum(P.npused) as npused, "
				 . "sum(P.npused * P.pagesize) as used_size, "
				 . "0 as free, "
				 . "0 as partly_used, "
				 . "0 as mostly_used, "
				 . "0 as very_full, "
				 . "0 as is_fragment, "
				 . "decode ( sum( decode( bitand(p.flags,'0x08000000'),0,0,1) ), 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed "
				 . $auto_compression_columns
				 . "FROM sysmaster:systabnames T, sysmaster:sysptnhdr P "
				 . "WHERE  T.partnum = P.partnum " 
				 . "AND T.partnum NOT IN (SELECT A.partnum FROM mon_page_usage A, sysmaster:sysptnhdr B WHERE A.partnum = B.partnum AND A.run_time > dbinfo('UTC_TO_DATETIME', B.created)) "
				 . "AND dbsname = '$dbname' "
				 . (($dbsnum != null) ? "AND t.partnum >= 1048576 * {$dbsnum} AND t.partnum < 1048576 * ({$dbsnum} + 1) " : "" )
				 . (($tabname_pattern != null) ? "AND tabname like '%{$tabname_pattern}%' " : "")
				 . (($include_catalogs)? "":"AND tabname not in (select tabname from systables where tabid <= 99) ")
				 . (($filter == 'tables')? "AND NOT (P.nkeys = 1 AND P.npused > 1 AND P.npdata = 0 AND P.partnum <> P.lockid AND bitand(P.flags,4) = 0) ":"")
				 . (($filter == 'indexes')? "AND (P.nkeys = 1 AND P.npused > 1 AND P.npdata = 0 AND P.partnum <> P.lockid AND bitand(P.flags,4) = 0) ":"")
				 . (($filter == 'compressed_tables')? "AND bitand(p.flags,'0x08000000')>0 ":"")
				 . (($filter == 'uncompressed_tables')? "AND bitand(p.flags,'0x08000000')=0 ":"");
				 
			if ($is_timeseries)
			{
			 	$tsTablesQuery = $this->selectTsTablesQuery($dbname, $tabname_pattern, true);
			 	$sql .= " AND '{$dbname}'||':'||trim(owner)||'.'||trim(tabname) in ($tsTablesQuery) "; 
			}
				 
			$sql .= "GROUP BY dbsname, owner, tabname, 4"; 
			
			$row = $this->doDatabaseWork($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col),'sysadmin',false,$locale);

			// We also need to get the total number of tables & indexes returned by the above query
			$result['COUNT'] = 0;
			$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), 'sysadmin');
			foreach ($temp as $t)
			{
				$result['COUNT'] = $t['COUNT'];
			}
			
		} else {
			// If the sysadmin:mon_page_usage table does not exist, use a version of the query that
			// does not return page usage data.
			$temp = $this->getTablesAndIndexesInfo_noPageUsage($dbsnum, $dbname, $rows_per_page, $page, $sort_col, 
				$tabname_pattern, $filter, $include_catalogs, $is_timeseries);
			$row = $temp['DATA'];
			$result['COUNT'] = $temp['COUNT'];
		}

		// Unless $rows_per_page = -1 indicating all rows, we also want to filter all of the following
		// functions by the tabnames returned by the above query.
		$tabnames = "";
		if ($rows_per_page != -1)
		{
			foreach ($row as $r)
			{
				$tabnames .= "'" . trim($r['TABNAME']) . "',";
			}
			if (strlen($tabnames) == 0)
			{
				$tabnames = null;
			} else {
				$tabnames = substr($tabnames,0,strlen($tabnames) - 1);  // Remove last comma
			}
		} else {
			$tabnames = null;
		}

		$compressionJobs = $this->getRunningCompressionJobs($tabnames,$locale);
		$estimates = $this->getEstimatesCompression($dbname, $dbsnum, $tabnames, false, $locale); 
		$fragments = $this->getFragmentsForTables($dbname,$dbsnum, $tabnames);
		$min_rows_compression = $this->getMinRowsCompression();
		
		$tables_seen = array();
		
		foreach ( $row as $k => $v )
        {
        	$estimate = array();
            $key = trim($v['DBNAME']).":".trim($v['OWNER']).".".trim($v['TABNAME']);
            
            // Check for duplicates
            if (isset($tables_seen[$key]))
            {
            	// Prevents us from getting duplicate tables in the list in OAT
            	unset($row[$k]);
            	continue;
            }
            
            $tables_seen[$key] = $k;
            
            $row[$k]['RUNNING'] = isset ($compressionJobs[$key]);

            if ( !$row[$k]['RUNNING'])
            {
                $estimate = $estimates[$key];
            }

            $row[$k]['ESTIMATES'] = $this->parse_estimate($estimate);
			
			//if the table has only one fragment, then there is no need to display its fragments because the table itself is one fragment.
			if(isset($fragments[$key]) && count($fragments[$key]) > 1)
			{
				for ($i = 0; $i < count($fragments[$key]); $i++)
				{
					$fragments[$key][$i]['RUNNING'] = $row[$k]['RUNNING'];
				}
				
				$row[$k]['FRAGMENTS'] = $fragments[$key];
				
				for ($i = 0; $i < count($row[$k]['FRAGMENTS']); $i++)
				{
					$estimate = array();
					if ( !$row[$k]['FRAGMENTS'][$i]['RUNNING'] )
		            {
		                $estimate = $estimates[hexdec($row[$k]['FRAGMENTS'][$i]['PARTNUM'])];
		            }
					$row[$k]['FRAGMENTS'][$i]['ESTIMATES'] = $this->parse_estimate($estimate);
				}
			}
			else
			{
				$row[$k]['FRAGMENTS'] = array();
			}
        }

		$result['DATA'] = $row;
		return $result;
	}

	/**
	 * This is an older version of the tables and indexes query that excludes the 
	 * page usage data.  We have to use this version of the query if the 
	 * sysadmin:mon_page_usage does not exist (which could happen if read only
	 * groups prevent OAT from creating that task in the scheduler).
	 * 
	 * This version of the query will return zeros for the page usage columns
	 * (free, partly_used, mostly_used, very_full).
	 */
	private function getTablesAndIndexesInfo_noPageUsage($dbsnum, $dbname, $rows_per_page, $page, $sort_col = null, 
					$tabname_pattern = null, $filter = null, $include_catalogs = false, $is_timeseries = false)
	{
	
		$result = array();
		
		if ($sort_col == null)
		{
			$sort_col = "NROWS DESC";
		}
		
		$filter = strtoupper($filter);			
		$filterClause_part1 = "";
		$filterClause_part2 = "";
		if ($filter != null)
		{
			switch ($filter)
			{
				case 'TABLES':
					$filterClause_part2 = " AND F.fragtype == 'T' ";
					break;
				case 'INDEXES':
					$filterClause_part2 = " AND F.fragtype == 'I' ";
					break;
				case 'COMPRESSED_TABLES':
					$filterClause_part1 = " AND bitand(p.flags,'0x08000000') > 0 ";
					$filterClause_part2 = $filterClause_part1 . " AND F.fragtype == 'T' ";
					break;
				case 'UNCOMPRESSED_TABLES':
					$filterClause_part1 = " AND bitand(p.flags,'0x08000000') = 0 ";
					$filterClause_part2 = $$filterClause_part1 . " AND F.fragtype == 'T' ";
					break;
			}
		}
		
		// Auto compression columns,  for server versions >= 12.10 only
		$auto_compression_columns = "";
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			// Auto compressed is indicated by sysptnhdr.flags2 = '0x00000001'
			$auto_compression_columns = ", decode ( sum(bitand(p.flags2,'0x00000001')), 0, 'no', count(*), 'yes', 'some') as auto_compressed ";
			// Also need to explicitly figure out if any of the table fragments are uncompressed
			$auto_compression_columns .= ", decode ( sum(decode( bitand(p.flags,'0x08000000') + bitand(p.flags2,'0x00000001'),0,1,0) ), 0, 'no', count(*), 'yes', 'some') as uncompressed ";
		}

		// Two SQL statements need to be union'ed together to get the full result set. 
		// But keep them separate for now because depending on the $filter, we may
		// not need both parts.
		
		// This part of the select gets unfragmented tables
		$sql_part1 = "SELECT T.tabname "
			. ", '{$dbname}' as dbname " 
			. ", T.owner "
			. ", 'T' as type "
			. ", 0 AS free "
			. ", 0 AS partly_used "
			. ", 0 AS mostly_used "
			. ", 0 AS very_full "
			. ", 0 AS is_fragment " 
			. ", sum(p.npused) as npused "
			. ", sum(p.npused * p.pagesize) as used_size "
			. ", sum(p.nrows) as nrows "
			. ", sum(p.nptotal) as nptotal "
			. ", sum(p.nextns) as extents "
			. ", max(decode(bitand(p.flags,'0x08000000'),0,'no','yes')) as compressed "
			. $auto_compression_columns
			. "FROM systables T, sysmaster:sysptnhdr P "
			. "WHERE T.partnum >0 "
			. (($include_catalogs)? "":"AND T.tabid > 99 ")
			. "AND P.partnum = T.partnum "
			. (($dbsnum != null) ? "AND t.partnum >= 1048576 * {$dbsnum} AND t.partnum < 1048576 * ({$dbsnum} + 1) " : "" ) 
			. $filterClause_part1
			. (($tabname_pattern != null) ? "AND t.tabname like '%{$tabname_pattern}%' " : "");
		
		if ($is_timeseries)
		{
			$tsTablesQuery = $this->selectTsTablesQuery($dbname, $tabname_pattern, true);
			$sql_part1 .= " AND '{$dbname}'||':'||trim(owner)||'.'||trim(tabname) in ($tsTablesQuery) ";
		}

		$sql_part1 .= " GROUP BY T.tabname, dbname, T.owner, 4, 5, 6, 7, 8, 9 ";
		
		// This part of the select gets fragmented tables and detachted indexes.
		// (Note: attached indexes will not be returned... we're going to live with this
		// limitation for now).
		$sql_part2 = "SELECT decode(F.fragtype,'T',T.tabname, F.indexname) as tabname "
			. ", '{$dbname}' as dbname " 
			. ", T.owner "
			. ", F.fragtype as type "
			. ", 0 AS free "
			. ", 0 AS partly_used "
			. ", 0 AS mostly_used "
			. ", 0 AS very_full "
			. ", 0 AS is_fragment " 
			. ", sum(p.npused) as npused "
			. ", sum(p.npused * p.pagesize) as used_size "
			. ", sum(p.nrows) as nrows "
			. ", sum(p.nptotal) as nptotal " 
			. ", sum(p.nextns) as extents "
			. ", decode ( sum(bitand(p.flags,'0x08000000')), 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed "
			. $auto_compression_columns
			. "FROM systables T, sysfragments F, sysmaster:sysptnhdr P "
			. "WHERE P.partnum > '0xFFFF' "
			. (($include_catalogs)? "":"AND T.tabid > 99 ")
			. "AND T.tabid = F.tabid "
			. "AND F.partn = P.partnum "
			. $filterClause_part2
			. (($dbsnum != null) ? "AND P.partnum >= 1048576 * {$dbsnum} AND P.partnum < 1048576 * ({$dbsnum} + 1) " : "" )
			. (($tabname_pattern != null) ? "AND t.tabname like '%{$tabname_pattern}%' " : "") ;
			
		if ($is_timeseries)
		{
			$tsTablesQuery = $this->selectTsTablesQuery($dbname, $tabname_pattern, true);
			$sql_part2 .= " AND '{$dbname}'||':'||trim(owner)||'.'||trim(tabname) in ($tsTablesQuery) ";
		}
		
		$sql_part2 .= "GROUP BY 1, 2, T.owner, 4, 5, 6, 7, 8, 9 ";
		
		if ($filter == 'INDEXES')
		{
			// if we just want indexes, we don't even need the first part of the union
			$sql = $sql_part2;
		} else {
			// otherwise we want to UNION the two queries
			$sql = "SELECT * FROM ( " . $sql_part1 . " UNION " . $sql_part2 . ")";
		}

		$result['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col),$dbname);

		// We also need to get the total number of tables & indexes returned by the above query
		$result['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), $dbname);
		foreach ($temp as $t)
		{
			$result['COUNT'] = $t['COUNT'];
		}
		
		return $result;
	}
	
	/**
	 * Make a select statement that returns all the Timeseries Tables (including virtual tables) of a specific database. 
	 *
	 * @param object $dbname
	 * @param object $tabname_pattern[optional]
	 * @param boolean $includeIndexes
	 * @param boolean $includeVirtual
	 * 
	 * @return sql of the select statement 
	 */
	function selectTsTablesQuery($dbname, $tabname_pattern = NULL, $includeIndexes = false, $includeVirtual = false)
	{
		//Get the Time Series base tables
		$sel = "SELECT '{$dbname}'||':'||trim(owner)||'.'||trim(tabname) "
			 . "FROM {$dbname}:systables WHERE "   
				 . "tabid in (select tabid from {$dbname}:syscolumns where extended_id in " //only select the tables that have timeseries columns
				 . "(select extended_id from {$dbname}:sysxtdtypes "
				 . "where type = (select type from {$dbname}:sysxtdtypes where name = 'timeseries'))) ";
		
		//Get the virtual tables	 
		$sel2 =  "SELECT '{$dbname}'||':'||trim(owner)||'.'||trim(tabname) " 
			 . "FROM {$dbname}:systables WHERE am_id in (select am_id from sysams where am_name = 'ts_vtam') ";   // Do not show sequences in table list
		
		//Get the indexes of the TS tables
		$sel3 =  "SELECT '{$dbname}'||':'||trim(owner)||'.'||trim(idxname) " 
			 . "FROM {$dbname}:sysindices WHERE " 
			 . "tabid in (select tabid from {$dbname}:syscolumns where extended_id in " //only select the tables that have timeseries columns
			 . "(select extended_id from {$dbname}:sysxtdtypes "
			 . "where type = (select type from {$dbname}:sysxtdtypes where name = 'timeseries'))) ";
			 
		if ($tabname_pattern != NULL)
		{
			$sel .= " and tabname like '%{$tabname_pattern}%' ";
			$sel2 .= " and tabname like '%{$tabname_pattern}%' ";
			$sel3 .= " and idxname like '%{$tabname_pattern}%' ";
		}
		
		$qry = "";
		
		if($includeVirtual)
		{
			$qry = $sel . " UNION " . $sel2;
		}
		else
		{
			$qry = $sel;
		}
		
		if($includeIndexes == true)
		{
			$qry .= " UNION " . $sel3;
		}

		return $qry;
	}

	
	
	/**
	 * Returns whether sysadmin:mon_page_usage table exists.
	 */
	private function checkIfMonPageUsageTableExists()
	{
		$sql = "select count(*) as count from systables where tabname = 'mon_page_usage'";
		$res = $this->doDatabaseWork($sql,'sysadmin');
		return ($res[0]['COUNT'] > 0);
	}
	 
	
	/**
	 * Take a fragment or table/index objects and return a comma seperated names/partnum
	 * @return partnums if the data are fragments, tables/index names othewise
	 * @param object $items fragemnts or tables and indexes
	 * @param object $numb number of items to work with an an array
	 * @param object $index the index of the first item to strat working with 
	 */
	function getCommaSeperatedNames($items, $index, $numb)
	{
		$tabnames = "";
		for ($i = $index; $i <= $index + $numb; $i++)
		{
			if($tabnames != "")
			{
				$tabnames .= ",";
			}
			$tabnames .=  (($items[$i]["isfragment"] == '0') ? ("'" . $items[$i]['tabname'] . "'") : hexdec($items[$i]['partnum'])) ;
		}

		return $tabnames;
	}
	
	function getFragmentsForTables($dbname,$dbsnum, $tabnames = null, $partnums = null)
    {
        
        // Get fragments for tables
        $sel  = " SELECT ti_nptotal AS nptotal ";
		$sel .= ", 1 AS is_fragment ";
        $sel .= ", 'T' AS type ";
        $sel .= ", ti_npdata AS npused ";
        $sel .= ", ti_nrows  AS nrows";
		$sel .= ", ti_nextns AS nextents";
        $sel .= ", 0 as est ";
        $sel .= ", '{$dbname}' as dbname  ";
        $sel .= ", 0 as running  ";
        $sel .= ", decode ( sysmaster:bitval(ti_flags,'0x08000000') , 1, 'yes' ,'no') as compressed";
        $sel .= ((Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))? ", decode ( sysmaster:bitval(p.flags2,'0x00000001') , 1, 'yes' ,'no') as auto_compressed":"");
        $sel .= ((Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))? ", decode ( sysmaster:bitval(ti_flags,'0x08000000') + sysmaster:bitval(p.flags2,'0x00000001') , 0, 'yes' ,'no') as uncompressed":"");
        $sel .= ", t2.partnum  ";
        $sel .= ", dbspace  ";
        $sel .= ", tabname  ";
        $sel .= ", owner ";
        $sel .= " from sysmaster:systabinfo ,";
        $sel .= ((Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))? " sysmaster:sysptnhdr p, ": "");
		
		if($dbsnum != null)
		{
			$sel .= " (select unique tabname as tname, t.partnum from sysmaster:systabnames t " .
			        " where t.partnum >= 1048576 * {$dbsnum} AND t.partnum < 1048576 * ({$dbsnum} + 1)) t, ";
		}
		
		$sel .= " (select hex(decode(partnum , 0 , partn , partnum )) , tabname as tabname , systables.tabid ";
        $sel .= " , trim(owner) as owner , tabtype , pagesize , trim(partition) as dbspace ";
        $sel .= " from systables , outer sysfragments";
        $sel .= " where";
        $sel .= " systables.tabid = sysfragments.tabid";
        $sel .= " and fragtype  = 'T'";
        $sel .= ") as  t2 (partnum , tabname , tabid , owner , tabtype , pagesize , dbspace)";
        $sel .= " where";
        $sel .= " ti_partnum = t2.partnum";
        $sel .= ((Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))? " and ti_partnum = p.partnum":"");
        
		if($dbsnum != null)
		{
			$sel .= " AND ti_partnum = t.partnum";
		    $sel .= " AND t2.tabname = t.tname";
		}
		
		$sel .= (($tabnames != null) ? " AND t2.tabname in ({$tabnames})" : "");
		$sel .= (($partnums != null) ? " AND t2.partnum in ({$partnums})" : "");
		
		// UNION query for tables fragments with query for index fragments
		$sel .= " UNION ";
		
		// Get fragments for fragmented indexes
		$sel .= " SELECT ti_nptotal AS nptotal ";
		$sel .= ", 1 AS is_fragment ";
		$sel .= ", 'I' AS type ";
		$sel .= ", ti_npused AS npused ";
		$sel .= ", ti_nrows  AS nrows";
		$sel .= ", ti_nextns AS nextents";
		$sel .= ", 0 as est ";
		$sel .= ", '{$dbname}' as dbname  ";
		$sel .= ", 0 as running  ";
		$sel .= ", decode ( sysmaster:bitval(ti_flags,'0x08000000') , 1, 'yes' ,'no') as compressed";
		$sel .= ((Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))? ", decode ( sysmaster:bitval(p.flags2,'0x00000001') , 1, 'yes' ,'no') as auto_compressed":"");
		$sel .= ((Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))? ", decode ( sysmaster:bitval(ti_flags,'0x08000000') + sysmaster:bitval(p.flags2,'0x00000001') , 0, 'yes' ,'no') as uncompressed":"");
		$sel .= ", t2.partnum  ";
		$sel .= ", dbspace  ";
		$sel .= ", tabname  ";
		$sel .= ", owner ";
		$sel .= " from sysmaster:systabinfo ,";
		$sel .= ((Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))? " sysmaster:sysptnhdr p, ": "");
		
		if($dbsnum != null)
		{
			$sel .= " (select unique tabname as tname, t.partnum from sysmaster:systabnames t " .
			        " where t.partnum >= 1048576 * {$dbsnum} AND t.partnum < 1048576 * ({$dbsnum} + 1)) t, ";
		}
		
		$sel .= " ( select hex(partn), idxname as tabname , sysindices.tabid ";
		$sel .= "  , trim(owner) as owner , 'I' as tabtype , pagesize , trim(partition) as dbspace ";
		$sel .= "  from sysindices, ";
		$sel .= "  (select * from sysfragments where indexname in ";
		$sel .= "     (select indexname from sysfragments where fragtype = 'I' group by indexname having count(*) > 1) ";
		$sel .= "   ) as f";
		$sel .= " where";
		$sel .= "  sysindices.idxname = f.indexname";
		$sel .= ") as  t2 (partnum , tabname , tabid , owner , tabtype , pagesize , dbspace)";
		$sel .= " where";
		$sel .= " ti_partnum = t2.partnum";
		$sel .= ((Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))? " and ti_partnum = p.partnum":"");
		
		if($dbsnum != null)
		{
			$sel .= " AND ti_partnum = t.partnum";
		    $sel .= " AND t2.tabname = t.tname";
		}
		
		$sel .= (($tabnames != null) ? " AND t2.tabname in ({$tabnames})" : "");
		$sel .= (($partnums != null) ? " AND t2.partnum in ({$partnums})" : "");		
		
		$rows = $this->doDatabaseWork($sel,$dbname);
		
		if($partnums == null)
		{
			$fragments = array();
		
			foreach ($rows as $row)
			{
				$key = trim($row['DBNAME']) . ":" . trim($row['OWNER']) . "." . trim($row['TABNAME']);
				if(!isset($fragments[$key]))
				{
					$fragments[$key] = array();
				}
				$fragments[$key][] = $row;
			}
	
			return $fragments;
		}
		else
		{
			return $rows;
		}
    }
	
    /**
     * Estimate compression and update page usage estimates for selected tables.
     * 
     * @param $tables - table/fragment information
     * @param $dbname
     */
	function estimateCompression($tables, $dbname)
    {
        $tables = unserialize($tables);
		$res = array();
		
		/* get the locale of the database */
		$locale = $this->getDatabaseLocale($dbname);
		
		foreach ($tables as $table)
		{
			// Update page usage estimates
			$this->updatePageUsageEstimate($table['partnum'], $table['tabname'], $table['owner'], $table['dbname'], $locale);
			
			// Update compression estimate
			// Estimates always possible for tables and table fragments.
			// Estimates only available for indexes for server versions >= CENTAURUS.
			$this->idsadmin->phpsession->set_serverInfo($this->idsadmin); 
			if (($table['type'] != "I") || 
				($table['type'] == "I" && Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion())) )
			{
				$res[$table['partnum']] = $this->estimateCompressionForItem($table, $locale);
			}
		}

        return $res;
    }
	
	function estimateCompressionForItem($table, $locale = null)
    {
		$dbname = $table['dbname'];
		$owner = $table['owner'];
		$tabname = $table['tabname'];
		$partnum = hexdec($table['partnum']);
		$isfragment = $table['isfragment'] == "1";
		$fullname = "{$dbname}:{$owner}.{$tabname}";
		$type = ($table['type'] == "T") ? 'table' : 'index';
     
        if ( $isfragment )
        {
			$sel = "EXECUTE FUNCTION admin_async_estimates('{$partnum}','fragment','','','COMPRESSION','estimate for:{$fullname}',current,null,null,'t','t','t','t','t','t','t')";          
        }
        else
        {
            $sel = "EXECUTE FUNCTION admin_async_estimates('{$tabname}','{$type}','{$dbname}','{$owner}','COMPRESSION','estimate for:{$fullname}',current,null,null,'t','t','t','t','t','t','t')";
        }
        
        $res = $this->doDatabaseWork($sel,"sysadmin", false, $locale);
        $res = array( "RESULT" => $res[0][""]  ) ;

        return $res;
    }
	
	function parse_estimate($r)
    {
	    if ( ! isset ( $r ) )
        {
            $ret = array (
                    "EST"    => "-99"
                    ,"CURR"   => "0"
                    ,"CHANGE" => "0"
                    ,"TABLE"  => ""
                    ,"EST_DATE" => ""
                    );

                    return $ret;
        }

        $ret = array();
        $lines = $r['EST_ESTIMATE'];
        $xx = preg_split("/\n/",$lines);
		
        foreach ( $xx as $k => $v )
        {
            if ( $v == "")
            {
                continue;
            }
			
            if ( substr($v,0,6) == "FAILED")
            {
                $ret = array (
                "EST"    => "-99"
                ,"CURR"   => "0"
                ,"CHANGE" => "0"
                ,"TABLE"  => ""
                ,"EST_DATE" => ""
                );
                continue;
            }
			
			
            if (substr($v,0,2) == "--")
            {
                $v = substr($v,65);
            }

            if ( substr($v,0,8) == "Partiton"  )
            {
                $v = substr($v,0,8);
            }

            if ( is_numeric(substr($v,0,2)) )
            {
                $v = str_replace("  "," ",$v);
                $v = str_replace("  "," ",$v);
                $v = str_replace("%","",$v);

                if ( substr ( $v , 0 , 1) == " " )
                {
                    $v = substr($v,1);
                }

                $val = preg_split("/ /",$v);
                if ( count( $val ) >= 5 )
                {
                    //est   curr  change partnum    table
                    $ret = array (
                    "EST"    => $val[0]
                    ,"CURR"   => $val[1]
                    ,"CHANGE" => str_replace("+","",$val[2])
                    ,"TABLE"  => $val[4]
                    ,"EST_DATE" => $r['EST_DATE']
                    );
                }
            }
        }
        return $ret;
    }
	
    /**
     * Get compression estimates
     * 
     * We want two types of estimate mappings:
     * (a) for fragmented tables -- partnum ==> estimate for each fragments
     * (b) for unfragmented tables -- database:owner.table ==> estimate
     */
	function getEstimatesCompression($dbname, $dbsnum = null, $tabnames = null, $estimates_checked = false, $locale = null)
    {
        $rows = array();
        
    	if ( !$estimates_checked && $this->check_for_estimates() === false )
        {
			return $rows;        	
        }
        
        $sel = " SELECT DISTINCT est_partnum as partnum, est_estimate, est_date, "; 
        $sel .= " est_dbname as dbname, est_owner as owner, t.tabname as tabname";
        $sel .= " FROM mon_compression_estimates c1, sysmaster:systabnames t ";
        $sel .= " WHERE ";
        $sel .= " ( ";
        $sel .= " SELECT count(*) FROM mon_compression_estimates c2 WHERE c2.est_partnum = c1.est_partnum AND c2.est_date > c1.est_date ";
		$sel .= " ) = 0 ";
		$sel .= " AND t.partnum = c1.est_partnum " ;
		$sel .= " AND t.dbsname = '{$dbname}' " ;
		$sel .= (($tabnames == null) ? "" : " AND t.tabname in ({$tabnames}) " );
		$sel .= ($dbsnum != null) ? " AND t.partnum >= 1048576 * {$dbsnum} AND t.partnum < 1048576 * ({$dbsnum} + 1)" : "";
		
        $rows = $this->doDatabaseWork($sel,"sysadmin", false, $locale);

		$mapped_estimates = array();
		$tables_seen = array();
		
		// Now we want to parse the estimates and store them in an array that maps
		// (a) partnum ==> estimate for each fragment, and 
		// (b) database:owner.table ==> estimate for each unfragmented table
		foreach ($rows as $row)
		{
			// save estimate by partnum
			$mapped_estimates [$row['PARTNUM']] = $row;
			
			// save estimate by table (unfragmented tables only)
			$key = trim($row['DBNAME']).":".trim($row['OWNER']).".".trim($row['TABNAME']);
			if (!isset($tables_seen[$key]))
			{
				// We haven't run into this table yet. So assume it is unfragmented, but mark it as seen.
				$tables_seen[$key] = true;
				$mapped_estimates[$key] = $row;
			} else {
				// We've seen this table before, so it must have multiple fragments.
				// So we don't have a single estimate for the entire table.
				$mapped_estimates[$key] = null;
			}
		}
	
        return $mapped_estimates;
    }
	
	/**
     * check_for_estimates
     *
     * Check if we have any estimates by checking for the existance of the mon_estimate_compression table
     * in the sysmaster database.
     *
     * @return boolean .
     *     true - yes we have some estimates .
     *     false - no estimates yet.
     */
    function check_for_estimates()
    {
        $sel = "SELECT count(*) AS cnt FROM systables WHERE tabname = 'mon_compression_estimates'";
        $ret = $this->doDatabaseWork($sel,"sysadmin");
        if ( isset($ret[0]['CNT']) === true && $ret[0]['CNT'] > 0 )
        {
                return true;
        }
        return false;
    }
	
	// get an array of running compression jobs.
    function getRunningCompressionJobs($tabnames = null,$locale = null)
    {
        $ret = array();
        if (Feature::isAvailable ( Feature::CHEETAH2_UC4 , $this->idsadmin->phpsession->serverInfo->getVersion() ))
        {
		    $sel = "select trim(smgr_dbname) as dbname , trim(smgr_owner) as owner ";
		    $sel .= ",trim(smgr_tabname) as tabname from sysmaster:sysstoragemgr ";
		    $sel .= (($tabnames != null) ? " WHERE smgr_tabname in ({$tabnames})" : "");
		    
		    $rows = $this->doDatabaseWork($sel,"sysmaster",false,$locale);
		    foreach ( $rows as $k => $v )
		    {
		        $comp = "{$v['DBNAME']}:{$v['OWNER']}.{$v['TABNAME']}";
		        $ret[$comp]=true;
		    }
        }

        return $ret;
    }
	
	// get the minimum number of rows needed for compression.
    function getMinRowsCompression()
    {
        $sel = "SELECT env_value FROM sysenv WHERE env_name='IFX_COMPRESSION_MIN_ROWS'";
        $val = $this->doDatabaseWork($sel,"sysmaster");
        // findout the minimum # of rows a table can have.
        $minrows = isset($val[0]['ENV_VALUE']) ? trim($val[0]['ENV_VALUE']) : 2000;
        return $minrows;
    }
    
    /**
     * Update page usage estimates for the specified partnum or table.
     */
    private function updatePageUsageEstimate($partnum, $tabname = null, $owner = null, $dbname = null, $locale = null)
    {
    	if (! $this->checkIfMonPageUsageTableExists())
    	{
    		return;
    	}
    	
    	if ($partnum == 0)
    	{
    		// If partnum = 0, then use tabname, owner, dbname to identify the partnum to update data for.
    		$partnum_sql = "select partnum from systabnames where tabname = '{$tabname}' "
    				 . "and owner = '{$owner}' and dbsname = '{$dbname}'";
    		$partnum_res = $this->doDatabaseWork($partnum_sql,"sysmaster", false, $locale);
    		$partnum_str = "";
    		$count = 0;
    		foreach ($partnum_res as $row)
    		{
    			if ($count > 0)
    			{
    				$partnum_str .= ",";
    			}
    			$partnum_str .= $row['PARTNUM'];
    			$count++;
    		}
    		if ($count == 0)
    		{
    			// partnum not found (for example, could be a temporary table), so just return
    			return;
    		}
    	} else {
    		//If partnum != 0, use the partnum specified
    		$partnum_str = "'{$partnum}'";
    	}
    	
    	$sql = "INSERT INTO mon_page_usage "
    		 . "select 0, trunc(P.partnum / 1048576) as dbsnum, "
    		 . "CASE WHEN P.nkeys = 1 AND P.npused > 1 AND P.npdata = 0 AND P.partnum <> P.lockid AND bitand(P.flags,4) = 0 THEN 'I' ELSE 'T' END as type, "
    		 . "P.partnum, "
    		 . "P.lockid, "
    		 . "P.nextns, "
    		 . "P.nrows, "
    		 . "P.nptotal, " 
    		 . "P.npused, "
    		 . "P.nptotal - ( BM.partly_used+BM.mostly_used+BM.very_full) AS free, "
    		 . "BM.partly_used, "
    		 . "BM.mostly_used, "
    		 . "BM.very_full, "
    		 . "CURRENT FROM sysmaster:sysptnhdr P, "
    		 . "outer ( "
    		 . "   select b.pb_partnum as partnum, "
    		 . "    (b.pb_partnum/1048576)::integer as dbsnum , "
    		 . "   sum(decode(bitand(b.pb_bitmap, 12),4 ,1,0)) as partly_used , "
    		 . "   sum(decode(bitand(b.pb_bitmap, 12),8 ,1,0)) as mostly_used , "
    		 . "   sum(decode(bitand(b.pb_bitmap, 12),12,1,0)) as very_full "
    		 . "   from sysmaster:sysptnbit b "
    		 . "   where b.pb_bitmap > 0 "
    		 . "   group by b.pb_partnum "
    		 . ") as BM "
    		 . "WHERE P.partnum = BM.partnum "
    		 . "AND bitand(p.flags,'0xE0') = 0 "
    		 . "AND sysmaster:partpagenum(P.partnum)>1 "
    		 . "AND (P.partnum IN ({$partnum_str}) OR P.lockid IN ({$partnum_str}))";
    		 
    	$this->doDatabaseWork($sql,"sysadmin", false, $locale);
    	return;
    }
	
	/**
	 * 
	 * Get tables and indexes info for the minimized pop view. 
	 */
	function getTablesAndIndexesInfoMinimized($dbsnum = null)
	{		
		/* Note: 
		 * This query is 99% correct. It misses one case - an attached index with no rows.  
		 * But this case is so rare that we're going to ignore it for now... 
		 */
		$sql = "select dbsname, sum( " 
			 . "case "
			 . "     WHEN P.nkeys = 0 THEN 1 "
			 . "     WHEN P.partnum = P.lockid  THEN 1 "
			 . "     WHEN P.nrows > 0 THEN 1 "
			 . "     WHEN P.npdata > 0 THEN 1 "
			 . "     WHEN bitand(P.flags, '0x4') > 0 THEN 1 "
			 . "     ELSE 0 "
			 . "END )  AS Table_Fragments, "
			 . "sum( " 
			 . "case "
			 . "     WHEN P.nkeys>0 THEN P.nkeys "
			 . "     ELSE 0 "
			 . "END ) AS Index_Fragments, "
			 . "sum( " 
			 . "case  WHEN bitand(P.flags, '0x08000000') > 0 THEN 1 " 
			 . "      ELSE 0 "
			 . "END )  AS comp_fragments " 
			 . "from systabnames T, sysptnhdr P " 
			 . "where T.partnum = P.partnum " 
			 . "and mod(T.partnum, 1048576)!=1 "
			 . "and (dbsname != 'system' and tabname != ' syslicenseinfo') "
			 . "and tabname NOT IN ( 'sbspace_desc', 'chunk_adjunc', 'LO_ud_free', 'LO_hdr_partn', 'rsccompdict') ";
		if ($dbsnum != null)
		{
			$sql .= "and P.partnum >= {$dbsnum} * 1048576 and P.partnum < ({$dbsnum}+1) * 1048576 ";
		} 
		
		$sql .= "group by dbsname "
		     .  "order by dbsname";

		$result = array();
		$result = $this->doDatabaseWork($sql,"sysmaster");
		
		return $result;
	}
	
	/**
	 * Get summary information about storage on the database server.
	 * This information is for the Dbspaces Pod on the server view
	 */
	private function getServerStorageSummaryInfo () 
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		
		/* data for dbspace graph */
		$data = array();
		$sql ="SELECT sum((chksize - decode(mdsize,-1,nfree,udfree)) * {$defPagesize}) as Used, ".
		" sum(decode(mdsize,-1,nfree,udfree) * {$defPagesize}) as Free FROM syschktab".
		" WHERE bitval(flags, '0x200')=0 AND bitval(flags, '0x4000')=0";
		$res = $this->doDatabaseWork($sql,"sysmaster");
		$data['DBSPACE_USED'] = $res[0]['USED'];
		$data['DBSPACE_FREE'] = $res[0]['FREE'];
		$data['DBSPACE_TOTAL'] = $res[0]['USED'] + $res[0]['FREE'];
		
		/* data for tempspace graph */
		$sql ="SELECT sum((A.chksize - decode(A.mdsize,-1,A.nfree,A.udfree)) * {$defPagesize}) as Used, ".
		" sum(decode(A.mdsize,-1,A.nfree,A.udfree) * {$defPagesize}) as Free ".
		" FROM syschktab A, sysdbstab B ".
		" WHERE A.dbsnum = B.dbsnum AND bitval(B.flags, '0x2000')=1 ";
		$res = $this->doDatabaseWork($sql,"sysmaster");
		$data['TEMPSPACE_USED'] = $res[0]['USED'];
		$data['TEMPSPACE_FREE'] = $res[0]['FREE'];
		$data['TEMPSPACE_TOTAL'] = $res[0]['USED'] + $res[0]['FREE'];
		
		/* data for blobspace graph */
		/* idsdb00245627 - syschktab.chksize is represented in terms of the default page size, but nfree is in terms of blobpage size for blobspaces and the respective page size is in syschktab.pagesize */
		$sql ="SELECT sum(chksize*{$defPagesize} - decode(mdsize,-1,nfree,udfree+nfree)*pagesize) as Used, ".
		" sum(decode(mdsize,-1,nfree,udfree+nfree)*pagesize) as Free FROM syschktab".
		" WHERE bitval(flags, '0x200')<>0 OR bitval(flags, '0x4000')<>0";
		$res = $this->doDatabaseWork($sql,"sysmaster");
		$data['BLOBSPACE_USED'] = $res[0]['USED'];
		$data['BLOBSPACE_FREE'] = $res[0]['FREE'];
		$data['BLOBSPACE_TOTAL'] = $res[0]['USED'] + $res[0]['FREE'];
		
		return $data;
	}
	
	/**
	 * Get information about all dbspaces on datbase server.
	 * This information is for the Dbspaces Pod on the server view
	 */
	public function getDbspacesInfo ($rows_per_page, $page = 1, $sort_col = null, $dbsname_pattern = null) 
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		
		/* default sort order is by dbsnum*/
		if (is_null($sort_col))
		{
			$sort_col = "A.dbsnum";
		}
		
		$panther_columns = "";
		if (Feature::isAvailable ( Feature::PANTHER , $this->idsadmin->phpsession->serverInfo->getVersion() ))
		{
			$panther_columns = ", create_size, extend_size, " .
			"CASE " .
			" WHEN ((create_size = 0) AND (extend_size = 0))"  .
			"   THEN 0" .
			" ELSE 1 END as expandable ";
			$panther_columns_groupby = ", create_size, extend_size ";
		}
		
		/* Full dbspace query */
		$full_query = "SELECT A.dbsnum AS dbsnum, " .
			" trim(B.name) as space_name, " .
			"CASE " .
			" WHEN (bitval(B.flags,'0x10')>0 AND bitval(B.flags,'0x2')>0)" .
			"   THEN 'mirrored_blobspace' " .
			" WHEN bitval(B.flags,'0x10')>0 " .
			"   THEN 'blobspace' " .
			" WHEN bitval(B.flags,'0x2000')>0 AND bitval(B.flags,'0x8000')>0" .
			"   THEN 'temp_sbspace' " .
			" WHEN bitval(B.flags,'0x2000')>0 " .
			"   THEN 'temp_dbspace' " .
			" WHEN (bitval(B.flags,'0x8000')>0 AND bitval(B.flags,'0x2')>0)" .
			"   THEN 'mirrored_sbspace' " .
			" WHEN bitval(B.flags,'0x8000')>0 " .
			"   THEN 'sbspace' " .
			" WHEN bitval(B.flags,'0x2')>0 " .
			"   THEN 'mirrored_dbspace' " .
			" ELSE " .
			"   'dbspace' " .
			" END  as dbstype, " . 
			"CASE " .
			" WHEN bitval(B.flags,'0x4')>0 " .
			"   THEN 'disabled' " .
			" WHEN bitand(B.flags,3584)>0 " .
			"   THEN 'recovering' " .
			" ELSE " .
			"   'operational' " .
			" END  as status, " .
			" B.flags , " .
			" CASE " .
			"   WHEN B.level2 <> 0 THEN dbinfo('UTC_TO_DATETIME',B.level2)::char(30)" .
			"   WHEN B.level1 <> 0 THEN dbinfo('UTC_TO_DATETIME',B.level1)::char(30)" .
			"   WHEN B.level0 <> 0 THEN dbinfo('UTC_TO_DATETIME',B.level0)::char(30)" .
			" ELSE" .
			"     'NONE'" .
			" END as last_backup, " . 
			" size, free_size, used, nchunks, pgsize, sortchksize, sortusedsize " .
			" $panther_columns " .
			" FROM " .
			" (SELECT dbsnum, " .
			" sum(chksize*{$defPagesize}) as size , " .
			" sum(decode(mdsize,-1,nfree,udfree)*pagesize) as free_size, " .
			" TRUNC(100-sum(decode(mdsize,-1,nfree,udfree)*pagesize)*100/ ".
			" sum(chksize*{$defPagesize}),2) as used,".
			" MAX(pagesize) as pgsize, " .
			" sum(chksize) as sortchksize, " .
			" sum(decode(mdsize,-1,nfree,udfree)) as sortusedsize " .
			" FROM syschktab " .
			" GROUP by dbsnum ) AS A, " .
			" sysdbstab B " .
			" WHERE A.dbsnum = B.dbsnum " ;
		if ($dbsname_pattern != null)
		{
			$full_query .= " AND B.name like '%$dbsname_pattern%'";
		}

		/* Simplified dbspaces query 
		 * The simplified query is used in two cases:
		 *    1.  If the server is a secondary server.  (The full query would result in -229/ISAM 140 
		 *        operation illegal on a DR Secondary.)
		 *    2.  If the rootdbs is full.  If the full query results in -229 on a stand-alone or primary, 
		 *        this is most likely an indication that the root space is full.  To load the storage
		 *        page and thus allow customers to add more space through OAT, we need to run the simplified
		 *        query that does not require the server to use temporary space.
		 */
		$simplified_query = "SELECT A.dbsnum AS dbsnum, " .
			" trim(B.name) as space_name, " .
			"CASE " .
			" WHEN (bitval(B.flags,'0x10')>0 AND bitval(B.flags,'0x2')>0)" .
			"   THEN 'mirrored_blobspace' " .
			" WHEN bitval(B.flags,'0x10')>0 " .
			"   THEN 'blobspace' " .
			" WHEN bitval(B.flags,'0x2000')>0 AND bitval(B.flags,'0x8000')>0" .
			"   THEN 'temp_sbspace' " .
			" WHEN bitval(B.flags,'0x2000')>0 " .
			"   THEN 'temp_dbspace' " .
			" WHEN (bitval(B.flags,'0x8000')>0 AND bitval(B.flags,'0x2')>0)" .
			"   THEN 'mirrored_sbspace' " .
			" WHEN bitval(B.flags,'0x8000')>0 " .
			"   THEN 'sbspace' " .
			" WHEN bitval(B.flags,'0x2')>0 " .
			"   THEN 'mirrored_dbspace' " .
			" ELSE " .
			"   'dbspace' " .
			" END  as dbstype, " . 
			"CASE " .
			" WHEN bitval(B.flags,'0x4')>0 " .
			"   THEN 'disabled' " .
			" WHEN bitand(B.flags,3584)>0 " .
			"   THEN 'recovering' " .
			" ELSE " .
			"   'operational' " .
			" END  as status, " .
			" B.flags , " .
			" sum(chksize*{$defPagesize}) as size , " .
			" sum(decode(mdsize,-1,nfree,udfree)*pagesize) as free_size, " .
			" TRUNC(100-sum(decode(mdsize,-1,nfree,udfree)*pagesize)*100/ ".
			" sum(chksize*{$defPagesize}),2) as used,".
			" MAX(B.nchunks) as nchunks, " .
			" MAX(A.pagesize) as pgsize, " .
			" sum(chksize) as sortchksize, " .
			" sum(decode(mdsize,-1,nfree,udfree)) as sortusedsize " .
			" $panther_columns " .
			"FROM syschktab A, sysdbstab B " .
			"WHERE A.dbsnum = B.dbsnum " .
			"GROUP BY A.dbsnum, name, 3, 4, 5 $panther_columns_groupby ";
		if ($dbsname_pattern != null)
		{
			$simplified_query .= " AND B.name like '%$dbsname_pattern%'";
		}

		if ($this->idsadmin->phpsession->serverInfo->isPrimary())
		{
			// Use the full query for primary or stand-alone servers.
			$sql = $full_query;
			try {
				$dbspaces = $this->doDatabaseWork($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col),"sysmaster",true);
			} catch (Exception $e){
				if($e->getCode() == -229) 
				{
					// This is the case when rootdbs is full and ODBC failed to create temp space.
					// Print debug message to error log and then try the simplified query.
					error_log("storageServer: -229 error when running Storage dbspaces query, indicating that the server is probably out of space.  Retrying with simplified query.");
					$sql = $simplified_query;
					$dbspaces = $this->doDatabaseWork($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col),"sysmaster");
				} else {
					// If it wasn't a -229 error due to space issues, re-run the original query again
					// and this time let trigger_error send the error to be displayed to the user in Flex
					$dbspaces = $this->doDatabaseWork($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col),"sysmaster");
				}
			}
		} else {
			// Use the simplified query for secondaries
			$sql = $simplified_query;
			$dbspaces = $this->doDatabaseWork($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col),"sysmaster");
		}
        	
		$ret = array();
		$ret['DATA'] = $dbspaces;
		
		// We also need to get the total number of dbspaces on the server
		$ret['COUNT'] = 0;
		try {
			$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysmaster", true);
		} catch (Exception $e) {
			if($e->getCode() == -229) 
			{
				// This is the case when rootdbs is full and ODBC failed to create temp space.
				// Print debug message to error log and then try a simplified query to get the count of spaces.
				error_log("storageServer: -229 error when running Storage dbspaces count query, indicating that the server is probably out of space.  Retrying with simplified query.");
				$countQuery = "select count(*) as count from sysdbstab";
				$temp = $this->doDatabaseWork($countQuery, "sysmaster");
			} else {
				// If it wasn't a -229 error due to space issues, re-run the original query again
				// and this time let trigger_error send the error to be displayed to the user in Flex
				$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysmaster");
			}
		}
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}

		return $ret;
	}
	
	public function getSpaceFragmentation($dbsnum, $dbssize)
	{
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		$dbssize /= $defPagesize;
		$qry = "SELECT pe_extnum as extnum, (pe_size / {$dbssize}) AS size, (pe_offset / {$dbssize}) AS offset " . 
			   "FROM sysptnext WHERE pe_chunk IN (SELECT chknum FROM syschunks where dbsnum = {$dbsnum}) " .
			   "ORDER BY 3";
		$fragmentation = $this->doDatabaseWork($qry,"sysmaster");
		return $fragmentation;
	}
	
	public function getChunkFragmentation($chunknum, $chunksize, $dbsnum)
	{
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		$chunksize /= $defPagesize;
		
		$qry = "SELECT nextns as extnum, (pe_size/{$chunksize}) as size,  (pe_offset/{$chunksize}) as offset, pe_partnum as partnum " .
			   "FROM sysmaster:sysptnhdr, sysmaster:sysptnext " .
			   "WHERE sysptnhdr.partnum = sysptnext.pe_partnum " .
			   "AND sysptnhdr.partnum > sysmaster:partaddr({$dbsnum},-1) " .
			   "AND  sysptnhdr.partnum < sysmaster:partaddr({$dbsnum}+1,-1) " .
			   "AND pe_chunk = {$chunknum} " .
			   "UNION " .
			   "SELECT 0, (size/{$chunksize}) as size, (offset/{$chunksize}) as offset, -1 as partnum " .
			   "FROM sysmaster:syslogfil " .
			   "WHERE chunk = {$chunknum} " .
			   "UNION " .
			   "SELECT 0, (pl_physize/{$chunksize}) as size, (pl_offset/{$chunksize}) as offset, -1 as partnum " .
			   "FROM sysmaster:sysplog " .
			   "WHERE pl_chunk = {$chunknum} ";
		
		$fragmentation = $this->doDatabaseWork($qry,"sysmaster");
		return $fragmentation;
	}
	
	/**
	 * Get information about chunks.
	 * 
	 * @param $dbsnum
	 *        if $dbsnum == -1, get information about all chunks for database server
	 *        if $dbsnum != -1, get information about all chunks for specified dbspace number
	 */
	public function getChunkInfo($dbsnum = -1, $rows_per_page = NULL, $page = 1, $sort_col = null, $dbsname_pattern = null)
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		$serverIsPantherOrNewer = Feature::isAvailable ( Feature::PANTHER , $this->idsadmin->phpsession->serverInfo->getVersion() );
		
		if ($sort_col != null && (strtolower($sort_col) == "offset asc" || strtolower($sort_col) == "offset desc"))
		{
			// It needs to be "A.offset" otherwise we'll get "Ambiguous column (offset)" error.
			$sort_col = "A.{$sort_col}";
		}

		/* Full query for chunks */
			$full_query = "SELECT ".
				"A.chknum, " .
				"A.dbsnum, " .
				"C.name as spacename, " .
				"A.fname as path, " .
				"A.pagesize/1024 as pgsize, " .
				"A.offset * {$defPagesize} as offset, " .
				"A.chksize * {$defPagesize} as size, " .
				(($serverIsPantherOrNewer) ? "B.is_extendable as expandable, " : "0 as expandable, ") .
				"(decode(A.mdsize,-1,A.nfree,A.udfree) * A.pagesize) as free, " .
				"TRUNC(100 - decode(A.mdsize,-1,A.nfree,A.udfree)*A.pagesize*100/(A.chksize*{$defPagesize}),2 ) as used, " .
				"CASE " .
				"WHEN B.is_offline=1 THEN 'offline'	 " .
				"WHEN B.is_recovering=1 THEN 'recovering' " .
				"ELSE 'online' " .
				"END as status, " .
				
				"CASE " .
				" WHEN (bitval(C.flags,'0x10')>0 AND bitval(C.flags,'0x2')>0)" .
				"   THEN 'mirrored_blobspace' " .
				" WHEN bitval(C.flags,'0x10')>0 " .
				"   THEN 'blobspace' " .
				" WHEN bitval(C.flags,'0x2000')>0 AND bitval(C.flags,'0x8000')>0" .
				"   THEN 'temp_sbspace' " .
				" WHEN bitval(C.flags,'0x2000')>0 " .
				"   THEN 'temp_dbspace' " .
				" WHEN (bitval(C.flags,'0x8000')>0 AND bitval(C.flags,'0x2')>0)" .
				"   THEN 'mirrored_sbspace' " .
				" WHEN bitval(C.flags,'0x8000')>0 " .
				"   THEN 'sbspace' " .
				" WHEN bitval(C.flags,'0x2')>0 " .
				"   THEN 'mirrored_dbspace' " .
				" ELSE " .
				"   'dbspace' " .
				" END  as dbstype, " . 
				
				"A.fname, ".
				"A.offset as sortoffset, ".
				"A.chksize as sortsize, ".
				"decode(A.mdsize,-1,A.nfree,A.udfree) as sortfree, ".
				"TRUNC(100 - decode(A.mdsize,-1,A.nfree,A.udfree)*100/A.chksize,2 ) as sortused, ".
				"A.reads, " .
				"A.writes, " .
				"(TRUNC(A.readtime/1000000,3)) AS readtime, ".  // readtime in microseconds, so covert to seconds and truncate to 3 decimal places
				"(TRUNC(A.writetime/1000000,3)) AS writetime ". // writetime in microseconds, so covert to seconds and truncate to 3 decimal places
				"FROM syschktab A, syschunks B, sysdbstab C " .
				"WHERE A.dbsnum = B.dbsnum " .
				"AND A.chknum = B.chknum " .
				"AND A.dbsnum = C.dbsnum " .
				(($dbsnum == -1) ? "" : "AND A.dbsnum = ".$dbsnum );
			if ($dbsname_pattern != null)
			{
				$full_query .= " AND C.name like '%$dbsname_pattern%'";
			}

			 
		/* Simplified query for chunks 
		 * The simplified query is used in two cases:
		 *    1.  If the server is a secondary server.  (The full query would result in -229/ISAM 140 
		 *        operation illegal on a DR Secondary.)
		 *    2.  If the rootdbs is full.  If the full query results in -229 on a stand-alone or primary, 
		 *        this is most likely an indication that the root space is full.  To load the storage
		 *        page and thus allow customers to add more space through OAT, we need to run the simplified
		 *        query that does not require the server to use temporary space.
		 */
			$simplified_query = "SELECT ".
				"A.chknum, " .
				"A.dbsnum, " .
				"B.name as spacename, " .
				"A.fname as path, " .
				"A.pagesize/1024 as pgsize, " .
				"A.offset * {$defPagesize} as offset, ".
				"A.chksize * {$defPagesize} as size, ".
				"0 as expandable, ".
				"(decode(A.mdsize,-1,A.nfree,A.udfree) * A.pagesize) as free, " .
				"TRUNC(100 - decode(A.mdsize,-1,A.nfree,A.udfree)*A.pagesize*100/(A.chksize*{$defPagesize}),2 ) as used, " .
				"A.fname, ".
				"A.offset as sortoffset, ".
				"A.chksize as sortsize, ".
				
				"CASE " .
				" WHEN (bitval(B.flags,'0x10')>0 AND bitval(B.flags,'0x2')>0)" .
				"   THEN 'mirrored_blobspace' " .
				" WHEN bitval(B.flags,'0x10')>0 " .
				"   THEN 'blobspace' " .
				" WHEN bitval(B.flags,'0x2000')>0 AND bitval(B.flags,'0x8000')>0" .
				"   THEN 'temp_sbspace' " .
				" WHEN bitval(B.flags,'0x2000')>0 " .
				"   THEN 'temp_dbspace' " .
				" WHEN (bitval(B.flags,'0x8000')>0 AND bitval(B.flags,'0x2')>0)" .
				"   THEN 'mirrored_sbspace' " .
				" WHEN bitval(B.flags,'0x8000')>0 " .
				"   THEN 'sbspace' " .
				" WHEN bitval(B.flags,'0x2')>0 " .
				"   THEN 'mirrored_dbspace' " .
				" ELSE " .
				"   'dbspace' " .
				" END  as dbstype, " .	
				"decode(A.mdsize,-1,A.nfree,A.udfree) as sortfree, ".
				"TRUNC(100 - decode(A.mdsize,-1,A.nfree,A.udfree)*100/A.chksize,2 ) as sortused, ".
				"A.reads, " .
				"A.writes, " .
				"(TRUNC(A.readtime/1000000,3)) AS readtime, ".  // readtime in microseconds, so covert to seconds and truncate to 3 decimal places
				"(TRUNC(A.writetime/1000000,3)) AS writetime ". // writetime in microseconds, so covert to seconds and truncate to 3 decimal places
				"FROM syschktab A, sysdbstab B " .
				"WHERE A.dbsnum = B.dbsnum " .
				(($dbsnum == -1) ? "" : "AND A.dbsnum = ".$dbsnum );
			if ($dbsname_pattern != null)
			{
				$simplified_query .= " AND B.name like '%$dbsname_pattern%'";
			}
		
		if ($this->idsadmin->phpsession->serverInfo->isPrimary())
		{
			// Use the full query for primary or stand-alone servers.
			$sql = $full_query;
			try {
				$chunks = $this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col),"sysmaster",true);
			} catch (Exception $e){
				if($e->getCode() == -229) 
				{
					// This is the case when rootdbs is full and ODBC failed to create temp space.
					// Print debug message to error log and then try the simplified query.
					error_log("storageServer: -229 error when running Storage chunks query, indicating that the server is probably out of space.  Retrying with simplified query.");
					$sql = $simplified_query;
					$chunks = $this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col),"sysmaster");
				} else {
					// If it wasn't a -229 error due to space issues, re-run the original query again
					// and this time let trigger_error send the error to be displayed to the user in Flex
					$chunks = $this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col),"sysmaster");
				}
			}
		} else {
			// Use the simplified query for secondaries
			$sql = $simplified_query;
			$chunks = $this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col),"sysmaster");
		}
		
		$res = array();
		$res['DATA'] = $chunks;
		
		// We also need to get the total number of chunks on the server
		$res['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysmaster");
		foreach ($temp as $row)
		{
			$res['COUNT'] = $row['COUNT'];
		}

		return $res;
	}
	
	/**
	 * Get storage pool information.
	 */
	public function getStoragePoolInfo ($rows_per_page, $page = 1, $sort_col = null, $status_filter = null) 
	{	
		$res = array();
		
		// default sort order is by priority
		if ($sort_col == null)
		{
			$sort_col = "priority";
		}
		$qry = "select entry_id, path, beg_offset as offset, end_offset as device_size, " .
			"chunk_size, priority, status, last_alloc as last_accessed, ".
			"case when (end_offset == 0) then -1 else (end_offset - beg_offset) end as space_remaining ".
			"from storagepool ";
		if ($status_filter != null)
		{
			$qry .= "where status == '{$status_filter}'";
		}
		
		$res['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry,$rows_per_page,$page,$sort_col),"sysadmin");
		
		// We also need to get the total number of storage pool entries on the server
		$res['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), "sysadmin");
		foreach ($temp as $row)
		{
			$res['COUNT'] = $row['COUNT'];
		}
		
		// We also need the count of active storage pool entries.  
		$res['ACTIVE_COUNT'] = 0;
		$qry = "select count(*) as count from storagepool where status == 'Active'";
		$temp = $this->doDatabaseWork($qry, "sysadmin");
		foreach ($temp as $row)
		{
			$res['ACTIVE_COUNT'] = $row['COUNT'];
		}
		
		return $res;
	}

	/**
	 * Get storage pool information.
	 */
	private function getStoragePoolConfig () 
	{
		$config = array();
		$qry = "select trim(cf_name) as cf_name, cf_effective from syscfgtab where cf_name in ('SP_THRESHOLD','SP_WAITTIME','SP_AUTOEXPAND')";
		$res = $this->doDatabaseWork($qry,"sysmaster");
		foreach ($res as $row)
		{
			switch ($row['CF_NAME'])
			{
				case 'SP_THRESHOLD':
					$config['SP_THRESHOLD'] = $row['CF_EFFECTIVE'];
					break;
				case 'SP_WAITTIME':
					$config['SP_WAITTIME'] = $row['CF_EFFECTIVE'];
					break;
				case 'SP_AUTOEXPAND':
					$config['SP_AUTOEXPAND'] = $row['CF_EFFECTIVE'];
					break;
			}
		}
		return $config;
	}
	
	/**
	 * Get the information needed for the expand space action.
	 * We need to know the current size, extend size, and all 
	 * of the chunks currently existing in that space.
	 */
	public function getExpandSpaceInfo($dbsnum) 
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		
		$res = array();
		$res['SIZE'] = 0;
		$res['EXTEND_SIZE'] = 0;
		
		$qry = "SELECT size, extend_size "
			 . "FROM "
			 . "(SELECT dbsnum, "
			 . "  sum(chksize*{$defPagesize}) as size " 
			 . "  FROM syschktab " 
			 . "  WHERE dbsnum = {$dbsnum} " 
			 . "  GROUP by dbsnum ) AS A, " 
			 . "sysdbstab B "
			 . "WHERE B.dbsnum = {$dbsnum} "
			 . "AND A.dbsnum = B.dbsnum";
			 
		try {
			$temp_res = $this->doDatabaseWork($qry,"sysmaster", true);
		} catch (Exception $e) {
			if($e->getCode() == -229) 
			{
				// This is the case when rootdbs is full and ODBC failed to create temp space.
				// Print debug message to error log and then break this query into two parts and run
				// each part separately to simplify.
				error_log("storageServer: -229 error when getting info for the expand space pop-up, indicating that the server is probably out of space.  Retrying with simplified query.");
				
				$temp_res = array();
				$simplified_qry = "SELECT dbsnum, sum(chksize*{$defPagesize}) as size FROM syschktab WHERE dbsnum = {$dbsnum} GROUP by dbsnum";
				$temp_res = $this->doDatabaseWork($simplified_qry,"sysmaster");
				
				$simplified_qry = "SELECT extend_size FROM sysdbstab WHERE dbsnum = {$dbsnum}";
				$temp_res2 = $this->doDatabaseWork($simplified_qry,"sysmaster");
				$temp_res[0]['EXTEND_SIZE'] = $temp_res2[0]['EXTEND_SIZE'];
			} else {
				// If it wasn't a -229 error due to space issues, re-run the original query again
				// and this time let trigger_error send the error to be displayed to the user in Flex
				$temp_res = $this->doDatabaseWork($qry,"sysmaster");
			}
		}
		if (count($temp_res) > 0)
		{
			$res['SIZE'] = $temp_res[0]['SIZE'];
			$res['EXTEND_SIZE'] = $temp_res[0]['EXTEND_SIZE'];
		}
		
		$res['CHUNKS'] = $this->getChunksInSpace($dbsnum);
		
		return $res;
	}
	
	/**
	 * Get the list of chunks in a particular space.  In this service, we always 
	 * want to return all chunks in the space (no pagination).
	 * 
	 * This is used in the advanced section of the Expand space pop-up.
	 */
	private function getChunksInSpace($dbsnum)
	{
		/** Assumption: this service only called for Panther server versions. 
		 * (since Expand Space is only for Panther server versions or above). **/
		$qry = "SELECT ".
			"A.chknum, " .
			"A.dbsnum, " .
			"A.fname as path, " .
			"B.is_extendable as expandable, " .
			"A.offset as offset ".
			"FROM syschktab A, syschunks B " .
			"WHERE A.dbsnum = B.dbsnum " .
			"AND A.chknum = B.chknum " .
			"AND A.dbsnum = ".$dbsnum;
			
		try {
			$res = $this->doDatabaseWork($qry,"sysmaster", true);
		} catch (Exception $e) {
			if($e->getCode() == -229) 
			{
				// This is the case when rootdbs is full and ODBC failed to create temp space.
				// Print debug message to error log and then run a simplified version of the query (without the join).
				error_log("storageServer: -229 error when getting chunks info for the expand space pop-up, indicating that the server is probably out of space.  Retrying with simplified query.");
				
				$simplified_qry = "SELECT ".
					"A.chknum, " .
					"A.dbsnum, " .
					"A.fname as path, " .
					"A.offset as offset ".
					"FROM syschktab A " .
					"WHERE A.dbsnum = ".$dbsnum;
				$res = $this->doDatabaseWork($simplified_qry,"sysmaster");
				
			} else {
				// If it wasn't a -229 error due to space issues, re-run the original query again
				// and this time let trigger_error send the error to be displayed to the user in Flex
				$res = $this->doDatabaseWork($qry,"sysmaster");
			}
		}
		
		return $res;
	}
	
	/**
	 * Execute action on storage pool, then requery to get refresh storage pool info.
	 **/
	public function executeStoragePoolAction($sql,$rows_per_page, $page = 1, $sort_col = null, $status_filter = null)
	{
		$res = $this->executeActions($sql);
		$res['STORAGE_POOL'] = $this->getStoragePoolInfo($rows_per_page,$page, $sort_col, $status_filter);
		$res['STORAGE_POOL_CONFIG'] = $this->getStoragePoolConfig();
		return $res;
	}
	
	/**
	 * Execute dbspace actions, then requery to get refresh storage pool info.
	 **/
	public function executeDbspaceAction($sql, $spacename = null, $rows_per_page, $page = 1, $sort_col = null, $dbsname_pattern = null)
	{
		$res = array();
		$res['SPACE_NAME'] = $spacename;
		
		/* Find out how many statements we have to run */
		$stmts = explode(";", $sql);
		$i = 0;
		foreach ($stmts as $stmt)
		{
			if (trim($stmt) == "")
			{
				unset($stmts[$i]);  // remove any empty elements from the array
			} else {
				$i++;
			}
		}
		
		/* Are there multiple statements? */
		if (count($stmts) > 1)
		{
			// We will have two sql statements to run only in the case
			// where we are creating a new space.

			// So, run the first create space statement
			$temp_result = $this->executeSQLAdminTask($stmts[0]);
			
			// Check whether space creation succeeed
			if ($temp_result['RETURN_CODE'] < 0)
			{
				// if space creation failed, we need to return now with the error message
				$res['CMD_RESULT'] = $temp_result;
				$res['DBSPACES'] = $this->getDbspacesInfo($rows_per_page, $page, $sort_col, $dbsname_pattern);
				return $res;
			}
			
			// Save space creation result message
			$res['CMD_RESULT']['RESULT_MESSAGE'] = $temp_result['RESULT_MESSAGE'] . "\n";
			
			// Now, find the dbspace number of the new space.  We need this to add 
			// the new space to the tree.
			$query = "select dbsnum, " .
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
					 "from sysdbspaces where name='{$spacename}'";
            		
			$temp_res2 = $this->doDatabaseWork($query);
			$dbsnum = 0;
			if (count($temp_res2) > 0)
			{
				$dbsnum = $temp_res2[0]['DBSNUM'];
				$res['DBSNUM'] = $dbsnum;
				$res['DBSTYPE'] = $temp_res2[0]['DBSTYPE'];;
			}
			
			// Now execute the remaining statements
			for ($j = 1; $j < count($stmts); $j++)
			{
				$query = $stmts[$j];
				
				$tmp_result = $this->executeSQLAdminTask($query);
				
				// Set return code to return code of most recent statment executed
				$res['CMD_RESULT']['RETURN_CODE'] = $tmp_result['RETURN_CODE'];
				
				// Concatenate return message of all statements executed
				$res['CMD_RESULT']['RESULT_MESSAGE'] .= $tmp_result['RESULT_MESSAGE'] . "\n";
				
				// If any statement failed, stop now and return
				if ($tmp_result['RETURN_CODE'] < 0)
				{
					break;
				} 	
			}
		} else {
			// We just have one sql statement to run
			$res['CMD_RESULT'] = $this->executeSQLAdminTask($stmts[0]);
		}
		
		$res['DBSPACES'] = $this->getDbspacesInfo($rows_per_page, $page, $sort_col, $dbsname_pattern);
		return $res;
	}
	
	public function executeChunksAction($sql)
	{
		$res = $this->executeActions($sql);
		return $res;
	}
	
	/**
	 * This a helper function that runs a set of SQL Admin API.
	 * This funciton will stop and return should any of the commands 
	 * result in a failure. 
	 *
	 * @param $sql semicolon separated string of sql admin api commands
	 * 
	 * Return values:
	 * The result of the command will be stored in the $task array as follows:
	 * 		$task['RESULT_MESSAGE'] --> concatenation of all success or failure 
	 *           message of all commands executed
	 *      $task['RETURN_CODE'] --> return code of the last command executed
	 */
	private function executeActions ($sql)
	{
		$res = array();
		
		/* If the sql is multiple statements concatenated by a semicolon,
		 * we need to execute them separately */
		$stmts = explode(";", $sql);
		
		$res['CMD_RESULT'] = array();
		$res['CMD_RESULT']['RESULT_MESSAGE'] = "";
		
		foreach ($stmts as $stmt)
		{
			if (trim($stmt) == "")
			{
				continue;
			}
			
			$tmp_result = $this->executeSQLAdminTask($stmt);
			
			// Set return code to return code of most recent statment executed
			$res['CMD_RESULT']['RETURN_CODE'] = $tmp_result['RETURN_CODE'];
			
			// Concatenate return message of all statements executed
			$res['CMD_RESULT']['RESULT_MESSAGE'] .= $tmp_result['RESULT_MESSAGE'] . "\n";
			
			// If any statement failed, stop now and return
			if ($tmp_result['RETURN_CODE'] < 0)
			{
				break;
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
     * Get tables (or indexes) with the worst extent utilization
     * 
     * @dbsnum - dbspace number
     * @dbname - database name
     * @num_tables - number of tables/indexes to return.  E.g. if $num_tables = 3, this
     *               query returns the top 3 tables/indexes will the poorest extent utilization.
     */
    public function getTablesWithWorstExtentUtilization ($dbsnum, $dbname, $num_tables) 
    {
    	if (! $this->checkIfMonPageUsageTableExists())
    	{
    		return array();
    	}
    	
    	/* get the locale of the database */
    	$locale = $this->getDatabaseLocale($dbname);
    	
    	$sql = "SELECT FIRST {$num_tables} "
    	     . "tabname, "
    	     . "((free * " . self::POOR_PAGE_UTILIZATION  
    	     . " + partly_used * " . self::FAIR_PAGE_UTILIZATION 
    	     . " + mostly_used * " . self::GOOD_PAGE_UTILIZATION 
    	     . " + very_full * " . self::EXCELLENT_PAGE_UTILIZATION
    	     . ")/((free + partly_used + mostly_used + very_full) * " . self::EXCELLENT_PAGE_UTILIZATION  
    	     . ")) as utilization_ratio "
    	     . "from " 
    	     . "("
    	     . "   SELECT "
    	     . "   tabname, "
    	     . "   sum(U.free) as free, "
    	     . "   sum(U.partly_used) as partly_used, "
    	     . "   sum(U.mostly_used) as mostly_used, "
    	     . "   sum(U.very_full) as very_full "
    	     . "   FROM sysmaster:systabnames T, mon_page_usage U, sysmaster:sysptnhdr P "
    	     . "   WHERE  U.partnum = T.partnum " 
    	     . "   AND U.partnum = P.partnum "
    	     . "   AND U.run_time > dbinfo('UTC_TO_DATETIME', P.created) " // Handles case where table dropped & re-created with same partnum
    	     . "   AND U.ID = ( SELECT max(IU.id) FROM mon_page_usage IU WHERE U.partnum = IU.partnum ) "
    	     . "   AND dbsname = '$dbname' "
    	     . (($dbsnum != null) ? "   AND U.dbsnum = {$dbsnum}":"" )
    	     . "   GROUP BY dbsname, owner, tabname, type" 
    	     . ") " 
    	     . " ORDER BY utilization_ratio ";
    	return $this->doDatabaseWork($sql,"sysadmin", false, $locale);
    }
    
	/**
     * compress a table
     */
    function optimizeTable ($dbname, $tables, $compress_option, $uncompress_option, $repack_option, $truncate_option, $defragment_option, 
    	$offline_repack, $offline_uncompress, $compress_blob_data = false, $can_compress_indexes = false)
    {
    
    	$result = null;
    	// First check if DELIMIDENT is set as an env variable on the connection.
    	// Our optimization commands need to use double quotes and so do not work 
    	// with DELIMIDENT set.  But since we are just executing functions in sysadmin, 
    	// DELIMIDENT is not needed even if we are optimizing a table created when 
    	// DELIMIDENT was set.  So if DELIMIDENT is currently set on the OAT connection, 
    	// we'll just temporarily reset it without the DELIMIDENT env variable so 
    	// we can execute the optimization commands.
    	$connection_reset = false;
    	if (strcasecmp($this->idsadmin->phpsession->instance->get_delimident(), "Y") == 0)
    	{
    		// DELIMIDENT is set, so save off the env variable setting and reset the connection.
    		$connection_reset = true;
    		$saved_delimident_value = $this->idsadmin->phpsession->instance->get_delimident();
    		$this->idsadmin->phpsession->instance->set_delimident("");
    		$saved_envvars = $this->idsadmin->phpsession->instance->get_envvars();
    		$this->idsadmin->phpsession->instance->set_envvars("");
    		$this->idsadmin->unset_database("sysadmin");
    	}
    	
    	$tables = unserialize($tables);
    	
    	//Validate tables
        $this->validateTables($tables);
		
        $locale = $this->getDatabaseLocale($dbname);
        $conn = $this->getDBConnection("sysadmin",$locale);
	    	
        $repack="";
        $truncate="";
        $compress="";
        
        $result = $this->doDatabaseWork("select first 1 ph_bg_jobs_seq.nextval from ph_bg_jobs","sysadmin");
        $jobname = "Job-" . ((isset($result[0]['NEXTVAL'])) ? $result[0]['NEXTVAL'] : 1); 	

        if ($repack_option)
        {
            $repack = ($offline_repack) ? "repack_offline" : "repack";
        }

	//if we're compressing, define the compress option
        if ( $compress_option )
        {
        	$compress="compress"; 
        }
	        
        //Define uncompress option only if we are uncompressing
	else if ( $uncompress_option )
        {
		$compress = ($offline_uncompress) ? "uncompress_offline" : "uncompress";
        }
	
	//define the shrink option
        if ($truncate_option)
        {
            $truncate="shrink";
        }
	
	//optimize every item (table, index or fragment)
	for ($i=0, $sequence=0; $i < count($tables); $i++)
	{
		$isFragment = $tables[$i]["partnum"] != 0;
		$sql = "";
				
		//defragment
		if ($defragment_option == true)
		{
			$sql = $this->generateDefragmentSQL($tables[$i], $sequence,$jobname, $isFragment) . ";";
		}
			
		//other optimization options
		if ($truncate_option || $compress_option || $uncompress_option || $repack_option)
		{
			if(!$can_compress_indexes && $tables[$i]["type"] == "I")//if we can't optimize indexes, just skip (This doesn't mean we can't defragment).
			{
				continue;
			}
			$minRowsCompression = $this->getMinRowsCompression();
			$sql .= $this->generateOptmizeSQL($tables[$i], $minRowsCompression, $sequence, $jobname, $isFragment,
				$compress, $repack, $truncate, $compress_blob_data);
		}
      		$this->doDatabaseWork($sql,"sysadmin", false, $locale, $conn);
	}
	
	$sel = "EXECUTE FUNCTION EXECTASK('Job Runner', '{$jobname}')";
	$result = $this->doDatabaseWork($sel, "sysadmin", false, $locale, $conn);
	$result = array( "RESULT" => $result[0][""]  ) ;
   
	// If we had to reset the connection due to the delimident setting, restore it now.
	if ($connection_reset)
	{
		$this->resetDelimident($saved_delimident_value, $saved_envvars);
	}
		
	return $result;
    } 
    
    private function validateTables($tables)
    {
    	$msg = "";
    	foreach  ($tables as $table) 
	{
	        if ( $table["dbname"] == "" )
	        {
	            $msg .= "{$this->idsadmin->lang('InvalidDB')}\n";
	        }
	
	        if ( $table["tabname"] == "" )
	        {
	            $msg .= "{$this->idsadmin->lang('NoTable')}\n";
	        }
	
	        if ( $table["owner"] == "" )
	        {
	            $msg .= "{$this->idsadmin->lang('NoOwner')}\n";
	        }
			
	}
		
	//If we have a problem with the items that are to be optimized, then stop here and return an error
	if ($msg != "")
	{   	
		trigger_error("{$this->idsadmin->lang('Failed')}: {$msg}");
	}
		
    }
    
    private function enableCompression($connection_reset, $saved_delimident_value, $saved_envvars)
    {
    	$res = $this->doDatabaseWork("EXECUTE FUNCTION task('enable compression')","sysadmin");
        $res = array( "RESULT" => $res[0][""] );
	    if ( substr($res['RESULT'],0,7) == "Unknown" )
    	{
        	$res['RESULT'] = "FAIL: {$res['RESULT']}";
        }
        if ( substr($res['RESULT'],0,4) == "FAIL" )
        {
        	// If we had to reset the connection due to the delimident setting, restore it now.
        	if ($connection_reset)
        	{
        		$this->resetDelimident($saved_delimident_value, $saved_envvars);
        	}
        	
        	return $res;
        }
        
        return null;
    }
    
    /**
     * This function generates SQL that performs compress/uncompress, repack, shrink. 
     */
    private function generateOptmizeSQL($table, $compressionMinimumRows, &$sequence, $jobname, 
   	 $isFragment, $compression, $repack, $truncate, $compress_blob_data)
    {
    	$type = $table["type"];
    	
		$sql =  "INSERT INTO ph_bg_jobs (ph_bg_name, ph_bg_job_id, ph_bg_type, "; 
		$sql .= "ph_bg_sequence, ph_bg_database, ph_bg_cmd, ph_bg_desc) VALUES ";
		$sql .= "('{$jobname}', ph_bg_jobs_seq.nextval, 'STORAGE OPTIMIZATION JOB', '{$sequence}', ";

		// For server versions below 12.10, don't try to compress a table/fragment whose number of rows is 
		// less the the minimum number of rows that a fragment needs to be compressed.
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		if(!Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()) &&
			$table['nrows'] < $compressionMinimumRows && $compression == "compress" && $type != "I") 
		{
			$compression = "";
		}
		
		// Don't try to uncompress indexes. 
		if ($type == "I" && ($compression == "uncompress" || $compression == "uncompress_offline"))
		{
			$compression = "";
		}
		
		// Figure out if we are compressing everything, or just row data.
		$compress_row_data = "";
		if ($compression == "compress" && !$compress_blob_data)
		{
			// For server versions >= CENTAURUS, users can choose to include or not include blob data in the compression.
			// If they don't want blob data compressed, we need to add a the 'rows' keyword to the statement.
			$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
			if (Feature::isAvailable ( Feature::CENTAURUS , $this->idsadmin->phpsession->serverInfo->getVersion()))
			{
				$compress_row_data = "rows";
			}
		}
		
		// Create SQL statement
		if($isFragment)
		{
			$sel = "EXECUTE FUNCTION task('fragment {$compression} {$repack} {$truncate} {$compress_row_data}', '{$table["partnum"]}' ) ";
			$desc = "fragment {$compression} {$repack} {$truncate} {$compress_row_data}, {$table["partnum"]}";

			$sql .= "'sysadmin', \"{$sel}\", \"$desc\")";
    	}
    	else
    	{
			if($type == "T")
			{
				$type = "table";
			}
			else
			{
				$type = "index";
				$compress_row_data = "";
				$compress_blob_data = "";
			}

			$sel = "EXECUTE FUNCTION task('{$type} {$compression} {$repack} {$truncate} {$compress_row_data}' , '{$table["tabname"]}' , '{$table["dbname"]}' , '{$table["owner"]}') ";
			$desc = "{$type} {$compression} {$repack} {$truncate} {$compress_row_data}, {$table["tabname"]}, {$table["dbname"]}, {$table["owner"]}";			
				
			$sql .= "'sysadmin', \"{$sel}\", \"$desc\")";
    	}
		
		$sequence++;
    	return $sql;
    }
    
    private function generateDefragmentSQL($table, &$sequence,$jobname,$isFragment)
    {
    	$sql =  "INSERT INTO ph_bg_jobs (ph_bg_name, ph_bg_job_id, ph_bg_type, ph_bg_sequence, ph_bg_database, ph_bg_cmd, ph_bg_desc) VALUES ";
    	
    	if($isFragment)
    	{
    		$sel = "EXECUTE FUNCTION task('defragment partnum', '{$table['partnum']}')"; 
			$desc = "defragment partnum, {$table['partnum']}";
			$sql .= "('{$jobname}', ph_bg_jobs_seq.nextval, 'STORAGE OPTIMIZATION JOB', '{$sequence}', ";
			$sql .= "'sysadmin', \"{$sel}\", \"$desc\")";
    	}
    	else
    	{
    		$sel = "EXECUTE FUNCTION task('defragment table' , '{$table["dbname"]}:{$table["owner"]}.{$table["tabname"]}')"; 
			$desc = "defragment, {$table["dbname"]}:{$table["owner"]}.{$table["tabname"]}";
			$sql .= "('{$jobname}', ph_bg_jobs_seq.nextval, 'STORAGE OPTIMIZATION JOB', '{$sequence}', ";
			$sql .= "'sysadmin', \"{$sel}\", \"$desc\")";
    	}
    	
   		$sequence++;
   		return $sql;
    }
    
	/**
	 * Reset delimident and envvar settings based on saved values.
	 */
	private function resetDelimident($saved_delimident_value, $saved_envvars)
	{
		$this->idsadmin->unset_database("sysadmin");
		$this->idsadmin->phpsession->instance->set_delimident($saved_delimident_value);
		$this->idsadmin->phpsession->instance->set_envvars($saved_envvars);
	}
	
	/** 
	 * Get the locale of a database
	 * 
	 * @param database name
	 * @return locale
	 */
	private function getDatabaseLocale($dbname)
	{
		/* get the locale of the database */
		$locale = null;
		$sql = "select trim(dbs_collate) as dbs_collate from sysdbslocale where dbs_dbsname = '{$dbname}'";
		$locale_res = $this->doDatabaseWork($sql,"sysmaster");
		if (count($locale_res) != 0)
		{
			$locale = $locale_res[0]['DBS_COLLATE'];
		}
		return $locale;
	}
	
	/**
	 * Get the unique non-English locale on this database.
	 * 
	 * Limitation - OAT only supports a database server that has at most one non-English locale. 
	 * 
	 * Why?  Tables in system databases like sysmaster, sysadmin could potentially have data in multiple locales.
	 * An example scenario is db A having Japanese char table names and db B having Chinese char table names.
	 * Compression would create data with these names in the above mentioned system db tables. Hence a table would
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
			$ret = array();
			$ret['faultcode'] = -1;
			$ret['faultstring'] = $this->idsadmin->lang('ErrorMultipleLocales');
			return $ret;
			
		} else if ( count($locale_res) == 1 )
		{
			$locale = $locale_res[0]['DBS_COLLATE'];
		}
		
		return $locale;
	}
	
	/**
	 * Get a connection to the database.
	 * 
	 * @param database name
	 * @param locale
	 * @return PDO connection
	 */
	private function getDBConnection($dbname, $locale=NULL)
	{
		if (is_null($locale)) 
		{
			$db = $this->idsadmin->get_database($dbname);			
		} else {
			require_once(ROOT_PATH."lib/database.php");
			$db = new database($this->idsadmin,$dbname,$locale,"","");
		}
		
		return $db;
	}
	
	
	/**
	 * do the database work.
	 *
	 * @param $sel - SQL to execute
	 * @param $dbname - database name
	 * @param $exceptions - true indicates to throw PDO exceptions,
	 *                      false indicates to handle exceptions via trigger_error
	 * @param $locale - locale of the database
	 * @param $conn - PDO connection object to use for querying.  If null, a new 
	 *                one will be created.   This argument can be used if you want to 
	 *                execute multiple statements on the same connection.
	 */
	private function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false,$locale=NULL,$conn=NULL) 
	{
		$ret = array();

		if (is_null($conn))
		{
			$db = $this->getDBConnection($dbname,$locale);
		} else {
			$db = $conn;
		}

		while (1 == 1)
		{
			$stmt = $db->query($sel,false,$exceptions,$locale);
			
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

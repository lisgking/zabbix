<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2009, 2013.  All rights reserved.
 **********************************************************************/

class schemamgrServer {

	var $idsadmin;
	var $clobType = false;
	
	const IWA_DATAMART_DEF_DB = 'oatmartprobedef';
	const IWA_WORKLOAD_TAB_PRE = "'dwa'.dwa_saved_workloadtab_";
	
	function __construct()
	{
		define ("ROOT_PATH","../../../../");
		define( 'IDSADMIN',  "1" );
		define( 'DEBUG', false);
		define( 'SQLMAXFETNUM' , 100 );

		define('STANDARD',0);
		define('PRIMARY',1);
		define('SECONDARY',2);
		define('SDS',3);
		define('RSS',4);

		include_once("../../../../services/serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");

		require_once(ROOT_PATH."lib/idsadmin.php");
		$this->idsadmin = new idsadmin(true);
		$this->idsadmin->load_lang("schemamgr");

		require_once(ROOT_PATH."lib/feature.php");
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);

	}

	/**
	 * Get the list of databases
	 * 
	 * @dbname - (Optional) if null, return all databases
	 * 	         if not null, return information about specific database
	 **/
	function getDatabases($dbname = null)
	{
		$sel = "SELECT trim(name) as name ";
		$sel .= " ,partnum ";
		$sel .= " ,trim(owner) as owner ";
		$sel .= " ,created ";
		$sel .= " ,is_logging ";
		$sel .= " ,is_buff_log ";
		$sel .= " ,is_ansi ";
		$sel .= " ,is_nls ";
		$sel .= " ,flags ";
		$sel .= " ,'DB' as tabtype ";
		$sel .= " FROM sysdatabases " ;
		if ( $this->idsadmin->phpsession->serverInfo->isPrimary() === false )
		{
			$sel .= " WHERE is_logging != 0 ";
		} 
		if ($dbname != null)
		{
			if ( $this->idsadmin->phpsession->serverInfo->isPrimary() === false )
			{
				$sel .= " AND name = '$dbname'";
			} else {
				$sel .= " WHERE name = '$dbname'";	
			}
		}
		$sel .= " order by name " ;
		$ret = $this->doDatabaseWork($sel,"sysmaster");
		return $ret;
	} 

	/**
	 * Get the list of tables for a specific database.
	 *
	 * @param dbname
	 * @param Optional, table name pattern to search for.
	 *        It will search using: WHERE tabname like '%{$tabname_pattern}%'
	 **/
	function getTableNamesForDatabase($dbname, $tabname_pattern = NULL)
	{
		$ret = array();
		$ret['DBNAME'] = $dbname;
		
		$virtual_tables = "select T.tabid from systables T, sysams A where A.am_type ='P' and  T.am_id == A.am_id ";  //virtual tables
		
		$sel = "SELECT trim(owner)||'.'||trim(tabname) as tabname, "
			 . "tabid, "
			 . "case when flags=16 and tabtype = 'T' then 'R' when tabid in ({$virtual_tables}) then 'X' else tabtype end as tabtype, "//Use 'X' to identify virtual tables
			 . "'{$dbname}' as dbname, "
			 . "case when dbname is null then 'dmname is null' else dbname end as datamartname, " // dbname field is used to store the data mart name. It is not used otherwise.
			 . "case when site is null then 'accel is null' else site end as accelname " // site field is used to store the Accelerator this dm has been deployed to. It is not used otherwise.
			 . "FROM systables WHERE tabtype != 'Q' "   // Do not show sequences in table list
			 . "AND tabtype != ''";                     // Do not show GL_COLLATE and GL_CTYPE type 'tables' in the list		 
			 
		if ($tabname_pattern != NULL)
		{
			$sel .= " and (tabname like '%{$tabname_pattern}%' or dbname like '%{$tabname_pattern}%')";
		}
		$sel .= "order by tabname";
		$ret['TABLES'] = $this->doDatabaseWork($sel,$dbname);
				
		return $ret;
	}
	
	/**
	 * Get the list of databases and tables (for the tree view) that match
	 * the specified table name search string.
	 **/
	function getDatabasesAndTablesWithSearch($tabname_pattern)
	{
		$ret = $this->getDatabases();
		foreach ( $ret as $k => $v )
		{
			$tables = $this->getTableNamesForDatabase( $v['NAME'], $tabname_pattern );
			$ret[$k]['TABLES'] = $tables['TABLES'];
		}
		return $ret;
	} 

	function getTemplatesForExternalTable($dbname)
	{
		$sel = "SELECT trim(owner)||'.'||trim(tabname) as tabname ";
		$sel.= "FROM systables ";
		$sel.= "WHERE tabid >= 100 ";
		$sel.= "AND tabtype IN ( 'T' , 'S' , 'V' , 'P' ) ";

		/* external tables will support IDS datatypes */
		/*$sel.= "AND tabid NOT IN ";
		 $sel.= "	(SELECT systables.tabid ";
		 $sel.= "	 FROM systables,syscolumns ";
		 $sel.= "	 WHERE systables.tabid = syscolumns.tabid ";
		 $sel.= "	 AND (	syscolumns.coltype IN (11,12,19,20,21,22,4118,23) ";
		 $sel.= "	 OR		syscolumns.extended_id IN (10,11)	)";
		 $sel.= "	)";*/

		$sel.= "ORDER BY tabname";

		$ret = $this->doDatabaseWork($sel,$dbname);
		return $ret;
	}

	/**
	 * Get basic information about the database
	 * including owner, dbspace, collation, and logging info.
	 *
	 * @param $dbname
	 * @return databaseServerInfo
	 */
	function getDBInfo($dbname)
	{
		$qry = "SET ISOLATION TO DIRTY READ";
		/* need to do this in a try catch because
		 * you cannot set isolation levels on a non logged database
		 */
		try {
			$this->doDatabaseWork($qry,$dbname,true);
		} catch ( PDOException $e )
		{

		}
		$qry = "SELECT TRIM(name) AS dbname, TRIM(owner) as OWNER, "
		. "created, TRIM(DBINFO('dbspace',partnum)) AS dbspace, "
		. "CASE when partnum > 0 THEN "
		. "   (select collate from sysmaster:systabnames where "
		. "    sysmaster:systabnames.partnum = sysmaster:sysdatabases.partnum) "
		. "ELSE 'Unknown'  End as collation, "
		. "CASE when is_logging = 1 THEN "
		. "     case when is_buff_log = 1 THEN 'Buffered' "
		. "    when is_ansi = 1 THEN 'ANSI' "
		. "    else 'Unbuffered' end "
		. "ELSE 'NotLogged' end as logging, ";
		if (Feature::isAvailable(Feature::PANTHER_UC2, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			$qry .= "is_case_insens as case_insensitive, ";
		} else {
			$qry .= " 0 as case_insensitive, ";
		}
		$qry .= "CASE when partnum > 0 THEN "
		. "	(SELECT ROUND(sum(E.size*T.pagesize),2) as bytes_used_tables "
		. "	from sysmaster:sysextents as E, systables as T "
		. "	where E.tabname = T.tabname "
		. "	and E.dbsname='{$dbname}' "
		. "	group by E.dbsname) end as bytes_used_tables, "
		. "CASE when partnum > 0 THEN "
		. "	(SELECT ROUND(sum(E.size*I.pagesize),2) as bytes_used_indexes "
		. "	from sysmaster:sysextents as E, sysindices I "
		. "	where E.tabname = I.idxname "
		. "	and E.dbsname='{$dbname}' "
		. "	group by E.dbsname) end as bytes_used_indexes "
		. "FROM sysmaster:sysdatabases WHERE name = '{$dbname}'";

		$ret = $this->doDatabaseWork($qry,$dbname);

		require_once("lib/databaseInfo.php");
		$dbInfo = new databaseInfo();
		$dbInfo->name = $ret[0]['DBNAME'];
		$dbInfo->owner = $ret[0]['OWNER'];
		$dbInfo->dbspace = $ret[0]['DBSPACE'];
		$dbInfo->locale  = $ret[0]['COLLATION'];
		$dbInfo->logging = $ret[0]['LOGGING'];
		$dbInfo->case_insensitive = $ret[0]['CASE_INSENSITIVE'];
		$dbInfo->space_used = ($ret[0]['BYTES_USED_TABLES'] + $ret[0]['BYTES_USED_INDEXES']);
		$dbInfo->creation_date = $ret[0]['CREATED'];

		$feature = Feature::CHEETAH2_UC6;
		// if we are UC6 or above get the # of unload / load jobs..
		if (  Feature::isAvailable ( $feature , $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			$res = $this->getNumJobs($dbname,$tabInfo->tabname);
			$dbInfo->numloadjobs = isset ( $res['LOAD']  ) ?  $res['LOAD'] : 0;
			$dbInfo->numunloadjobs = isset ( $res['UNLOAD']  ) ?  $res['UNLOAD'] : 0;
		}
		else
		{
			$dbInfo->numloadjobs = 0;
			$dbInfo->numunloadjobs = 0;
		}
		
		/* For server versions >= 12.10, we also want to figure out if this db has tables that are 'gridtables'.
		 * A table can be a gridtable in only one grid, even if the server is part of multiple grids (because you can only be in a single grid when you perform an alter).
		 */
		$dbInfo->grid = ''; //Default for all server versions
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			// First check if syscdr exists.  If not, then no ER/Grids defined on the server, so the 
			// table is not a grid table.
			$qry = "select count(*) as count from sysdatabases where name = 'syscdr'";
			$ret = $this->doDatabaseWork($qry,"sysmaster");
			if (count($ret) > 0 && $ret[0]["COUNT"] == 1)
			{ 
				$qry = "select gd_name as grid_name "
					. "from grid_def gdef, grid_database gdb "
					. "where gdef.gd_id = gdb.gdb_gid and gdb.gdb_dbname = '{$dbname}'";
				$ret = $this->doDatabaseWork($qry,"syscdr");
				if (count($ret))
				{
					$dbInfo->grid = ($ret[0]["GRID_NAME"]);
				}
			}
		}
		return $dbInfo;
	}

	/**
	 * Get all the parts of the databaseview .. info , procedures casts etc..
	 * @param $dbname
	 * @return unknown_type
	 */
	function getDBViewInfo($dbname, $rows_per_page = null)
	{
		$ret = array();

		$res = $this->getDBInfo($dbname);
		$ret['INFO'] = $res;

		$res = $this->getDBAggregates($dbname, $rows_per_page);
		$ret['AGGREGATES'] = $res;

		$res = $this->getDBProcedures($dbname, $rows_per_page);
		$ret['PROCEDURES'] = $res;
		
		$res = $this->getDBSequences($dbname, $rows_per_page);
		$ret['SEQUENCES'] = $res;

		$res = $this->getDBUDTs($dbname, $rows_per_page);
		$ret['UDT'] = $res;

		$res = $this->getDBPrivileges($dbname, $rows_per_page);
		$ret['PRIVILEGES'] = $res;

		$res = $this->getDBDatablades($dbname);
		$ret['DATABLADES'] = $res;

		$res = $this->getDBCasts($dbname, $rows_per_page);
		$ret['CASTS'] = $res;

		$res = $this->getDBOpclasses($dbname, $rows_per_page);
		$ret['OPCLASSES'] = $res;

		return $ret;
	}

	/**
	 * Get procedures/functions for a database
	 *
	 * @param $dbname
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $procname_search_pattern (optional)
	 */
	function getDBProcedures($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $procname_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select '{$dbname}' as dbname, procid, trim(procname) as procname, "
		. "TRIM(owner) AS owner, "
		. "decode(isproc, 'f', 'Function', 'Procedure') as type, "
		. "case LOWER(mode) "
		. "    when 'd' then 'DBA' "
		. "    when 'o' then 'Owner' "
		. "    when 'p' then 'Protected' "
		. "    when 'r' then 'Restricted' "
		. "    when 't' then 'Trigger' "
		. "    else 'Unknown' end as mode, "
		. "langname as lang, "
		. "numargs, "
		. "paramtypes::lvarchar as paramtypes, "
		. "retsize "
		. "from sysprocedures p "
		. "left outer join sysroutinelangs l "
		. "on p.langid = l.langid ";
		if ($procname_search_pattern != null)
		{
			$qry .= "where procname like '%{$procname_search_pattern}%'";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get procedures/functions details for a database
	 *
	 * @param $dbname
	 */
	function getDBProceduresDetail($dbname,$procid)
	{
		$procbody = "";

		$qry = "select data from sysprocbody where procid={$procid} and datakey='T'";
		$routine_text = $this->doDatabaseWork($qry,$dbname);
		foreach($routine_text as $i=>$v)
		{
			$procbody.=trim($v['DATA']);
		}

		$procbody = trim($procbody);
		$procbody .= "\n";

		$qry = "select data from sysprocbody where procid={$procid} and datakey='A'";
		$routine_text = $this->doDatabaseWork($qry,$dbname);
		foreach($routine_text as $i=>$v)
		{
			$procbody.=trim($v['DATA']);
		}

		return $procbody;
	}

	/**
	 * Get sequences for database
	 *
	 * @param $dbname
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - sequence name to search for (optional)
	 */
	function getDBSequences ($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select tabname as seqname, owner, "
		. "inc_val, start_val, min_val, max_val, "
		. "restart_val, cycle, cache, a.grantor, a.grantee, a.tabauth "
		. "from syssequences s, systables t, systabauth a "
		. "where s.tabid=t.tabid and s.tabid=a.tabid and t.tabid=a.tabid";
		
		if ($name_search_pattern != null)
		{
			$qry .= " and tabname like '%{$name_search_pattern}%'";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get user defined types for database
	 *
	 * @param $dbname
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - type name to search for (optional)
	 */
	function getDBUDTs($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select decode(name,NULL,'',name) as typename, "
		. "owner, type as type_id, "
		. "case LOWER(mode) "
		. "    when 'b' then 'Opaque' "
		. "    when 'c' then 'Collection_or_Unnamed_ROW' "
		. "    when 'd' then 'Distinct' "
		. "    when 'r' then 'Named_ROW' "
		. "    when ' ' then 'Built-in' "
		. "    else 'Unknown' end as mode, "
		. "decode(description,NULL,'',description) as desc, "
		. "maxlen as maxlength, length, "
		. "case byvalue "
		."		when 'T' then 'TRUE' "
		."		when 'F' then 'FALSE' "
		."		else 'UNKNOWN' end as byvalue, "
		. "case cannothash "
		."		when 'T' then 'TRUE' "
		."		when 'F' then 'FALSE' "
		."		else 'UNKNOWN' end as cannothash, "
		. "align "
		. "from sysxtdtypes t "
		. "left outer join sysxtddesc d "
		. "on t.extended_id = d.extended_id";
		
		if ($name_search_pattern != null)
		{
			$qry .= " where name like '%{$name_search_pattern}%'";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get privileges for database
	 *
	 * @param $dbname
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $username_search_pattern - user name to search for (optional)
	 */
	function getDBPrivileges($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $username_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select username as name, "
		. "CASE usertype WHEN 'C' THEN 'CONNECT' "
		. "    WHEN 'D' THEN 'DBA' "
		. "    WHEN 'R' THEN 'RESOURCE' "
		. "    END  as usertype, "
		. "defrole "
		. "from sysusers "
		. "where usertype<>'G'";
		
		if ($username_search_pattern != null)
		{
			$qry .= " and username like '%{$username_search_pattern}%' ";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get datablade information for database
	 *
	 * @param $dbname
	 */
	function getDBDatablades($dbname)
	{

		$qry = "select count(*) as cnt from systables where tabname = 'sysbldregistered'";
		$row = $this->doDatabaseWork($qry,$dbname);
		if ( $row[0]['CNT'] == 0 )
		{
			return array();
		}

		$qry = "select bld_id from sysbldregistered";
		return $this->doDatabaseWork($qry,$dbname);
	}

	/**
	 * Get aggregates for database
	 *
	 * @param $dbname
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - aggregate name to search for (optional) 
	 */
	function getDBAggregates($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select name, owner, init_func, iter_func, "
		. "combine_func, final_func, "
		. "decode(handlesnulls,'f','FALSE','TRUE') as handlesnulls "
		. "from sysaggregates";
		
		if ($name_search_pattern != null)
		{
			$qry .= " where name like '%{$name_search_pattern}%' ";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get casts for database
	 *
	 * @param $dbname
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $datatype_search_pattern, to or from data type to search for (optional) 
	 **/
	function getDBCasts($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $datatype_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select owner, "
		. "schema_coltypename(argument_type,argument_xid) as from_type, "
		. "schema_coltypename(result_type,result_xid) as to_type, "
		. "decode(routine_name,NULL,'Not_Cataloged',routine_name) as routine_name, "
		. "case (class) when 'E' then 'Explicit' "
		. "     when 'I' then 'Implicit' "
		. "     when 'S' then 'Built-in' "
		. "     end as cast_type "
		. "from syscasts";
		if ($datatype_search_pattern != null)
		{
			$qry .= " where (schema_coltypename(argument_type,argument_xid) like '%{$datatype_search_pattern}%' " 
				 .  "OR schema_coltypename(result_type,result_xid) like '%{$datatype_search_pattern}%')";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get opclasses for database
	 *
	 * @param $dbname
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - opclass name to search for (optional) 
	 */
	function getDBOpclasses($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select opclassname, owner, "
		. "am_name as access_method, "
		. "ops as operators, support as support_func "
		. "from sysopclasses, sysams "
		. "where sysopclasses.amid = sysams.am_id";
		
		if ($name_search_pattern != null)
		{
			$qry .= " and opclassname like '%{$name_search_pattern}%'";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get dbspaces.
	 * 
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - space name to search for (optional) 
	 */
	function getDBSpaces($rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();

		$sql = "SELECT A.dbsnum, " .
        " trim(B.name) as name, " .
        "CASE " .
        " WHEN bitval(B.flags,'0x4')>0 " .
        "   THEN 'Disabled' " .
        " WHEN bitand(B.flags,3584)>0 " .
        "   THEN 'Recovering' " .
        " ELSE " .
        "   'Operational' " .
        " END  as dbsstatus, " .
		" 'false' as selected , ".
		" B.flags , ".
        " sum(chksize*{$defPagesize}) as DBS_SIZE , " .
        " sum(decode(mdsize,-1,nfree,udfree) * {$defPagesize}) as free_size, " .
        " TRUNC(100-sum(decode(mdsize,-1,nfree,udfree))*100/ ".
        " sum(chksize),2) as used,".
        " MAX(B.nchunks) as nchunks, " .
        " MAX(A.pagesize) as pgsize, " .
        " sum(chksize) as sortchksize, " .
        " sum(decode(mdsize,-1,nfree,udfree)) as sortusedsize " .
        "FROM syschktab A, sysdbstab B " .
        "WHERE A.dbsnum = B.dbsnum " .
		"AND bitval(B.flags, '0x2000') = 0 ". // no tempdbs
		"AND bitval(B.flags, '0x8000') = 0 ". // no sbspaces
		"AND bitval(B.flags, '0x10') = 0 ".   // no blobspaces
		(($name_search_pattern != null)? "AND B.name like '%{$name_search_pattern}%' ":"") . 
        "GROUP BY A.dbsnum , name, 3 ,4 ,5";
		
		if ($sort_col == null)
		{
			// default sort order
			$sort_col = "A.dbsnum";
		}

		$dbspaces = array();
		$dbspaces['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sql,$rows_per_page,$page,$sort_col),"sysmaster");
		
		$dbspaces['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysmaster");
		foreach ($temp as $row)
		{
			$dbspaces['COUNT'] = $row['COUNT'];
		}
		return $dbspaces;
	}


	/**
	 * Get basic info about table:
	 * owner, dbspace, # of columns, # of rows, etc.
	 *
	 * @param $dbname
	 * @param $tabid
	 */
	function getTabInfo($dbname, $tabid, $tabtype = "")
	{
		/* does this version of the server have external table support */
		$feature = Feature::CHEETAH2_UC6;
		if (  Feature::isAvailable ( $feature , $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			$qry = <<< EOF
select
 decode ( (sum ( sysmaster:bitval(ti_flags,'0x08000000') )) , 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed
, tabname
, owner
, pagesize
, created
, ncols , nrows , rowsize
, npused
, fextsize
, nextsize
, audited
, locklevel , tabtype , dbspace
, maxerrors
, case  fmttype
   when 'D' then 'Delimited'
   when 'F' then 'Fixed'
   when 'I' then 'Informix'
   else 'unknown'
   end as fmttype
, recdelim
, flddelim
, rejectfile
, ndfiles
, mode
, escape
, dbdate
, dbmoney
from sysmaster:systabinfo
,
( select
   hex(decode(partnum , 0 , partn , partnum))
   ,trim(tabname) as tabname
   ,systables.tabid
   ,trim(owner) as owner
   ,pagesize
   ,created as created
   ,ncols
   ,systables.nrows
   ,rowsize
   ,systables.npused
   ,fextsize
   ,nextsize
   , sysmaster:bitval(systables.flags,'0x40') as audited
   , case locklevel
     when 'B' then 'Page'
     when 'P' then 'Page'
     when 'R' then 'Row'
     when 'T' then 'Table'
     else 'Unknown'
     end as locklevel
   , case
     when tabtype = 'T' and systables.flags != 16 then 'Table'
     when tabtype = 'T' and systables.flags = 16 then 'Raw'
     when tabtype = 'E' then 'External'
     when tabtype = 'V' then 'View'
     when tabtype = 'Q' then 'Sequence'
     when tabtype = 'P' then 'PrivateSynonym'
     when tabtype = 'S' then 'PublicSynonym'
     else 'Unknown'
     end as tabtype
   , case
     when partnum = 0 then
       case systables.tabtype
           when 'V' then 'NotApplicable'
           when 'P' then 'NotApplicable'
           when 'S' then 'NotApplicable'
           when 'E' then 'NotApplicable'
           else 'FragmentedTable'
       end
     when partnum < 1000
        then 'PseudoTable'
     else
         trim( dbinfo('dbspace',partnum) )
     end as dbspace
, maxerrors
, fmttype
, recdelim
, flddelim
, rejectfile
, ndfiles
, sysmaster:bitval(sysexternal.flags,'0x2') as escape
, case
    when sysmaster:bitval(sysexternal.flags,'0x4') = 1 then 'deluxe'
    when sysmaster:bitval(sysexternal.flags,'0x8') = 1 then 'express'
    else 'unknown'
 end  as mode
 , datefmt as dbdate
 , moneyfmt as dbmoney
from systables
, outer sysfragments , outer sysexternal
where systables.tabid = sysfragments.tabid  and fragtype  = 'T'
and sysexternal.tabid = systables.tabid
)
as
t2 (partn , tabname , tabid , owner ,  pagesize , created , ncols , nrows , rowsize , npused , fextsize , nextsize , audited , locklevel , tabtype , dbspace , maxerrors , fmttype , recdelim , flddelim , rejectfile , ndfiles , escape , mode , dbdate , dbmoney )

where ti_partnum = partn
and tabid = {$tabid}
group by tabname , owner  , pagesize , created , ncols , nrows , rowsize , npused , fextsize , nextsize , audited , locklevel , tabtype, dbspace , maxerrors , fmttype , recdelim , flddelim , rejectfile , ndfiles , escape , mode , dbdate , dbmoney

EOF;
		}
		else
		{
			$qry = <<< EOF
select
 decode ( (sum ( sysmaster:bitval(ti_flags,'0x08000000') )) , 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed
, tabname
, owner
, pagesize
, created
, ncols , nrows , rowsize
, npused
, fextsize
, nextsize
, audited
, locklevel , tabtype , dbspace

from sysmaster:systabinfo
,
( select
   hex(decode(partnum , 0 , partn , partnum))
   ,trim(tabname) as tabname
   ,systables.tabid
   ,trim(owner) as owner
   ,pagesize
   ,created as created
   ,ncols
   ,systables.nrows
   ,rowsize
   ,systables.npused
   ,fextsize
   ,nextsize
   , sysmaster:bitval(systables.flags,'0x40') as audited
   , case locklevel
     when 'B' then 'Page'
     when 'P' then 'Page'
     when 'R' then 'Row'
     when 'T' then 'Table'
     else 'Unknown'
     end as locklevel
   , case
     when tabtype = 'T' and systables.flags != 16 then 'Table'
     when tabtype = 'T' and systables.flags = 16 then 'Raw'
     when tabtype = 'E' then 'External'
     when tabtype = 'V' then 'View'
     when tabtype = 'Q' then 'Sequence'
     when tabtype = 'P' then 'PrivateSynonym'
     when tabtype = 'S' then 'PublicSynonym'
     else 'Unknown'
     end as tabtype
   , case
     when partnum = 0 then
       case systables.tabtype
           when 'V' then 'NotApplicable'
           when 'P' then 'NotApplicable'
           when 'S' then 'NotApplicable'
           when 'E' then 'NotApplicable'
           else 'FragmentedTable'
       end
     when partnum < 1000
        then 'PseudoTable'
     else
         trim( dbinfo('dbspace',partnum) )
     end as dbspace
from systables
, outer sysfragments
where systables.tabid = sysfragments.tabid  and fragtype  = 'T'
)
as
t2 (partn , tabname , tabid , owner ,  pagesize , created , ncols , nrows , rowsize , npused , fextsize , nextsize , audited , locklevel , tabtype , dbspace  )

where ti_partnum = partn
and tabid = {$tabid}
group by tabname , owner  , pagesize , created , ncols , nrows , rowsize , npused , fextsize , nextsize , audited , locklevel , tabtype, dbspace

EOF;
		}

		$ret = $this->doDatabaseWork($qry,$dbname);
		/* we should get something back , if not then it maybe a pseudo table which does not have any systabinfo */
		if ( count($ret) == 0 )
		{
			$qry = "select tabname, owner, created, "
			. " case  "
			. " when partnum = 0 then "
			. " case  when tabtype = 'V' then 'NotApplicable' "
			. "        when tabtype = 'P' then 'NotApplicable' "
			. "        when tabtype = 'S' then 'NotApplicable' "
			. "        when tabtype = 'E' then 'NotApplicable' "
			. " else "
			. "       'FragmentedTable' "
			. " end "
			. " when partnum > 0 and  partnum < 1000 "
			. " then 'PseudoTable' "
			. " else "
			. " TRIM(dbinfo('dbspace',partnum)) "
			. " end as dbspace "
			. " ,ncols, nrows, rowsize, "
			. "case tabtype when 'T' then 'Table' "
			. "    when 'E' then 'External Table' "
			. "    when 'V' then 'View' "
			. "    when 'Q' then 'Sequence' "
			. "    when 'P' then 'PrivateSynonym' "
			. "    when 'S' then 'PublicSynonym' "
			. "    else 'Unknown' end as tabtype, "
			. "case locklevel when 'B' then 'Page' "
			. "    when 'P' then 'Page' "
			. "    when 'R' then 'Row' "
			. "    when 'T' then 'Table' "
			. "    else 'Unknown' end as locklevel, "
			. "  npused, fextsize, nextsize "
			. ", 0  as compressed, 0 as audited "
			. " from systables where tabid = {$tabid}";

			$ret = $this->doDatabaseWork($qry,$dbname);
		}

		/* if we still get nothing then there must be a problem .. perhaps someone has dropped the table */

		if ( count ( $ret ) == 0 )
		{
			trigger_error("Information for tabid {$tabid} could not be found. It may have been dropped.");
			return;
		}

		require_once("lib/tableInfo.php");
		$tabInfo = new tableInfo();
		$tabInfo->tabname = trim($ret[0]['TABNAME']);
		$tabInfo->owner   = trim($ret[0]['OWNER']);
		$tabInfo->type    = ($tabtype == "X") ? "Virtual" : trim($ret[0]['TABTYPE']);
		$tabInfo->tabid   = $tabid;
		if ($ret[0]['DBSPACE'] == "NotApplicable" || $ret[0]['DBSPACE'] == "FragmentedTable" || $ret[0]['DBSPACE'] == "PseudoTable")
		{
			$tabInfo->dbspace = $this->idsadmin->lang(trim($ret[0]['DBSPACE']));
		} else {
			$tabInfo->dbspace = trim($ret[0]['DBSPACE']);
		}
		$tabInfo->numcols = trim($ret[0]['NCOLS']);
		$tabInfo->nrows = trim($ret[0]['NROWS']);
		$tabInfo->rowsize = trim($ret[0]['ROWSIZE']);
		$tabInfo->locklevel = trim($ret[0]['LOCKLEVEL']);
		$tabInfo->datapages = trim($ret[0]['NPUSED']);
		$tabInfo->firstextent = $ret[0]['FEXTSIZE'];
		$tabInfo->nextextent  = $ret[0]['NEXTSIZE'];
		$tabInfo->compressed  = trim($ret[0]['COMPRESSED']);
		$tabInfo->audited     = $ret[0]['AUDITED'];
		/* test if these are set , because it maybe we are connected to a pre UC6 */
		$tabInfo->maxerrors   = isset ( $ret[0]['MAXERRORS']  ) ?  $ret[0]['MAXERRORS'] : 0;
		$tabInfo->fmttype     = isset ( $ret[0]['FMTTYPE']    ) ?  $ret[0]['FMTTYPE']   : "";
		$tabInfo->recdelim    = isset ( $ret[0]['RECDELIM']   ) ?  $ret[0]['RECDELIM']  : "";
		$tabInfo->flddelim    = isset ( $ret[0]['FLDDELIM']   ) ?  trim($ret[0]['FLDDELIM'])  : "";
		$tabInfo->dbdate      = isset ( $ret[0]['DBDATE']     ) ?  trim($ret[0]['DBDATE'])    : "";
		$tabInfo->dbmoney     = isset ( $ret[0]['DBMONEY']    ) ?  $ret[0]['DBMONEY']  : "";
		$tabInfo->rejectfile  = isset ( $ret[0]['REJECTFILE'] ) ?  trim($ret[0]['REJECTFILE']) : "";
		$tabInfo->ndfiles     = isset ( $ret[0]['NDFILES']    ) ?  $ret[0]['NDFILES']  : 0;
		$tabInfo->mode        = isset ( $ret[0]['MODE']       ) ?  $ret[0]['MODE']     : "";
		$tabInfo->escape      = isset ( $ret[0]['ESCAPE']     ) ?  (( $ret[0]['ESCAPE'] == 0 ) ? "false" : "true" ) : "" ;
		if ( $tabInfo->ndfiles > 0 )
		{
			$tabInfo->extFiles = $this->getExternalFiles($dbname,$tabid);
		}
		if ($tabInfo->type == "View")
		{
			$tabInfo->viewDefinition = $this->getViewDefinition($dbname,$tabid);
		}

		if (  Feature::isAvailable ( $feature , $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			$res = $this->getNumJobs($dbname,$tabInfo->tabname);
			$tabInfo->numloadjobs = isset ( $res['LOAD']  ) ?  $res['LOAD'] : 0;
			$tabInfo->numunloadjobs = isset ( $res['UNLOAD']  ) ?  $res['UNLOAD'] : 0;
		}
		else
		{
			$tabInfo->numloadjobs = 0;
			$tabInfo->numunloadjobs = 0;
		}
		
		// For server versions >= 11.70, we also want to find out if the table has an automatic fragmentation strategy
		// (i.e. some form of interval fragmentation) turned on.  If so, we want to bring back that info too.
		if (Feature::isAvailable(Feature::PANTHER, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			$qry = "select evalpos, exprtext, flags, colno, npused, nrows "
				 . "from sysfragments "
				 . "where tabid = {$tabid} "
				 . "and fragtype = 'T' "
				 . "and strategy = 'N' "
				 . "and evalpos < 0 "
				 . "order by evalpos desc";
			
			// We cannot use the doDatabaseWork here because exprtext is a TEXT column, otherwise it crashes apache
			$db = $this->idsadmin->get_database($dbname);
			$stmt = $db->query($qry);
			$fragData = array();
			while ( $row = $stmt->fetch() )
			{
				$buf = "";
				if ( get_resource_type( $row['EXPRTEXT'] )  == "stream"  )
				{
					$buf = stream_get_contents($row['EXPRTEXT']);
				}
				$row['EXPRTEXT'] = $buf;
				$fragData[] = $row;
			}
			
			if (count($fragData) > 0)
			{
				$tabInfo->autoFragStrategy = $this->processAutoFragmentationStrategyForTable($fragData);
			}
		} 
		
		/* For server versions >= 12.10, that support auto compression, we need to get some more detailed information on the compression state of table. */
		$tabInfo->autoCompressed = "no"; //Default for all server versions
		$tabInfo->uncompressed = (($tabInfo->compressed == "yes")? "no" : (($tabInfo->compressed == "some")? "some":"yes"));
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			$qry = "select "
				 . "decode ( sum( decode( bitand(p.flags,'0x08000000'),0,0,1) ), 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed, "
				 . "decode ( sum(bitand(p.flags2,'0x00000001')), 0, 'no', count(*), 'yes', 'some') as auto_compressed, "
				 . "decode ( sum(decode( bitand(p.flags,'0x08000000') + bitand(p.flags2,'0x00000001'),0,1,0) ), 0, 'no', count(*), 'yes', 'some') as uncompressed "
				 . "from systabnames t, sysptnhdr p "
				 . "where dbsname='{$dbname}' "
				 . "and tabname='{$tabInfo->tabname}' "
				 . "and owner='{$tabInfo->owner}'"
				 . "and t.partnum = p.partnum";
			$ret = $this->doDatabaseWork($qry,"sysmaster");
			if (count($ret) > 0)
			{
				$tabInfo->compressed = $ret[0]['COMPRESSED'];
				$tabInfo->autoCompressed = $ret[0]['AUTO_COMPRESSED'];
				$tabInfo->uncompressed = $ret[0]['UNCOMPRESSED'];
			}
		}
		
		/* For server versions >= 12.10, we also want to figure out if this table is a grid table. */
		$tabInfo->gridTable = ''; //Default for all server versions
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			// First check if syscdr exists.  If not, then no ER/Grids defined on the server, so the 
			// table is not a grid table.
			$qry = "select count(*) as count from sysdatabases where name = 'syscdr'";
			$ret = $this->doDatabaseWork($qry,"sysmaster");
			if (count($ret) > 0 && $ret[0]["COUNT"] == 1)
			{
				/* If syscdr exists, check if this table is a grid table. Also get the grid name. A server can be a part of multiple grids, but a table can be
				 * a gridtable in only one grid (because you can only be in a single grid when you perform an alter).
				 */ 
				$qry = "select count(*) as count, gd_name as grid_name "
					 . "from grid_database d, grid_tablelist t, grid_def g "
					 . "where d.gdb_key=t.gtb_dbkey "
					 . "and g.gd_id = d.gdb_gid "
					 . "and d.gdb_dbname = '{$dbname}' "
					 . "and t.gtb_owner = '{$tabInfo->owner}' " 
					 . "and t.gtb_table = '{$tabInfo->tabname}' "
					 . "group by gd_name";
				$ret = $this->doDatabaseWork($qry,"syscdr");
				if (count($ret))
				{
					$tabInfo->gridTable = ($ret[0]["GRID_NAME"]);
				}
			}
		}
		
		return $tabInfo;
	}
	
	/**
	 * Process the auto (interval) fragmentation strategy for a table by converting the data stored 
	 * in sysfragments to a human-readble text string.
	 * 
	 * @param $fragData - rows from sysfragments that represent the auto fragmentation strategy
	 **/
	private function processAutoFragmentationStrategyForTable($fragData)
	{
		$frag_col = "";
		$dbspace_list = "";
		$interval = "";
		$rolling_window = "";
		
		foreach ($fragData as $row)
		{
			switch($row['EVALPOS'])
			{
				case -1:
					// EVALPOS = -1 holds the list of dbspaces to be used for automatic fragmentation
					$dbspace_list = trim($row['EXPRTEXT']);
					break;
					
				case -2:
					// EVALPOS = -2 holds the interval
					// This could be a numeric value or a date interval
					$exprtext = $row['EXPRTEXT'];
					if (strpos($exprtext, "interval") === false)
					{
						// Numeric interval.
						$interval = $exprtext;
					} else {
						// Date interval.
						// The actual interval value enclosed in (), so parse that out first.
						$interval_val = substr($exprtext, strpos($exprtext,'(') + 1);
						$interval_val = trim(substr($interval_val,0, strpos($interval_val, ')')));
						  
						//Next step is to figure out if it is a year to month interval or day to second interval
						if (strpos($exprtext, "day") === false)
						{
							// Year to month interval.  Only year or month will be set in $interval_val
							$interval_arr = preg_split('/-/', $interval_val);
							if (isset($interval_arr[0]) && $interval_arr[0] > 0)
							{
								$interval = $this->idsadmin->lang('x_years', array(intval($interval_arr[0])));
							} 
							else if (isset($interval_arr[1]) && $interval_arr[1] > 0)
							{
								$interval = $this->idsadmin->lang('x_months', array(intval($interval_arr[1])));
							}
						} else {
							// Day to second interval - so the number of days is the first value in $interval_val
							$interval_arr = preg_split('/ /', $interval_val);
							$interval = $this->idsadmin->lang('x_days', array(intval($interval_arr[0])));
						}
					}
					break;
					
				case -3: 
					// EVALPOS = -3 holds the column to fragment by
					$frag_col =  trim($row['EXPRTEXT']);
					break;
					
				case -4:
					// EVALPOS = -4 holds the rolling window strategy
					$rolling_window = $this->idsadmin->lang('rolling_window_frag_strategy') . " ";
					
					// COLNO holds the max fragments limit
					if ($row['COLNO'] > 0)
					{
						$rolling_window .= $this->idsadmin->lang('rolling_window_limit_by_frag', array($row['COLNO'])) . ", ";
					}
					
					// NROWS holds the max size limit.  NPUSED holds the max size units multiplier.
					if ($row['NROWS'] > 0)
					{
						$max_size = $row['NROWS'] * $row['NPUSED'] * 1024;  // Multiply by extra 1024 since base units is KB
						$rolling_window .= $this->idsadmin->lang('rolling_window_limit_by_size', array($this->idsadmin->format_units($max_size,0))) . ", ";
					}
					
					// FLAGS holds the old fragment policy: detach or discard
					if (($row['FLAGS'] & 0x0008) > 0)
					{
						$rolling_window .= $this->idsadmin->lang('discard');
					} else {
						$rolling_window .= $this->idsadmin->lang('detach');
					}
					$rolling_window .= ".";
					
					break;
			}
		}
		
		$text = $this->idsadmin->lang("interval_frag_strategy", array($frag_col, $interval, $dbspace_list));  
		if ($rolling_window != "")
		{
			$text .= "  " . $rolling_window;
		}
		return $text;
	}

	/**
	 * get the number of jobs
	 */
	function getNumJobs($dbname , $tabname , $justfordb = false)
	{
		$sql = " SELECT type , count(*) AS cnt ";
		$sql .= " FROM ( select UPPER(ph_bg_type) AS type , ph_bg_job_id , count(*) AS cnt FROM ph_bg_jobs ";
		$sql .= " WHERE ph_bg_database = '{$dbname}' ";

		if ( $justfordb === false )
		{
			$sql .= "AND ph_bg_cmd matches ('*{$tabname}*')";
		}

		$sql .= " group by 1 , 2 ) ";

		$sql .= " GROUP BY 1";

		$res = $this->doDatabaseWork($sql , "sysadmin");
		$ret = array();
		foreach ( $res as $k => $v )
		{
			$ret[$v['TYPE']] = $v['CNT'];
		}
		return $ret;
	}

	/**
	 * get External Tables file info.
	 */
	function getExternalFiles($dbname,$tabid)
	{
		$sql = <<< EOF
		SELECT TRIM(dfentry) as FILENAME FROM sysextdfiles WHERE tabid = {$tabid}
EOF;
		$ret = $this->doDatabaseWork($sql , $dbname);

		return $ret;
	}
	
	/**
	 * get view definition
	 */
	private function getViewDefinition($dbname,$tabid)
	{
		$sql = "select seqno, viewtext from sysviews where tabid={$tabid} order by seqno";
		$ret = $this->doDatabaseWork($sql , $dbname);
		
		$view_def = "";
		foreach ($ret as $row)
		{
			$view_def .= $row['VIEWTEXT'];
		}
		return $view_def;
	}

	function deleteJob($jobs="")
	{
		$ret = array();
		$ret['TYPE'] = "delete";

		$jobstorun = preg_split("/;/",$jobs);
		foreach ( $jobstorun as $k => $job )
		{
			if ( $job == "" )
			{
				continue;
			}
			$sql = " DELETE FROM ph_bg_jobs WHERE ph_bg_name = '{$job}' ";

			try {
				$ret['DATA'] = $this->doDatabaseWork($sql,"sysadmin",true);
			}
			catch ( PDOException $e )
			{
				/* the statement may contain '<' chars , this causes a problem when we display the text
				 * back in flex , so we use the htmlspecial chars on the statment.
				 */
				$job = htmlspecialchars($job,ENT_COMPAT,"UTF-8");
				$ret['DATA'] = array($e->getCode(),"{$job}",  $e->getMessage() );

			}
		}
		return $ret;
	}

	function deleteResults($jobs="")
	{
		$ret = array();
		$ret['TYPE'] = "deleteresults";
		$jobstorun = preg_split("/;/",$jobs);
		foreach ( $jobstorun as $k => $job )
		{
			if ( $job == "" )
			{
				continue;
			}
			$sp = preg_split("/:/",$job);
			$seq = $sp[0];
			$id  = $sp[1];
			$sql = " DELETE FROM ph_bg_jobs_results WHERE ph_bgr_tk_sequence = '{$seq}' AND ph_bgr_tk_id = '{$id}'";

			try {
				$ret['DATA'] = $this->doDatabaseWork($sql,"sysadmin",true);
			}
			catch ( PDOException $e )
			{
				/* the statement may contain '<' chars , this causes a problem when we display the text
				 * back in flex , so we use the htmlspecial chars on the statment.
				 */
				$job = htmlspecialchars($job,ENT_COMPAT,"UTF-8");
				$ret['DATA'] = array($e->getCode(),"{$job}",  $e->getMessage() );

			}
		}
		return $ret;
	}

	function groupJob($grpName="grp",$jobs="")
	{
		$ret = array();
		$ret['TYPE'] = "group";

		$jobstorun = preg_split("/;/",$jobs);

		foreach ( $jobstorun as $k => $job )
		{
			if ( $job == "" )
			{
				continue;
			}
			$sql .= "EXECUTE FUNCTION exectask('Job Runner','{$job}');\n";
		}
		$ret['DATA'] = $this->createJob("sysadmin","ph_bg_jobs",$sql,"group",$grpName,0);

		return $ret;
	}

	function runJob($jobs="")
	{
		$ret = array();
		$ret['TYPE'] = "run";

		$jobstorun = preg_split("/;/",$jobs);
		foreach ( $jobstorun as $k => $job )
		{
			if ( $job == "" )
			{
				continue;
			}
			$sql = " EXECUTE FUNCTION exectask_async('Job Runner' , '{$job}') ";

			try {
				$res = $this->doDatabaseWork($sql,"sysadmin",true);
				if ( $res[0][0] != 0 )
				{
					$ret['DATA'] = "{$job} returned {$res[0][0]} ".$this->idsadmin->lang("check_admin_log");
				}
				else
				{
					$ret['DATA'] = $this->idsadmin->lang("running",array($job));
				}
			}
			catch ( PDOException $e )
			{
				/* the statement may contain '<' chars , this causes a problem when we display the text
				 * back in flex , so we use the htmlspecial chars on the statment.
				 */
				$job = htmlspecialchars($job,ENT_COMPAT,"UTF-8");
				$ret['DATA'] = array($e->getCode(),"{$job}",  $e->getMessage() );

			}
		}

		return $ret;
	}

	function getJobDetail($jobName="")
	{
		$ret = array();
		$ret['TYPE'] = "jobdetails";
		
		$sql = "SELECT * FROM ph_bg_jobs WHERE ph_bg_name = '{$jobName}' ORDER BY ph_bg_sequence";
		$ret['DATA'] = $this->doDatabaseWork($sql,"sysadmin");
		
		return $ret;
	}

	function getStatusDetail($dbname="" , $tabname="" , $jobName="")
	{
		$ret = array();
		$ret['TYPE'] = "statusdetails";
		
		$args = preg_split("/:/",$tabname);
		$id = $args[0];
		$seq = $args[1];
		$sql = "SELECT ph_bg_jobs.ph_bg_name , ph_bg_jobs_results.* FROM ph_bg_jobs_results , ph_bg_jobs ";
		$sql .= " WHERE ph_bg_id = ph_bgr_bg_id AND ph_bgr_tk_id = {$id} ";
		$sql .= " AND ph_bgr_tk_sequence = {$seq} ORDER BY ph_bgr_id";
		$ret['DATA'] = $this->doDatabaseWork($sql,"sysadmin");
		
		return $ret;
	}

	/**
	 * get job info.
	 *
	 */
	function getJobs($dbname,$tabname = "" , $type = "", $rows_per_page = null, $page = 1, 
			$sort_col = null, $jobname_search_pattern = null)
	{
		$type = strtolower($type);

		switch ( $type )
		{
			case "run":
				return $this->runJob($tabname);
				break;
			case "delete":
				return $this->deleteJob($tabname);
				break;
			case "deleteresults":
				return $this->deleteResults($tabname);
				break;
			case "group":
				return $this->groupJob($dbname,$tabname);
				break;
			case "jobdetails":
				return $this->getJobDetail($tabname);
				break;
			case "statusdetails":
				return $this->getStatusDetail($dbname,$tabname,$type);
				break;
		}

		$ret = array();
		$ret['TYPE'] = $type;

		// Since we want our query to return both matching jobs and any groups that contain these 
		// same jobs, we need to use a recursive query by using the CONNECT BY syntax.
		// Our CONNECT BY clause is made even more complicated by that fact that we are matching
		// the job name (ph_bg_name) to part of the string that forms the group's command text (ph_bg_cmd). 
		// The only way to do this is to use the nested REPLACE functions to pull out just
		// the job name from the command text.  We assume that the command text would always match
		// "EXECUTE FUNCTION exectask ('Job Runner','{$ph_bg_name}')"... which is how it's defined 
		// in the groupJob() function above.
		
		$sql = "SELECT unique ph_bg_name , ph_bg_type , ph_bg_job_id FROM ph_bg_jobs ";

		if ($jobname_search_pattern != null)
		{
			$sql .= " WHERE ph_bg_name like '%{$jobname_search_pattern}%'";
		}

		if ( $type != "" )
		{
			$sql .= " START WITH LOWER(ph_bg_type)='{$type}' ";
		} else {
			$sql .= " START WITH (LOWER(ph_bg_type)='load' OR LOWER(ph_bg_type) = 'unload' )";
				 
		}
		if ( $dbname != "" )
		{
			$sql .= " AND ph_bg_database = '{$dbname}' ";
		}
		if ( $tabname != "" )
		{
			$sql .= " AND LOWER(ph_bg_desc) MATCHES '*{$tabname}*' ";
		}
		
		$sql .= " CONNECT BY NOCYCLE PRIOR ph_bg_name = "
			 .  " REPLACE(REPLACE( ph_bg_cmd, \"EXECUTE FUNCTION exectask('Job Runner','\", \"\"), \"')\",\"\")"; 

		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col),"sysadmin");
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysadmin");
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}
	
	/**
	 * get job status info.
	 *
	 */
	function getJobsResults($dbname,$tabname = "" , $jobname = "", $rows_per_page = null, $page = 1, 
			$sort_col = null, $jobname_search_pattern = null)
	{
		$ret = array();
		
		if ( $jobname != NULL )
		{
			$jobname = strtolower($jobname);
		}

		$sql = "SELECT unique ph_bgr_tk_sequence as SEQ, ph_bg_name, ph_bg_type "
			 . "FROM ph_bg_jobs_results , ph_bg_jobs "
			 . "WHERE ph_bg_id = ph_bgr_bg_id AND LOWER(ph_bg_desc) matches '*{$tabname}*' AND ph_bg_database = '{$dbname}'";
		if ( $jobname == "load" || $jobname == "unload" )
		{
			$sql .= "  AND LOWER(ph_bg_type) = '{$jobname}' ";
		}
		if ($jobname_search_pattern != null)
		{
			$sql .= " AND ph_bg_name like '%{$jobname_search_pattern}%'";
		}
		
		$db = $this->idsadmin->get_database("sysadmin");
		
		$stmt = $db->query($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col));
		
		while ( $row = $stmt->fetch() )
		{
			$result_sql = "SELECT FIRST 1 ph_bg_name AS name , ph_bg_type AS type FROM ph_bg_jobs "
				. "WHERE ph_bg_id IN ( SELECT ph_bgr_bg_id FROM ph_bg_jobs_results WHERE ph_bgr_tk_sequence = {$row['SEQ']}) ";
			$job = $this->doDatabaseWork($result_sql,"sysadmin");
			$ret['DATA'][] = $this->getJobSummary($job[0]['NAME'],$row['SEQ'] ,$job[0]['TYPE']);
		}
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), "sysadmin");
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}

		return $ret;
	}

	function getJobSummary($jobName,$seq,$type)
	{
		$sql = "SELECT count(*) as cnt FROM ph_bg_jobs WHERE ph_bg_name = '{$jobName}' ";
		$numElements = $this->doDatabaseWork($sql,"sysadmin");

		$ret = array();

		$ret['PH_BG_NAME'] = $jobName;
		$ret['PH_BG_TYPE'] = $type;

		$ret['cnt']  = $numElements[0]['CNT'];

		$compcnt=0;
		$errors=0;
		$ressql = "SELECT * FROM ph_bg_jobs_results WHERE ph_bgr_tk_sequence = {$seq} ORDER BY 2";
		$db = $this->idsadmin->get_database("sysadmin");
		$stmt = $db->query($ressql);
		while ( $row = $stmt->fetch() )
		{
			$save = $row;
			if ( $row['PH_BGR_STOPTIME'] != "" )
			{
				$compcnt++;
			}

			if ( $row['PH_BGR_RETCODE'] != 0
			|| $row['PH_BGR_RETCODE2'] != 0 )
			{
				$errors++;
			}

		}

		$ret['completed'] = $compcnt > 0 ? ($ret['cnt']/$compcnt)*100 : 0 ;
		$ret['errors'] = $errors;

		$sql = "SELECT * FROM ph_run WHERE run_task_id = {$save['PH_BGR_TK_ID']} AND run_task_seq = {$save['PH_BGR_TK_SEQUENCE']}";

		if ($ret['completed'] >= 100 )
		{
			$ret['PH_BGR_STOPTIME'] = $save['PH_BGR_STOPTIME'] ;
		}

		$row = $this->doDatabaseWork($sql,"sysadmin");
		$ret['PH_BGR_STARTTIME'] = $row[0]['RUN_TIME'];
		$ret['id'] = $save['PH_BGR_TK_ID'];
		$ret['seq'] = $save['PH_BGR_TK_SEQUENCE'];
		return $ret;

	}
	
	/**
	 * Get the columns information of a table.
	 * @return 
	 * @param object $dbname
	 * @param object $tabid
	 * @param object $rows_per_page[optional]
	 * @param object $page[optional]
	 * @param object $sort_col[optional]
	 * @param object $colname_search_pattern[optional]
	 */
	function getTabColumns($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, $colname_search_pattern = null)
	{
		$ret = array();
		
		if ($sort_col == null)
		{
			// default sort order
			$sort_col = "syscolumns.colno";
		}
		
		/* does this version of the server have external table support */
		$feature = Feature::CHEETAH2_UC6;
		if (  Feature::isAvailable ( $feature , $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			$qry = "select ".
            " schema_nullable(coltype) as allownulls ".
            " ,syscolumns.colno, trim(colname) as colname, case mod(coltype,256) ".
            "  when 0 THEN 'CHAR' ".
            "  when 1 THEN 'SMALLINT' ".
            "  when 2 THEN 'INTEGER' ".
            "  when 3 THEN 'FLOAT' ".
            "  when 4 THEN 'SMALLFLOAT' ".
            "  when 5 THEN 'DECIMAL' ".
            "  when 6 THEN 'SERIAL' ".
            "  when 7 THEN 'DATE' ".
            "  when 8 THEN 'MONEY' ".
            "  when 9 THEN 'NULL' ".
            "  when 10 THEN 'DATETIME' ".
            "  when 11 THEN 'BYTE' ".
            "  when 12 THEN 'TEXT' ".
            "  when 13 THEN 'VARCHAR' ".
            "  when 14 THEN 'INTERVAL' ".
            "  when 15 THEN 'NCHAR' ".
            "  when 16 THEN 'NVARCHAR' ".
            "  when 17 THEN 'INT8' ".
            "  when 18 THEN 'SERIAL8' ".
            "  when 19 THEN 'SET' ".
            "  when 20 THEN 'MULTISET' ".
            "  when 21 THEN 'LIST' ".
            "  when 22 THEN 'ROW' ".
            "  when 23 THEN 'COLLECTION' ".
            "  when 24 THEN 'ROWREF' ".
            "  when 40 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1 THEN 'LVARCHAR' ".
            "          ELSE 'UDTVAR' ".
            "          END  ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 5 THEN 'BOOLEAN' ".
            "          when 10 THEN 'BLOB' ".
            "          when 11 THEN 'CLOB' ".
            "          ELSE 'UDTFIXED' ".
            "          END  ".
            "  when 42 THEN 'REFSER8' ".
            "  when 52 THEN 'BIGINT' ".
            "  when 53 THEN 'BIGSERIAL' ".
            "  ELSE 'UNKNOWN '||mod(coltype,256) ".
            "  END as mytype , ".
			#####  Now, decide what needs to be printed for the length.
            "case MOD(coltype,256) ".
            "  when 5 THEN ".
            " '(' || TRUNC(collength/256) || ',' ".
            "                   || MOD(collength, 256) || ')' ".
            "  when 8 THEN ".
            " '(' || TRUNC(collength/256) || ',' ".
            "                   || MOD(collength, 256) || ')' ".
			#datetime
            "  when 10 THEN ".
            "    (select decode ( ".
            "     TRUNC(MOD(informix.syscolumns.collength, 256)/17), ".
            "     0 ,  'YEAR',  ".
            "     2 ,  'MONTH', ".
            "     4 ,  'DAY',   ".
            "     6 ,  'HOUR',  ".
            "     8 ,  'MINUTE',".
            "     10,  'SECOND',".
            "     11,  'FRACTION(1)', ".
            "     12,  'FRACTION(2)', ".
            "     13,  'FRACTION(3)', ".
            "     14,  'FRACTION(4)', ".
            "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
            "     || ' TO ' || decode( ".
            "     MOD(MOD(informix.syscolumns.collength, 256),16), ".
            "     0 ,  'YEAR',  ".
            "     2 ,  'MONTH', ".
            "     4 ,  'DAY',   ".
            "     6 ,  'HOUR',  ".
            "     8 ,  'MINUTE',".
            "     10,  'SECOND',".
            "     11,  'FRACTION(1)', ".
            "     12,  'FRACTION(2)', ".
            "     13,  'FRACTION(3)', ".
            "     14,  'FRACTION(4)', ".
            "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
            "     from systables where tabid=1) ".
            "  when 11 THEN '' ".
            "  when 12 THEN '' ".
            "  when 13 THEN ".
            " '(' || MOD(collength,256) || ',' ".
            "                   || TRUNC(collength/256) || ')' ".
			#interval
            "  when 14 THEN ".
            "    (select decode ( ".
            "     TRUNC(MOD(informix.syscolumns.collength, 256)/17), ".
            "     0 ,  'YEAR',  ".
            "     2 ,  'MONTH', ".
            "     4 ,  'DAY',   ".
            "     6 ,  'HOUR',  ".
            "     8 ,  'MINUTE',".
            "     10,  'SECOND',".
            "     11,  'FRACTION', ". // FRACTION(1)
            "     12,  'FRACTION', ". // FRACTION(2)
            "     13,  'FRACTION', ". // FRACTION(3)
            "     14,  'FRACTION', ". // FRACTION(4)
            "     15,  'FRACTION', 'UNKNOWN' ) ". // FRACTION(5)
            "     || '(' || ".
            "     ( TRUNC(informix.syscolumns.collength/256) - ".
            "     (MOD(MOD(informix.syscolumns.collength, 256),16) - ".
            "      TRUNC(MOD(informix.syscolumns.collength, 256)/17) )) ".
            "     || ')' || ' TO ' || decode( ".
            "     MOD(MOD(informix.syscolumns.collength, 256),16), ".
            "     0 ,  'YEAR',  ".
            "     2 ,  'MONTH', ".
            "     4 ,  'DAY',   ".
            "     6 ,  'HOUR',  ".
            "     8 ,  'MINUTE',".
            "     10,  'SECOND',".
            "     11,  'FRACTION(1)', ".
            "     12,  'FRACTION(2)', ".
            "     13,  'FRACTION(3)', ".
            "     14,  'FRACTION(4)', ".
            "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
            "     from systables where tabid=1) ".
            "  when 19 THEN '' ".
            "  when 20 THEN '' ".
            "  when 21 THEN '' ".
			#             "  when 22 THEN '' ".
			#             "  when 23 THEN '' ".
			# "  when 40 THEN '' ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1001 THEN '' ".
            "          when 1111 THEN '' ".
            "          ELSE trim(collength::lvarchar) ".
            "          END  ".
            "  ELSE trim(collength::lvarchar) ".
            "END as mylength, ".
			"collength, ".
            " case informix.syscolumns.extended_id ".
            "   when 0 THEN 'None' ".
            "   ELSE ".
            "     case informix.sysxtdtypes.mode ".
            "       when 'C' THEN  ".
            "           (select case count(*) ".
            "                when  1 THEN ".
            "                  (select trim(informix.sysxtddesc.description) ".
            "                  from informix.sysxtddesc where ".
            "                  informix.sysxtdtypes.extended_id =  ".
            "                  informix.sysxtddesc.extended_id and ".
            "                  informix.sysxtddesc.seqno = 1 )  ".
            "                 ELSE '*description longer than 256 chars' ".
            "                 END ".
            "             from informix.sysxtddesc where ".
            "             informix.sysxtdtypes.extended_id =  ".
            "             informix.sysxtddesc.extended_id ".
            "             )  ".
		 	"       ELSE ".
			"           case informix.sysxtdtypes.type ".
			"              when (select type from sysxtdtypes where name = 'timeseries') THEN 'timeseries' ".
			"              ELSE trim(sysxtdtypes.name) ".
            "              END ".
			"   END " .
            "END as myextdesc ".
            " , case sysdefaults.type  ".
            "  when 'U' then 'USER' ".
            "  when 'T' then 'TODAY' ".
            "  when 'C' then 'CURRENT' ".
            " else trim(sysdefaults.default) ".
            " end as default ".
			//" ,trim(sysdefaults.default) as default ".
			//" ,trim(sysdefaults.type) as type ".
            " ,trim(sysdefaults.class) as class ".
            " , extlength  ".
            " , coltype  ".
            " , syscolumns.extended_id as extid  ".
            " from syscolumns, systables, ".
            " outer sysxtdtypes , outer sysdefaults  , outer sysextcols ".
            " where syscolumns.tabid = systables.tabid ".
            " and informix.syscolumns.extended_id = ".
            " informix.sysxtdtypes.extended_id and ".
            " systables.tabid = '{$tabid}' ".
            " and sysdefaults.tabid = systables.tabid ".
            " and systables.tabid = sysextcols.tabid ".
            " and syscolumns.colno = sysextcols.colno ".
            " and sysdefaults.colno = syscolumns.colno ".
            " and sysdefaults.colno = syscolumns.colno ";
		}
		else 
		{
			$qry = "select ".
            " schema_nullable(coltype) as allownulls ".
            " ,syscolumns.colno, trim(colname) as colname, case mod(coltype,256) ".
            "  when 0 THEN 'CHAR' ".
            "  when 1 THEN 'SMALLINT' ".
            "  when 2 THEN 'INTEGER' ".
            "  when 3 THEN 'FLOAT' ".
            "  when 4 THEN 'SMALLFLOAT' ".
            "  when 5 THEN 'DECIMAL' ".
            "  when 6 THEN 'SERIAL' ".
            "  when 7 THEN 'DATE' ".
            "  when 8 THEN 'MONEY' ".
            "  when 9 THEN 'NULL' ".
            "  when 10 THEN 'DATETIME' ".
            "  when 11 THEN 'BYTE' ".
            "  when 12 THEN 'TEXT' ".
            "  when 13 THEN 'VARCHAR' ".
            "  when 14 THEN 'INTERVAL' ".
            "  when 15 THEN 'NCHAR' ".
            "  when 16 THEN 'NVARCHAR' ".
            "  when 17 THEN 'INT8' ".
            "  when 18 THEN 'SERIAL8' ".
            "  when 19 THEN 'SET' ".
            "  when 20 THEN 'MULTISET' ".
            "  when 21 THEN 'LIST' ".
            "  when 22 THEN 'ROW' ".
            "  when 23 THEN 'COLLECTION' ".
            "  when 24 THEN 'ROWREF' ".
            "  when 40 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1 THEN 'LVARCHAR' ".
            "          ELSE 'UDTVAR' ".
            "          END  ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 5 THEN 'BOOLEAN' ".
            "          when 10 THEN 'BLOB' ".
            "          when 11 THEN 'CLOB' ".
            "          ELSE 'UDTFIXED' ".
            "          END  ".
            "  when 42 THEN 'REFSER8' ".
            "  when 52 THEN 'BIGINT' ".
            "  when 53 THEN 'BIGSERIAL' ".
            "  ELSE 'UNKNOWN '||mod(coltype,256) ".
            "  END as mytype , ".
			#####  Now, decide what needs to be printed for the length.
            "case MOD(coltype,256) ".
            "  when 5 THEN ".
            " '(' || TRUNC(collength/256) || ',' ".
            "                   || MOD(collength, 256) || ')' ".
            "  when 8 THEN ".
            " '(' || TRUNC(collength/256) || ',' ".
            "                   || MOD(collength, 256) || ')' ".
			#datetime
            "  when 10 THEN ".
            "    (select decode ( ".
            "     TRUNC(MOD(informix.syscolumns.collength, 256)/17), ".
            "     0 ,  'YEAR',  ".
            "     2 ,  'MONTH', ".
            "     4 ,  'DAY',   ".
            "     6 ,  'HOUR',  ".
            "     8 ,  'MINUTE',".
            "     10,  'SECOND',".
            "     11,  'FRACTION(1)', ".
            "     12,  'FRACTION(2)', ".
            "     13,  'FRACTION(3)', ".
            "     14,  'FRACTION(4)', ".
            "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
            "     || ' TO ' || decode( ".
            "     MOD(MOD(informix.syscolumns.collength, 256),16), ".
            "     0 ,  'YEAR',  ".
            "     2 ,  'MONTH', ".
            "     4 ,  'DAY',   ".
            "     6 ,  'HOUR',  ".
            "     8 ,  'MINUTE',".
            "     10,  'SECOND',".
            "     11,  'FRACTION(1)', ".
            "     12,  'FRACTION(2)', ".
            "     13,  'FRACTION(3)', ".
            "     14,  'FRACTION(4)', ".
            "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
            "     from systables where tabid=1) ".
            "  when 11 THEN '' ".
            "  when 12 THEN '' ".
            "  when 13 THEN ".
            " '(' || MOD(collength,256) || ',' ".
            "                   || TRUNC(collength/256) || ')' ".
			#interval
            "  when 14 THEN ".
            "    (select decode ( ".
            "     TRUNC(MOD(informix.syscolumns.collength, 256)/17), ".
            "     0 ,  'YEAR',  ".
            "     2 ,  'MONTH', ".
            "     4 ,  'DAY',   ".
            "     6 ,  'HOUR',  ".
            "     8 ,  'MINUTE',".
            "     10,  'SECOND',".
            "     11,  'FRACTION', ". // FRACTION(1)
            "     12,  'FRACTION', ". // FRACTION(2)
            "     13,  'FRACTION', ". // FRACTION(3)
            "     14,  'FRACTION', ". // FRACTION(4)
            "     15,  'FRACTION', 'UNKNOWN' ) ". // FRACTION(5)
            "     || '(' || ".
            "     ( TRUNC(informix.syscolumns.collength/256) - ".
            "     (MOD(MOD(informix.syscolumns.collength, 256),16) - ".
            "      TRUNC(MOD(informix.syscolumns.collength, 256)/17) )) ".
            "     || ')' || ' TO ' || decode( ".
            "     MOD(MOD(informix.syscolumns.collength, 256),16), ".
            "     0 ,  'YEAR',  ".
            "     2 ,  'MONTH', ".
            "     4 ,  'DAY',   ".
            "     6 ,  'HOUR',  ".
            "     8 ,  'MINUTE',".
            "     10,  'SECOND',".
            "     11,  'FRACTION(1)', ".
            "     12,  'FRACTION(2)', ".
            "     13,  'FRACTION(3)', ".
            "     14,  'FRACTION(4)', ".
            "     15,  'FRACTION(5)', 'UNKNOWN' ) ".
            "     from systables where tabid=1) ".
            "  when 19 THEN '' ".
            "  when 20 THEN '' ".
            "  when 21 THEN '' ".
			#             "  when 22 THEN '' ".
			#             "  when 23 THEN '' ".
			# "  when 40 THEN '' ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1001 THEN '' ".
            "          when 1111 THEN '' ".
            "          ELSE trim(collength::lvarchar) ".
            "          END  ".
            "  ELSE trim(collength::lvarchar) ".
            "END as mylength, ".
			"collength, ".
            " case informix.syscolumns.extended_id ".
            "   when 0 THEN 'None' ".
            "   ELSE ".
            "     case informix.sysxtdtypes.mode ".
            "       when 'C' THEN  ".
            "           (select case count(*) ".
            "                when  1 THEN ".
            "                  (select trim(informix.sysxtddesc.description) ".
            "                  from informix.sysxtddesc where ".
            "                  informix.sysxtdtypes.extended_id =  ".
            "                  informix.sysxtddesc.extended_id and ".
            "                  informix.sysxtddesc.seqno = 1 )  ".
            "                 ELSE '*description longer than 256 chars' ".
            "                 END ".
            "             from informix.sysxtddesc where ".
            "             informix.sysxtdtypes.extended_id =  ".
            "             informix.sysxtddesc.extended_id ".
            "             )  ".
            "       ELSE ".
			"           case informix.sysxtdtypes.type ".
			"              when (select type from sysxtdtypes where name = 'timeseries') THEN 'timeseries' ".
			"              ELSE trim(sysxtdtypes.name) ".
            "              END ".
			"    END  ".
            "END as myextdesc ".
            " , case sysdefaults.type  ".
            "  when 'U' then 'USER' ".
            "  when 'T' then 'TODAY' ".
            "  when 'C' then 'CURRENT' ".
            " else trim(sysdefaults.default) ".
            " end as default ".
			//" ,trim(sysdefaults.default) as default ".
			//" ,trim(sysdefaults.type) as type ".
            " ,trim(sysdefaults.class) as class ".
            " , coltype  ".
            " , syscolumns.extended_id as extid  ".
            " from syscolumns, systables, ".
            " outer sysxtdtypes , outer sysdefaults ".
            " where syscolumns.tabid = systables.tabid ".
            " and informix.syscolumns.extended_id = ".
            " informix.sysxtdtypes.extended_id and ".
            " systables.tabid = '{$tabid}' ".
            " and sysdefaults.tabid = systables.tabid ".
            " and sysdefaults.colno = syscolumns.colno ".
            " and sysdefaults.colno = syscolumns.colno ";
		}

		if ($colname_search_pattern != null)
		{
			$qry .= " and colname like '%{$colname_search_pattern}%' ";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}

		return $ret;
	}
	
	function getDatabaseIndexes ($dbname)
	{
		$qry = "select i.idxname as idxname, t.tabname as tabname from sysindexes i, systables t where i.tabid = t.tabid";
		$ret = $this->doDatabaseWork($qry,$dbname);
		/* return if we have no indexes */
		if ( count ( $ret ) == 0 )
		{
			$ret = array();
		}

		return $ret;
	}

	/**
	 * Get indexes for table
	 *
	 * @param $dbname
	 * @param $tabid
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - index name to search for (optional)
	 */
	function getTabIndexes($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$ret = array();
		
		$panther_columns = "";
		if (Feature::isAvailable ( Feature::PANTHER , $this->idsadmin->phpsession->serverInfo->getVersion() ))
		{
			$panther_columns = ", ustlowts::DATETIME YEAR TO SECOND as ustlowts, ustbuildduration ";
		}
		
		/*** Disabling partial index feature - server feature postponed
		$partial_index_column = "";
		if (Feature::isAvailable ( Feature::PANTHER_UC3 , $this->idsadmin->phpsession->serverInfo->getVersion() ))
		{
			$partial_index_column = ", indexkeyarray_out(indexkeys, 1) as indexkeys ";
		}
		****/
		
		$qry = "select idx.idxname, idx.owner, "
		. "idx.clustered, idx.idxtype, "
		. "part1, part2, part3, part4, part5, part6, part7, part8, "
		. "part9, part10, part11, part12, part13, part14, part15, part16, "
		. "idx.levels, idx.leaves, idx.nunique, idx.clust , state, ams.am_name as idxtype2, "
		. "idcs.collation, constrname "
		. $panther_columns
		// . $partial_index_column  // Disabling partial index feature
		. " from sysindexes idx, sysindices idcs, sysams ams, "
		. " outer sysobjstate obj, outer sysconstraints constr "
		. " where idx.tabid = {$tabid} "
		. " and idx.tabid = obj.tabid "
		. " and idx.owner = obj.owner "
		. " and idcs.amid = ams.am_id "
		. " and idcs.idxname = idx.idxname "
		. " and idcs.owner = idx.owner "
		. " and idx.idxname = obj.name "
		. "and obj.objtype = 'I' "
		. " and constr.tabid = idx.tabid "
		. " and constr.idxname = idx.idxname ";

		if ($name_search_pattern != null)
		{
			$qry .= " and idx.idxname like '%{$name_search_pattern}%' ";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		/* return if we have no indexes */
		if ( count ( $ret['DATA'] ) == 0 )
		{
			return $ret;
		}

		/* get all the columns for the table */
		$qry = "SELECT TRIM(colname) as colname FROM syscolumns where tabid = {$tabid} ORDER BY colno ";
		$cols = $this->doDatabaseWork($qry,$dbname);

		/* map columns to indexed columns */
		foreach ( $ret['DATA'] as $k => $v )
		{
			$columns = array();
			$x = 0;
			while ( $x++ < 16 )
			{
				$way = "";
				if ( $v["PART{$x}"] == 0 )
				{
					continue;
				}

				/* if indexes are created desc then the colno is negative*/
				if ( $v["PART{$x}"] < 0 )
				{
					$v["PART{$x}"] *= -1;
					$way = "DESC";
				}
				
				/* Check if the index is a partial index*/
				/*** Disabling partial index feature - server feature postponed
				$partial_index = "";
				if (isset($v["INDEXKEYS"]))
				{
					$index_keys_arr = explode (",", $v["INDEXKEYS"]);
					// If the index key for this column includes a number in parenthesis, then it's a partial index.
					if (isset($index_keys_arr[$x - 1]) && strpos($index_keys_arr[$x - 1], "("))
					{
						// It is a partial index column.
						$left_idx = strpos($index_keys_arr[$x - 1], "(");
						$right_idx = strpos($index_keys_arr[$x - 1], ")");
						$partial_index =  substr($index_keys_arr[$x - 1], $left_idx, $right_idx - $left_idx  + 1);
					} 
				}
				***/
				
				$colval = trim( $cols[$v["PART{$x}"] -1 ]['COLNAME'] );
				if ( $colval != "" )
				{
					$columns[] = "{$colval}{$partial_index} {$way}";
				}
			}
			$ret['DATA'][$k]['COLUMNS'] = implode ( ", " , $columns );

		}
		return $ret;
	}

	/**
	 * Get table references
	 *
	 * @param $dbname
	 * @param $tabid
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - reference name to search for (optional)
	 */
	function getTabReferences($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select c.constrname, c.owner, c.idxname, "
		. "c.collation, r.primary, t.tabname as primarytab, delrule, "
		. "part1, part2, part3, part4, part5, part6, part7, part8, "
		. "part9, part10, part11, part12, part13, part14, part15, part16 "
		. ",state "
		. "from sysreferences r, systables t, sysconstraints c "
		. ", sysindexes i "
		." ,outer sysobjstate obj "
		. "where c.constrtype =  'R' "
		. "and c.idxname = i.idxname "
		. "and r.ptabid = t.tabid "
		. "and c.constrid = r.constrid "
		." and c.tabid = obj.tabid "
		." and c.owner = obj.owner "
		." and c.idxname = obj.name "
		." and obj.objtype = 'I' "
		. "and c.tabid = {$tabid}";
		
		if ($name_search_pattern != null)
		{
			$qry .= " and constrname like '%{$name_search_pattern}%'";
		}

		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		

		/* return if we have no constraints */
		if ( count ( $ret['DATA'] ) == 0 )
		{
			return $ret;
		}

		/* get all the columns for the table */
		$qry = "SELECT TRIM(colname) as colname FROM syscolumns where tabid = {$tabid} ORDER BY colno ";

		$cols = $this->doDatabaseWork($qry,$dbname);

		/* map columns to constraints */
		foreach ( $ret['DATA'] as $k => $v )
		{
			$columns = array();
			$x = 0;
			while ( $x++ < 16 )
			{
				if ( $v["PART{$x}"] == 0 )
				{
					continue;
				}

				if ( $v["PART{$x}"] < 0 )
				{
					$v["PART{$x}"] *= -1;
				}

				$colval = trim( $cols[$v["PART{$x}"] -1 ]['COLNAME'] );
				if ( $colval != "" )
				{
					$columns[] = "{$colval}";
				}
			}
			$ret['DATA'][$k]['COLUMNS'] = implode(",",$columns);
		}
		
		return $ret;
	}

	/**
	 * Get table privileges
	 *
	 * @param $dbname
	 * @param $tabid
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $username_search_pattern - user name to search for (optional)
	 */
	function getTabPrivileges ($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, $username_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select grantor, grantee, tabauth from systabauth where tabid = {$tabid}";
		
		if ($username_search_pattern != null)
		{
			$qry .= " and grantee like '%{$username_search_pattern}%' ";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get statistics information for the table
	 *
	 * @param $dbname
	 * @param $tabid
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - column or index name to search for (optional)
	 * @param $filter - "all" for all statistics, "column" for only column statistics, 
	 *        or "index" for only index statistics (optional)
	 */
	function getTabStatistics ($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, 
								$name_search_pattern = null, $filter = "all")
	{
		$res = array();
		$res['TABLE_STATS'] = array();
		$res['IS_FRAGMENTED'] = "";
		$res['STATLEVEL'] = "";
		$res['STATCHANGE_TABLE'] = "";
		$res['STATCHANGE_SYSTEM'] = "";
		
		$isPanther = Feature::isAvailable ( Feature::PANTHER , $this->idsadmin->phpsession->serverInfo->getVersion() );
		
		// Determine if the table is fragmented.  We'll need this to determine if the 
		// fragment-level statistics tab should be enabled.
		$qry = "select partnum from systables where tabid={$tabid}";
		$tempRes = $this->doDatabaseWork($qry,$dbname);
		if (count($tempRes) > 0)
		{
			$res['IS_FRAGMENTED'] = ($tempRes[0]['PARTNUM'] == 0); 
		}
		
		// Get STATCHANGE/STATLEVEL setting for this table and for the system
		if ($isPanther)
		{
			// Statchange for the table
			$qry = "select statchange, statlevel from systables where tabid={$tabid}";
			$tempRes = $this->doDatabaseWork($qry,$dbname);
			if (count($tempRes) > 0)
			{
				$res['STATCHANGE_TABLE'] = $tempRes[0]['STATCHANGE'];
				$res['STATLEVEL'] = $tempRes[0]['STATLEVEL'];
			}
			
			// Statchane for the system
			$qry = "select cf_effective from syscfgtab where cf_name='STATCHANGE'";
			$tempRes = $this->doDatabaseWork($qry,"sysmaster");
			if (count($tempRes) > 0)
			{
				$res['STATCHANGE_SYSTEM'] = $tempRes[0]['CF_EFFECTIVE'];
			}
			
			$statchange = (is_null($res['STATCHANGE_TABLE']))? $res['STATCHANGE_SYSTEM']:$res['STATCHANGE_TABLE'];
		}
		
		/** 
		 * IMPORTANT NOTE:
		 * The ninserts, nupdates, and ndeletes columns in the sysdistrib table stores the UDI
		 * counters for that table as of the last time update statistics was run.  By contrast, 
		 * the ninserts, nupdates, ndeletes columns in the sysmaster:sysptnhdr table stores the 
		 * total UDI counter for the table's fragments since the table was created.  In OAT, we 
		 * want to show the change on the table since the last time update statistics was
		 * run. Therefore the UDI counter and change percentage shown in OAT is calculated as the 
		 * UDI counter in sysmaster:sysptnhr minus the UDI counter in sysdistrib.  
		 */
		
		$qry = "";
		// Get Table-Level Statistics info
		if ($isPanther)
		{
			if ($filter == "all" || $filter == "column")
			{
				$qry .= "select colname as name, "
				 . "'Column' as type, "
				 . "constr_time::datetime year to second as build_date, "
				 . "rowssmpld::bigint as sample, "
				 . "d.ustnrows::bigint as nrows, "
				 . "case when d.mode = 'M' then 'Medium' "
				 . "     when d.mode = 'H' then 'High' "
				 . "end as mode, "
				 . "resolution, " 
				 . "confidence, "
				 . "ustbuildduration as build_duration, "
				 . "(table_counter.udi_counter - d.ninserts - d.nupdates - d.ndeletes) as udi_counter, "
				 // compute percent change column... equals udi_counter/nrows (except where nrows = 0)
				 . "CASE WHEN d.ustnrows=0 and (table_counter.udi_counter - d.ninserts - d.nupdates - d.ndeletes) = 0 THEN 0.00 "
				 . "     WHEN d.ustnrows=0 and (table_counter.udi_counter - d.ninserts - d.nupdates - d.ndeletes) != 0 THEN -1 "
				 . "     ELSE ROUND((table_counter.udi_counter - d.ninserts - d.nupdates - d.ndeletes)/d.ustnrows * 100,2) END as change, " 
				 . "{$statchange} as statchange "
				 . "from sysdistrib d, syscolumns c,  "
				 . "( select SUM(nupdates + ndeletes + ninserts) as udi_counter "
				 . "  from sysmaster:sysptnhdr "
				 . "  where partnum in " 
				 . "  (select partn from sysfragments where tabid = {$tabid} and fragtype='T' " // covers fragmented tables
				 . "   union select partnum as partn from systables where tabid = {$tabid}) " //cover non-fragmented tables
				 . " ) as table_counter "
				 . "where d.tabid={$tabid} "
				 . "and c.tabid={$tabid} "
				 . "and d.colno = c.colno "
				 . "and d.seqno = 1";
				 if ($name_search_pattern != null)
				 {
				 	$qry .= "AND colname like '%{$name_search_pattern}%' ";
				 }
			}
			
		} else {
			// Statistics query for server version below Panther
			$bigIntCast = (Feature::isAvailable(Feature::CHEETAH2, $this->idsadmin->phpsession->serverInfo->getVersion()))? "::bigint":"";
			$qry .= "select colname as name, "
			 . "'Column' as type, "
			 . "constr_time::datetime year to second as build_date, "
			 . "rowssmpld{$bigIntCast} as sample, "
			 . "case when d.mode = 'M' then 'Medium' "
			 . "     when d.mode = 'H' then 'High' "
			 . "end as mode, "
			 . "resolution, " 
			 . "confidence "
			 . "from sysdistrib d, syscolumns c "
			 . "where d.tabid={$tabid} "
			 . "and c.tabid={$tabid} "
			 . "and d.colno = c.colno "
			 . "and d.seqno = 1";
			if ($name_search_pattern != null)
			{
				$qry .= "AND colname like '%{$name_search_pattern}%' ";
			}
		}
		
		if ($isPanther)
		{
			// For Panther, we'll UNION this query with one that capture index statistics for the table.
			
			if ($filter == "all")
			{
				$qry .= " UNION ";
			}
			
			if ($filter == "all" || $filter == "index")
			{
				$qry .= "SELECT idxname as name, "
				 	. "MIN('Index') as type, "
				 	. "MIN(ustlowts)::datetime year to second as build_date, "
				 	. "MIN(0) as sample, "
				 	. "SUM(f.nrows)::bigint as nrows, " 
				 	. "MIN('Low') as mode, "
				 	. "MIN(0) as resolution, "
				 	. "MIN(0) as confidence, "
				 	. "SUM(i.ustbuildduration) as build_duration, "
				 	. "SUM(NVL(p.ninserts,0) + NVL(p.nupdates,0) + NVL(p.ndeletes,0)) - "
				 	. "SUM(NVL(f.ninserts,0) + NVL(f.nupdates,0) + NVL(f.ndeletes,0)) as udi_counter, "
				    // compute percent change column... equals udi_counter/nrows (except where nrows = 0)
				 	. "CASE WHEN SUM(NVL(i.nrows,0))=0 and (SUM(NVL(p.ninserts,0) + NVL(p.nupdates,0) + NVL(p.ndeletes,0)) - SUM(NVL(f.ninserts,0) + NVL(f.nupdates,0) + NVL(f.ndeletes,0))) = 0 THEN 0.00 "
				    . "     WHEN SUM(NVL(i.nrows,0))=0 and (SUM(NVL(p.ninserts,0) + NVL(p.nupdates,0) + NVL(p.ndeletes,0)) - SUM(NVL(f.ninserts,0) + NVL(f.nupdates,0) + NVL(f.ndeletes,0))) != 0 THEN -1 "
				    . "     ELSE ROUND((SUM(NVL(p.ninserts,0) + NVL(p.nupdates,0) + NVL(p.ndeletes,0)) - SUM(NVL(f.ninserts,0) + NVL(f.nupdates,0) + NVL(f.ndeletes,0)))/SUM(i.nrows) * 100,2) END as change, "
				 	. "{$statchange} as statchange "
				 	. "FROM sysindices i, sysmaster:sysptnhdr p, sysfragments f "
				 	. "WHERE i.idxname = f.indexname "
				 	. "AND i.tabid = {$tabid} "
				 	. "AND i.tabid = f.tabid "
				 	. "AND F.partn = p.partnum ";
				 if ($name_search_pattern != null)
				 {
				 	$qry .= "AND i.idxname like '%{$name_search_pattern}%' ";
				 }
				 $qry .= "GROUP BY i.idxname ";
			}
			 
			 // Default sort order for Panther is by percent change column
			 if ($sort_col == null)
			 {
			 	$sort_col = "change DESC";
			 }
		}
		
		$res['TABLE_STATS']['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$res['TABLE_STATS']['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$res['TABLE_STATS']['COUNT'] = $row['COUNT'];
		}
		
		return $res;
	}
	
	/**
	 * Get fragment-level statistics information for the table.
	 * 
	 * This service will only be called of Panther servers (or above) and will
	 * only be called for fragmented tables, so we don't need do that checking.
	 *
	 * @param $dbname
	 * @param $tabid
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - fragment name to search for (optional)
	 * @param $filter - "all" for all statistics, "column" for only column statistics, 
	 *        or "index" for only index statistics (optional)
	 */
	function getFragmentStatistics($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, 
									$name_search_pattern = null, $filter = "all")
	{
		$res = array();
		
		// Get statchange for the table
		$statchange = 0;
		$qry = "select statchange from systables where tabid={$tabid}";
		$tempRes = $this->doDatabaseWork($qry,$dbname);
		if (count($tempRes) > 0)
		{
			if (is_null($tempRes[0]['STATCHANGE'])) 
			{
				// Table is using the system statchange setting
				$qry = "select cf_effective from syscfgtab where cf_name='STATCHANGE'";
				$tempRes = $this->doDatabaseWork($qry,"sysmaster");
				$statchange = $tempRes[0]['CF_EFFECTIVE'];
			} else {
				$statchange = $tempRes[0]['STATCHANGE'];
			}
		}
		
		/** 
		 * IMPORTANT NOTE:
		 * The ninserts, nupdates, and ndeletes columns in the sysfragdist table stores the 
		 * UDI counters for the fragments as of the last time update statistics was run.
		 * By contrast, the ninserts, nupdates, ndeletes columns in the sysmaster:sysptnhdr 
		 * table stores the total UDI counter for those fragments since the table was created.  
		 * In OAT, we want to show the change on the fragments since the last time update 
		 * statistics was run. Therefore the UDI counter and change percentage shown in OAT 
		 * is calculated as the UDI counter in sysmaster:sysptnhr minus the UDI counter in 
		 * sysfragdist.  
		 */
		
		$qry = "";
		// Fragment level statistics for columns is in sysfragdist.
		if ($filter == "all" || $filter == "column")
		{
			$qry .= "select distinct fragid, "
				 . "partition as fragname, "
				 . "colname as name, "
				 . "'Column' as fragtype, " 
				 . "resolution, confidence, "
				 . "constr_time::datetime year to second as build_date, "
			 	 . "rowssmpld::bigint as sample, "
			 	 . "case when d.mode = 'M' then 'Medium' "
			 	 . "     when d.mode = 'H' then 'High' "
			 	 . "end as mode, "
			 	 . "ustbuildduration as build_duration, "
			 	 . "(p.ninserts + p.nupdates + p.ndeletes - d.ninserts - d.nupdates - d.ndeletes) as udi_counter, "
			 	 // compute percent change column... equals udi_counter/nrows (except where nrows = 0)
				 . "CASE WHEN ustnrows=0 and (p.ninserts + p.nupdates + p.ndeletes - d.ninserts - d.nupdates - d.ndeletes) = 0 THEN 0.00 "
				 . "     WHEN ustnrows=0 and (p.ninserts + p.nupdates + p.ndeletes - d.ninserts - d.nupdates - d.ndeletes) != 0 THEN -1 "
				 . "     ELSE ROUND((p.ninserts + p.nupdates + p.ndeletes - d.ninserts - d.nupdates - d.ndeletes)/ustnrows * 100,2) END as change, " 
			 	 . "ustnrows as nrows, "
			 	 . " {$statchange} as statchange "
			 	 . "from sysfragdist d, syscolumns c, sysfragments f, sysmaster:sysptnhdr p "
			 	 . "where d.tabid={$tabid} "
			 	 . "and c.tabid={$tabid} "
			 	 . "and f.tabid={$tabid} "
			 	 . "and d.colno = c.colno "
			 	 . "and f.partn=d.fragid "
			 	 . "and f.partn=p.partnum "
			 	 . "and f.fragtype='T' "
			 	 . (($name_search_pattern != null)? "and partition like '%{$name_search_pattern}%'":"");
		}
		
		if ($filter == "all")
		{
			$qry .= " UNION ";
		}
		
		// Fragment level statistics for index fragments is in sysfragments
		if ($filter == "all" || $filter == "index")
		{
		 	$qry .= "select partn as fragid, "
		 	 . "partition as fragname, "
		 	 . "indexname as name, "
		 	 . "'Index' as fragtype, "
		 	 . "0 as reslution, "
		 	 . "0 as confidence, "
		 	 . "ustlowts::datetime year to second as build_date, "
		 	 . "0 as sample, "
		 	 . "'Low' as mode, "
		 	 . "ustbuildduration as build_duration, "
		 	 . "(p.ninserts + p.nupdates + p.ndeletes - f.ninserts - f.nupdates - f.ndeletes) as udi_counter, "
		 	 // compute percent change column... equals udi_counter/nrows (except where nrows = 0)
			 . "CASE WHEN NVL(i.nrows,0)=0 and (p.ninserts + p.nupdates + p.ndeletes - f.ninserts - f.nupdates - f.ndeletes) = 0 THEN 0.00 "
			 . "     WHEN NVL(i.nrows,0)=0 and (p.ninserts + p.nupdates + p.ndeletes - f.ninserts - f.nupdates - f.ndeletes) != 0 THEN -1 "
			 . "     ELSE ROUND((p.ninserts + p.nupdates + p.ndeletes - f.ninserts - f.nupdates - f.ndeletes)/i.nrows * 100,2) END as change, " 
		 	 . "f.nrows, "
		 	 . " {$statchange} as statchange "
		 	 . "from sysfragments f left outer join sysindices i "
		 	 . "on f.indexname = i.idxname, "
		 	 . "sysmaster:sysptnhdr p "
		 	 . "where f.tabid = {$tabid} "
		 	 . "and f.fragtype='I' "
		 	 . "and f.partn=p.partnum "
		 	 . (($name_search_pattern != null)? "and partition like '%{$name_search_pattern}%'":"");
		}
		 	 
		 if ($sort_col == null)
		 {
		 	// default sort order
		 	$sort_col = "change DESC";
		 }
		 
		 $res['FRAGMENT_STATS']['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		 $res['FRAGMENT_STATS']['COUNT'] = 0;
		 $temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		 foreach ($temp as $row)
		 {
			$res['FRAGMENT_STATS']['COUNT'] = $row['COUNT'];
		 }
		 
		 return $res;
	} 

	/**
	 * Get constraints on a table
	 *
	 * @param $dbname
	 * @param $tabid
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - constraint name to search for (optional)
	 */
	function getTabConstraints($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$ret = array();
		
		/* the first part of the UNION gets all index constraints */
		$qry = "select c.constrname, -1 as constrid, c.owner, trim(constrtype) as constrtype, "
		. " c.idxname, c.collation, "
		. " part1, part2, part3, part4, part5, part6, part7, part8, "
		. " part9, part10, part11, part12, part13, part14, part15, part16 , state"
		. " from sysconstraints c,  sysindexes i , outer sysobjstate obj "
		. " where c.tabid = {$tabid}"
		. " and c.idxname = i.idxname "
		. " and i.tabid = obj.tabid "
		. " and i.owner = obj.owner "
		. " and i.idxname = obj.name "
		. " and obj.objtype = 'I' ";
		if ($name_search_pattern != null)
		{
			$qry .= " and c.constrname like '%{$name_search_pattern}%'";
		}
		
		/* the second part gets the check constraints - the column value will be the check constraint text */
		$qry .= "UNION "
		. "select  "
		. " trim(c.constrname) as constrname "
		. ",c.constrid"
		. ",trim(c.owner) as owner"
		. ",trim(constrtype) as constrtype "
		. ",c.idxname "
		. ",trim(c.collation) as collation "
		. ",0 as part1, 0 as part2, 0 as part3, 0 as part4, 0 as part5, 0 as part6, 0 as part7, 0 as part8 "
		. ",0 as part9, 0 as part10, 0 as part11, 0 as part12, 0 as part13, 0 as part14, 0 as part15, 0 as part16 "
		. " ,state "
		. " from sysconstraints c,  outer sysobjstate obj "
		. " where c.tabid = {$tabid}"
		. " and c.constrtype = 'C' "
		. " and c.tabid = obj.tabid "
		. " and c.owner = obj.owner "
		. " and c.constrname = obj.name ";
		if ($name_search_pattern != null)
		{
			$qry .= " and c.constrname like '%{$name_search_pattern}%'";
		}

		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}

		/* Now for all index constraints, we need to map part1-16 to the column names for the table */
		$qry = "SELECT TRIM(colname) as colname FROM syscolumns where tabid = {$tabid} ORDER BY colno ";
		$cols = $this->doDatabaseWork($qry,$dbname);
		
		foreach ( $ret['DATA'] as $k => $v )
		{
			$columns = array();
			$x = 0;
			while ( $x++ < 16 )
			{
				if ( $v["PART{$x}"] == 0 )
				{
					continue;
				}

				if ( $v["PART{$x}"] < 0 )
				{
					$v["PART{$x}"] *= -1;
				}

				$colval = trim( $cols[$v["PART{$x}"] -1 ]['COLNAME'] );
				if ( $colval != "" )
				{
					$columns[] = " {$colval}";
				}
			}
			$ret['DATA'][$k]['COLUMNS'] = implode(",",$columns);

		}
		
		/* And for all check constraints (where constrid in our query results != -1), 
		 * we need to get the text for the check constraint */
		foreach ( $ret['DATA'] as $k => $v )
		{
			if ($v['CONSTRID'] == -1)
			{
				continue;
			}
			$qry = " SELECT trim(checktext) AS t FROM syschecks where type='T' and constrid = {$v['CONSTRID']} order by seqno ";
			$texts = $this->doDatabaseWork($qry,$dbname);
			if ( count($texts) == 0 )
			{
				continue;
			}
			$vals = "";
			foreach ( $texts as $c => $t )
			{
				$vals .= "{$t['T']}";
			}
			$ret['DATA'][$k]['COLUMNS'] = $vals;
		}

		return $ret;
	}

	/**
	 * Get fragments for a table
	 * 
	 * @param $dbname
	 * @param $tabid
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $partition_search_pattern - partition name to search for (optional)
	 */
	function getTabFragments($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, $partition_search_pattern = null)
	{
		$ret = array();
		
		if (Feature::isAvailable ( Feature::PANTHER , $this->idsadmin->phpsession->serverInfo->getVersion() ))
		{
			$qry = "select f.fragtype "
			." , f.strategy as type "
			." , trim(f.indexname) as indexname, f.colno, hex(partn) as partn "
			." , strategy, trim(dbspace) as dbspace, trim(partition) as partition "
			." , exprtext  "
			." , (p.nupdates + p.ndeletes + p.ninserts) as udi_counter "
			." from sysfragments f, sysmaster:sysptnhdr p "
			." where f.tabid = {$tabid} "
			." and f.partn = p.partnum";
		} else {
			$qry = "select fragtype "
			." , strategy as type "
			." , trim(indexname) as indexname, colno, hex(partn) as partn, strategy, trim(dbspace) as dbspace, trim(partition) as partition "
			." , exprtext  "
			." from sysfragments where tabid = {$tabid}";
		}
		
		if ($partition_search_pattern != null)
		{
			$qry .= " and partition like '%{$partition_search_pattern}%' ";
		}
		
		/* we cannot use the doDatabaseWork here because exprtext is a TEXT column, otherwise it crashes apache */
		$db = $this->idsadmin->get_database($dbname);

		$stmt = $db->query ($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col));

		$data = array();

		while ( $row = $stmt->fetch() )
		{
			$buf = "text";
			if ( get_resource_type( $row['EXPRTEXT'] )  == "stream"  )
			{
				$fd = $row['EXPRTEXT'];
				$buf = stream_get_contents($row['EXPRTEXT']);
			}
			$row['EXPRTEXT'] = $buf;
			$data[] = $row;
		}

		/* if there is nothing then just return */
		if ( count ( $data ) == 0 )
		{
			$data = array();
		}
		
		$ret['DATA'] = $data;
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get triggers on a table
	 *
	 * @param $dbname
	 * @param $tabid
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $name_search_pattern - trigger name to search for (optional)
	 */
	function getTabTriggers($dbname, $tabid, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$ret = array();
		
		$qry = "select trigname, t.owner, event, data as action , o.state as STATUS "
		. " from systriggers t , systrigbody tb , outer sysobjstate o "
		. " where tb.datakey = 'A'"
		. " and tb.trigid = t.trigid "
		. " and o.objtype = 'T' "
		. " and o.name = trigname "
		. " and o.owner = t.owner "
		. " and t.tabid = {$tabid} ";

		if ($name_search_pattern != null)
		{
			$qry .= " and trigname like '%{$name_search_pattern}%' ";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}

	/**
	 * Get statistics on a table
	 *
	 * @param $dbname
	 * @param $tabid
	 */
	function getTabStats($dbname, $tabid)
	{
		$qry = "SELECT ";
		$qry .= " ustlowts::datetime year to second as uslow ";
		$qry .= ", sum(iswrites) as inserts ";
		$qry .= ", sum(isreads) as selects ";
		$qry .= ", sum(isdeletes) as deletes ";
		$qry .= ", sum(seqscans) as seqscans ";
		$qry .= ",  trunc ( decode ( sum(bufreads) , 0 , 0 , ( 100 - (( sum(pagreads) * 100 ) / sum(bufreads))) ) , 2 ) as bufhits ";
		$qry .= " from systables s , outer sysfragments f, outer sysmaster:sysptprof p ";
		$qry .= " where dbsname = '{$dbname}' ";
		$qry .= " and p.tabname = s.tabname ";
		$qry .= " and f.tabid = s.tabid ";
		$qry .= " and s.tabid = {$tabid} ";
		$qry .= " group by 1 ";
		
		$ret =  $this->doDatabaseWork($qry,$dbname);

		return    $ret;
	}

	/**
	 * Get the named row types available on a particular database
	 * @param $dbname
	 */
	function getNamedRowTypes($dbname)
	{
		$qry = "select name, "
		. "case when (length > maxlen) then length "
		. "else maxlen end as maxlength "
		. "from sysxtdtypes where mode ='R'";
		$ret =  $this->doDatabaseWork($qry,$dbname);
		return $ret;
	}

	/**
	 * Create Table Wizard Page 1 - get the available primary and unique
	 * keys that can be used for creating foreign key references.
	 */
	function createTableWizard_getAvailableKeysToReference($dbname)
	{
		$qry = "select c.constrid, c.constrname, c.constrtype, t.tabid,"
		. " trim(t.owner) ||'.'|| trim(t.tabname) as tabname "
		. " from sysconstraints c"
		. " left outer join systables t on c.tabid=t.tabid"
		. " left outer join sysindexes i on c.idxname = i.idxname"
		. " where c.constrtype in ('P','U')";

		$res =  $this->doDatabaseWork($qry,$dbname);

		$xml="";
		$tabid = 0;
		foreach($res as $row)
		{
			if ($tabid != $row['TABID'])
			{
				if ($xml != "")
				{
					$xml .= "</table>";
				}
				$xml .= "<table type='T' tabid='{$row['TABID']}' name='{$row['TABNAME']}'>";
			}
			$tabid = $row['TABID'];
			$xml .= "<key constrid='{$row['CONSTRID']}' tabid='{$row['TABID']}' tabname='{$row['TABNAME']}' "
			. "type='{$row['CONSTRTYPE']}' name='{$row['CONSTRNAME']}'/>";
		}
		if ($xml != "")
		{
			$xml .= "</table>";
		}

		return $xml;
	}

	/**
	 * Create Table Wizard Page 1 - get reference key column names and types
	 * for a given constraint; used for creating foreign key references.
	 *
	 * @dbname
	 * @tabid table id of the referenced table
	 * @constrid constraint id of the constraint being referenced
	 */
	function createTableWizard_getReferenceKeyColumns($dbname, $tabid, $constrid)
	{
		// Get column names and numbers in the referenced table
		$qry = "select colno, colname, " .
        	"case mod(coltype,256) ".
            "  when 0 THEN 'CHAR' ".
            "  when 1 THEN 'SMALLINT' ".
            "  when 2 THEN 'INTEGER' ".
            "  when 3 THEN 'FLOAT' ".
            "  when 4 THEN 'SMALLFLOAT' ".
            "  when 5 THEN 'DECIMAL' ".
            "  when 6 THEN 'SERIAL' ".
            "  when 7 THEN 'DATE' ".
            "  when 8 THEN 'MONEY' ".
            "  when 9 THEN 'NULL' ".
            "  when 10 THEN 'DATETIME' ".
            "  when 11 THEN 'BYTE' ".
            "  when 12 THEN 'TEXT' ".
            "  when 13 THEN 'VARCHAR' ".
            "  when 14 THEN 'INTERVAL' ".
            "  when 15 THEN 'NCHAR' ".
            "  when 16 THEN 'NVARCHAR' ".
            "  when 17 THEN 'INT8' ".
            "  when 18 THEN 'SERIAL8' ".
            "  when 19 THEN 'SET' ".
            "  when 20 THEN 'MULTISET' ".
            "  when 21 THEN 'LIST' ".
            "  when 22 THEN 'ROW' ".
            "  when 23 THEN 'COLLECTION' ".
            "  when 24 THEN 'ROWREF' ".
            "  when 40 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1 THEN 'LVARCHAR' ".
            "          ELSE 'UDTVAR' ".
            "          END  ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 5 THEN 'BOOLEAN' ".
            "          when 10 THEN 'BLOB' ".
            "          when 11 THEN 'CLOB' ".
            "          ELSE 'UDTFIXED' ".
            "          END  ".
            "  when 42 THEN 'REFSER8' ".
            "  when 52 THEN 'BIGINT' ".
            "  when 53 THEN 'BIGSERIAL' ".
            "  ELSE 'UNKNOWN '||mod(coltype,256) ".
            "  END as coltype ".
            "from syscolumns where tabid=$tabid";
		$res =  $this->doDatabaseWork($qry,$dbname);

		$columns = array();
		foreach($res as $row)
		{
			$columns[$row['COLNO']] = array ('colname' => $row['COLNAME'], 'coltype' => $row['COLTYPE']);
		}

		// Get column numbers from the referenced constraint
		$qry = "select i.part1,"
		. " i.part2, i.part3, i.part4, i.part5, i.part6, i.part7,"
		. " i.part8, i.part9, i.part10, i.part11, i.part12, i.part13,"
		. " i.part14, i.part15, i.part16"
		. " from sysconstraints c"
		. " left outer join sysindexes i on c.idxname = i.idxname"
		. " where c.constrid = $constrid";

		$res =  $this->doDatabaseWork($qry,$dbname);
		$row = $res[0];

		// Convert part (column) numbers from the referenced constraint
		// into column names
		$refColumns = array();
		for ($i = 1; $i <= 16; $i++)
		{
			if ($row['PART' . $i] == "0")
			{
				break;
			}
			$refColumns[] = $columns[$row['PART' . $i]];
		}

		return $refColumns;
	}

	/**
	 * Create Table Wizard Page 1 - get a list of blobspaces
	 */
	function createTableWizard_getBlobspaces()
	{
		$qry = "select name from sysdbspaces where is_blobspace=1";
		$res = $this->doDatabaseWork($qry,"sysmaster");

		$blobspaces = array();
		foreach($res as $row)
		{
			$blobspaces[] = $row['NAME'];
		}

		return $blobspaces;
	}

	/**
	 * Create Table Wizard Page 1 - get a list of sbspaces
	 */
	function createTableWizard_getSBspaces()
	{
		$qry = "select name from sysdbspaces where is_sbspace=1";
		$res = $this->doDatabaseWork($qry,"sysmaster");

		$sbspaces = array();
		foreach($res as $row)
		{
			$sbspaces[] = $row['NAME'];
		}

		return $sbspaces;
	}

	/**
	 * Get sbspaces.
	 */
	function getSBSpaces()
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();

		$sql = "SELECT A.dbsnum, " .
        " trim(B.name) as name, " .

        "CASE " .
        " WHEN bitval(B.flags,'0x4')>0 " .
        "   THEN 'Disabled' " .
        " WHEN bitand(B.flags,3584)>0 " .
        "   THEN 'Recovering' " .
        " ELSE " .
        "   'Operational' " .
        " END  as dbsstatus, " .
		" 'false' as selected , ".
		" B.flags , ".
        " sum(chksize*{$defPagesize}) as DBS_SIZE , " .
        " sum(decode(mdsize,-1,nfree,udfree) * {$defPagesize}) as free_size, " .
        " TRUNC(100-sum(decode(mdsize,-1,nfree,udfree))*100/ ".
        " sum(chksize),2) as used,".
        " MAX(B.nchunks) as nchunks, " .
        " MAX(A.pagesize) as pgsize, " .
        " sum(chksize) as sortchksize, " .
        " sum(decode(mdsize,-1,nfree,udfree)) as sortusedsize " .
        "FROM syschktab A, sysdbstab B " .
        "WHERE A.dbsnum = B.dbsnum " .
		"AND bitval(B.flags, '0x2000') = 0". // no tempdbs
		"AND bitval(B.flags, '0x8000') = 1". // must be sbspace
		"AND bitval(B.flags, '0x10') = 0".   // no blobspaces
        "GROUP BY A.dbsnum , name, 3 ,4 ,5" .
        "ORDER BY A.dbsnum";

		$dbspaces = $this->doDatabaseWork($sql,"sysmaster");
		return $dbspaces;
	}

	/**
	 * create the table.
	 *
	 */
	function createTable($dbname , $tabname , $sql)
	{
		/* split the sql statement into seperate statements */
		$res = array();
		$stmts = preg_split ("/;\n/",$sql);

		$err = false;

		foreach ( $stmts as $k => $v )
		{
			$v = trim($v);
			if ( $v == "" )
			{
				continue;
			}

			try
			{
				$this->doDatabaseWork($v,$dbname,true);
			}
			catch ( PDOException $e )
			{
				/* the statement may contain '<' chars , this causes a problem when we display the text
				 * back in flex , so we use the htmlspecial chars on the statment.
				 */
				$v = htmlspecialchars($v,ENT_COMPAT,"UTF-8");
				$res['RESULTS'][] = array($e->getCode(),"{$v}",  $e->getMessage() );
				/* if error is on the create table then lets just break.. */
				$err=true;
				break;
			}

			/* the statement may contain '<' chars , this causes a problem when we display the text
			 * back in flex , so we use the htmlspecial chars on the statment.
			 */
			$v = htmlspecialchars($v,ENT_COMPAT,"UTF-8");
			$res['RESULTS'][] = array(0,"{$v}","");
		}

		if ( $err === true )
		{
			/* the error was not on the create table so we just drop the table to rollback.. */
			if ( $k != 0  )
			{
				//error_log ("DROPPING TABLE {$tabname}");
				$sql = "DROP TABLE {$tabname}";
				try
				{
					$this->doDatabaseWork($sql , $dbname , true);
				}
				catch ( PDOException $e)
				{
				}
			}
		} else {
			/* if table creation is successful, we need to retrieve the tabid of the new table */
			$delimident = $this->idsadmin->phpsession->instance->get_delimident();
			if ($delimident != "Y" && $delimident != "y")
			{
				$tabname = strtolower($tabname);
			}
			$qry = "select tabid from systables where tabname = '{$tabname}'";
			$result = $this->doDatabaseWork($qry,$dbname);
			$res['TABID'] = $result[0]['TABID'];
		}

		return $res;
	}
	/**
	 * This function is used to drop, enable or disable indexes.
	 * @return result of queries
	 * @param $dbname
	 * @param $sql
	 * @param $tabid
	 * @param $action_type - 'drop', 'enable', or 'disable'
	 * @param $rows_per_page for indexes pod
	 * @param $page - current page number of indexes pod
	 * @param $sort_col - sort columns for order by clause for indexes pod
	 * @param $name_search_pattern - index name to search for indexes pod
	 */
	function alterIndexes($dbname, $sql, $tabid, $action_type, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$sql = trim($sql);
		$result = array();
		$result['ACTION_TYPE'] = $action_type;
		$result['CODE'] = 0;
		$result['MESSAGE'] = "";
		$result['INDEXES'] = null;

		try
		{
			$this->doDatabaseWork($sql,$dbname,true);
		}
		catch (PDOException $e)
		{
			$result['CODE'] = $e->getCode();
			$result['MESSAGE'] = $e->getMessage();
		}

		//if we make a change, we update the indexes in the UI
		if($result['CODE'] == 0)
		{
			$result['INDEXES'] = $this->getTabIndexes($dbname,$tabid,$rows_per_page,$page,$sort_col,$name_search_pattern);
		}
		return $result;
	}

	/**
	 * create the index.
	 * 
	 * @param $dbname
	 * @param $indexename
	 * @param $tabid
	 * @param $sql
	 * @param $index_pod_rows_per_page - for updating index pod data
	 * @param $index_pod_page - for updating index pod data
	 * @param $index_pod_sort_col - for updating index pod data
	 * @param $index_pod_name_search_pattern - for updating index pod data
	 * @param $constraints_pod_rows_per_page - for updating constraints pod data
	 * @param $constraints_pod_page - for updating constraints pod data
	 * @param $constraints_pod_sort_col - for updating constraints pod data
	 * @param $constraints_pod_name_search_pattern - for updating constraints pod data
	 */
	function createIndex($dbname, $indexname, $sql, $tabid, 
						 $index_pod_rows_per_page = null, $index_pod_page = 1, $index_pod_sort_col = null, $index_pod_name_search_pattern = null,
						 $constraints_pod_rows_per_page = null, $constraints_pod_page = 1, $constraints_pod_sort_col = null, $constraints_pod_name_search_pattern = null)
	{
		/* split the sql statement into seperate statements */
		$res = array();
		$stmts = preg_split ("/;/",$sql);

		$err = false;
		$res['INDEXES'] = null;

		foreach ( $stmts as $k => $v )
		{
			$v = trim($v);
			if ( $v == "" )
			{
				continue;
			}

			try
			{
				$this->doDatabaseWork($v,$dbname,true);
			}
			catch ( PDOException $e )
			{
				/* the statement may contain '<' chars , this causes a problem when we display the text
				 * back in flex , so we use the htmlspecial chars on the statment.
				 */
				$v = htmlspecialchars($v,ENT_COMPAT,"UTF-8");
				$res['RESULTS'][] = array($e->getCode(),"{$v}",  $e->getMessage() );
				/* if error is on the create index then lets just break.. */
				$err=true;
				break;
			}

			/* the statement may contain '<' chars , this causes a problem when we display the text
			 * back in flex , so we use the htmlspecial chars on the statment.
			 */
			$v = htmlspecialchars($v,ENT_COMPAT,"UTF-8");
			$res['RESULTS'][] = array(0,"{$v}","");
		}

		if ( $err === true )
		{
			/* the error was not on the create index so we just drop the index to rollback.. */
			if ( $k != 0  )
			{
				$sql = "DROP INDEX {$indexname}";
				try
				{
					$this->doDatabaseWork($sql , $dbname , true);
				}
				catch ( PDOException $e)
				{
				}
			}
		} else
		{
			//if the create index succeeded, we update the indexes and constraints pods in the UI
			if($result['CODE'] == 0)
			{
				$res['INDEXES'] = $this->getTabIndexes($dbname,$tabid,$index_pod_rows_per_page,$index_pod_page,$index_pod_sort_col,$index_pod_name_search_pattern);
				$res['CONSTRAINTS'] = $this->getTabConstraints($dbname,$tabid,$constraints_pod_rows_per_page,$constraints_pod_page,$constraints_pod_sort_col,$constraints_pod_name_search_pattern);
			}
		}

		return $res;
	}

	/**
	 * create an unload / load job.
	 *
	 */
	function createJob($dbname , $tabname , $sql ,$jobtype, $jobname , $runNow)
	{
		/* split the sql statement into seperate statements */
		$res = array();
		/* split the statement on a semi-colon return as the syntax for create external table allows for ; in statement
		 * eg:
		 * PATH:/tmp;CLOBDIR:/tmp/clobs
		 */
		$stmts = preg_split ("/;\n/",$sql);

		$err = false;
		
		$sql = " INSERT INTO ph_bg_jobs ";
		$sql .= "(ph_bg_name , ph_bg_job_id , ph_bg_type ";
		$sql .= "  , ph_bg_sequence ,ph_bg_stop_on_error, ph_bg_desc, ph_bg_database,ph_bg_cmd) ";
		$sql .= " VALUES ";

		$sequence = "ph_bg_jobs_seq.nextval";
		$firstsql =  "{$sql} (:jobname,{$sequence},:jobtype,:sequence,'f',:tabname,:database,:cmd)";
		$sequence = "ph_bg_jobs_seq.currval";
		$secondsql =  "{$sql} (:jobname,{$sequence},:jobtype,:sequence,'f',:tabname,:database,:cmd)";

		$db = $this->idsadmin->get_database("sysadmin");

		$stmt1 = $db->prepare($firstsql);
		$stmt2 = $db->prepare($secondsql);

		$sequence = 0;

		foreach ( $stmts as $k => $v )
		{
			$v = trim($v);
			if ( $v == "" )
			{
				continue;
			}
			if ( $sequence == 0)
			{
				$stmt = $stmt1;
			}
			else
			{
				$stmt = $stmt2;
			}

			$stmt->bindparam(":jobname",$jobname);
			$stmt->bindparam(":jobtype",$jobtype);
			$stmt->bindparam(":sequence",$sequence);
			$stmt->bindparam(":tabname",$tabname);
			$stmt->bindparam(":database",$dbname);
			$stmt->bindparam(":cmd",$v);
			$sequence++;

			try
			{
				$stmt->execute();
			}
			catch ( PDOException $e )
			{
				/* the statement may contain '<' chars , this causes a problem when we display the text
				 * back in flex , so we use the htmlspecial chars on the statment.
				 */
				$v = htmlspecialchars($v,ENT_COMPAT,"UTF-8");
				$res['RESULTS'][] = array($e->getCode(),"{$v}",  $e->getMessage() );
				/* if error is on the create table then lets just break.. */
				$err=true;
				break;
			}
			
			$err = $db->errorInfo();
			if ($err[1] != 0)
			{
				$v = htmlspecialchars($v,ENT_COMPAT,"UTF-8");
				$res['RESULTS'][] = array($err[1],"{$v}",  $err[2] );
				/* if error is on the create table then lets just break.. */
				$err=true;
				break;
			}

			/* the statement may contain '<' chars , this causes a problem when we display the text
			 * back in flex , so we use the htmlspecial chars on the statment.
			 */
			$v = htmlspecialchars($v,ENT_COMPAT,"UTF-8");
			$res['RESULTS'][] = array(0,"{$v}","");
			$res['RESULTS']['jobid'] = $db->lastInsertId();
		}

		if ( $err === true )
		{
			return $res;
		}
		//		else
		//		{
		//			/* if table creation is successful, we need to retrieve the tabid of the new table */
		//			$delimident = $this->idsadmin->phpsession->instance->get_delimident();
		//			if (strtolower($delimident != "y"))
		//			{
		//				$tabname = strtolower($tabname);
		//			}
		//			$qry = "select tabid from systables where tabname = '{$tabname}'";
		//			$result = $this->doDatabaseWork($qry,$dbname);
		//			$res['TABID'] = $result[0]['TABID'];
		//		}
		if ( $runNow == 1 )
		{
			$res['runjob']=$this->runJob($jobname);
		}
		return $res;
	}


	/**
	 * Drop table (or view or synonym)
	 *
	 * @param $dbname
	 * @param $tabname
	 * @param $tabtype
	 */
	function dropTable($dbname, $tabname, $tabtype)
	{
		$sql = "DROP ";
		switch ($tabtype)
		{
			case 'V':
				$sql .= "VIEW ";
				break;
			case 'P':  // Private synonym
			case 'S':  // Public synonym
				$sql .= "SYNONYM ";
				break;
			case 'T':
			default:
				$sql .= "TABLE ";
				break;
		}
		$sql .= $tabname;

		$result = array();
		$result['CODE'] = 0;
		$result['MESSAGE'] = "";
		try
		{
			$this->doDatabaseWork($sql , $dbname , true);
		}
		catch (PDOException $e)
		{
			$result['CODE'] = $e->getCode();
			$result['MESSAGE'] = $e->getMessage();
		}
		return $result;
	}

	/**
	 * Truncate table
	 *
	 * @param $dbname
	 * @param $tabname
	 * @param $reuseStorage true/false
	 */
	function truncateTable ($dbname, $tabname, $reuseStorage)
	{
		$sql = "TRUNCATE TABLE {$tabname} ";
		if ($reuseStorage)
		{
			$sql .= "REUSE STORAGE";
		} else {
			$sql .= "DROP STORAGE";
		}

		$result = array();
		$result['CODE'] = 0;
		$result['MESSAGE'] = "";
		try
		{
			$this->doDatabaseWork($sql , $dbname , true);
		}
		catch (PDOException $e)
		{
			$result['CODE'] = $e->getCode();
			$result['MESSAGE'] = $e->getMessage();
		}
		return $result;
	}
	
	/**
	 * Create a database.
	 * 
	 * If successful, return the database information that will
	 * be added to the tree.
	 * 
	 * @param dbname
	 * @param sql statement to create the database
	 */
	public function createDatabase ($dbname, $sql)
	{
		$result = $this->executeSQLAdminTask($sql);
		
		if ($result['RETURN_CODE'] < 0)
		{
			// If the create database statement failed, return now
			return $result;
		}
		
		if ($dbname != null)
		{
    		// If the create database statement succeed, get the information
    		// on this database to be added to the tree.
    		$dbRes = $this->getDatabases(strtolower($dbname));
    		$result['DATABASE'] = $dbRes[0];
		}
		
		return $result;
	} 

	/**
	 * Get all the parts of the tableview .. info , indexes , triggers etc..
	 * @param dbname
	 * @param tabid
	 * @param rows_per_page
	 * @return unknown_type
	 */
	function getTabViewInfo($dbname , $tabid, $tabtype, $rows_per_page)
	{
		$ret = array();

		$res = $this->getTabInfo($dbname,$tabid, $tabtype);
		$ret['INFO'] = $res;

		$res = $this->getTabColumns($dbname,$tabid,$rows_per_page);
		$ret['COLUMNS'] = $res;

		$res = $this->getTabIndexes($dbname,$tabid,$rows_per_page);
		$ret['INDEXES'] = $res;

		$res = $this->getTabReferences($dbname,$tabid,$rows_per_page);
		$ret['REFERENCES'] = $res;

		$res = $this->getTabPrivileges($dbname,$tabid,$rows_per_page);
		$ret['PRIVILEGES'] = $res;

		$res = $this->getTabStatistics($dbname,$tabid,$rows_per_page);
		$ret['STATISTICS'] = $res;

		$res = $this->getTabConstraints($dbname,$tabid,$rows_per_page);
		$ret['CONSTRAINTS'] = $res;

		$res = $this->getTabFragments($dbname,$tabid,$rows_per_page);
		$ret['FRAGMENTS'] = $res;

		$res = $this->getTabTriggers($dbname,$tabid,$rows_per_page);
		$ret['TRIGGERS'] = $res;

		return $ret;
	}
	
	/**
	 * Get available database locales
	 * 
	 * @param $rows_per_page (optional)
	 * @param $page - current page number (optional)
	 * @param $sort_col - sort columns for order by clause (optional)
	 * @param $search_pattern - locale to search for (optional)
	 * @param $refresh - boolean indicating whether we need to refresh the locales
	 *            information on the database server
	 */
	public function getDBLocales ($rows_per_page = null, $page = 1, $sort_col = null, $search_pattern = null, $refresh = false)
	{
		// Check for locales_ext table
		$this->testAndDeployLocalesExt();
		
		if ($refresh)
		{
			$this->refreshDBLocalesInfo();
		}
		
		$res = array();
		
		$qry = "select language, name from locales_ext ";
		$qry .= "where name != 'en_US.unicode' and name != 'en_US.ucs4' ";  // en_US.unicode and en_US.ucs4 are not valid DB_LOCALEs
		if ($search_pattern != null)
		{
			$qry .= " and (name like '%{$search_pattern}%' or language like '%{$search_pattern}%')";
		}
		if ($sort_col == null)
		{
			// default sort order
			$sort_col = "language";
		}
		$res['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry,$rows_per_page,$page,$sort_col),"sysadmin");
		
		// Localize the language names
		$this->idsadmin->load_lang("language");
		for ($i = 0; $i < count($res['DATA']); $i++)
		{
			// replace language name by localized language name if possible
			$res['DATA'][$i]['LANGUAGE'] = $this->localizeLanguage($res['DATA'][$i]['LANGUAGE']);
		} 
		
		$res['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), "sysadmin");
		foreach ($temp as $row)
		{
			$res['COUNT'] = $row['COUNT'];
		}
		
		return $res;
	}
	
	public function getMartDef($accelName, $martName, $dbName)
	{  
	   if ($this->isAccelAccessible($accelName, $dbName) == 'false') {
	   		$result = array();
        	$result['CODE'] = -1;
        	$result['ERROR_MESSAGE'] = "";
        	
        	return $result;
	   }
	   
	   $query = "select ifx_getMartDef('{$accelName}','{$martName}') as martdef from sysmaster:sysdual";
	   
	   $this->clobType = true;
	   $res = $this->doDatabaseWork($query,$dbName);
	   // reset clobType
       $this->clobType = false;
	   
	   try {
	       $martdef = new SimpleXMLElement($res[0]['MARTDEF']);
       } catch (Exception $e) {
           $result['ERROR_MESSAGE'] = $e->getMessage() . " - Query:$query";
           return $result;
       }
       
       $tabInfo = array();
       $idx = 0;
       foreach ($martdef->mart->table as $table) {
            $tabInfo[$idx]['TABNAME'] = (string) $table['name'];
            $tabInfo[$idx]['ISFACT'] = (string) $table['isFactTable'];
            ++$idx;
       }
          
	   $ret['TABINFO'] = $tabInfo;
	   
	   $query2 = "SELECT c.* FROM TABLE(ifx_listMarts('{$accelName}')) (c) where c.name = '{$martName}'";
	   
       $res1 = $this->idsadmin->doDatabaseWork($query2, $dbName);
       $ret['DMINFO'] = $res1[0];
       
       $ret['LOAD_SCHEDULE'] = $this->getLoadMartSchedule("$martName:$dbName:$accelName");
       
       $query3 = "SELECT m_trickle_secs as trickle_feed FROM iwa_datamarts WHERE m_name = '{$martName}' AND m_accel_name = '{$accelName}'";
       $res2 = $this->idsadmin->doDatabaseWork($query3, 'sysadmin'); 
       $ret['TRICKLE_FEED'] = $res2[0];
	   
	   return $ret;
	}
	
	/*
	 * Function to enable/disable/drop a data mart.
	 */
	public function doMartActions($action, $accelName, $martName, $dbName, $params="")
	{
	   if (!$this->isAccelAccessible($accelName, $dbName)) {
	   		$result = array();
	    	$result['ACTION'] = $action;
        	$result['CODE'] = -1;
        	$result['MESSAGE'] = "";
        	
        	return $result;
	   }
	   
	   if ($action == 'enable_mart' || $action == 'disable_mart')
	   {
	       $query = "execute function ifx_setMart('{$accelName}','{$martName}','{$params}')";
	   } else {
	       $query = "execute function ifx_dropMart('{$accelName}','{$martName}')";
	   }	   
	   
	    $result = array();
	    $result['ACTION'] = $action;
        $result['CODE'] = 0;
        $result['MESSAGE'] = "";
        try
        {
            $this->doDatabaseWork($query , $dbName , true);
        }
        catch (PDOException $e)
        {
            $result['CODE'] = $e->getCode();
            $result['MESSAGE'] = $e->getMessage();
        }
        
        return $result;
	}
	
	/* 
	 * Create Mart - Step 1: Setup to start capture of workload
	 *
	 *      Stored procedure doc:
	 *      CREATE FUNCTION dwa_crea_wltab(
     *                                       workload_name VARCHAR(20),  // name of the workload, max. 20 characters
     *                                       mode INT )                  // if workload table already exists, mode
     *                                                                   // decides what to do:
     *                                                                   // 0=append, 1=truncate, 2=cancel
     *                                       RETURNING INT AS retcode,   // 0: table created or will be appended to
     *                                                                   // 1: existing table was truncated (mode = 1)
     *                                                                   // 2: table exists, cancelled (mode =2)
     *                                       VARCHAR(90) AS message;     // dummy message
	 *
	 *      CREATE FUNCTION dwa_start_capture(
     *                                           warehouse_db VARCHAR(128),    // name of the warehouse database
     *                                           uname VARCHAR(50),            // optional: user name
     *                                           sess_id INT,                  // optional: session ID
     *                                           tr_num INT,                   // optional: number of traces
     *                                           tr_size INT)                  // optional: size (kB) of single trace row
     *                                           RETURNING INT AS sql_id,      // last sqlid before capture started or
     *                                                                         // zero if no trace record exists.
     *                                             INT as sql_finishtime,      // last finishtime before capture started
     *                                                                         // or zero if no trace record exists.
     *                                             VARCHAR(255) AS message;    // dummy message
	 *
	 */
	public function createMartStartCapture($dbName, $workLoadName, $userName, $traceNum, $traceSize)
	{
	   $mode = 0;
	   $savedTrace = "select task('set sql tracing info') as tracinginfo from systables where tabid = 1";
	   $result['SAVED_TRACE'] = $this->doDatabaseWork($savedTrace , "sysadmin" , true);
	   
	   $createWlTab = "execute function dwa_crea_wltab('${workLoadName}', $mode)"; 
       $result['CR_TAB'] = $this->doDatabaseWork($createWlTab , $dbName , true);
       
       $userName = (empty($userName))? 'NULL' : "'${userName}'";
       $startCap = "execute function dwa_start_capture('${dbName}', ${userName}, NULL, ${traceNum}, ${traceSize})";
       $result['START_CAP'] = $this->doDatabaseWork($startCap , "sysadmin" , true);
       
	   return $result;
	}
	
	/*
	 * Create Mart - Step 2: Stop the workload queries capture
	 *
	 * Stored procedure doc:
	 * CREATE FUNCTION dwa_fill_wltab(
  	 * 									workload_name VARCHAR(20),   // name of the workload
  	 *									sqlid1 INT,                  // id of workload start in SQL Trace buffer
  	 *									sqlfinishtime1 INT,          // time of workload start
  	 *									sqlid2 INT,                  // id of workload end in SQL Trace buffer
  	 *									sqlfinishtime2 INT )         // time of workload end
  	 *									RETURNING INT AS retcode,    // 0: success, 1,2: warning, <0: failure
     *										INT AS num_stmt,         // number of saved query statements
     *										VARCHAR(90) AS message;  // dummy message
	 *
	 *
	 * CREATE FUNCTION dwa_stop_capture()
  	 *									RETURNING INT AS sql_id,  // id of last trace record within capture
     *									INT AS sql_finishtime,    // finishtime of last record within capture
     *									VARCHAR(255) as message;  // dummy message
     */
	
	public function createMartStopCapture($dbName, $workLoadName, $sqid1, $sqft1, $trInfo)
	{
	   $stopCap = "execute function dwa_stop_capture()";
       $result['STOP_CAP'] = $this->doDatabaseWork($stopCap , "sysadmin" , true);
       
       $sqid2 = $result['STOP_CAP'][0][SQL_ID];
       $sqft2 = $result['STOP_CAP'][0][SQL_FINISHTIME];
       
       $fillTab = "execute function dwa_fill_wltab('${workLoadName}', ${sqid1}, ${sqft1}, ${sqid2}, ${sqft2})";
       $result['FILL_TAB'] = $this->doDatabaseWork($fillTab , $dbName , true);
       
       // restore sql tracing values to what it was before 'start capture'
       $result['RESTORE_TRACE'] = $this->doDatabaseWork($trInfo , "sysadmin" , true);
       
       // run probing to determine which statements can be accelerated
       $probe1 = "execute function dwa_probe1('${workLoadName}')";
       $result['PROBE1'] = $this->doDatabaseWork($probe1 , $dbName , true);
       
       $wrkLoad = $this->createMartGetWorkloadStmts($workLoadName, $dbName, $this->idsadmin->get_config('ROWSPERPAGE'));
       
       $result['WORKLOAD_STMTS'] = $wrkLoad['WORKLOAD_STMTS'];
       $result['COUNT'] = $wrkLoad['COUNT'];

       $wrkLoadAccelCnt = $this->createMartGetWorkloadAcceleratableStmtCount($workLoadName, $dbName);
       $result['WORKLOAD_ACCEL_COUNT'] = $wrkLoadAccelCnt['WORKLOAD_ACCEL_COUNT'];
       
       return $result;
	}
	
	/** 
	 * Get the count of acceleratable statements in the workload
	 */	
	public function createMartGetWorkloadAcceleratableStmtCount($workLoadName, $dbName)
	{
		$result = array();
		$sql = "select count(*) as count from " . self::IWA_WORKLOAD_TAB_PRE . "${workLoadName} where dwa_acceleratable=1";
		$count = $this->doDatabaseWork($sql, $dbName, true);
		$result['WORKLOAD_ACCEL_COUNT'] = $count[0]['COUNT'];
		return $result;
	}

	public function createMartGetWorkloadStmts($workLoadName, $dbName, $rows_per_page = NULL, $page = 1, $sort_col = null, $search_pattern = null)
	{
       $workloadStmts = "select * from " . self::IWA_WORKLOAD_TAB_PRE . "${workLoadName}";
       if ($search_pattern != null)
       {
           $workloadStmts .= " WHERE sql_statement like '%{$search_pattern}%'";
       }
       $result['WORKLOAD_STMTS'] = $this->doDatabaseWork($this->idsadmin->transformQuery($workloadStmts,$rows_per_page,$page,$sort_col), $dbName, true);
       
       $count = $this->doDatabaseWork($this->idsadmin->createCountQuery($workloadStmts), $dbName, true);
       
       $result['COUNT'] = $count[0]['COUNT'];
       
       return $result;
	}
	
	public function getUpdatedSQLcount($dbName, $sqlid1, $sqlft1)
	{
	   $count = "SELECT count(*) as count from sysmaster:syssqltrace " .
	               "where sql_id > ${sqlid1} and sql_finishtime >= ${sqlft1} " .
	               "AND sql_dbspartnum in (SELECT partnum FROM sysmaster:sysdatabases WHERE name = '${dbName}') " .
                   "AND (sql_stmttype = 2 OR sql_stmttype = 3)";
	   $res = $this->doDatabaseWork($count, "sysmaster", true);
	   
	   $result['COUNT'] = $res[0]['COUNT'];
	   return $result;
	}
	
	public function createMartCreate($accel, $workloadName, $dmName, $dbName, $dbSpace, $dbLocale, $loadOption, $martDefDBcreate, $deleteWorkloadTable=false)
	{
	   if ($martDefDBcreate == true)
	   {
	       $result['CREATE_DB'] = $this->createDatabase(null,"EXECUTE FUNCTION ADMIN ('CREATE DATABASE WITH LOG','" . self::IWA_DATAMART_DEF_DB . "','${dbSpace}','${dbLocale}')");
           $lmtrick = "create procedure 'dwa'.logmode_trick() external name '(mi_set_no_logmodecheck)' language C";
           $result['LM_TRICK_RESULT'] = $this->doDatabaseWork($lmtrick, self::IWA_DATAMART_DEF_DB, true);
	   }
	   
	   // probe
	   $probe = "execute function dwa_probe_final('${workloadName}')";
	   $result['PROBE_RESULT'] = $this->doDatabaseWork($probe, $dbName, true);
	   
	   // create mart
	   $mode = 1; //overwrite mart definition, if it exists
	   $deploy = 1;
	   	   
	   $XML_do = 0;
	   $XML_file = '';
	   //turn off load mart in dwa_create_mart
	   $load = 0;
       $locking = '';
	   
	   $createMart = "execute function dwa_create_mart('${dmName}', '${dbName}', '${accel}', ${mode}, ${deploy}, ${load}, '${locking}', ${XML_do}, '${XML_file}')";
	   $result['CREATE_MART'] = $this->doDatabaseWork($createMart, self::IWA_DATAMART_DEF_DB, true); 
	
	   if ($loadOption != '') {
           $result['LOAD_MART'] = $this->createMartLoad($accel, $dmName, $dbName, $loadOption);
       }
       
       if ($deleteWorkloadTable == true) {
           $result['DROP_WKL_TAB'] = $this->dropTable($dbName, self::IWA_WORKLOAD_TAB_PRE . "${workloadName}", "T");
       }
       
       // drop database oatmartprobedef
       $result['CLOSE_DB'] = $this->doDatabaseWork('CLOSE DATABASE', self::IWA_DATAMART_DEF_DB, true);
       $dropdb = "EXECUTE FUNCTION ADMIN ('DROP DATABASE','oatmartprobedef');";
       $result['DROP_DB'] = $this->executeSQLAdminTask($dropdb);

       return $result;
	}
	
    public function createMartLoad($accelName, $dmName, $dbName, $loadOption)
    {
        $delTask = "delete from ph_task where tk_name = 'OAT Load Mart'";
        $res[DELETE_TASK] = $this->doDatabaseWork($delTask, "sysadmin", true);
        
        $sql = " INSERT INTO ph_task(tk_name, tk_description, tk_type, tk_dbs, tk_start_time, tk_stop_time, tk_frequency, tk_execute) ";
        $sql .= " VALUES ";
        $sql .=  " ('OAT Load Mart','OAT Load Mart task','TASK','{$dbName}', NULL, NULL, NULL, :cmd)";

        $db = $this->idsadmin->get_database("sysadmin");
        $loadCmd = "execute function ifx_loadMart('{$accelName}','{$dmName}','{$loadOption}')";

        //preparing/binding just the tk_execute column to accommodate string-within-string/DELIMIDENT e.g. 'execute function loadmart("XM3IWA2","maySalesMart","NONE")'      
        try {
            $stmt = $db->prepare($sql);
        } catch ( PDOException $e ) {
            $res['PREPARE_EXCEPTION'] = array($e->getCode(),"{$loadCmd}",  $e->getMessage() );    
        }        
        
        if ($stmt == false) {
            $res['PREPARE_ERROR'] = $db->errorInfo();
            return $res;
        }
       
        try {
            $stmt->bindparam(":cmd",$loadCmd);
        } catch ( PDOException $e ) {
            $res['BIND_ERROR'] = array($e->getCode(),"{$loadCmd}",  $e->getMessage() );    
        }
        
        try {
            $stmt->execute();
        } catch ( PDOException $e ) {
            $res['INSERT_EXCEPTION'] = array($e->getCode(),"{$loadCmd}",  $e->getMessage() );    
        }
            
        $err = $db->errorInfo();
        if ($err[1] != 0){
            $res['INSERT_ERROR'] = array($err[1],"{$loadCmd}",  $err[2] );
        }

        $res['LOAD_TASKID'] = $db->lastInsertId();
        
        return $res;
                
    }	
    
    public function loadMart($accelName, $dmName, $dbName, $loadOption)
    {
        $setMartOff = "execute function ifx_setMart('{$accelName}','{$dmName}','OFF')";
        $loadCmd = "execute function ifx_loadMart('{$accelName}','{$dmName}','{$loadOption}')";
        $jobs = $setMartOff . ";\n" . $loadCmd;
        
        $bgJobName = $this->iwaJobName($accelName, $dmName, $dbName);
        $result['DATA'] = $this->createJob($dbName,"IWA Load mart actions",$jobs,"group",$bgJobName,0);        
        
        //sysadmin:ph_task.tk_description has reference to the data mart corresponding to this task
        
        $tkDesc = $dmName . ":" . $dbName . ":" . $accelName;
        $loadTask = "insert into ph_task (tk_name, tk_description, tk_type, tk_frequency, tk_execute, tk_start_time, tk_stop_time) ";
        $loadTask .= "VALUES ('{$bgJobName}', '{$tkDesc}', 'TASK', NULL, :execCmd, DATETIME(14:25:00) HOUR TO SECOND, NULL)";
        
        $db = $this->idsadmin->get_database("sysadmin");
        $stmt = $db->prepare($loadTask);
        $cmd = "EXECUTE FUNCTION exectask_async('Job Runner','{$bgJobName}')";
        $stmt->bindparam(":execCmd",$cmd);

        try {
            $stmt->execute();
        } catch ( PDOException $e ) {
            $result['RESULTS'][] = array($e->getCode(),"{$cmd}",  $e->getMessage() );
        }
      
        //error_log("result is:" . var_export($result,true));
        return result;
    }
    
    /*
     */
    public function loadMartSched($tkDesc, $bgJobName, $setMartOff, $loadCmd, $loadTask, $loadTaskExecCmd, $deleteTask, $deleteJob, $loadNow, $trickleFeed)
    {
        /*
         *      $loadNow values:
         *			default = -1;
         *          LOAD_IMMEDIATE_ONLY:int = 1;
         *          LOAD_IMMEDIATE_AND_INSERT_SCHED:int = 2;
         *          INSERT_SCHED_ONLY:int = 3;
         *          UPDATE_SCHED:int = 4;
         *			DELETE_SCHED:int = 5;
         *			DELETE_INSERT_SCHED:int = 6; // change from full refresh to partition refresh or vice versa
         *			LOAD_IMMEDIATE_AND_INSERT_TASK:int = 7; // same as LOAD_IMMEDIATE_AND_INSERT_SCHED except tk_name is of format OAT_IWA_i_xxxxx (i => immediate load, no schedule already set or being set)
         */
        // create a new background job and the corresponding ph_task to run it.
        
        $result = array();
        
        // $tkDesc format: "martName:dbName:accelName:loadLockingOption"
    	$pieces = explode(":",$tkDesc);
    	
        if ($loadNow == 2 || $loadNow == 3 || $loadNow == 7) {
        
        	$res1 = $this->insertSched($tkDesc, $bgJobName, $setMartOff, $loadCmd, $loadTask, $loadTaskExecCmd);
        	$result = array_merge($result, $res1);
        } 
        
        // run a load now
        if ($loadNow == 1 || $loadNow == 2 || $loadNow == 7) {
            if ($loadNow == 1) {
                // In a LOAD_IMMEDIATE_ONLY case get the unique task name that needs to be executed.
                $query = "SELECT trim(tk_name) as tk_name from ph_task where tk_description = '{$tkDesc}'";
                $tkRes = $this->doDatabaseWork($query,"sysadmin",true);
                $tkName = $tkRes[0]['TK_NAME'];
            } else if ($loadNow == 2 || $loadNow == 7) {
                $tkName = $bgJobName;
            }
            $loadNowCmd = "EXECUTE FUNCTION exectask_async('{$tkName}')";
            $result['LOAD_NOW'] = $this->doDatabaseWork($loadNowCmd, "sysadmin", true);
            $result['LOAD_NOW'][0]['RES'] = $result['LOAD_NOW'][0][''];
            unset($result['LOAD_NOW'][0]['']);
        }
        
        // update the schedule
        if ($loadNow == 4) {
            $result['UPDATE_SCHED'] = $this->doDatabaseWork($loadTask, "sysadmin", true);
            if ( empty($result['UPDATE_SCHED']) ) {
                $result['UPDATE_SCHED']['RES'] = '0';
            }
            
            $db = $this->idsadmin->get_database("sysadmin");
            $stmt = $db->prepare($loadTaskExecCmd);
            $stmt->bindparam(":loadMartStmt",$loadCmd);
    
            try {
                $stmt->execute();
            } catch ( PDOException $e ) {
                $result['RESULTS'][] = array($e->getCode(),"{$loadCmd}",  $e->getMessage() );
            }
        } 
        
        // delete the schedule or delete-&-insert
        if ($loadNow == 5 || $loadNow ==6 ) {
        	$result['DELETE_TASK'] = $this->doDatabaseWork($deleteTask, "sysadmin", true);
        	if ( empty($result['DELETE_TASK']) ) {
                $result['DELETE_TASK']['RES'] = '0';
            }
            $result['DELETE_JOB'] = $this->doDatabaseWork($deleteJob, "sysadmin", true);
            if ( empty($result['DELETE_JOB']) ) {
                $result['DELETE_JOB']['RES'] = '0';
            }
            
            if ($loadNow == 6) {
            	$res2 = $this->insertSched($tkDesc, $bgJobName, $setMartOff, $loadCmd, $loadTask, $loadTaskExecCmd);
            	$result = array_merge($result, $res2);
            }
        }    
        
        // setup trickle-feed
        if (strlen($trickleFeed) > 0) {
        	$result['TRICKLE_FEED_CMD'] = $this->doDatabaseWork($trickleFeed, "{$pieces[1]}", true);
        	$result['TRICKLE_FEED_CMD'][0]['RES'] = $result['TRICKLE_FEED_CMD'][0][''];
            unset($result['TRICKLE_FEED_CMD'][0]['']);
        }
         
        //error_log("trickle-feed: {$trickleFeed}:{$pieces[1]}" . var_export($result, true));
        
        return $result;
    }
    
    public function insertSched($tkDesc, $bgJobName, $setMartOff, $loadCmd, $loadTask, $loadTaskExecCmd)
    {
    	$result = array();
    	
		$jobs = $setMartOff . ";\n" . $loadCmd;
        
        $tkDescArr = preg_split("/:/",$tkDesc);
        $dbName = $tkDescArr[1];
        
        $result['CREATE_JOB'] = $this->createJob($dbName, "IWA Load mart actions", $jobs, "group", $bgJobName, 0);
        
        $db = $this->idsadmin->get_database("sysadmin");
        $stmt = $db->prepare($loadTask);
        $stmt->bindparam(":execCmd",$loadTaskExecCmd);

        try {
            $stmt->execute();
        } catch ( PDOException $e ) {
            $result['RESULTS'][] = array($e->getCode(),"{$loadTaskExecCmd}",  $e->getMessage() );
        } 
        
        return $result;   
    }
    
    public function getLoadMartSchedule($martReference)
    {	
        $query = "SELECT trim(tk_name) as tk_name, tk_description, tk_start_time, tk_monday, tk_tuesday, tk_wednesday, "
              . "tk_thursday, tk_friday, tk_saturday,tk_sunday, tk_enable, "
              . "NVL(tk_stop_time,'NULL') as tk_stop_time, "
              . "NVL(tk_frequency,'NULL') as tk_frequency "
              . "FROM ph_task WHERE tk_description LIKE '{$martReference}%' "
              . "ORDER BY tk_name";
        $taskParams = $this->idsadmin->doDatabaseWork($query, 'sysadmin');
        
        return $taskParams;
    }
    
    public function savePopUpState($insStmt)
    {
        $saveRes = $this->idsadmin->doDatabaseWork($insStmt, 'sysadmin', true);
        return $saveRes;
    }
    
    /*
     *	To check if data mart is accessible
     */
    public function isAccelAccessible($accelName, $dbName)
    {
    	$query = "SELECT c.* FROM TABLE(ifx_getDwaMetrics('{$accelName}')) (c)";
    	
    	$res = $this->doDatabaseWork($query, $dbName);
    	
    	/* When there is no data from the getdwametrics() query we assume the data mart is not accessible (most likely the accelerator server is down).
    	 * When the accelerator server is down, in dbaccess we get an exception such as "(U0001) - severe: AQT10202I:  A connection with the 'GAMAIWA'
    	 * accelerator cannot be established because none of the coordinator nodes of the accelerator can be contacted." Currently (8/15/2012) PHP/pdo_informix
    	 * is unable to return this exception back to OAT (the pdo_informix team is investigating the issue, a defect is yet to be opened).
    	 */
    	if (empty($res)) {
			return 'false';
		} else {
			return 'true';
		}
    }
	
	/********************************
	 * Private functions
	 *******************************/
	/**
	 * Generate IWA background job name
	 */
	private function iwaJobName($accelName, $dmName, $dbName)
	{
        $bgJobName = "OAT_IWA_";
        $bgJobName .= substr($dmName,0,3);
        $bgJobName .=  "_";
        $bgJobName .= substr($dbName,0,3);
        $bgJobName .= "_";
        $bgJobName .= substr($accelName,0,3);
        $bgJobName .= "_";
        $bgJobName .= rand(100,999);
        
        return $bgJobName;	
	}
	
	/**
	 * Get the localized string for the language name
	 */
	private function localizeLanguage($language)
	{
		$lang_keyword = trim($language);
		$lang_keyword = str_replace(" ", "_", $lang_keyword);
		$message = $this->idsadmin->lang($lang_keyword);
		
		if (strpos($message,"MISSING LANG FILE ITEM") !== false)
		{
			// If language not stored in the message file, return the language as stored in the database table
			return $language;
		} else {
			// Else return localized version of the string
			return $message;
		}
	}
	
	
	/**
	 * Check if locales_ext table exists in sysadmin.
	 * If not create that table.
	 */
	private function testAndDeployLocalesExt() 
	{
		// First check if locales_ext table exists
		$sql = "select count(*) as count from systables where tabname = 'locales_ext'";
		$res = $this->doDatabaseWork($sql,"sysadmin");
		if ($res[0]['COUNT'] == 1)
		{
			// The locales_ext table already exists, so we can return
			return;
		}
		
		// If the table does not exist, run the 'CREATE GLFILES' command to create the glsinfo.csv file.
		$this->refreshDBLocalesInfo();
		
		// Get INFORMIXDIR
		$sql = "select env_value from sysenv where env_name = 'INFORMIXDIR'";
		$res = $this->doDatabaseWork($sql,"sysmaster");
		if (count($res) > 0)
		{
			$informixdir = trim($res[0]['ENV_VALUE']);
		} else {
			trigger_error("Error deploying locales_ext table.  Cannot determine INFORMIXDIR.");
		}
		
		// Determine the OS where the database server is installed.
		// We need this to properly setup path names.
		$sql = "select os_name from sysmachineinfo";
		$res = $this->doDatabaseWork($sql,"sysmaster");
		if (count($res) > 0)
		{
			$os_name = trim($res[0]['OS_NAME']);
		} else {
			trigger_error("Error deploying locales_ext table.  Cannot determine database server OS name.");
		}
		
		if (strcasecmp($os_name,"Windows") == 0)
		{
			$datafile = "{$informixdir}\gls\glsinfo.csv";
			$rejectfile = "{$informixdir}\\tmp\glsinfo_bad.out";
		} else {
			$datafile = "{$informixdir}/gls/glsinfo.csv";
			$rejectfile = "{$informixdir}/tmp/glsinfo_bad.out";
		}
		
		// And create the external table that will read the the glsinfo.csv file
		$sql = "CREATE EXTERNAL TABLE locales_ext "
			 . "( "
			 . "filename varchar(200), "
			 . "language varchar(200), "
			 . "territory varchar(200), "
			 . "modifier varchar(200), "
			 . "codeset varchar(200), "
			 . "name varchar(200), "
			 . "lc_source_version integer, "
			 . "cm_source_version integer "
			 . ") USING ( "
			 . "DATAFILES ('DISK:$datafile'), "
			 . "REJECTFILE '$rejectfile', "
			 . "FORMAT 'delimited', "
			 . "DELIMITER ',')";
		$this->doDatabaseWork($sql, "sysadmin");
	}

	/**
	 * Refresh the db locales information by running the 
	 * EXECUTE FUNCTION TASK ('CREATE GLFILES') command.
	 * This task will update the glsinfo.csv file that the 
	 * external table locales_ext uses.
	 */
	private function refreshDBLocalesInfo()
	{
		$sql = "EXECUTE FUNCTION ADMIN ('CREATE GLFILES')";
		$res = $this->executeSQLAdminTask($sql);
		
		if ($res['RETURN_CODE'] < 0)
		{
			trigger_error($res['RESULT_MESSAGE']);
		}
	}
	
	/**
	 * Run an SQL action.  For example, an ALTER TABLE STATEMENT.
	 * 
	 * Note that this function can handle multiple SQL statments
	 * concatenated together with semicolons.
	 * 
	 * @return $result['RETURN_CODE'] = return code from the statement
	 *         $result['RETURN_MESSAGE'] = error message (if applicable)
	 **/
	public function runSQLAction($sql,$database)
	{
		$res = array();

		$db = $this->idsadmin->get_database($database);
		
		$sql_stmts = explode(";", $sql);
		foreach ($sql_stmts as $sql_stmt)
		{
			if (trim($sql_stmt) == "")
			{
				continue;
			}
			
			$stmt = $db->query($sql_stmt);
			$err = $db->errorInfo();
			if ( $err[2] == 0 )
			{
				$stmt->closeCursor();
				$res['RETURN_CODE'] = 0;
				$res['RETURN_MESSAGE'] = 0;
			} else {
				$res['RETURN_CODE'] = $err[2];
				$res['RETURN_MESSAGE'] = $err[1];
				// return immediately upon error.
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
	public function executeSQLAdminTask($sql)
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
	 * do the database work.
	 */
	function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false) //,$dirty=true)
	{
		$stime = microtime(true);
		$ret = array();

		$db = $this->idsadmin->get_database($dbname);

		while (1 == 1)
		{
			$stmt = $db->query($sel,false,$exceptions);
			/* Some statements don't have a result set (e.g.: SET ISOLATION TO DIRTY READ).
	 		 * Trying to fetch the results for such a statement will result in error,
	 		 * -11031 (Invalid cursor state.). columnCount() returns 0 if there is no result set.
	 		 */
			while ($stmt->columnCount() > 0 && $row = $stmt->fetch() )
			{
			    if (!$this->clobType)
			    {
				    $ret[] = $row;
				}
				else
				{
				    foreach ($row as $index => $val) {
				        $str=stream_get_contents($val);
				        $ret[][$index] = $str;
				    }
				}
			}
			
			$err = $db->errorInfo();

			/* Ignore -11066 code (invalid argument value). It seems like we are running into pdo_informix bug: https://bugs.php.net/bug.php?id=58735
			 * Additionally, in Schema Manager, SQL statements are from the product and not from the user and hence ignoring this code
			 * is perhaps harmless 
			 */ 
			if ( $err[1] == 0 || $err[1] == -11066)
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
		//$etime = microtime(true);
		//error_log ("TIME: ".($etime - $stime));
		//error_log($dbname." ".$sel);
		return $ret;
	}

}
?>

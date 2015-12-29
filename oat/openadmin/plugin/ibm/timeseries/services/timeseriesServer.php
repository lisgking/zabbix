<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2011, 2012.  All rights reserved.
 **********************************************************************/

class timeseriesServer {
	
	const POOR_PAGE_UTILIZATION = 0;           //corresponds to 0% utilization of a page -- free/empty page
	const FAIR_PAGE_UTILIZATION = 20;          //corresponds to 20% utilization of a page -- partially used page
	const GOOD_PAGE_UTILIZATION = 80;          //corresponds to 80% utilization of a page -- mostly used page
	const EXCELLENT_PAGE_UTILIZATION = 100;    //corresponds to 100% utilization of a page -- fully used page

	var $idsadmin;
	
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
		$this->idsadmin->load_lang("timeseries");

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
		$sel .= " FROM sysdatabases " ;
		$sel .= " WHERE name in (select trim(dbsname) from systabnames where tabname = 'calendarpatterns') ";//select databases that use timeseries
		
		if ( $this->idsadmin->phpsession->serverInfo->isPrimary() === false )
		{
			$sel .= " AND is_logging != 0 ";
		} 
		
		if ($dbname != null)
		{
			$sel .= " AND name = '$dbname'";
		}
		
		$sel .= " order by name " ;
		$ret = $this->doDatabaseWork($sel,"sysmaster");
		
		return $ret;
	} 
	
	/**
	 * Get all the parts of the tableview .. info , columns, etc ...
	 * @param dbname
	 * @param tabid
	 * @param tabtype
	 * @param rows_per_page
	 * @return unknown_type
	 */
	function getTabViewInfo($dbname , $tabid, $tabtype, $rows_per_page = null)
	{
		$ret = array();

		$res = $this->getTSTabInfo($dbname,$tabid);
		$ret['INFO'] = $res;

		$res = $this->getTabColumns($dbname, $tabid, $rows_per_page);
		$ret['COLUMNS'] = $res;

		//if the selected table is a virtual table, don't get the virtual tables information
		if($tabtype != 'X')
		{
			$res = $this->getVirtualTables($dbname, $tabid, $rows_per_page);
			$ret['VIRTUAL_TABLES'] = $res;
		}
		else
		{
			//To allow users identify a virtual table in OAT, we have to specify it separately.
			$ret['INFO']['type']= "virtual";
			$ret['INFO']['base_table_info'] = $this->getBaseTable($dbname,$tabid);
		}

		return $ret;
	}
	
	/**
	 * Get all the parts of the databaseview . info , calendars, containers etc ...
	 * @param $dbname
	 * @return unknown_type
	 */
	function getDBViewInfo($dbname, $rows_per_page = null)
	{
		$ret = array();

		$res = $this->getDBInfo($dbname);
		$ret['INFO'] = $res;

		$res = $this->getCalendars($dbname, $rows_per_page);
		$ret['CALENDARS'] = $res;

		$res = $this->getContainers($dbname, $rows_per_page);
		$ret['CONTAINERS'] = $res;

		$res = $this->getRowTypes($dbname, $rows_per_page);
		$ret['ROW_TYPES'] = $res;

		$res = $this->getTablesAndIndexesInfoMinimized($dbname, $rows_per_page);
		$ret['TABLES_AND_INDEXES'] = $res;

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
	function getDBSpaces($page_size, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();

		$sql = "SELECT A.dbsnum as dbsnum, " .
        " trim(B.name) as name, " .
        " sum(decode(mdsize,-1,nfree,udfree) * {$defPagesize}) as free_space_size, " .
        " MAX(A.pagesize) as pagesize " .
        "FROM syschktab A, sysdbstab B " .
        "WHERE A.dbsnum = B.dbsnum " .
		"AND bitval(B.flags, '0x2000') = 0 ". // no tempdbs
		"AND bitval(B.flags, '0x8000') = 0 ". // no sbspaces
		"AND bitval(B.flags, '0x10') = 0 ".   // no blobspaces
		(($name_search_pattern != null)? "AND B.name like '%{$name_search_pattern}%' ":"") . 
        "GROUP BY A.dbsnum , name";
		
		$sql = "SELECT * FROM ($sql) WHERE pagesize = {$page_size}";//need to avoid aggregate usage error
		
		if ($sort_col == null)
		{
			// default sort order
			$sort_col = "dbsnum";
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
		. "	(SELECT ROUND(sum(E.size*T.pagesize/1048576),2) as mb_used_tables "
		. "	from sysmaster:sysextents as E, systables as T "
		. "	where E.tabname = T.tabname "
		. "	and E.dbsname='{$dbname}' "
		. "	group by E.dbsname) end as mb_used_tables, "
		. "CASE when partnum > 0 THEN "
		. "	(SELECT ROUND(sum(E.size*I.pagesize/1048576),2) as mb_used_indexes "
		. "	from sysmaster:sysextents as E, sysindices I "
		. "	where E.tabname = I.idxname "
		. "	and E.dbsname='{$dbname}' "
		. "	group by E.dbsname) end as mb_used_indexes "
		. "FROM sysmaster:sysdatabases WHERE name = '{$dbname}'";

		$ret = $this->doDatabaseWork($qry,$dbname);

		$dbInfo = array();
		$dbInfo['NAME'] = $ret[0]['DBNAME'];
		$dbInfo['OWNER'] = $ret[0]['OWNER'];
		$dbInfo['DBSPACE'] = $ret[0]['DBSPACE'];
		$dbInfo['LOCALE']  = $ret[0]['COLLATION'];
		$dbInfo['LOGGING'] = $ret[0]['LOGGING'];
		$dbInfo['CASE_INSENSITIVE'] = $ret[0]['CASE_INSENSITIVE'];
		$dbInfo['SPACE_USED'] = ($ret[0]['MB_USED_TABLES'] + $ret[0]['MB_USED_INDEXES']) . " MB" ;
		$dbInfo['CREATION_DATE'] = $ret[0]['CREATED'];
		
		return $dbInfo;
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
	
	/**
	 * Get basic info about a timeseries table:
	 * owner, dbspace, # of columns, # of rows, etc.
	 *
	 * @param $dbname
	 * @param $tabid
	 */
	function getTSTabInfo($dbname, $tabid)
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

		$tabInfo = array();
		$tabInfo['tabname'] = trim($ret[0]['TABNAME']);
		$tabInfo['owner'] = trim($ret[0]['OWNER']);
		$tabInfo['type'] = trim($ret[0]['TABTYPE']);
		$tabInfo['tabid'] = $tabid;
		if ($ret[0]['DBSPACE'] == "NotApplicable" || $ret[0]['DBSPACE'] == "FragmentedTable" || $ret[0]['DBSPACE'] == "PseudoTable")
		{
			$tabInfo['dbspace'] = $this->idsadmin->lang(trim($ret[0]['DBSPACE']));
		} else {
			$tabInfo['dbspace'] = trim($ret[0]['DBSPACE']);
		}
		$tabInfo['numcols'] = trim($ret[0]['NCOLS']);
		$tabInfo['nrows']= trim($ret[0]['NROWS']);
		$tabInfo['rowsize'] = trim($ret[0]['ROWSIZE']);
		$tabInfo['locklevel'] = trim($ret[0]['LOCKLEVEL']);
		$tabInfo['datapages'] = trim($ret[0]['NPUSED']);
		$tabInfo['firstextent'] = $ret[0]['FEXTSIZE'];
		$tabInfo['nextextent'] = $ret[0]['NEXTSIZE'];
		$tabInfo['compressed'] = trim($ret[0]['COMPRESSED']);
		$tabInfo['audited'] = $ret[0]['AUDITED'];
		/* test if these are set , because it maybe we are connected to a pre UC6 */
		$tabInfo['maxerrors']   = isset ( $ret[0]['MAXERRORS']  ) ?  $ret[0]['MAXERRORS'] : 0;
		$tabInfo['fmttype']     = isset ( $ret[0]['FMTTYPE']    ) ?  $ret[0]['FMTTYPE']   : "";
		$tabInfo['recdelim']   = isset ( $ret[0]['RECDELIM']   ) ?  $ret[0]['RECDELIM']  : "";
		$tabInfo['flddelim']   = isset ( $ret[0]['FLDDELIM']   ) ?  trim($ret[0]['FLDDELIM'])  : "";
		$tabInfo['dbdate']    = isset ( $ret[0]['DBDATE']     ) ?  trim($ret[0]['DBDATE'])    : "";
		$tabInfo['dbmoney']    = isset ( $ret[0]['DBMONEY']    ) ?  $ret[0]['DBMONEY']  : "";
		$tabInfo['rejectfile']  = isset ( $ret[0]['REJECTFILE'] ) ?  trim($ret[0]['REJECTFILE']) : "";
		$tabInfo['ndfiles']    = isset ( $ret[0]['NDFILES']    ) ?  $ret[0]['NDFILES']  : 0;
		$tabInfo['mode']       = isset ( $ret[0]['MODE']       ) ?  $ret[0]['MODE']     : "";
		$tabInfo['escape']    = isset ( $ret[0]['ESCAPE']     ) ?  (( $ret[0]['ESCAPE'] == 0 ) ? "false" : "true" ) : "" ;
		if ( $tabInfo['ndfiles'] > 0 )
		{
			$tabInfo['extfiles'] = $this->getExternalFiles($dbname,$tabid);
		}
		if ($tabInfo->type == "View")
		{
			$tabInfo['viewDefinition'] = $this->getViewDefinition($dbname,$tabid);
		}
		
		/* For server versions >= 12.10, that support auto compression, we need to get some more detailed information on the compression state of table. */
		$tabInfo['autoCompressed'] = "no"; //Default for all server versions
		$tabInfo['uncompressed'] = (($tabInfo['compressed'] == "yes")? "no" : (($tabInfo['compressed'] == "some")? "some":"yes"));
		if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			$qry = "select "
			. "decode ( sum( decode( bitand(p.flags,'0x08000000'),0,0,1) ), 0 , 'no' , count(*) , 'yes' , 'some' ) as compressed, "
			. "decode ( sum(bitand(p.flags2,'0x00000001')), 0, 'no', count(*), 'yes', 'some') as auto_compressed, "
			. "decode ( sum(decode( bitand(p.flags,'0x08000000') + bitand(p.flags2,'0x00000001'),0,1,0) ), 0, 'no', count(*), 'yes', 'some') as uncompressed "
			. "from systabnames t, sysptnhdr p "
			. "where dbsname='{$dbname}' "
			. "and tabname='{$tabInfo['tabname']}' "
			. "and owner='{$tabInfo['owner']}'"
			. "and t.partnum = p.partnum";
			$ret = $this->doDatabaseWork($qry,"sysmaster");
			if (count($ret) > 0)
			{
			$tabInfo['compressed'] = $ret[0]['COMPRESSED'];
			$tabInfo['autoCompressed'] = $ret[0]['AUTO_COMPRESSED'];
			$tabInfo['uncompressed'] = $ret[0]['UNCOMPRESSED'];
			}
		}
		
		return $tabInfo;
	}
	
	/**
	 * Get the list of tables for a specific database.
	 * Only get the Time Series base tables and virtual tables.
	 *
	 * @param dbname
	 * @param Optional, table name pattern to search for.
	 *        It will search using: WHERE tabname like '%{$tabname_pattern}%'
	 **/
	function getTableNamesForDatabase($dbname, $tabname_pattern = NULL)
	{
		$ret = array();
		$ret['DBNAME'] = $dbname;
		
		//Get the Time Series base tables
		$sel = "SELECT trim(owner)||'.'||trim(tabname) as tabname, "
			 . "tabid, "
			 . "case when flags=16 and tabtype = 'T' then 'R' else tabtype end as tabtype, "
			 . "'{$dbname}' as dbname "
			 . "FROM systables WHERE tabtype != 'Q' "   // Do not show sequences in table list
			 . "AND tabtype != ''"                     // Do not show GL_COLLATE and GL_CTYPE type 'tables' in the list
			 . "AND ("
				 . "tabid in (select tabid from syscolumns where extended_id in " //only select the tables that have timeseries columns
				 . "(select extended_id from sysxtdtypes "
				 . "where type = (select type from sysxtdtypes where name = 'timeseries'))) "
			 . ")";
		
		//Get the virtual tables	 
		$sel2 = "SELECT trim(owner)||'.'||trim(tabname) as tabname, "
			 . "tabid, "
			 . "'X' as tabtype, "
			 . "'{$dbname}' as dbname "
			 . "FROM systables WHERE am_id in (select am_id from sysams where am_name = 'ts_vtam') ";   // Do not show sequences in table list
			
		if ($tabname_pattern != NULL)
		{
			$sel .= " and tabname like '%{$tabname_pattern}%'";
			$sel2 .= " and tabname like '%{$tabname_pattern}%'";
		}
		
		$sel .= "order by tabname";
		$ret['TABLES'] = $this->doDatabaseWork($sel,$dbname);

		$ret['TABLES'] = array_merge($ret['TABLES'], $this->doDatabaseWork($sel2,$dbname));

		return $ret;
	}	
	
	/**
	 * Get calendars information for a specific database
	 * @return 
	 * @param object $dbname
	 * @param object $rows_per_page[optional]
	 * @param object $page[optional]
	 * @param object $sort_col[optional]
	 * @param object $name_search_pattern[optional]
	 */
	function getCalendars ($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$qry = "SELECT c_name as name, c_calendar::lvarchar as calendar, c_refcount as timeseries_count FROM calendartable ";
		
		if ($name_search_pattern != null)
		{
			$qry .= " WHERE c_name like '%{$name_search_pattern}%'";
		}
		
		$temp = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		$ret['DATA'] =  array();
		
		foreach ( $temp as $key => $calendar)
		{
			$matches = array();
			preg_match('/^.*pattstart\((?P<pattstart>.*)\),pattern\(\{(?P<pattern>.*)\},(?P<frequency>.*)\)/', $calendar['CALENDAR'], $matches);
			$ret['DATA'][$key] = array();
			$ret['DATA'][$key]['PATTERN'] = str_replace(",", ", ", $matches['pattern']);
			$ret['DATA'][$key]['FREQUENCY']= $matches['frequency'];
			$ret['DATA'][$key]['PATTERN_START']= substr($matches['pattstart'], 0,-6);
			$ret['DATA'][$key]['TIMESERIES_COUNT']= $calendar['TIMESERIES_COUNT'];
			$ret['DATA'][$key]['NAME']= $calendar['NAME'];
		}
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}
	
	/**
	 * Get the TimeSeries columns for a table. A TimeSeries table usually has one TimSeries columns.
	 * A TimeSeries table has a small number of TimeSeries columns, so pagination should not be needed.
	 */
	function getTimeSeriesColumnsForTable($tabid,$dbname) 
	{
		$sql = "select b.colname from sysxtdtypes a, syscolumns b, systables c " .
			   "where a.extended_id = b.extended_id " .
			   "and b.tabid = {$tabid} " .
			   "and c.tabid = {$tabid}" .
			   "and a.type = (select type from sysxtdtypes where name = 'timeseries') ";
		
		return $this->doDatabaseWork($sql,$dbname);
	}
	
	/**
	 * Get containers information for a specific database.
	 * @return 
	 * @param object $dbname
	 * @param object $table[optional]
	 * @param object $virtual[optional]
	 * @param object $rows_per_page[optional]
	 * @param object $page[optional]
	 * @param object $sort_col[optional]
	 * @param object $name_search_pattern[optional]
	 */
	function getContainers ($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		/* we need some serverInfo */
		$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
		$defPagesize = $this->idsadmin->phpsession->serverInfo->getDefaultPagesize();
		
		$qry = "SELECT name, subtype, partitiondesc, ";
		
		if (Feature::isAvailable(Feature::PANTHER_UC3, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			$qry .= " TSContainerTotalPages(name) as size, ROUND(TSContainerPctUsed(name),2) as space_usage, pool, ";
		}
		
		$qry .= " case WHEN flags = 0 THEN 'empty'" .
				"	ELSE " .
				" 		case sysmaster:bitval(flags,'0x2') " .
				"          WHEN 1 THEN 'irregular' " .
				"		   ELSE 'regular' " .
				"       END " .
				" END as container_type " .
				" from tscontainertable ";
		
		if ($name_search_pattern != null)
		{
			$qry .= " where name like '%{$name_search_pattern}%'";
		}
		
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		foreach ( $ret['DATA'] as $key => $container)
		{
			$matches = array();
			preg_match('/^(?P<container>.*) (?P<space>.*) (?P<first_extent>.*) (?P<next_extent>.*) (?P<flag>.*)/', $container['PARTITIONDESC'], $matches);
			$ret['DATA'][$key]['DBSPACE'] = $matches['space'];
			
			$dbspace_qry = "select TRUNC(100-sum(decode(mdsize,-1,nfree,udfree))*100/ sum(chksize),2) as used "
				  ."from syschktab a, sysdbstab b where a.dbsnum = b.dbsnum and b.name = '{$ret['DATA'][$key]['DBSPACE']}'";
			$db_space_usage = $this->doDatabaseWork($dbspace_qry,"sysmaster");
			$ret['DATA'][$key]['USED'] = $db_space_usage[0]['USED'];
		}
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}
	
	/**
	 * Get the TimeSeries Row Types in a database
	 * @return 
	 * @param object $dbname
	 * @param object $rows_per_page[optional]
	 * @param object $page[optional]
	 * @param object $sort_col[optional]
	 * @param object $name_search_pattern[optional]
	 */
	function getRowTypes ($dbname, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$qry =  "SELECT name , extended_id ";
		$qry .= "FROM sysxtdtypes t ";
		$qry .= "WHERE name <> '' AND extended_id in (SELECT extended_id from sysattrtypes where type = 10 and fieldno = 1)";//select the row types whose first field name is of date time type
				
		if ($name_search_pattern != null)
		{
			$qry .= " and name like '%{$name_search_pattern}%'";
		}

		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		foreach ($ret['DATA'] as $key => $rowType)
		{
			$fields = $this->getFieldNamesOfRowType($dbname, $rowType['EXTENDED_ID']);
			$ret['DATA'][$key]['FIELDS'] = $fields['FIELDS'];
			$ret['DATA'][$key]['FIELDS_ARRAY'] = $fields['FIELDS_ARRAY'];//needed so the tooltip can display teh details nicely.
		}
		
		return $ret;
	}
	
	/**
	 * Get the Fileds information of a time series row type
	 * @return 
	 * @param object $dbname
	 * @param object $extendedId
	 */
	function getFieldNamesOfRowType($dbname, $extendedId)
	{
		$qry = "SELECT fieldname, case mod(type,256) ".
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
        "  when 40 THEN 'UDTVAR' ".
        "  when 41 THEN  ".
        "        case xtd_type_id ".
        "          when 1 THEN 'LVARCHAR' ".
        "          when 5 THEN 'BOOLEAN' ".
        "          when 10 THEN 'BLOB' ".
        "          when 11 THEN 'CLOB' ".
        "          ELSE 'UDTFIXED' ".
        "          END  ".
        "  when 42 THEN 'REFSER8' ".
        "  when 52 THEN 'BIGINT' ".
        "  when 53 THEN 'BIGSERIAL' ".
        "  ELSE 'UNKNOWN '||mod(type,256) ".
		"  END as type, ".
		" length, fieldno ".
		" FROM sysattrtypes ".
		" WHERE fieldname <> '' ".
		" AND extended_id = {$extendedId} ";
		
		$ret = $this->doDatabaseWork($qry, $dbname);
		
		$comma_separated_fileds= "";
		
		foreach ($ret as $key => $field)
		{
			$comma_separated_fileds .= ($key > 0) ? ", " : "";
			$comma_separated_fileds .= "{$field['FIELDNAME']} {$field['TYPE']}";
		}
		$result = array();
		$result['FIELDS'] = $comma_separated_fileds;
		$result['FIELDS_ARRAY'] = $ret;
		return $result;
	}
	
	/**
	 * Get the virtual table of the selected time series base table.
	 * @return 
	 * @param object $dbname
	 * @param object $baseTableId
	 * @param object $rows_per_page[optional]
	 * @param object $page[optional]
	 * @param object $sort_col[optional]
	 * @param object $name_search_pattern[optional]
	 */
	function getVirtualTables ($dbname, $baseTableId, $rows_per_page = null, $page = 1, $sort_col = null, $name_search_pattern = null)
	{
		$qry = "select first 1 tabname from systables where tabid = {$baseTableId}";
		$res = $this->doDatabaseWork($qry,$dbname);

		$baseTable = $res[0]['TABNAME'];
		$qry = "SELECT a.owner, a.tabname, a.tabid, b.am_param from systables a, systabamdata b ";
		$qry .= "WHERE a.tabid = b.tabid and b.am_param LIKE '%basetabname=''{$baseTable}''%'";

		if ($name_search_pattern != null)
		{
			$qry .= " and tabname like '%{$name_search_pattern}%' ";
		}

		$res = array();
		
		$temp = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page, $page, $sort_col),$dbname);
		$res['DATA'] = array();
		
		foreach ($temp as $key => $virtualTable)
		{
			$res['DATA'][$key]['OWNER'] = $virtualTable['OWNER'];
			$res['DATA'][$key]['TABNAME'] = $virtualTable['TABNAME'];
			
			$matches = array();
			preg_match("/^.*tscolname='(?P<tscolname>.*?)',.*/", $virtualTable['AM_PARAM'], $matches);
			$res['DATA'][$key]['TIMESERIES_COLUMN_NAME'] = $matches['tscolname'];
			preg_match("/^.*tselemtype='(?P<tselemtype>.*?)'.*/", $virtualTable['AM_PARAM'], $matches);
			$res['DATA'][$key]['TIMESERIES_COLUMN_TYPE'] = $matches['tselemtype'];

			$res['DATA'][$key]['COLUMNS'] = $this->makeCommaSeparatedColumns($this->getTabColumnsLessInfo($dbname, $virtualTable['TABID'])); 
		}

		$res['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		
		foreach ($temp as $row)
		{
			$res['COUNT'] = $row['COUNT'];
		}
		return $res;
	}
	
	function makeCommaSeparatedColumns($columns)
	{
		$columns_string = "";

		foreach ($columns as $key => $column)
		{
			$columns_string .= ($key > 0) ? ", " : "";
			$columns_string .= $column['COLNAME'] . " " . $column['MYTYPE'];
		}
		
		return $columns_string;
	}
	
	/**
	 * Get the base table of a virtual table
	 * @return 
	 * @param object $dbname
	 * @param object $virtualTableId
	 */
	function getBaseTable($dbname, $virtualTableId) 
	{
		$qry = "SELECT FIRST 1 am_param from systables a, systabamdata b";
		$qry .= " WHERE a.tabid = b.tabid and a.tabid = {$virtualTableId}";
		$ret = $this->doDatabaseWork($qry,$dbname);

		$matches = array();
		$result = array();
		$match = preg_match("/^.*basetabname='(?P<basetabname>.*?)'/", $ret[0]['AM_PARAM'], $matches);
		$result['BASE_TABLE_NAME'] = trim($matches['basetabname']);
		$match = preg_match("/^.*tscolname='(?P<tscolname>.*?)'/", $ret[0]['AM_PARAM'], $matches);
		$result['TS_COLUMN_NAME'] = trim($matches['tscolname']);
		$match = preg_match("/^.*tselemtype='(?P<tselemtype>.*?)'/", $ret[0]['AM_PARAM'], $matches);
		$result['TS_ROW_TYPE'] = trim($matches['tselemtype']);

		return $result;
	}
	
	/**
	 * This is a short version of getTabColumns. It's designed to get necessary information using a faster query.
	 * @return 
	 * @param object $dbname
	 * @param object $tabid
	 */
	function getTabColumnsLessInfo($dbname, $tabid)
	{
		$ret = array();
		
		/* does this version of the server have external table support */
		$feature = Feature::CHEETAH2_UC6;
		if (  Feature::isAvailable ( $feature , $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
		{
			$qry = "select ".
			"  syscolumns.colno, " .
            "  trim(colname) as colname, case mod(coltype,256) " .
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
            "  when 40 THEN 'UDTVAR' ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1 THEN 'LVARCHAR' ".
            "          when 5 THEN 'BOOLEAN' ".
            "          when 10 THEN 'BLOB' ".
            "          when 11 THEN 'CLOB' ".
            "          ELSE 'UDTFIXED' ".
            "          END  ".
            "  when 42 THEN 'REFSER8' ".
            "  when 52 THEN 'BIGINT' ".
            "  when 53 THEN 'BIGSERIAL' ".
            "  ELSE 'UNKNOWN '||mod(coltype,256) ".
            "  END as mytype  ".
            " from syscolumns, systables ".
            " where syscolumns.tabid = systables.tabid ".
            " and systables.tabid = '{$tabid}' ".
			" order by colno";
		}
		else 
		{
			$qry = "select ".
            " trim(colname) as colname, case mod(coltype,256) ".
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
            "  when 40 THEN 'UDTVAR' ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1 THEN 'LVARCHAR' ".
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
			
            " from syscolumns, systables ".
            " where syscolumns.tabid = systables.tabid ".
            " and systables.tabid = '{$tabid}' ".
			" order by colno";
		}

		return $this->doDatabaseWork($qry,$dbname);
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
            "  when 40 THEN 'UDTVAR' ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1 THEN 'LVARCHAR' ".
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
            "  when 40 THEN 'UDTVAR' ".
            "  when 41 THEN  ".
            "        case informix.syscolumns.extended_id ".
            "          when 1 THEN 'LVARCHAR' ".
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
	
	/**
	 * 
	 * Get tables and indexes info for the minimized pod view. 
	 */
	private function getTablesAndIndexesInfoMinimized($dbname, $rows_per_page)
	{
		//get TS tables tabids
		$timeseriesTables = "(SELECT tabid from syscolumns WHERE extended_id in "
			  . "(SELECT extended_id FROM sysxtdtypes "
			  . "WHERE type = (SELECT type FROM sysxtdtypes WHERE name = 'timeseries'))) ";
			  
		//get TS and virtual tables indexes
		$qry = "SELECT idxname as name, owner, 'I' as type FROM sysindices "
		     . "WHERE tabid IN {$timeseriesTables} "
			 //. "OR tabid IN {$virtualTables} "  //virtual tables cannot be indexed
			 . " UNION "
		
			 //Get TS tables and virtual tables
		     . "SELECT tabname as name, owner, 'T' as type FROM systables "
		     . "WHERE tabid IN {$timeseriesTables} ";
		
		$ret = array();
		$ret['DATA'] = $this->doDatabaseWork($this->idsadmin->transformQuery($qry, $rows_per_page),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($qry), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}

		return $ret;
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
	
	function doTimeseriesAction ($dbname, $sql, $action)
	{
		$sql = trim($sql);
		$result = array();
		$results = array();
		$result['SUCCESS'] = true;
		$result['ACTION'] = $action;
		$statements = explode(";", $sql);

		foreach ($statements as $key => $statement)
		{
			$statement = trim($statement);
			if($statement == "")
			{
				continue;
			}
			
			try
			{
				$results[$key]['SQL'] = $statement;
				$this->doDatabaseWork($statement, $dbname, true);
				$results[$key]['CODE'] = 0;
				$results[$key]['MESSAGE'] = "";
			}
			catch (PDOException $e)
			{ 
				$results[$key]['CODE'] = $e->getCode();
				$results[$key]['MESSAGE'] = $e->getMessage();
				$result['SUCCESS'] = false;
			}
		}
		$result['RESULTS'] = $results;
		return $result;
	}
	
	/**
	 * do the database work.
	 *
	 */
	function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false) //,$dirty=true)
	{
		$ret = array();

		$db = $this->idsadmin->get_database($dbname);

		while (1 == 1)
		{
			$stmt = $db->query($sel,false,$exceptions);
			while ($row = $stmt->fetch())
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

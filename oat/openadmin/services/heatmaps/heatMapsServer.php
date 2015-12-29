<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation, 2011.  All Rights Reserved
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

/* Services for Heat Maps feature */

class heatMapsServer {

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
	
	public function getDatabases () 
	{
		$qry = "SELECT unique trim(name) AS dbsname FROM sysdatabases";
		return $this->doDatabaseWork($qry);
	}
	
	public function getExtentData($dbname = "")
	{
		$qry = "SELECT trim(dbsname) AS dbsname, "
			 . "trim(string_to_utf8(tabname, NVL(dbs_collate, 'en_US.819'))) AS tabname, " 
			 . "sum(size) AS size, "
			 . "count(*) AS nextents "
			 . "FROM sysextents e left outer join sysdbslocale l on e.dbsname = l.dbs_dbsname ";
		if ($dbname != "")
		{
			if ($dbname == "system_objects")
			{
				$qry .= "WHERE dbsname NOT IN (SELECT unique name FROM sysdatabases) ";
			} else {
				$qry .= "WHERE dbsname = '{$dbname}' ";
			}
		}
		$qry .= "GROUP BY 1,2 "
			  . "ORDER BY 1,2 ";
		
		$result = $this->doDatabaseWork($qry, "sysmaster", false, "en_US.UTF8");
		return $result;
	}
	
	public function getBufferData($dbname = "")
	{
		$qry = "SELECT " 
			 . "MAX(dbsname) AS dbsname, "
			 . "MAX(tabname) AS tabname, "
			 . "sum(Buffered_pages) as Buffered_pages, "
			 . "sum(nptotal) AS total_pages, "
			 . "sum(nrows) AS total_rows, "
			 . "trunc( sum(Buffered_pages) / sum(nptotal) * 100.0,2 ) AS cache_percent "
			 . "FROM ( "
			 . "    SELECT {+ INDEX( syspaghdr syspaghdridx) } "
			 . "    count(*)  Buffered_pages,  "
			 . "    pg_partnum "
			 . "    FROM  sysmaster:syspaghdr G, sysmaster:sysbufhdr B "
			 . "    WHERE G.pg_chunk   = B.chunk "
			 . "    AND   G.pg_offset  = B.offset "
			 . "    AND   G.pg_partnum > 65535 " // Must be greater than 0
			 . "    GROUP BY G.pg_partnum) as BUF(Buffered_pages, ptnum), "
			 . "( " 
			 . "    SELECT partnum, dbsname, "
			 . "    trim(string_to_utf8(tabname, NVL(dbs_collate,'en_US.819'))) AS tabname "
			 . "    FROM sysmaster:systabnames left outer join sysdbslocale on dbsname = dbs_dbsname "
			 . "    ) as T(partnum, dbsname, tabname), "
			 . "sysmaster:sysptnhdr P "
			 . "WHERE "
			 . "BUF.ptnum = T.partnum "
			 . "AND BUF.ptnum = P.partnum "
			 . "AND T.partnum = P.partnum ";
		
		if ($dbname != "")
		{
			if ($dbname == "system_objects")
			{
				$qry .= "AND T.dbsname NOT IN (SELECT unique name FROM sysdatabases) ";
			} else {
				$qry .= "AND T.dbsname = '{$dbname}' ";
			}
		}
		
		$qry .= "GROUP BY BUF.ptnum ";
		$qry .= "ORDER BY dbsname, cache_percent, total_pages DESC";
		
		$result = $this->doDatabaseWork($qry, "sysmaster", false, "en_US.UTF8");
		return $result;
	}
	
	/**
	 * do the database work.
	 */
	private function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false, $locale=null)
	{
		$ret = array();
		
		if ($locale == null)
		{
			$db = $this->idsadmin->get_database($dbname);
		} else {
			require_once(ROOT_PATH."lib/database.php");
			$db = new database($this->idsadmin,$dbname,$locale);
		}

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

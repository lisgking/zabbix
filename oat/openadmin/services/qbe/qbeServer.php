<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation, 2009, 2012.  All Rights Reserved
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

/* Services for Query By Example (QBE) feature */

class qbeServer {

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
		
		// Set act=qbe so that when we form database connections, it uses the 
		// SQL Toolbox user/password.
		$this->idsadmin->in = array("act" => "qbe");
	}
	
	/**
	 * Get the name of all databases on the server,
	 * except for the system databases.
	 **/
	public function getDatabases()
	{
		$sql = "SELECT name, 'database' as type ";
		$sql.= "FROM sysdatabases ";
		$sql.= "WHERE name ";
		$sql.= "NOT IN ('sysmaster','sysutils','syscdr','sysuser','system',";
		$sql.= "'syscdcv1','syscdcv2','syscdcv3','syscdcv4','syscdcv5','syscdcv6','syscdcv7',";
		$sql.= "'syscdcv8','syscdcv9') ";
		$sql.= "order by name ";
		
		$res = $this->doDatabaseWork($sql,"sysmaster",array(),false);
		return $res;
	}
	
	/**
	 * Get the name and tabid of all tables for a database. 
	 *  - Filter out tables with columns unsupported by QBE
	 *  - Filter out system tables, with tabid < 99
	 *  
	 * @param database name
	 * @param Optional, table name pattern to search for.
	 *        It will search using: WHERE tabname like '%{$tabname_pattern}%'
	 **/
	public function getTables($database, $tabname_pattern = NULL)
	{
		$res = array();
		$res['DBNAME'] = $database; 
		
		$sql = "SELECT UNIQUE";
		$sql.= " systables.tabname as name, systables.tabid , systables.owner, ";
		$sql.= "'table' as type, '{$database}' as dbname ";
		$sql.= " FROM systables,syscolumns";
		$sql.= " WHERE systables.tabid = syscolumns.tabid";
		$sql.= " AND tabtype != 'Q'"; // This removes sequences
		$sql.= " AND systables.tabid > 99";	// This removes all system tables	
		$sql.= " AND syscolumns.tabid NOT IN (";
		$sql.= " 	SELECT tabid FROM syscolumns";
		$sql.= "	WHERE BITAND(coltype,'0x00ff') IN (11,12,15,16,19,20,21,22)"; // This removes BYTE,TEXT,NCHAR,NVARCHAR,SET,MULTISET,LIST,ROW types
		$sql.= " 	OR BITAND(coltype,'0x0800')==2048"; // This removes DISTINCT TYPES
		$sql.= " 	OR extended_id NOT IN (0,1,5)"; // This removes all UDT types, except boolean, lvarchar		
		$sql.= " 	)";
		if ($tabname_pattern != NULL)
		{
			$sql .= " and systables.tabname like '%{$tabname_pattern}%'";
		}
		$sql.= " ORDER BY name";
		
		$res['TABLES'] = $this->doDatabaseWork($sql,$database,array(),false);

		return $res;
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
			$tables = $this->getTables( $v['NAME'], $tabname_pattern );
			$ret[$k]['TABLES'] = $tables['TABLES'];
		}
		return $ret;
	} 
	
	
	/**
	 * Get the column names, colno, coltype and extended_id for a tabid, database.
	 * Also get the column or set of columns that can be used to uniquely identify 
	 * rows in that table.
	 **/ 
	function getColumnInfo($tabname,$tabid,$database)
	{

		$retval = array();
		$sql = "select tabtype, owner, nrows FROM 'informix'.systables WHERE tabid={$tabid}";
		$res = $this->doDatabaseWork($sql,$database);
		$retval['tabtype'] = trim($res[0]['TABTYPE']);
		$owner = trim( $res[0]['OWNER'] );
		$nrows = $res[0]['NROWS'];
		
		$sql = "SELECT colname, colno, mod(coltype,256) as coltype, extended_id, collength ,";
		$sql.= " DECODE(BITAND(coltype,'0x0100'),256,1,0) AS allownull,";
		$sql.= " DECODE(BITAND(coltype,'0x0200'),512,1,0) AS hvar ";
		$sql.= "FROM 'informix'.syscolumns WHERE tabid={$tabid}";
		$sql.= "AND colname NOT IN ('ifx_insert_checksum','ifx_row_version','ifx_replcheck')";  // ignore VERCOLS, CRCOLS, and REPLCHECK columns
		$res = $this->doDatabaseWork($sql,$database);
		$retval['database'] = $database;
		$retval['table'] = $tabname;
		$retval['tabid'] = $tabid;
		$retval['owner'] = $owner;
		$retval['nrows'] = $nrows;
		$retval['columns'] = array();
		foreach($res as $i=>$v)
		{
			$colname = trim($v['COLNAME']);
			$colno = trim($v['COLNO']);
			$coltype = trim($v['COLTYPE']);
			$extended_id = trim($v['EXTENDED_ID']);
			$notnull = (trim($v['ALLOWNULL']) == "0");
			$hvar = trim($v['HVAR']);
			$len  = $v['COLLENGTH'];
			$retval['columns'][] = array("colname"=>$colname,"colno"=>$colno,"coltype"=>$coltype,
			"extended_id"=>$extended_id,"notnull"=>$notnull,"hvar"=>$hvar,"collength"=>$len);
		}
		
		$retval['uniqueColSet'] = $this->findUniqueColumn($tabid,$database);
		
		return $retval;
	}

	/**
	 * Get list of grids + each grid's regions
	 * This information will be shown in the Tree.
	 */
	function getGridsAndRegions()
	{
		$result = array();
		
		// Get all grids
		$query1 = "select trim(gd_name) as gridname, gd_id from grid_def order by gridname";
		$result = $this->doDatabaseWork($query1, 'syscdr');
		
		// For each grid, get regions
		foreach ( $result as $k => $v )
		{
			$query2 = "select trim(gr_name) as region_name, gr_regid as region_id "
			. "from grid_region_tab "
			. "where gr_grid = {$v['GD_ID']} "
			. "order by region_name";
		
			$regions = $this->doDatabaseWork($query2, 'syscdr');
			$result[$k]['GRID_REGIONS'] = $regions;
		}
		
		return $result;	
	}
	
	/**
	 * Find the column or set of columns that can be used to uniquely 
 	 * identify rows in this table. 
	 * 
	 * There are 2 possibilities:
	 * (a) rowid - for non-fragemented tables
	 * (b) unique indexes for unique constraint, primary key constraint,
	 *       or unique index
	 * 
	 * @param tabid of the table
	 * @return set of columns that uniquely identify rows in that table
	 **/
	private function findUniqueColumn($tabid, $database) 
	{
		$unique_columns = array();
		
		/* Is there are rowid for this table? */
		$sql ="SELECT tabid, partnum  FROM 'informix'.systables WHERE tabid = " . $tabid . "";
		$res = $this->doDatabaseWork($sql,$database);
		if (trim($res[0]['PARTNUM']) != 0)
		{
			// If partnum != 0, there will be unique rowids for each row in the table
			$unique_columns[] = "rowid";
			return $unique_columns;
		}
		
		/* If not, is there a unqiue index on this table?
		 * Unique indexes are created internally for primary key or unique constraints 
		 * or for unique indexes. */
		
		/* We want to find the unique index on the fewest columns.  So we'll start
		 * by looking for a unique index on a single column (only part1 is non-zero),
		 * then for a unique index on two columns (only part1 and part2 are non-zero), 
		 * etc. searching up to indexes with 16 colums until we find the unique index 
		 * on this table with the fewest columns. */ 
		for( $i=2 ; $i < 17 ; $i++ )
		{
			$sql  = "SELECT * FROM 'informix'.sysindexes WHERE";
			$sql .= " ( ( idxname in  ( " ;
			$sql .= "        select idxname from 'informix'.sysconstraints ";
			$sql .= "        where constrtype in ('U','P') ) )" ;
			$sql .= "    OR idxtype='U'  ) " ;
			$sql .= " AND part" .$i. "=0 " ;
			$sql .= " AND tabid = " . $tabid  ;
			$res = $this->doDatabaseWork($sql,$database);

			// If we found a unique index, find the column names that 
			// make up that index.
			if (count($res) > 0)
			{
				$col_where="";
				foreach( $res[0] as $index => $val )
				{
					if ( (strncmp("PART",$index,4) == 0 ) && $val>0 )
	                {
						if ( $col_where == "" )
						{
							$col_where = " colno IN ( " . abs($val) ;
						} else {
							$col_where .= " , " . abs($val) ;
						}
					}
				}
				$col_where .= " ) ";
				$sql  = "SELECT colname  FROM 'informix'.syscolumns ";
				$sql .= " WHERE tabid = " . $tabid . " AND " . $col_where;
                $res = $this->doDatabaseWork($sql,$database);

                foreach ($res as $row)
                {
                    $unique_columns[] = $row['COLNAME'];
                }
                return $unique_columns;
			}	
		}
		
		return $unique_columns;
	}
	
	/**
	 * Get the data from the table.
	 * 
	 * @param $dbname - database name
	 * @param $owner - table owner
	 * @param $tabname - table name
	 * @param $filters - array of where clause filters
	 * @param $rowid - boolean indicating if we need to return rowids for this table
	 * @param $rowid - boolean indicating if we need to return er key columns for this table
	 */
	function getDataForTable($dbname,$owner,$tabname,$filters,$gridOrRegname='',$rowid=false,$erkey=false)
	{
		$filter_array = unserialize($filters);
	    
		$query = " SELECT * ";
		if ($rowid) $query .= ", rowid ";
		if ($erkey) $query .= ", ifx_erkey_1, ifx_erkey_2, ifx_erkey_3 ";
		$query .= "FROM '{$owner}'.{$tabname} ";
		if ($gridOrRegname != '') $query .= "GRID '{$gridOrRegname}' ";
		$where_clause = "";
		$where_clause_parameters = array();
		$count = 0;
	    foreach ( $filter_array as $column => $columnFilters )
	    {
	    	if (count($columnFilters) > 0)
			{
				$colFilterCount = 0;
				$colFilterStr = "";
	    		foreach ($columnFilters as $colFilter)
	    		{
	    			if ($colFilterCount > 0)
	    			{
	    				$colFilterStr .= "{$colFilter['BOOLEAN_OPERATOR']} ";
	    			}
	    			if ($colFilter['OPERATOR'] == "IS NULL" || $colFilter['OPERATOR'] == "IS NOT NULL")
	    			{
	    				$colFilterStr .= "$column {$colFilter['OPERATOR']} ";
	    			} else {
	    				$colFilterStr .= "$column {$colFilter['OPERATOR']} ? ";
	    				$where_clause_parameters[] = $colFilter['VALUE'];
	    			}
	    			$colFilterCount++; 
	    		}
	    		if ($count > 0)
	    		{
	    			$where_clause .= "AND ";
	    		}
	    		if ($colFilterCount > 1)
	    		{
	    			$where_clause .= "(" . $colFilterStr . ")";
	    		} else {
	    			$where_clause .= $colFilterStr;
	    		}
				$count++;	
			}
	    }
	    
	    if ($count > 0)
	    {
	    	$query .= "WHERE $where_clause";
	    }
	    
	    $dataResult = $this->doDatabaseWork($query,$dbname,$where_clause_parameters);

		/* Projection list (of the 'select *...' query above) containing multi-byte characters
		 * returns as un-intelligible data. Work-around is to query for column names from syscolumns.
		 * Encountered similar issue in the Schema Browser. Potentially a pdo_informix/ODBC driver bug.
		 * Cost: an extra query to the server and 16 lines of code containing 2 loops.
		 */
		$colnameQuery = "select colname from 'informix'.syscolumns, 'informix'.systables "
					  . "where 'informix'.systables.tabid = 'informix'.syscolumns.tabid and 'informix'.systables.tabname = '$tabname' "
					  . "and colname NOT IN ('ifx_insert_checksum','ifx_row_version','ifx_replcheck'" // ignore VERCOLS, CRCOLS, and REPLCHECK columns
					  . (($erkey)? "":",'ifx_erkey_1','ifx_erkey_2','ifx_erkey_3'")  // ignore ERKEY columns unless these are used to unqiuely identify the row
					  . ")";
	    $colnameResult = $this->doDatabaseWork($colnameQuery,$dbname);
	    $colnameArr = array();
	    foreach ($colnameResult as $col)
	    {
	    	array_push($colnameArr,$col['COLNAME']);
	    }
	    
	    if ($rowid)
	    {
	    	// add ROWID to colnameArr
	    	array_push($colnameArr,'ROWID');
    	}
	    
		/* array jugglery as part of the workaround mentioned above, to combine the column names from syscolumns and 
		 * the column data from the 'select *...' query.
		 */
		$result = array();
	    foreach ($dataResult as $dataRow)
	    {
	    	array_push($result,array_combine($colnameArr,$dataRow));
	    }

	    return $result;
	}

	/**
	 * Insert row into table. 
	 * 
	 * @param $dbname database name
	 * @param $owner table owner name
	 * @param $tabname table name
	 * @param $columnData serialized array mapping column name to the value to be inserted 
	 *           e.g.  array('COL1' => '100', 'COL2' => '200');
	 * @return return code/message from executing the insert statement
	 */
	function insertRow($dbname,$owner,$tabname,$columnData)
	{
		$columnData = unserialize($columnData);
		
		$sql = "INSERT INTO '{$owner}'.{$tabname} (";
		$count = 0;
		$params = array();
		foreach ($columnData as $colname => $value)
		{
			if ($count > 0) $sql .= ", ";
			$sql .= "$colname";
			$params[] = ($value == "null")? null: $value;
			$count++;
		}
		$sql .= ") VALUES (";
		for ($i = 0; $i < $count; $i++)
		{
			if ($i > 0) $sql .= ", ";
			$sql .= "?";
		}
		$sql .= ")";
		
		$db = $this->idsadmin->get_database($dbname, true);
		$stmt = $db->prepare($sql);
		$err = $db->errorInfo();
		if ($err[1] != 0) 
		{
			return array('RETURN_CODE' => $err[1], 'RETURN_MSG' => $err[2]);
		}
		
		$stmt->execute($params);
		$err = $db->errorInfo();
		$result = array('RETURN_CODE' => $err[1], 'RETURN_MSG' => $err[2]);
		return $result; 
	}
	
	
	/**
	 * Update row in the table. 
	 * 
	 * @param $dbname database name
	 * @param $owner table owner name
	 * @param $tabname table name
	 * @param $updateColumns serialized array mapping column name to the value for the 
	 *           columns to be updated in the table.  
	 *           e.g.  array('COL1' => '101', 'COL2' => '201');
	 * @param $uniqueColumns serialized array mapping column name to the value for the 
	 *           where clause of the delete statement.  
	 *           e.g.  array('COL1' => '100', 'COL2' => '200');
	 * @return return code/message from executing the update statement
	 */
	function updateRow($dbname,$owner,$tabname,$updateColumns,$uniqueColumns)
	{
		$updateColumns = unserialize($updateColumns);
		$uniqueColumns = unserialize($uniqueColumns);
		
		if (count($uniqueColumns) == 0)
		{
			return array('RETURN_CODE' => -1, 
				'RETURN_MSG' => 'Cannot udpate row on the database server because there are no unique columns for this table.');
		}
		
		$sql = "UPDATE '{$owner}'.{$tabname} SET ";
		$count = 0;
		$params = array();
		foreach ($updateColumns as $colname => $value)
		{
			if ($count > 0) $sql .= ", ";
			$sql .= "$colname = ?";
			$params[] = ($value == "null")? null: $value;
			$count++;
		}
		
		$count = 0;
		$where_clause = " WHERE ";
		$where_params = array();
		foreach ($uniqueColumns as $colname => $value)
		{
			if ($count > 0 ) $where_clause .= " AND ";
			$where_clause .= "$colname = ?";
			$where_params[] = $value;
			$count++;
		}
		$sql .= $where_clause;
		$params = array_merge($params,$where_params);
		
		$db = $this->idsadmin->get_database($dbname, true);
		$stmt = $db->prepare($sql);
		$err = $db->errorInfo();
		if ($err[1] != 0) 
		{
			return array('RETURN_CODE' => $err[1], 'RETURN_MSG' => $err[2]);
		}
		
		$stmt->execute($params);
		$err = $db->errorInfo();
		$result = array('RETURN_CODE' => $err[1], 'RETURN_MSG' => $err[2]);
		
		// Requery the server for that row
		$qry  = "SELECT * ";
		$qry .= isset($uniqueColumns['rowid'])? ", rowid ":"";
		$qry .= "FROM '{$owner}'.{$tabname} ";
		$qry .= $where_clause;
		$dataResult = $this->doDatabaseWork($qry,$dbname,$where_params);
		
		/* Projection list (of the 'select *...' query above) containing multi-byte characters
		 * returns as un-intelligible data. Work-around is to query for column names from syscolumns.
		 * Encountered similar issue in the Schema Browser. Potentially a pdo_informix/ODBC driver bug.
		 * Cost: an extra query to the server and 16 lines of code containing 2 loops.
		 */
		$colnameQuery = "select colname from 'informix'.syscolumns, 'informix'.systables "
					  . "where 'informix'.systables.tabid = 'informix'.syscolumns.tabid and 'informix'.systables.tabname = '$tabname' "
					  . "and colname NOT IN ('ifx_insert_checksum','ifx_row_version','ifx_replcheck'" // ignore VERCOLS, CRCOLS, and REPLCHECK columns
					  . ((isset($uniqueColumns['ifx_erkey_1']))? "":",'ifx_erkey_1','ifx_erkey_2','ifx_erkey_3'")  // ignore ERKEY columns unless these are used to unqiuely identify the row
					  . ")";
		$colnameResult = $this->doDatabaseWork($colnameQuery,$dbname);
		$colnameArr = array();
		foreach ($colnameResult as $col)
		{
			array_push($colnameArr,$col['COLNAME']);
		}
	    
		if (isset($uniqueColumns['rowid']))
		{
			// add ROWID to colnameArr
			array_push($colnameArr,'ROWID');
		}
	    
		/* array jugglery as part of the workaround mentioned above, to combine the column names from syscolumns and 
		 * the column data from the 'select *...' query.
		 */
		$transformResult = array();
		foreach ($dataResult as $dataRow)
		{
			array_push($transformResult,array_combine($colnameArr,$dataRow));
		}
		$result['ROW'] = $transformResult;

		return $result;
	}
	
	/**
	 * Delete row on the table. 
	 * 
	 * @param $dbname database name
	 * @param $owner table owner name
	 * @param $tabname table name
	 * @param $uniqueColumns serialized array mapping column name to the value for the 
	 *           where clause of the delete statement.  
	 *           e.g.  array('COL1' => '100', 'COL2' => '200');
	 * @return return code/message from executing the delete statement
	 */
	function deleteRow($dbname,$owner,$tabname,$uniqueColumns)
	{
		$uniqueColumns = unserialize($uniqueColumns);
		
		if (count($uniqueColumns) == 0)
		{
			return array('RETURN_CODE' => -1, 
				'RETURN_MSG' => 'Cannot delete row on the database server because there are no unique columns for this table.');
		}
		
		$sql = "DELETE FROM '{$owner}'.{$tabname} WHERE ";
		$count = 0;
		$params = array();
		foreach ($uniqueColumns as $colname => $value)
		{
			if ($count > 0 ) $sql .= " AND ";
			$sql .= "$colname = ?";
			$params[] = $value;
			$count++;
		}		
		
		$db = $this->idsadmin->get_database($dbname, true);
		$stmt = $db->prepare($sql);
		$err = $db->errorInfo();
		if ($err[1] != 0) 
		{
			return array('RETURN_CODE' => $err[1], 'RETURN_MSG' => $err[2]);
		}
		
		$stmt->execute($params);
		$err = $db->errorInfo();
		$result = array('RETURN_CODE' => $err[1], 'RETURN_MSG' => $err[2]);
		return $result; 
	}
	
	/**
	 * do the database work.
	 *
	 */
	function doDatabaseWork($sel,$dbname="sysmaster",$params=array(),$sqltoolbox_user=true)
	{
		$ret = array();
		
		$db = $this->idsadmin->get_database($dbname, $sqltoolbox_user);

		while (1 == 1)
		{
			if (count($params) == 0)
        		$stmt = $db->query($sel);
			else {
				$stmt = $db->prepare($sel);
				$stmt->execute($params);
			}
			
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC) )
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

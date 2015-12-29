<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation, 2010.  All Rights Reserved
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

/* Services for trusted context feature */

class trustedContextServer {

	var $idsadmin;
	
	function __construct()
	{
		define ("ROOT_PATH","../../");

		include_once("../serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");

		require_once(ROOT_PATH."lib/idsadmin.php");
		$this->idsadmin = new idsadmin(true);
		$this->idsadmin->in = array("act" => "trustedContext");
		$this->idsadmin->load_lang("trustedContext");
		
		require_once(ROOT_PATH."lib/feature.php");
	}
	
	function getDatabases()
	{
		$sel = "SELECT trim(name) as database_name ";
		$sel .= " FROM sysdatabases " ;
		$sel .= " order by name " ;
		return $this->doDatabaseWork($sel);
	} 
	
	function getTrustedContexts($dbname,$rows_per_page, $page, $sort_col)
	{
		$ret = array();
		
		// Get the context ids of all that match the $rows_per_page, $page, and $sort_col parameters
		$sel = "SELECT a.contextid as id ";
		$sel .= " FROM systrustedcontext a " ;
		$sel .= " WHERE a.database == '{$dbname}' ";
		$rows = $this->doDatabaseWork($this->idsadmin->transformQuery($sel, $rows_per_page, $page, $sort_col),"sysuser");
		foreach ($rows as $row)
		{
			$list_of_ids .= $row['ID'] .",";
		}
		$list_of_ids = substr($list_of_ids,0,strlen($list_of_ids) - 1);
		
		// Now query for all the user and attribute data for trusted contexts that match those ids.
		$sel = "SELECT a.contextid as id, a.contextname, a.authid, a.defaultrole, a.enabled, b.encryption,";
		$sel .= " b.address, c.username, c.usertype, c.userrole, c.authreq";
		$sel .= " FROM systrustedcontext a, outer systcxattributes b, outer systcxusers c" ;
		$sel .= " WHERE a.contextid == b.contextid ";
		$sel .= " AND a.contextid == c.contextid ";
		$sel .= " AND a.database == '{$dbname}' ";
		$sel .= (strlen($list_of_ids) > 0) ? " AND a.contextid in ($list_of_ids) ":"";
		$rows = $this->doDatabaseWork($sel,"sysuser");
		
		$trusted_contexts = array();
		$users = array();
		$addresses = array();
		
		foreach ($rows as $row)
		{
			if(!isset($trusted_contexts[$row['ID']]))
			{
				if(!isset($row['DEFAULTROLE']) || $row['DEFAULTROLE'] == null)
				{
					$row['DEFAULTROLE'] = "";
				}
				$trusted_contexts[$row['ID']] = array('CONTEXTNAME' => $row['CONTEXTNAME'], 'AUTHID' => $row['AUTHID'], 
					'DEFAULTROLE' => $row['DEFAULTROLE'], 'ENABLED' => $row['ENABLED']);
				$trusted_contexts[$row['ID']]['USERS'] = array();
				$trusted_contexts[$row['ID']]['ATTRIBUTES'] = array();
			}
			
			if($row['USERNAME'] != null && !isset($users[$row['USERNAME']]))
			{
				$trusted_contexts[$row['ID']]['USERS'][] = array('USERNAME' => $row['USERNAME'], 'USERTYPE' => $row['USERTYPE'], 'USERROLE' => $row['USERROLE'], 'AUTHREQ' => $row['AUTHREQ']);
				$users[$row['USERNAME']] = true;
			}
			
			if($row['ADDRESS'] != null && !isset($addresses[$row['ADDRESS']]))
			{
				$trusted_contexts[$row['ID']]['ATTRIBUTES'][] = array('ADDRESS' => $row['ADDRESS'], 'ENCRYPTION' => $row['ENCRYPTION']);
				$addresses[$row['ADDRESS']] = true;
			}
			$trusted_contexts[$row['ID']]['USERS_NUMB'] = count($trusted_contexts[$row['ID']]['USERS']);
		}
		$tc_ctxts = array();
		foreach ($trusted_contexts as $trusted_context)
		{
			$tc_ctxts[] = $trusted_context;
		} 
		$ret['DATA'] = $tc_ctxts;
		
		// Now get count of all trusted contexts for this databse
		$ret['COUNT'] = 0;
		$countQuery = "select count(*) as count from systrustedcontext where database == '{$dbname}'";
		$temp = $this->doDatabaseWork($countQuery, "sysuser");
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}

		return $ret;
	} 
	
	function trustedContextAction ($sql, $dbname, $type)
	{
		$sql = trim($sql);
		$result = array();
		$result['TYPE'] = $type;
		$result['CODE'] = 0;
		$result['MESSAGE'] = "";
		$result['STATEMENT'] = $sql;
		
		try
		{
			$this->doDatabaseWork($sql, $dbname, true);
		}
		catch (PDOException $e)
		{ 
			$result['CODE'] = $e->getCode();
			$result['MESSAGE'] = $e->getMessage();
		}
		return $result;
	}
	
	function getRoles ($dbname)
	{
		$sql = "select username from sysusers where usertype = 'G' ";
		return $this->doDatabaseWork($sql,$dbname);
	}
	
	function getUsers ($dbname, $rows_per_page, $page, $sort_col)
	{
		$ret = array();
		
		$sql = "select username from sysusers where usertype <> 'G' ";
		$ret['DATA'] =  $this->doDatabaseWork($this->idsadmin->transformQuery($sql, $rows_per_page, $page, $sort_col),$dbname);
		
		$ret['COUNT'] = 0;
		$temp = $this->doDatabaseWork($this->idsadmin->createCountQuery($sql), $dbname);
		foreach ($temp as $row)
		{
			$ret['COUNT'] = $row['COUNT'];
		}
		
		return $ret;
	}
	
	/**
	 * do the database work.
	 *
	 */
	private function doDatabaseWork($sel,$dbname="sysmaster",$exceptions=false) 
	{
		$ret = array();

		$db = $this->idsadmin->get_database($dbname);

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

<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007, 2011.  All Rights Reserved
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

/**
 * Class for connection management
 *
 */
class connections {
	public $idsadmin;
	public $db;

	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;
		$this->set_db();
	} #end connections

	function set_db()
	{
		$dbfile=$this->idsadmin->get_config('CONNDBDIR')."/connections.db";
		if ( ! file_exists ($dbfile) )
		{
			$msg = "Cannot find connections db. ( {$dbfile} ). Have you run the install ? <br/>" .
                   "<a href='" . ROOT_PATH . "install/index.php'>Start the install</a>"; 
			$this->idsadmin->fatal_error($msg,false);
			return;
		}

		if ( ! is_readable($dbfile) )
		{
			$msg = "connections db ( {$dbfile} ) is not readable. Please check the permissions on this file.";
			$this->idsadmin->fatal_error($msg,false);
			return;
		}

		if ( ! is_writable($dbfile) )
		{
			$msg = "connections db ( {$dbfile} ) is not writable. Please check the permissions on this file.";
			$this->idsadmin->fatal_error($msg,false);
			return;
		}

		$this->db = new PDO ("sqlite:{$dbfile}");
		$this->db->setAttribute(PDO::ATTR_CASE,PDO::CASE_UPPER);
	}

	/**
	 * add a connection to the connections database.
	 *
	 * @param connection details -  $conn
	 * @return -1 for error / last insert id (ie. conn_num )
	 */
	function add_conn($conn)
	{
		// setup our sql statement.
		$ins_sql  = " insert into connections  ";
		$ins_sql .= " ( group_num  , host , port , server , idsprotocol, lat , lon , username , password ) ";
		$ins_sql .= " VALUES ";
		$ins_sql .= " ( :group_num , :host , :port , :server , :idsprotocol, :lat, :lon, :username, :password ) ";

		// prepare it
		$stmt = $this->db->prepare ($ins_sql);

		$x = $this->db->errorInfo();
		if ( $x[1] != 0 )
		{
			return -1;
		}

		//encode the password ready for storing in the connections.db
		$encoded_password = $this->encode_password( $conn['PASSWORD'] );

		// bind the values
		$stmt->bindParam ( ":group_num"  , $conn['GROUP_NUM'] );
		$stmt->bindParam ( ":host"       , $conn['HOST'] );
		$stmt->bindParam ( ":port"       , $conn['PORT'] );
		$stmt->bindParam ( ":server"     , $conn['SERVER'] );
		$stmt->bindParam ( ":idsprotocol", $conn['IDSPROTOCOL'] );
		$stmt->bindParam ( ":lat"        , $conn['LAT'] );
		$stmt->bindParam ( ":lon"        , $conn['LON'] );
		$stmt->bindParam ( ":username"   , $conn['USERNAME'] );
		$stmt->bindParam ( ":password"   , $encoded_password );

		// execute it ..
		$stmt->execute();
		$x = $this->db->errorInfo();
		if ( $x[1] != 0 )
		{
			return -1;
		}

		// return .
		return $this->db->lastInsertId();
	} #end add_conn

	/**
	 * update a connection in the connections.db
	 *
	 * @param connection information - $conn
	 * @return -1 for failure. 0 for success.
	 */
	function update_conn($conn)
	{
		//setup our sql
		$upd_sql  = " UPDATE connections SET ";
		$upd_sql .= " group_num = :group_num ";
		$upd_sql .= ",host      = :host ";
		$upd_sql .= ",port      = :port ";
		$upd_sql .= ",server    = :server ";
		$upd_sql .= ",idsprotocol= :idsprotocol ";
		$upd_sql .= ",lat       = :lat " ;
		$upd_sql .= ",lon       = :lon " ;
		$upd_sql .= ",username  = :username ";
		$upd_sql .= ",password  = :password ";
		$upd_sql .= " WHERE conn_num = :conn_num ";

		//prepare the statment
		$stmt = $this->db->prepare( $upd_sql );
		$x = $this->db->errorInfo();
		if ( $x[1] != 0 )
		{
			return -1;
		}

		//encode the password ready for storing in the connections.db
		$encoded_password = $this->encode_password( $conn['PASSWORD'] );

		// bind the values
		$stmt->bindParam ( ":group_num"  , $conn['GROUP_NUM'] );
		$stmt->bindParam ( ":host"       , $conn['HOST'] );
		$stmt->bindParam ( ":port"       , $conn['PORT'] );
		$stmt->bindParam ( ":server"     , $conn['SERVER'] );
		$stmt->bindParam ( ":idsprotocol", $conn['IDSPROTOCOL'] );
		$stmt->bindParam ( ":lat"        , $conn['LAT'] );
		$stmt->bindParam ( ":lon"        , $conn['LON'] );
		$stmt->bindParam ( ":username"   , $conn['USERNAME'] );
		$stmt->bindParam ( ":password"   , $encoded_password );
		$stmt->bindParam ( ":conn_num"   , $conn['CONN_NUM'] );

		// execute it ..
		$stmt->execute();
		$x = $this->db->errorInfo();
		if ( $x[1] != 0 )
		{
			return -1;
		}

		// return
		return 0;

	} #end update_conn

	/**
	 * encode the password before storing it in the connections db.
	 *
	 * @param String $password
	 * @return String  // the encoded password
	 */
	static function encode_password($password)
	{
		return $password;
		//return strrev($password);
	}

	/**
	 * decode the password after retrieving it from the connections db.
	 *
	 * @param String $password
	 * @return String  // the un-encoded password.
	 */
	static function decode_password($password)
	{
		return $password;
		//return strrev($password);
	}


	function edit_group($name,$num)
	{
	}#end edit_group

	function add_group($name,$num)
	{
	}#end add_group


	function getconnectionsforGroup($grpnum=1)
	{
		$sql = "select *,datetime(lastpingtime, 'unixepoch' , 'localtime') as lpt , datetime(lastpingtime, 'unixepoch' , 'localtime') as asof from connections where group_num = {$grpnum} and ( lat != '' or lon != '')  ";
		$stmt = $this->db->query($sql);
		header("Content-Type: text/xml");
		print ("<connections>");

		$rows = $stmt->fetchAll();

		foreach ($rows as $k=>$row)
		{
			if (! $row['LASTSTATUS'] )
			{
				$row['LASTSTATUS'] = 5;
			}

			if (! $row['LASTSTATUSMSG'])
			{
				$row['LASTSTATUSMSG'] = "Unknown";
			}

			if ( $row['LASTSTATUS'] == 1 )
			{
				$row['LASTSTATUSMSG'] = "Online";
			}

			if ( $this->idsadmin->phpsession->instance->get_servername() == $row['SERVER'] )
			{
				$row['LASTSTATUS']= 4 ;
				$row['LASTSTATUSMSG']="Online: CURRENT";
			}
			print ("<connection>\n");
			print ("<conn_num>{$row['CONN_NUM']}</conn_num>\n");
			print ("<host>{$row['HOST']}</host>\n");
			print ("<port>{$row['PORT']}</port>\n");
			print ("<server>{$row['SERVER']}</server>\n");
			print ("<idsprotocol>{$row['IDSPROTOCOL']}</idsprotocol>\n");
			print ("<lat>{$row['LAT']}</lat>\n");
			print ("<lon>{$row['LON']}</lon>\n");
			print ("<state>{$row['LASTSTATUS']}</state>\n");
			print ("<message>{$row['LASTSTATUSMSG']}</message>\n");
			print ("<lpt>{$row['LPT']}</lpt>\n");
			print ("<asof>{$row['ASOF']}</asof>\n");
			print ("</connection>\n");
		}

		print ("</connections>");
		die();
	}
}

?>

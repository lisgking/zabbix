<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007, 2012.  All Rights Reserved
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
 * This class forms a PDO connection to the Informix database server that OAT is
 * currently connected to.
 */
require_once(ROOT_PATH."lib/PDO_OAT.php");

class database extends PDO_OAT {
	 
	public $idsadmin = "";

	/******************************************
	 * Constructor:
	 *******************************************/
	function __construct(&$idsadmin,$dbname="sysmaster",$locale="",$username="",$password="",$throw_fatal_error=false)
	{
		$this->idsadmin=&$idsadmin;
		$this->idsadmin->load_lang("database");

		$host = $this->idsadmin->phpsession->instance->get_host();
		$port = $this->idsadmin->phpsession->instance->get_port();
		$protocol = $this->idsadmin->phpsession->instance->get_idsprotocol();
		$servername = $this->idsadmin->phpsession->instance->get_servername();
		 
		$envvars = $this->idsadmin->phpsession->instance->get_envvars();
		$conn_num = $this->idsadmin->phpsession->instance->get_conn_num();
		if (is_null($envvars) && $conn_num != "")
		{
			// If $envvars is null, this is the first connection after login,
			// so we need to get the env vars from the connections.db
			require_once ROOT_PATH."/lib/connections.php";
			$conndb = new connections($this->idsadmin);

			$sql  = "select envvar_name, envvar_value from conn_envvars where conn_num = $conn_num ";
			$stmt = $conndb->db->query($sql);

			$envvars = "";
			while ($row = $stmt->fetch())
			{
				$envvars .= "{$row['ENVVAR_NAME']}={$row['ENVVAR_VALUE']};";
				if (strcasecmp($row['ENVVAR_NAME'],"DELIMIDENT") == 0)
				{
					$this->idsadmin->phpsession->set_delimident($row['ENVVAR_VALUE']);
				}
			}

			// Store envvars in the session, so we don't have to requery each time
			$this->idsadmin->phpsession->set_envvars($envvars);
		}

		if ($username!="" && $password!="")
		{
			$user	= $username;
			$passwd	= $password;
		}else
		{
			$user   = $this->idsadmin->phpsession->instance->get_username();
			$passwd = $this->idsadmin->phpsession->instance->get_passwd();	
		}

		try {
			parent::__construct($idsadmin,$servername,$host,$port,$protocol,$dbname,$locale,$envvars,$user,$passwd);
		} catch(PDOException $e) {
			$idsadmin->load_lang("database");
			
			$idsadmin->get_config("INFORMIXDIR");
			$dsn = PDO_OAT::getDSN($servername,$host,$port,$protocol,$informixdir,$dbname,$locale,$envvars);
			
			$errorMessage = $e->getMessage();
			if (strpos($errorMessage,"-387") !== false)
			{
				$errorMessage = $idsadmin->lang('NoConnPermissionOnDB',array($user,$dbname)) . "<br/><br/>" . $errorMessage;
			}
			error_log("{$idsadmin->lang('ConnFailed')} DSN: {$dsn}. Error: {$errorMessage}");
                        
            if ( $idsadmin->render === false )
			{
				trigger_error("{$idsadmin->lang('ConnFailed')}  {$errorMessage}  DSN: {$dsn}",E_USER_ERROR);
			}

			if ($throw_fatal_error)
			{
				$idsadmin->fatal_error("{$errorMessage}<br/><br/>DSN: {$dsn}", "{$idsadmin->lang('ConnFailed')}");
			} else {
				$idsadmin->db_error("{$errorMessage}<br/><br/>DSN: {$dsn}", "{$idsadmin->lang('ConnFailed')}");
			}
			$idsadmin->html->render();
			die();
		}

		$this->idsadmin->phpsession->set_connected(true);

	}

}
?>

<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007 , 2009.  All Rights Reserved
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


class idsdb extends PDO {

	private $fetch_mode = PDO::FETCH_ASSOC;
	//static $xml =  array(PDO::ATTR_STATEMENT_CLASS,array("xmlStatement"));


	/* function __construct
	 * constructor
	 */

	function __construct($host,$port,$servername,$protocol="onsoctcp",$dbname="sysmaster",$user="",$passwd="" , $handleException = false)
	{

		putenv("INFORMIXCONTIME=3");
		putenv("INFORMIXCONRETRY=1");
		putenv("INFORMIXSERVER=blah");

		$informixdir= getenv("INFORMIXDIR");
		$dsn = <<<EOF
informix:host={$host};service={$port};database={$dbname};server={$servername};protocol={$protocol};
EOF;

		if ( substr(PHP_OS,0,3) != "WIN" )
		{
			$libsuffix = (strtoupper(substr(PHP_OS,0,3)) == "DAR")? "dylib":"so";
			$dsn .= ";TRANSLATIONDLL={$informixdir}/lib/esql/igo4a304.".$libsuffix;
			$dsn .= ";Driver={$informixdir}/lib/cli/libifdmr.".$libsuffix.";";
		}


		try {
			parent::__construct($dsn,$user,utf8_decode($passwd));
		} catch(PDOException $e) {
			if ( $handleException === false )
			{
			$err_str = "Connection Failed: DSN:{$dsn} ERROR:{$e->getMessage()}";
			trigger_error($err_str,E_USER_ERROR);
			}
			else
			{
				return null;
			}
		}

		 
	} #end ___construct

	/* function setFetchMode
	 * set the default setFetchMode
	 */

	function setFetchMode($fetchmode)
	{
		$this->fetch_mode = $fetchmode;
	} #end setFetchMode

	/* function query
	 * query function
	 */

	function query($sql,$params="",$handleException = false)
	{
		if ($sql=="") {
			$err_str = "Server: No SQL to execute";
			trigger_error($err_str,E_USER_ERROR);
		}

		if ($params == 1 )
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS,array("xmlStatement"));
		try {
			$stmt = parent::query($sql);
		} catch (PDOException $e)
		{
			if ( $handleException === false )
			{
			$err_str = "Query Failed: SQL: {$sql} ERROR:{$e->getMessage()}";
			trigger_error($err_str,E_USER_ERROR);
			}
			else
			{
				return null;
			}
		}
		
		if ($this->errorCode() != 0 || ($stmt==false) ) {
			$errstr=$this->errorInfo();
			$err_str = "Server : Query:<br/> {$sql} <br/> Error: {$errstr[1]} <br/> {$errstr[2]} <br/>";
			trigger_error($err_str,E_USER_ERROR);
			return null;
		}

		if ($stmt) {
			$stmt->setFetchMode($this->fetch_mode);
		}

		return $stmt;
	} #end query

} // end class idsdb

?>
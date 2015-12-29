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


class clusterdb extends PDO {

	private $fetch_mode = PDO::FETCH_ASSOC;

	/* function __construct
	 * constructor
	 */

	function __construct($is_ext = false)
	{
		$CONF = array();
		if ($is_ext)
		{
			require("../../../../conf/config.php");
		} 
		else 
		{
			require("../../conf/config.php");
		}
		
		if ( ! isset($CONF['CONNDBDIR']) )
		{
			$err_str = "Please check config.php param CONNDBDIR - it doesnt seem to be set.";
			error_log($err_str);
			trigger_error($err_str,E_USER_ERROR);
		}

		$file="{$CONF['CONNDBDIR']}/connections.db";
		if ( ! is_dir($CONF['CONNDBDIR']) )
		{
			$err_str = "Please check config.php param CONNDBDIR - it doesnt seem to be set to a directory.";
			error_log($err_str);
			trigger_error($err_str,E_USER_ERROR);
		}
		try {
			parent::__construct("sqlite:{$file}");
		} catch(PDOException $e) {
			error_log($file);
			error_log($e->getMessage());
			$err_str = "Connection Failed: ERROR:{$e->getMessage()}";
			trigger_error($err_str,E_USER_ERROR);
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

	function query($sql,$params="")
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
			return null;			
		}

		if ($stmt) {
			$stmt->setFetchMode($this->fetch_mode);
		}

		return $stmt;
	} #end query

} // end class clusterdb


class xmlStatement extends PDOStatement
{
/*
	function fetch()
	{
		 
		if (is_array($row))
		return $this->row_to_xml($row);
		 
		return "";
	}
*/
	function row_to_xml($array, $level=1) {
		$xml = "<row>\n";
		$cnt=0;
		foreach ($array as $key=>$value) {
			$value=trim($value);
			$colInfo = $this->getColumnMeta($cnt++);
			// print_r($colInfo);
			$xml .= "\t<{$key} ";
			foreach ($colInfo as $t => $v)
			$xml .= " {$t}=\"{$v}\" ";
			$xml .= ">{$value}</{$key}>\n";
		}
		$xml .= "</row>\n";
		return $xml;
	}

} //end class xmlStatement

?>
<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2012.  All Rights Reserved
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

class PDO_OAT extends PDO {
	 
	public $idsadmin = "";
	private $fetch_mode = PDO::FETCH_ASSOC;
	private $dbname = ""; 

	/******************************************
	 * Constructor:
	 *******************************************/
	function __construct(&$idsadmin,$servername,$host,$port,$protocol,$dbname="sysmaster",$locale="",$envvars=null,$username="",$password="")
	{
		$this->idsadmin=&$idsadmin;
		$this->idsadmin->load_lang("database");
		$this->dbname = $dbname;
		$informixdir = $this->idsadmin->get_config("INFORMIXDIR");
		$dsn = self::getDSN($servername,$host,$port,$protocol,$informixdir,$dbname,$locale,$envvars);
		
		putenv("INFORMIXCONTIME={$this->idsadmin->get_config("INFORMIXCONTIME",20)}");
		putenv("INFORMIXCONRETRY={$this->idsadmin->get_config("INFORMIXCONRETRY",3)}");

		parent::__construct($dsn,$username,utf8_decode($password));
	}
	
	static function getDSN ($servername,$host,$port,$protocol,$informixdir,$dbname="sysmaster",$locale="",$envvars=null)
	{
		$dsn = "informix:host={$host}";
		$dsn .= ";service={$port}";
		$dsn .= ";database={$dbname}";
		$dsn .= ";protocol={$protocol}";
		$dsn .= ";server={$servername}";
		
		if ( substr(PHP_OS,0,3) != "WIN" )
		{
			$libsuffix = (strtoupper(substr(PHP_OS,0,3)) == "DAR")? "dylib":"so";
			$dsn .= ";TRANSLATIONDLL={$informixdir}/lib/esql/igo4a304.".$libsuffix;
			$dsn .= ";Driver={$informixdir}/lib/cli/libifdmr.".$libsuffix.";";
		}

		if (!is_null($envvars) && $envvars != "" )
		{
			// add envvars to connection string
			$dsn .= ";$envvars";
		}

		if ( $locale != "" )
		{
			// CLIENT_LOCALE should always be UTF-8 version of databse locale
			$client_locale = substr($locale,0,strrpos($locale,".")) . ".UTF8";
			$dsn .= ";CLIENT_LOCALE={$client_locale};DB_LOCALE={$locale};";
		}
		
		return $dsn;
	}

	function query($sql="",$params=false,$throw_exceptions_only=false,$locale=null)
	{
		if ($sql=="")
		{
			if ( ! $this->idsadmin->render )
			{
				error_log("Error: no sql to run. ");

				trigger_error("Error: no sql to run. ");
			}
			$this->idsadmin->error("{$this->idsadmin->lang('NoSQLToExecute')}");
			return null;
		}
		
		if (is_null($locale))
		{
			$locl = $this->idsadmin->get_locale($this->dbname);
		} else {
			$locl = $locale;
		}

		if ( $params === true )
		{
			$this->setAttribute(PDO::ATTR_STATEMENT_CLASS,array("xmlOATStatement"));
		}
	    else if ( $locl == "" || strncasecmp($locl,'en_',3) === 0 )
	    {
		    $this->setAttribute(PDO::ATTR_STATEMENT_CLASS,array("PDOStatement"));
	    }
	    else
	    {
	    	$this->setAttribute(PDO::ATTR_STATEMENT_CLASS,array("localeOATStatement"));
	    }
		$stmt = parent::query($sql);
		
		if($throw_exceptions_only && ($this->errorCode() != 0 || ($stmt==false) ))
		{
			$errstr=$this->errorInfo();
			throw new PDOException( $errstr[2],$errstr[1]);
			die();
		}

		if ($this->errorCode() != 0 || ($stmt==false) )
		{
			$errstr=$this->errorInfo();
			error_log("{$this->idsadmin->lang('QueryF')}<br/> {$sql} <br/> {$this->idsadmin->lang('ErrorF')} {$errstr[1]} <br/> {$errstr[2]} <br/>");
			if ( ! $this->idsadmin->render )
			{
                 $encode_sql = htmlentities($sql);
                 error_log("{$this->idsadmin->lang('DatabaseQueryFailed')} {$this->idsadmin->lang('QueryF')} {$encode_sql} {$this->idsadmin->lang('ErrorF')} {$errstr[1]} {$errstr[2]} ");
                 trigger_error("{$this->idsadmin->lang('DatabaseQueryFailed')} - \n\n{$this->idsadmin->lang('ErrorF')} {$errstr[1]} {$errstr[2]} \n\n{$this->idsadmin->lang('QueryF')} {$encode_sql} \n\n ",E_USER_ERROR);
			}
			$this->idsadmin->db_error("{$this->idsadmin->lang('ErrorF')} {$errstr[1]} <br/> {$errstr[2]} <br/><br/>{$this->idsadmin->lang('QueryF')}<br/> {$sql} ");
			$this->idsadmin->html->render();
			die();
		}

		if ($stmt)
		{
			$stmt->setFetchMode($this->fetch_mode);
		}

		return $stmt;
	} // end query

	/**********************************
	 * setFetchMode
	 * set the default setFetchMode
	 **********************************/
	function setFetchMode($fetchmode)
	{
		$this->fetch_mode = $fetchmode;
	} // end setFetchMode
	 
	/******************************************
	 * Destruct:
	 *******************************************/
	function __destruct()
	{
		 
	} //end __destruct
	 
} // end database

class xmlOATStatement extends PDOStatement
{
	function fetch( $fetch_style = PDO::FETCH_ASSOC,
	                $cursor_orientation = PDO::FETCH_ORI_NEXT,
	                $cursor_offset = 0 )
	{
		 
		$row = parent::fetch($fetch_style, $cursor_orientation, $cursor_offset);

		if (is_array($row))
		return $this->row_to_xml($row);
		 
		return "";
	}
	function row_to_xml($array, $level=1) {
		$xml = '<row>';
		$cnt=0;
		foreach ($array as $key=>$value) 
		{
			$colInfo = $this->getColumnMeta($cnt++);
			$xml .= "\t<{$key}";
			foreach ($colInfo as $t => $v)
			{
				if ( $t == "native_type")
				{
					if ( stripos($v,"CHAR") === false )
					{
						$value = $value;
					}
					else
					{
						$value = trim($value);
						$value = "<![CDATA[{$value}]]>";
					}
				}
			}
			$xml .= ">{$value}</{$key}>";
		}
		
		$xml .= "</row>\n";
		return $xml;
	}

} //end xmOATlStatement

class localeOATStatement extends PDOStatement
{
	function fetch( $fetch_style = PDO::FETCH_ASSOC,
	                $cursor_orientation = PDO::FETCH_ORI_NEXT,
	                $cursor_offset = 0 )
	{
		$row = parent::fetch($fetch_style, $cursor_orientation, $cursor_offset);
		if (is_array($row))
		{
			return $this->nullTerminateCharCols($row);
		}
		return "";
	}
	
	/* We get a buffer from pdo_informix/ODBC with null-terminated strings. Unlike C, PHP strings are not
	 * null-terminated, hence the buffer needs to be truncated at the null value.
	 */
	function nullTerminateCharCols($array) {
		$cnt=0;
		foreach ($array as $key=>$value) 
		{
			$colInfo = $this->getColumnMeta($cnt++);
			foreach ($colInfo as $t => $v)
			{
				if ( $t == "native_type" && (stripos($v,"CHAR") === 0 || strncasecmp($v,"VARCHAR",7) === 0) ) //if and only if $v = 'CHAR', 'VARCHAR' or 'CHARACTER'
				{
					$needle = "\0";
					//if ( ($nullPos = strpos(utf8_decode($value),$needle)) !== false ) 
					if ( ($nullPos = strpos($value,$needle)) !== false )
					{
					    
						$array[$key] = substr($value , 0 , $nullPos+1);
					}
				}
			}
		}
		return $array;
	}

} //end localeOATStatement

?>

<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2010.  All Rights Reserved
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


class soapdb extends PDO {

      private $fetch_mode = PDO::FETCH_ASSOC;
      //static $xml =  array(PDO::ATTR_STATEMENT_CLASS,array("xmlStatement"));
      

/* function __construct
 * constructor
 */

      function __construct($host,$port,$servername,$protocol="onsoctcp",$dbname="sysmaster",$user="",$passwd="")
      {
	
	#$persist = array( PDO::ATTR_PERSISTENT => false);
	$persist = array( PDO::ATTR_PERSISTENT => true);
	putenv("INFORMIXCONTIME=3");
	putenv("INFORMIXCONRETRY=1");

$informixdir= getenv("INFORMIXDIR");
$dsn = <<<EOF
informix:host={$host};service={$port};database={$dbname};server={$servername};protocol={$protocol};
EOF;

      try {
          parent::__construct($dsn,$user,utf8_decode($passwd),$persist);
      } catch(PDOException $e) {
               throw new SoapFault("Connection Failed:","DSN:{$dsn} ERROR:{$e->getMessage()}");
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
               throw new SoapFault("Server","No SQL to execute");
          }

	  if ($params == 1 ) 
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS,array("xmlStatement"));
         try {
          $stmt = parent::query($sql);
         } catch (PDOException $e)
         {
               throw new SoapFault("Query Failed:","SQL: {$sql} ERROR:{$e->getMessage()}");
         }
          if ($this->errorCode() != 0 || ($stmt==false) ) {
               $errstr=$this->errorInfo();
               throw new SoapFault ("Server","Query:<br/> {$sql} <br/> Error: {$errstr[1]} <br/> {$errstr[2]} <br/>");
               return null;
          }

          if ($stmt) {
             $stmt->setFetchMode($this->fetch_mode);
          }

          return $stmt;
      } #end query

} // end class soapdb


class xmlStatement extends PDOStatement 
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

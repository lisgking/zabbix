<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007, 2009.  All Rights Reserved
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

/*********************************************************************
* OAT
*   
* Instance class
**********************************************************************/

class instance {
    
    public  $idsadmin;
    
    private $host;        /* the HOSTNAME to connect too */
    private $port;        /* the port to connect too  */
    private $servername;  /* the INFORMIXSERVER value */
    private $db;          /* the database name */
    private $idsprotocol; /* IDS protocol to use (e.g. onsoctcp) */
    private $username;    /* username to connect to the database */
    private $passwd;      /* user password for this instance */
    private $conn_num;    /* conn_num from the connections.db*/
    private $envvars;     /* connection env vars */
    private $delimident;  /* delimident env var */
    
   // private $is_primary;   /* is this server a primary */
   // private $is_secondary; /* is this server a secondary */
   // private $is_rss;       /* is this server a rss server */
   // private $is_sds;       /* is this server a sds server */
  

    
    function __construct(&$idsadmin,$host="",$port="",$servername="",$idsprotocol="onsoctcp",$db="sysmaster") {
        
        $this->idsadmin = $idsadmin;
        $this->set_host($host);
        $this->set_port($port);
        $this->set_idsprotocol($idsprotocol);
        $this->set_servername($servername);
        $this->set_db($db);       
        $this->set_conn_num("");
        $this->set_delimident("");    
    }
    
    function set_host($host)
    {
        $this->host = $host;
    }
    
    function get_host()
    {
        return $this->host;
    }
    
    function set_port($port)
    {
        $this->port = $port;
    }
    
    function get_port()
    {
        return $this->port;
    }
    
    function set_servername($servername)
    {
        $this->servername = $servername;
    }
    
    function get_servername()
    {
        return $this->servername;
    }
    
    function set_db($db)
    {
          // require_once("lib/database.php");
         //  $this->db[$db] = new Database();    
    }
    
    function get_db($db)
    {
        return $this->db[$db];
    }
    
    function set_idsprotocol($idsprotocol)
    {
        $this->idsprotocol = $idsprotocol;
    }
    
    function get_idsprotocol()
    {
        if ($this->idsprotocol == "")
        {
            // use onsoctcp as default protocol if none is set
            return "onsoctcp";
        }
        
        return $this->idsprotocol;
    }
    
    function set_username($username="")
    {
        if ( ( isset($this->idsadmin->in['act'] ) )
           &&( $this->idsadmin->in['act'] == "sqlwin" )
           &&( $this->idsadmin->get_config( 'SECURESQL' , "on" ) == "on" ) )
        {
        	$_SESSION['SQLTOOLBOX_USERNAME'] = $username;
        }
        else
        {
        	$this->username = $username ; 
        }
    }
    
    function get_username()
    {
        	return $this->username;
    }
    
    function set_passwd($passwd)
    {
        if ((isset($this->idsadmin->in['act']))&&($this->idsadmin->in['act'] == "sqlwin")&&($this->idsadmin->get_config('SECURESQL',"on") == "on")){
        	$_SESSION['SQLTOOLBOX_PASSWORD'] = $passwd;
        }else{
        	$this->passwd = $passwd ; 
        }       
    }
    
    function get_passwd()
    { 
        	return $this->passwd;
    }
    
    function set_envvars($envvars)
    {
        $this->envvars = $envvars;
    }
    
    function get_envvars()
    {
        return $this->envvars;
    }

    function set_delimident($delimident)
    {
        $this->delimident = $delimident;
    }
    
    function get_delimident()
    {
        return $this->delimident;
    }
    
    function set_conn_num($conn_num)
    {
        $this->conn_num = $conn_num;
    }
    
    function get_conn_num()
    {
        return $this->conn_num;
    }
    
//    function set_isprimary($state)
//    {
//        $this->is_primary = $state;
//    }
//    
//    function set_issecondary($state)
//    {
//        $this->is_secondary = $state;
//    }
//    
//    function set_isrss($state)
//    {
//        $this->is_rss = $state;
//    }
//    
//    function set_issds($state)
//    {
//        $this->is_sds = $state;
//    }
//    
//    function get_isprimary()
//    {
//        return $this->is_primary;
//    }
//    
//    function get_issecondary()
//    {
//        return $this->is_secondary;
//    }
//    
//    function get_isrss()
//    {
//        return $this->is_rss;
//    }
//    
//    function get_issds()
//    {
//        return $this->is_sds;
//    }
//    
//    function get_iscluster()
//    {
//        
//        
//    }
}
?>

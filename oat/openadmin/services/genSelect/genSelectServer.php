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


class genSelectServer {

    function __construct()
    {
        define ("ROOT_PATH","../../");
        define( 'IDSADMIN',  "1" );
        define( 'DEBUG', false);
        define( 'SQLMAXFETNUM' , 100 );
        
        include_once("../serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");
    }

    function doSelect($sel,$dbname="sysmaster")
    {
        require_once(ROOT_PATH."lib/idsadmin.php");
        $idsadmin = new idsadmin(true);
        
        $db = $idsadmin->get_database($dbname);
        
        $stmt = $db->query($sel, false);
      
        while ($row = $stmt->fetch() )
        {
            $ret[] = $row;
        }
        
        $err = $db->errorInfo();
        
        if ( $err[2] == 0 )
		{
			
			$stmt->closeCursor();
		}
		else
		{
			$err = "Error: {$err[2]} - {$err[1]}";
			$stmt->closeCursor();
			trigger_error($err,E_USER_ERROR);
			continue;
		}
     
        return $ret;
    } // end doSelect

}
?>

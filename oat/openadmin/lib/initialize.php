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

error_reporting  (E_ERROR | E_WARNING | E_PARSE);
define('ROOT_PATH', "./" );
;
define('IDSADMIN',  "1" );
define('DEBUG', false);
define("SQLMAXFETNUM", 100);

define('STANDARD',0);
define('PRIMARY',1);
define('SECONDARY',2);
define('SDS',3);
define('RSS',4);

set_error_handler("idsadmin_error_handler");

require_once("feature.php");
require_once("version.php");


/********************************************************************
 * idsadmin_error_handler
 *    error handling function.
 ********************************************************************/
function idsadmin_error_handler($errno, $errstr, $errfile, $errline)
{
    
    // dont report errors in the pear stuff..
    if (stripos ($errfile , "pear") > 0)
    return;
     
    global $idsadmin;
    switch($errno)
    {
        case E_NOTICE:
            return;
            $type = "E_NOTICE";
            if (stripos ($errstr , "Undefined") > 0)
            return;
             
            break;
        case E_ERROR:
            $type = "E_ERROR";
            break;
        case 2048:
            $type = "2048";
            return;
            break;
        case E_WARNING:

            $type = "E_WARNING";
             
            break;
        case E_PARSE:
            $type = "E_PARSE";
            break;
        default:
            $type = "UNKNOWN";
            break;
    }
     
    if ( $idsadmin instanceOf IDSAdmin )
    {
        $idsadmin->html->add_to_error("ERROR: {$type} {$errno} - {$errstr} <br/>");
        $idsadmin->html->add_to_error("has occurred in {$errfile} at line: {$errline}<br/>");
    }
    else
    {
        print ("ERROR: {$type} {$errno} - {$errstr} <br/>");
        print ("has occurred in {$errfile} at line: {$errline}<br/>");
    }
     
} #end idsadmin_error_handler

?>
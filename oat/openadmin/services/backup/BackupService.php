<?php
/*
*****************************************************************************

  (c) Copyright IBM Corporation. 2011.  All Rights Reserved

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

       http:www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.

*****************************************************************************
 */


    $ini = ini_set("soap.wsdl_cache_enabled","0");
    require_once("BackupServer.php");
    $server = new SoapServer("Backup.wsdl");
    $server->setClass("BackupServer");
    if (isset($HTTP_RAW_POST_DATA)) {
        $request = $HTTP_RAW_POST_DATA;
    } else {
        $request = file_get_contents('php://input');
    }

    $server->handle($request);
?>

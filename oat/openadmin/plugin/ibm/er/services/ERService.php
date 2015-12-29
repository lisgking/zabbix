<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation, 2008.  All rights reserved.
 ************************************************************************
 */


    $ini = ini_set("soap.wsdl_cache_enabled","0");
    require_once("ERServer.php");
    $server = new SoapServer("ER.wsdl");
    $server->setClass("ERServer");
    if (isset($HTTP_RAW_POST_DATA)) {
        $request = $HTTP_RAW_POST_DATA;
    } else {
        $request = file_get_contents('php://input');
    }

    $server->handle($request);
?>

<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation, 2010.  All rights reserved.
 ************************************************************************
 */


    $ini = ini_set("soap.wsdl_cache_enabled","0");
    require_once("CMServer.php");
    $server = new SoapServer("CM.wsdl");
    $server->setClass("CMServer");
    if (isset($HTTP_RAW_POST_DATA)) {
        $request = $HTTP_RAW_POST_DATA;
    } else {
        $request = file_get_contents('php://input');
    }

    $server->handle($request);
?>

<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2009.  All rights reserved.
 **********************************************************************/

	$ini = ini_set("soap.wsdl_cache_enabled","0");
    $classmap = array("databaseInfo" => "databaseInfo" , "tableInfo" => "tableInfo");
	$server = new SoapServer("schemamgr.wsdl" , array('classmap' => $classmap));
	require_once("schemamgrServer.php");
	$server->setClass("schemamgrServer");

	if (isset($HTTP_RAW_POST_DATA))
	{
	    $request = $HTTP_RAW_POST_DATA;
	}
	else
	{
	    $request = file_get_contents('php://input');
	}

	$server->handle($request);
?>

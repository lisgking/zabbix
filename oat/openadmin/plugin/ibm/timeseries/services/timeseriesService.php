<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2011.  All rights reserved.
 **********************************************************************/

	$ini = ini_set("soap.wsdl_cache_enabled","0");
	$server = new SoapServer("timeseries.wsdl");
	require_once("timeseriesServer.php");
	$server->setClass("timeseriesServer");

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

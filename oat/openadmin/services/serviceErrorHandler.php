<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007 , 2009.  All Rights Reserved
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

/*
 * When soapFault is returned to Flash / Flex , it is unable to process the SOAP
 * fault as the server responds with a http status code 500 ( Server Error ) . 
 * Flash has no way of knowing if this was a real server error or not and doesnt
 * read the response from SOAP .  This makes sending meaningful errors back to flash / flex
 * impossible ( till there is an option within PHP Soap to set the status code on a soap fault )
 * 
 * This is a work-around by setting an error Handler for the 'Server' of the service 
 * and the error handler returning a SOAP fault message when that handler is invoked.
 * So instead of using SoapFault - the php function trigger_error is used instead ..
 */

function serviceErrorHandler($errno, $errstr, $errfile, $errline)
{
	if ( $errno == E_NOTICE )
	{
		return;
	}
	
	if ( $errno != E_USER_WARNING )
	{
		$faultString = "<faultstring>{$errno} : {$errstr} - {$errfile} - {$errline}</faultstring>"; 
	}
	else
	{
		$faultString = "<faultstring>{$errstr}</faultstring>"; 		
	}
echo <<< EOF
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">
<SOAP-ENV:Body>
<SOAP-ENV:Fault>
<faultcode>$errno</faultcode> 
{$faultString}
</SOAP-ENV:Fault>
</SOAP-ENV:Body>
</SOAP-ENV:Envelope>
EOF;
error_log($faultString);
exit(1);
return true;
}
?>

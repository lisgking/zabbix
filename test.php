<?php
        $soapUrl = "http://172.24.18.135:8080/openadmin/services/dashboard/dashboardService.php"; // asmx URL of WSDL
        
        $xml_post_string = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <SOAP-ENV:Body SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <typens:getCpu xmlns:typens="urn:DashBoard"/>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';
        
        $arr = array(
            'location' => $soapUrl,
            'uri'      => $soapUrl
        );
        
	 $soapClient = new SoapClient('http://172.24.18.135:8080/openadmin/services/dashboard/dashboard.wsdl', $arr);
	 $soapClient->getCpu();
	// $r = $soapClient->__soapCall('getCpu', array($xml_post_string));
?>
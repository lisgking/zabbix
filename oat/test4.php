<?php 

    require 'SoapClientAuth.php';
    
   
        //$soapUrl = "http://172.24.18.135:8080/openadmin/services/dashboard/dashboard.wsdl"; // asmx URL of WSDL
        $soapUrl = "http://127.0.0.1:8080/openadmin/services/dashboard/dashboard.wsdl"; // asmx URL of WSDL
        /* $soapUser = "username";  //  username
        $soapPassword = "password"; // password */

        // xml post structure
        $xml_post_string = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <SOAP-ENV:Body SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    <typens:getCpu xmlns:typens="urn:DashBoard"/>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';   // data from the form, e.g. some ID number
       
        echo "addddddaa";
        $s = new SoapClientAuth($soapUrl, array('host'=>'127.0.0.1:8080','service'=>'fd', 'location'=> "http://172.24.18.135:8080/openadmin/services/dashboard/dashboardService.php", 'uri'=>'http://172.24.18.135:8080/openadmin/services/dashboard/dashboardService.php'));
        $r = $s->getCpu();
        echo $r;

    ?>
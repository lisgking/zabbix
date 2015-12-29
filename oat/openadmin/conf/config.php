<?php 
$CONF['LANG']="en_US";#The default language for the OAT pages.
$CONF['BASEURL']="http://observable:8080/openadmin";#The URL where OAT is installed in this format: http://servername:port/location.
$CONF['HOMEDIR']="D:/Program Files/IBM Informix Client SDK/OAT/Apache_2.2.22/htdocs/openadmin/";#The directory for the OAT installation.
$CONF['CONNDBDIR']="d:\\Program Files\\IBM Informix Client SDK\\OAT\\OAT_conf";#The directory for the OAT connections database. Specify a secure directory that is not under the document directory for the web server.
$CONF['HOMEPAGE']="welcome";#Home page for OAT
$CONF['PINGINTERVAL']="300";#The length of time (in seconds) between updates of the server status. The server status is shown on the Health Center > Dashboard > Group Summary page.
$CONF['ROWSPERPAGE']="25";#The default number of rows per page to display when data is shown in a table format.
$CONF['SECURESQL']="on";#Require login credentials for the SQL ToolBox.
$CONF['INFORMIXCONTIME']="20";#The length of time (in seconds) that OAT attempts to connect to the database server before returning an error (INFORMIXCONTIME).
$CONF['INFORMIXCONRETRY']="3";#The number of times that OAT attempts to connect to the database server during the Informix connect time (INFORMIXCONRETRY).
?>

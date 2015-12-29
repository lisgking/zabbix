<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2008, 2012.  All rights reserved.
 ************************************************************************
 */



class template_er {

    public $idsadmin;

    function error($err="")
    {
        $HTML = "";

        if ($err)
        {
           $HTML .= $this->idsadmin->template["template_global"]->global_error($err);
        }
        return $HTML;
    }

    function renderER($do,$lang="en_US")
    {
    	
    	// Determine if we are connected to a leaf node
    	$qry="select isleaf from syscdrs where cnnstate = 'L'";
    	$db = $this->idsadmin->get_database("sysmaster");
        $stmt = $db->query($qry);
        $leafNode = "N";
        while ($res = $stmt->fetch())
        {
            $leafNode = $res['ISLEAF'];
        }
        
        // Determine server groupname
        $servName = $this->idsadmin->phpsession->instance->get_servername();
        $qry="select svrgroup from syssqlhosts where dbsvrnm='{$servName}'";
        $stmt = $db->query($qry);
		$row = $stmt->fetch();
		$stmt->closeCursor();
		$groupName = trim($row['SVRGROUP']);
    	
        $url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?" . $_SERVER['QUERY_STRING'];
    	$flashvars = "servername={$this->idsadmin->phpsession->instance->get_servername()}" 
    		. "&conn_num={$this->idsadmin->phpsession->instance->get_conn_num()}"
    		. "&state={$do}"
    		. "&serverVersion={$this->idsadmin->phpsession->serverInfo->getVersion()}"
    		. "&readonly=" . (($this->idsadmin->isreadonly())? "true":"false")
    		. "&default_rows_per_page={$this->idsadmin->get_config('ROWSPERPAGE', 25)}"
    		. "&svrGroupName={$groupName}"
    		. "&isLeaf=" . (($leafNode == "Y")? "true":"false")
    		. "&url={$url}";
    		
    	$resourceModuleURLS = "plugin/ibm/er/swfs/ER_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
    	if ($lang == "en_US")
    	{
    	    $localeChain = "en_US";
    	} else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",plugin/ibm/er/swfs/ER_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";  
    	}
    	$flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
        
        $HTML = "";
        $HTML .= <<< EOF
 
<script type="text/javascript"> 
function switchServerThroughTopology(connectionNum, serverName, doParameter)
{
	//Adding a new entry in the drop down list
	var newServer = document.createElement("OPTION");
	newServer.text = serverName;
	newServer.value = connectionNum;
	document.serverswitch.conn_num.options.add(newServer);
	
	// Set the drop-down list's value to the desired connection number
	document.serverswitch.conn_num.value = connectionNum;

	// Redirect to ER's node summary page
	document.serverswitch.action = 'index.php?act=login&do=loginnopass&ract=ibm/er/er&rdo=' + doParameter;

	// Refresh the page 
	document.serverswitch.submit();
}
</script>
	

		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="er" 
				width="100%" 
				height="720"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="plugin/ibm/er/swfs/ER.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
            <param name="flashvars" 
                value="{$flashvars}" />
        	
        	<embed src="plugin/ibm/er/swfs/ER.swf" 
    	    	quality="high" bgcolor="#869ca7"
				width="100%" 
       		 	height="720" 
        		name="er"
        		flashvars="{$flashvars}"
				align="middle" 
				play="true" 
				loop="false" 
				quality="high"
				wmode="transparent"
				allowScriptAccess="sameDomain" 
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer">
			</embed> 
		</object>


EOF;
return $HTML;
    } // end of renderER()
    
    function renderGridRepl($do,$lang="en_US")
    {	
        $url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?" . $_SERVER['QUERY_STRING'];
    	$flashvars = "servername={$this->idsadmin->phpsession->instance->get_servername()}" 
    		. "&conn_num={$this->idsadmin->phpsession->instance->get_conn_num()}"
    		. "&state={$do}"
    		. "&serverVersion={$this->idsadmin->phpsession->serverInfo->getVersion()}"
    		. "&readonly=" . (($this->idsadmin->isreadonly())? "true":"false")
    		. "&default_rows_per_page={$this->idsadmin->get_config('ROWSPERPAGE', 25)}"
    		. "&url={$url}";
    		
    	$resourceModuleURLS = "plugin/ibm/er/swfs/GridRepl_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
    	if ($lang == "en_US")
    	{
    	    $localeChain = "en_US";
    	} else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",plugin/ibm/er/swfs/GridRepl_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";  
    	}
    	$flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
        
        $HTML = "";
        $HTML .= <<< EOF

		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="er" 
				width="100%" 
				height="720"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="plugin/ibm/er/swfs/GridRepl.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
            <param name="flashvars" 
                value="{$flashvars}" />
        	
        	<embed src="plugin/ibm/er/swfs/GridRepl.swf" 
    	    	quality="high" bgcolor="#869ca7"
				width="100%" 
       		 	height="720" 
        		name="er"
        		flashvars="{$flashvars}"
				align="middle" 
				play="true" 
				loop="false" 
				quality="high"
				wmode="transparent"
				allowScriptAccess="sameDomain" 
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer">
			</embed> 
		</object>


EOF;
return $HTML;
    } // end of renderGridRepl()
}
?>

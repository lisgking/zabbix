<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2011, 2012.  All rights reserved.
 ************************************************************************
 */


class template_ucm {

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

    function renderUCM($lang="en_US", $ifmx_dir, $ifmx_serv, $os_name)
    {
 
		$url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?" . $_SERVER['QUERY_STRING'];

		$flashvars = "servername={$this->idsadmin->phpsession->instance->get_servername()}"
        . "&state={$do}"
        . "&serverVersion={$this->idsadmin->phpsession->serverInfo->getVersion()}"
        . "&readonly=" . (($this->idsadmin->isreadonly())? "true":"false")
        . "&default_rows_per_page={$this->idsadmin->get_config('ROWSPERPAGE', 25)}"
        . "&delimident={$this->idsadmin->phpsession->instance->get_delimident()}"
		. "&url={$url}"
		. "&ifmx_dir={$ifmx_dir}"
		. "&ifmx_serv={$ifmx_serv}"
		. "&os_name={$os_name}";

		$resourceModuleURLS = "plugin/ibm/er/swfs/connectionManager_en_US.swf,plugin/ibm/er/swfs/ER_en_US.swf,plugin/ibm/er/swfs/GridRepl_en_US.swf,swfs/Mach11/Mach11_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
		if ($lang == "en_US")
		{
			$localeChain = "en_US";
		} else {
			$localeChain = "{$lang},en_US";
			$resourceModuleURLS .= ",plugin/ibm/er/swfs/connectionManager_{$lang}.swf,plugin/ibm/er/swfs/ER_{$lang}.swf,plugin/ibm/er/swfs/GridRepl_{$lang}.swf,swfs/Mach11/Mach11_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";
		}
		$flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";

        $HTML = "";
        $HTML .= <<< EOF
        
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="cm" 
				width="100%" 
				height="600"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="plugin/ibm/er/swfs/connectionManager.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
        	<param name="flashvars" value="{$flashvars}" />
        	
        	<embed src="plugin/ibm/er/swfs/connectionManager.swf" 
    	    	quality="high" bgcolor="#869ca7"
				width="100%" 
       		 	height="600" 
        		name="cm"
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
    }
}
?>

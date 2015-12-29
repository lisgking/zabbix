<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2009, 2012.  All rights reserved.
 **********************************************************************/

class template_schemamgr {

    public $idsadmin;

    function error($err="")
    {
        if ($err)
        {
            $HTML .= $this->idsadmin->template["template_global"]->global_error($err);
        }

        return $HTML;
    }

    function render($lang="en_US")
    {
        $height = 685;
        $wmode = 'wmode="transparent"';

        if ( preg_match('/FireFox/i' , $_SERVER['HTTP_USER_AGENT'] ) )
        {
            if (preg_match("#(Firefox)[/ ]?([0-9.]*)#", $_SERVER['HTTP_USER_AGENT'], $info))
            {
                $version = $info[2] ;
                if ( $version >= 3 && $version < 3.2 )
                {
                    $wmode = "";
                }
            }
        }

        $flashvars = "servername={$this->idsadmin->phpsession->instance->get_servername()}"
        . "&state={$do}"
        . "&serverVersion={$this->idsadmin->phpsession->serverInfo->getVersion()}"
        . "&readonly=" . (($this->idsadmin->isreadonly())? "true":"false")
        . "&default_rows_per_page={$this->idsadmin->get_config('ROWSPERPAGE', 25)}"
        . "&isPrimary=" . (($this->idsadmin->phpsession->serverInfo->isPrimary())? "true":"false")
        . "&delimident={$this->idsadmin->phpsession->instance->get_delimident()}"
        . "&currentUser={$this->idsadmin->phpsession->instance->get_username()}"
        . "&adtrows={$this->get_adtrows()}"
        . "&crMartState={$this->get_create_datamart_state()}"
        . "&hasGrid=" . (($this->idsadmin->phpsession->serverInfo->isServerInGrid($this->idsadmin))? "true":"false")
        . "&gridList=" . implode(",",$this->idsadmin->phpsession->serverInfo->getGridsForServer($this->idsadmin, false));
        
        if (isset($this->idsadmin->in['dbname']))
        {
        	$flashvars .= "&dbname=" . $this->idsadmin->in['dbname'];
        }
        if (isset($this->idsadmin->in['dmname']))
        {
        	$flashvars .= "&dmname=" . $this->idsadmin->in['dmname'];
        }

    	$resourceModuleURLS = "plugin/ibm/schemamgr/swfs/schemamgr_en_US.swf,swfs/qbe/qbe_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
        if ($lang == "en_US")
    	{
    	    $localeChain = "en_US";
    	} else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",plugin/ibm/schemamgr/swfs/schemamgr_{$lang}.swf,swfs/qbe/qbe_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";  
    	}
    	$flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";

        $HTML = "";
        $HTML .= <<< EOF

		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="schemamgr"
				width="100%"
				height="{$height}"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="plugin/ibm/schemamgr/swfs/schemamgr.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
        	<param name="allowFullScreen" value="true" />
        	 <param name="flashvars"
                value="{$flashvars}"/>

        	<embed src="plugin/ibm/schemamgr/swfs/schemamgr.swf"
    	    	quality="high" bgcolor="#869ca7"
				width="100%"
       		 	height="{$height}"
        		name="schemamgr"
        		flashvars="{$flashvars}"
				align="middle"
				play="true"
				loop="false"
				quality="high"
				{$wmode}
				allowFullScreen="true"
				allowScriptAccess="sameDomain"
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer">
			</embed>
		</object>
EOF;
				return $HTML;
    }
    
    /**
     * Get the audit configuration of the database server.
     * (adtrows indicates whether selective row-level auditing is on).
     */
    private function get_adtrows() 
    {
    	$adtrows = 0;
    	require_once ROOT_PATH."lib/feature.php";
    	if ( Feature::isAvailable(Feature::PANTHER, $this->idsadmin->phpsession->serverInfo->getVersion()) )
    	{
	    	$sysmaster = $this->idsadmin->get_database("sysmaster");
	    	$qry = "select adtrows from sysadtinfo";
	    	$stmt = $sysmaster->query($qry);
	    	while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
	    	{
	    		$adtrows = $res['ADTROWS'];
	    	}
    	}
    	return $adtrows;
    }
    
    private function get_create_datamart_state()
    {
        $sysadmin = $this->idsadmin->get_database("sysadmin");
        $qry = "select value from ph_threshold where name = 'OAT IWA create mart workload state'";
        $stmt = $sysadmin->query($qry);
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
        {
            $value = $res['VALUE'];
        }
        return $value;    
    }    
}
?>

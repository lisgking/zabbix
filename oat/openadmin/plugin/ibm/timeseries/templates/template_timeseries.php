<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2011, 2012.  All rights reserved.
 **********************************************************************/

class template_timeseries {

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
        . "&delimident={$this->idsadmin->phpsession->instance->get_delimident()}";

        $resourceModuleURLS = "plugin/ibm/timeseries/swfs/timeseries_en_US.swf,plugin/ibm/timeseries/swfs/schemamgr_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf,swfs/storage/storage_en_US.swf";
        if ($lang == "en_US")
        {
    	    $localeChain = "en_US";
        } else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",plugin/ibm/timeseries/swfs/timeseries_{$lang}.swf,plugin/ibm/timeseries/swfs/schemamgr_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf,swfs/storage/storage_{$lang}.swf";
        }
        $flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";

        $HTML = "";
        $HTML .= <<< EOF

		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="timeseries"
				width="100%"
				height="{$height}"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="plugin/ibm/timeseries/swfs/timeseries.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
        	<param name="allowFullScreen" value="true" />
        	 <param name="flashvars"
                value="{$flashvars}"/>

        	<embed src="plugin/ibm/timeseries/swfs/timeseries.swf"
    	    	quality="high" bgcolor="#869ca7"
				width="100%"
       		 	height="{$height}"
        		name="timeseries"
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
    
  }
?>

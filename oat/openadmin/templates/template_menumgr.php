<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2008, 2011.  All Rights Reserved
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

class template_menumgr {

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

    function renderMenuManager($lang="en_US")
    {
    	$resourceModuleURLS = "swfs/menumgr/menumgr_en_US.swf,../swfs/lib/oat_en_US.swf,../swfs/lib/rdfwidgets_en_US.swf";
    	if ($lang == "en_US")
    	{
    		$localeChain = "en_US";
    	} else {
    		$localeChain = "{$lang},en_US";
    		$resourceModuleURLS .= ",swfs/menumgr/menumgr_{$lang}.swf,../swfs/lib/oat_{$lang}.swf,../swfs/lib/rdfwidgets_{$lang}.swf";
    	}
    	$flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
    	 
        $HTML = "";
        $HTML .= <<< EOF
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="menumgr" 
				width="100%" 
				height="500"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="swfs/menumgr/menumgr.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
        	<param name="flashvars" value="{$flashvars}" />
        	
        	<embed src="swfs/menumgr/menumgr.swf" 
    	    	quality="high" 
    	    	bgcolor="#869ca7"
				width="100%" 
       		 	height="500" 
        		name="menumgr"
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

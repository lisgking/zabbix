<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2010, 2012.  All Rights Reserved
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


class template_storage {

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

    function render_storage($lang="en_US", $mirrorEnabled="0")
    {
		$height = 685;
		
		$flashvars = "servername={$this->idsadmin->phpsession->instance->get_servername()}"
        . "&serverVersion={$this->idsadmin->phpsession->serverInfo->getVersion()}"
        . "&readOnly=" . (($this->idsadmin->isreadonly())? "true":"false")
        . "&default_rows_per_page={$this->idsadmin->get_config('ROWSPERPAGE', 25)}"
        . "&isPrimary=" . (($this->idsadmin->phpsession->serverInfo->isPrimary())? "true":"false")
        . "&mirrorEnabled=" . $mirrorEnabled;

        $resourceModuleURLS = "swfs/storage/storage_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
        if ($lang == "en_US")
        {
    	    $localeChain = "en_US";
        } else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",swfs/storage/storage_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";
        }
        $flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
         
        $HTML = "";
        $HTML .= <<< EOF
        
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="storage" 
				width="100%"
				height="{$height}"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="swfs/storage/storage.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
        	<param name="flashvars" value="{$flashvars}" />
        	
        	<embed src="swfs/storage/storage.swf" 
    	    	quality="high" bgcolor="#869ca7"
				width="100%" 
       		 	height="{$height}" 
        		name="storage"
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

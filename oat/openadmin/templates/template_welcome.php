<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2012.  All Rights Reserved
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


class template_welcome {

    public $idsadmin;

    function render_welcome($home_page="welcome", $is_admin=false, $lang="en_US")
    {
		$height = (($is_admin)? 400:600);
		
		$flashvars = "homePage={$home_page}"
			. "&is_admin=" . (($is_admin)? "true":"false")
			. "&readOnly=" . (($this->idsadmin->isreadonly())? "true":"false")
			. "&secureSQLToolbox=" . $this->idsadmin->get_config("SECURESQL");

		$path_prefix = "";
		if ($is_admin)
		{
			$path_prefix = "../";
		} 
		
        $resourceModuleURLS = "{$path_prefix}swfs/welcome/welcome_en_US.swf,{$path_prefix}swfs/lib/oat_en_US.swf";
        if ($lang == "en_US")
        {
    	    $localeChain = "en_US";
        } else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",{$path_prefix}swfs/welcome/welcome_{$lang}.swf,{$path_prefix}swfs/lib/oat_{$lang}.swf";
        }
        $flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
    	           
        $HTML = "";
        $HTML .= <<< EOF
        
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="welcome" 
				width="100%"
				height="{$height}"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="{$path_prefix}swfs/welcome/welcome.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
        	<param name="flashvars" value="{$flashvars}" />
        	
        	<embed src="{$path_prefix}swfs/welcome/welcome.swf" 
    	    	quality="high" bgcolor="#869ca7"
				width="100%" 
       		 	height="{$height}" 
        		name="trustedContext"
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

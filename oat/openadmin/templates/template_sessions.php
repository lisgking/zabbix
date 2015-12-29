<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007, 2012.  All Rights Reserved
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


class template_sessions {
    
    public $idsadmin;
    
    function header($lang="en_US")
    {
        $flashvars = "loc={$this->idsadmin->get_config("BASEURL")}"
        	. "&default_rows_per_page={$this->idsadmin->get_config('ROWSPERPAGE',25)}";
        $resourceModuleURLS = "swfs/sessions/sessions_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
        if ($lang == "en_US")
        {
    	    $localeChain = "en_US";
        } else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",swfs/sessions/sessions_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";
        }
        $flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
        
        $HTML = "";
        
     $HTML .= <<< EOF
<div id="sessionExplorer" style="width:100%;height:100%">
<table style="width:100%;height:100%">
<tr>
<td valign="top" style="width:100%;height:100%" >
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="sessexp" 
				width="100%" 
				height="500"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="swfs/sessions/sessions.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#D2DCE5" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="flashvars" value="{$flashvars}"/>
        	<embed src="swfs/sessions/sessions.swf" 
    	    	quality="high" bgcolor="#D2DCE5"
				width="100%" 
       		 	height="500" 
        		name="sessexp"
        		flashvars="{$flashvars}"
				align="top" 
				play="true" 
				loop="false" 
				quality="high"
				allowScriptAccess="sameDomain" 
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer">
			</embed> 
		</object>
</td>
</tr>
</table>
</div>
EOF;
        return $HTML;
    }
    
    function picker()
    {
        $HTML = "";
        
     $HTML .= <<< EOF
<div id="dash" style="width:100%;height:100%;background-color:#ff0000">
<table style="width:100%;height:100%;background-color:#0000ff">
<tr>
<td>
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="picker" 
				width="100%" 
				height="500"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="swfs/Dashboard/Picker.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	
        	<embed src="swfs/Dashboard/Picker.swf" 
    	    	quality="high" bgcolor="#869ca7"
				width="100%" 
       		 	height="500" 
        		name="picker"
        		flashvars=""
				align="middle" 
				play="true" 
				loop="false" 
				quality="high"
				allowScriptAccess="sameDomain" 
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer">
			</embed> 
		</object>
</td>
</tr>
</table>
</div>
EOF;
        return $HTML;
    }

    function end()
    {
        $HTML = "";
        $HTML .= <<<EOF
        
EOF;
        return $HTML;
    }

}

?>

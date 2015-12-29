<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007.  All Rights Reserved
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


class template_Charts {

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

    function renderChart()
    {
        $HTML = "";
        $HTML .= <<< EOF
  
	
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="{$this->idsadmin->Charts->getId()}" 
				width="{$this->idsadmin->Charts->getWidth()}" 
				height="{$this->idsadmin->Charts->getHeight()}"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="swfs/Charts/{$this->idsadmin->Charts->getSWF()}.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#C2D0DD" />
        	<param name="allowScriptAccess" value="sameDomain" />
		     <param name="wmode" value="transparent" />
        	<param name="flashvars" value="{$this->idsadmin->Charts->getFlashVars()}"/>
        	
        	<embed src="swfs/Charts/{$this->idsadmin->Charts->getSWF()}.swf" 
    	    	quality="high" bgcolor="#C2D0DD"
				width="{$this->idsadmin->Charts->getWidth()}" 
       		 	height="{$this->idsadmin->Charts->getHeight()}" 
        		name="{$this->idsadmin->Charts->getId()}"
        		flashvars="{$this->idsadmin->Charts->getFlashVars()}"
				align="middle" 
				play="true" 
				 wmode = "transparent"
				loop="false" 
				quality="high"
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

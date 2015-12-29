<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007, 2011.  All Rights Reserved
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


class output {
    
    private $toRender;
    private $pageTitle;
    private $pageFooter;
    private $lang = "en_US";
    
    function __construct()
    {
        $this->toRender="";
    }
    
   function httpheader()
    {
        @header("HTTP/1.0 200 OK");
        @header("HTTP/1.1 200 OK");
        @header("Content-type: text/html");
        @header("Cache-control: no-cache, must-revalidate, max-age=0");
        @header("Expires: Wed, 01 Mar 2006 00:00:00 GMT");
        @header("Pragma: no-cache");
    }
    
    function setPageTitle($str)
    {
        $this->pageTitle=$str;
    }
    
    function getPageTitle()
    {
        return $this->pageTitle;
    }
    
    function setPageFooter($str)
    {
        $this->pageFooter=$str;
    }
    
    function getPageFooter()
    {
        return $this->pageFooter;
    }
        
    function setLang($l)
    {
        $this->lang=$l;
    }
    
    function getLang()
    {
        return $this->lang;
    }
    
    function add_to_output($str)
    {
        $this->toRender .= $str;
    } // end add_to_output
    
    /******************************************
     * get_html_lang:
     *  Get the setting for the HTML lang attribute.
     *  The HTML lang attribute only needs the primary
     *  language code (i.e. "en" instead of "en_US).
     *******************************************/
    public function get_html_lang() 
    {
    	$lang_arr = preg_split("/_/",$this->lang);
    	return $lang_arr[0];
    }
        
    function render()
    {
        $css = <<< EOF
<style type="text/css" media="all">
        @import url(../templates/style.css);
</style>
EOF;
	
		$header = <<< EOF
<div id="logo">
<table width="100%" height=55>
	<tr>
		<td width="70%">
		</td>
		<td>
		</td>
	</tr>
</table>
</div>
EOF;

        $this->httpheader();
    
        $html = file_get_contents("templates/install.html");
        $html = str_replace("OAT_LANG",$this->get_html_lang(),$html);
        $html = str_replace("<!--HEADER-->",$header,$html);
        $html = str_replace("<!--CSS-->",$css,$html);
        $html = str_replace("<!--TITLE-->",$this->getPageTitle(),$html);
        $html = str_replace("<!--CONTENT-->",$this->toRender,$html);
        $html = str_replace("<!--FOOTER-->",$this->getPageFooter(),$html);
        print $html;
    } // end render()
    
} // end output
?>

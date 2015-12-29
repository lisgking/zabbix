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


class template_help {

    public $idsadmin;

    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
    }
    function show_help($data)
    {
        $pagetitle = $this->idsadmin->lang('OATHelp');
        $HTML .= <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html lang="{$this->idsadmin->html->get_html_lang()}" xml:lang="{$this->idsadmin->html->get_html_lang()}">
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<head>

<title>{$pagetitle}</title>
<style type="text/css" media="all">
        @import url("templates/ibmdita.css");
</style>
<style type="text/css" media="all">
        @import url("templates/style.css");
</style>
</head>

<body>
<div id="popwintitle">
<table width="100%">
<tr>
<td>
{$this->idsadmin->lang('Help')}
</td>
</tr>
</table>
</div>

<div class="borderwrap">
<table width="100%">
<tr>
<td class="formright">
{$data['desc']}
</td>
</tr>
</table>
</div>
<br/>
<table width="100%">
<tr>
<td align="center">
   <input type="button" class="button" value="{$this->idsadmin->lang('CloseWindow')}" onClick="javascript:window.close();"/>
</td>
</tr>
</table>

</body>
</html>
EOF;
    return $HTML;
    } // end show_help



} // end class

?>

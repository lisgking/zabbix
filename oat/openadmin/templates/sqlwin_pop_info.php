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


class sqlwin_pop_info {

    public $idsadmin;
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;     
    }
    
    function show_partnum($disp_title, $partnum, $hdr, $data)
    {

	$this->idsadmin->load_lang("sqlwin");
	$this->idsadmin->load_lang("misc_template");
        $HTML .= <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html lang="{$this->idsadmin->html->get_html_lang()}" xml:lang="{$this->idsadmin->html->get_html_lang()}">
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<head>

<title>{$this->idsadmin->lang('PartitionInfo')}</title>
<style type="text/css" media="all">
        @import url("templates/style.css");
</style>
</head>

<body>
<div id="popwintitle">
<table width="100%">
<tr>
<th class='tblheader'>
{$disp_title}
</th>
</tr>
</table>
</div>

<div class="borderwrap">
<table width="100%">
EOF;


        $cnt=0;
        foreach ($data as $val)
        {
            $cnt++;
            if ($hdr[$cnt] == $this->idsadmin->lang('HasIPgs'))
            { 
                $val = $this->idsadmin->lang($val);
            }
            $HTML .= <<<EOF
    <tr>
    <td class="formright">$hdr[$cnt]</td>
    <td class="formright">$val</td>
    </tr>
EOF;
        }

        $HTML .= <<<EOF
</table>
</div>
<br/>
<table width="100%">
<tr>
<td align="center">
   <a href="javascript:window.close();" class="button" title="{$this->idsadmin->lang('CloseWindow')}">{$this->idsadmin->lang('CloseWindow')}</a>
</td>
</tr>
</table>

</body>
</html>
EOF;

        return $HTML;
    }#end show_partnum



}

?>

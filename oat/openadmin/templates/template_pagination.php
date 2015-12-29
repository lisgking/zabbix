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

class template_pagination {

    public $idsadmin;

    function __construct()
    {
    }

    function pag_prevlink($prev)
    {
	      $this->idsadmin->load_lang("misc_template");
        $HTML = "";
        $HTML .= <<<EOF
<td><a href="{$prev}" title="{$this->idsadmin->lang('PrevPage')}"><img src="images/back.gif" border="0" alt="{$this->idsadmin->lang('PrevPage')}"/></a></td>
EOF;
return $HTML;
    }

/*
    function pag_first($link)
    {
        $HTML = "";
        $HTML .= <<<EOF
<td class="borderwrap"><span class="pglink"><a href="{$link}" title="{$this->idsadmin->lang('Page')} 1">&laquo;</a></span></td>
EOF;
return $HTML;
    }

    function pag_last($link,$page)
    {
        $HTML = "";
        $HTML .= <<<EOF
<td class="borderwrap"><span class="pglink"><a href="{$link}" title="{$this->idsadmin->lang('Page')} ${page}">&raquo;</a></span></td>
EOF;
return $HTML;
}

function pag_info($current,$total)
{
    $pages="Pages";

    if ($total == 1)
    $pages="Page";

    $HTML = "";
    $HTML .= <<<EOF
<td class="rowblue" width="10%">{$total} {$pages}</td>
EOF;
return $HTML;
}
*/

function pag_nextlink($next)
{
    $this->idsadmin->load_lang("misc_template");
 //   $next = htmlentities($next);
    $HTML = "";
    $HTML .= <<<EOF
<td>
<a href="{$next}" title="{$this->idsadmin->lang('NextPage')}" class="pglink"><img src="images/forward.gif" border="0" alt="{$this->idsadmin->lang('NextPage')}"/></a>
</td>
EOF;
return $HTML;
}

function pag($info="",$firstpg="",$next="",$data="",$prev="",$lastpg="",$perpage="")
{
    $HTML = "";
    $HTML .= <<<EOF
<table cellspacing="2" border="0" width="100%">
<tr>
{$data}{$prev}{$next}
{$perpage}
</tr>
</table>
EOF;
return $HTML;
}


function pag_perpage($total="",$pp,$perpages)
{
    if (isset($this->idsadmin->in['perpage']))
    {
    	$perpage = $this->idsadmin->in['perpage'];
    } else {
    	$perpage = $pp;
    }
    $url = $this->idsadmin->removefromurl("perpage");
    $url = htmlentities($url);
    $HTML = "";
    if ($total > 0)#$perpage)
    {
        $HTML .= "<td width='100%' align='right'>";
        foreach($perpages as $k => $v) {
            if ($k != $perpage )
            {
            	$HTML .= "<a href='{$url}&amp;perpage={$k}'>{$v}</a>&nbsp;";
            } else {
            	$HTML .= "<span class='rowblue'>{$v}</span>&nbsp;";
            }
        }
        $HTML .= "</td>";
    } else {
        $HTML .= "<td width='100%' align='right'>&nbsp;</td>";
    }
    return $HTML;
}#end pag_perpage

function pag_addpage($link="",$page="")
{
    $this->idsadmin->load_lang("misc_template");
  //  $link = htmlentities($link);
    $HTML = "";
    if ($link=="")
    {
        $HTML .= <<<EOF
<option value="{$link}" selected='selected' >{$this->idsadmin->lang('Page')} {$page}</option>
EOF;
    }
    else
    {
        $HTML .= <<<EOF
<option value="{$link}">{$this->idsadmin->lang('Page')} {$page}</option>
EOF;
    }
    return $HTML;
}

} // end class
?>

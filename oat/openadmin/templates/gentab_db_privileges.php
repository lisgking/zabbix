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


class gentab_db_privileges {

    public $idsadmin;
    private $sz=0;

    function __construct()
    {
    }

    function sysgentab_start_output( $title, $column_titles, $pag="")
    {

        $url=$this->idsadmin->removefromurl("orderby");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
        $url = htmlentities($url);

        $this->sz=sizeof($column_titles);
        $title_colspan = $this->sz + 1;
        
        $HTML = <<<EOF
<script type='text/javascript'>
// Toggle for expanding or collapsing an HTML element
function expand_collapse(id) {
    if (document.getElementById(id).style.display=="none") {
		document.getElementById(id).style.display = "";
    } else {
        document.getElementById(id).style.display = "none";
    }
}
</script>
        $pag
<div class="borderwrap">
<table class="gentab">
<tr>
<td class="tblheader" align="center" colspan="{$title_colspan}">{$title}</td>
</tr>
EOF;
	$this->idsadmin->load_lang("misc_template");
        $HTML .= "<tr>";
        foreach ($column_titles as $index => $val)
        {
            $HTML .= "<td class='formsubtitle' align='center'>";
            $img="";
            if( isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
            {
                $img="<img src='images/arrow-up.gif' border='0' alt='{$this->idsadmin->lang('Ascending')}'/>";
                if (isset($this->idsadmin->in['orderway']))
                $img="<img src='images/arrow-down.gif' border='0' alt='{$this->idsadmin->lang('Descending')}'/>";
            }

            if( (isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
            && !(isset($this->idsadmin->in['orderway'])) )
            {
                $HTML .= "<a href='{$url}&amp;orderby=$index&amp;orderway=DESC' title='{$this->idsadmin->lang("OrderDesc",array($val))}'>{$val}{$img}</a>";
            }
            else
            {
                $HTML .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc",array($val))}'>{$val}{$img}</a>";
            }
            $HTML .= "</td>";
        }
        if (!$this->idsadmin->isreadonly())
        {
        	$HTML .= "<td  class='formsubtitle'></td>";
        }
        $HTML .= "</tr>";
        return $HTML;
    }

    function sysgentab_row_output($data)
    {
	$this->idsadmin->load_lang("privileges");
    	$user = trim($data['NAME']);
    	$usertype = trim($data['USERTYPE']);
    	$defrole = $data['DEFROLE'];
    	$dbname = $data['DBNAME'];
    	
    	$con_selected = $res_selected = $dba_selected = "";
    	if (strcasecmp($usertype, "CONNECT") == 0) {
    		$con_selected = "selected='selected' ";
    	} elseif (strcasecmp($usertype, "RESOURCE") == 0) {
    		$res_selected = "selected='selected' ";    		
    	} elseif (strcasecmp($usertype, "DBA") == 0) {
    		$dba_selected = "selected='selected' ";
    	}
    	
		$HTML = <<<EOF
		<tr>
<td class='center'>{$user}</td>
<td class='center'>{$usertype}</td>
<td class='center'>{$defrole}</td>
EOF;
		if (!$this->idsadmin->isreadonly())
		{
			$HTML .= <<<EOF
<td class='center'>
  <form name='modifyprivform_{$user}' method="post" action="index.php?act=privileges&amp;do=database&amp;dbname={$dbname}&amp;save">
  <input type='button' id='{$user}_modifybtn' class='button' value='{$this->idsadmin->lang('Modify')}' 
         onclick='expand_collapse("modifypriv_{$user}");expand_collapse("{$user}_modifybtn");'/>
  <div id='modifypriv_{$user}' style='display:none'>
  <input type='hidden' name='username' value='{$user}' />
  <input type='hidden' name='prevpriv' value='{$usertype}' />
  <select name='newpriv' >
    <option value='CONNECT' {$con_selected}>{$this->idsadmin->lang('CONNECT')}</option>
    <option value='RESOURCE' {$res_selected}>{$this->idsadmin->lang('RESOURCE')}</option>
    <option value='DBA' {$dba_selected}>{$this->idsadmin->lang('DBA')}</option>
    <option value='Revoke'>{$this->idsadmin->lang('Revoke')}</option>
  </select>
  <input type='submit' class='button' name='save' value='{$this->idsadmin->lang("Save")}' onclick='modifyprivform_{$user}.submit()'/>
  <input type='button' class='button' name='cancel' value='{$this->idsadmin->lang("Cancel")}' 
         onclick='expand_collapse("modifypriv_{$user}");expand_collapse("{$user}_modifybtn");'/>
  </div>
  </form>
</td>
EOF;
		}
		$HTML.="</tr>";
    	return $HTML;
    }

    function sysgentab_end_output($pag="")
    {
        $HTML = <<<EOF
</table>
</div>
        $pag
EOF;
        return $HTML;
    }

}// end class
?>

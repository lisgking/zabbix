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


class gentab_tab_privileges {

    public $idsadmin;
    private $sz=0;

    function __construct()
    {
    }

    function sysgentab_start_output( $title, $column_titles, $pag="")
    {
    	$this->idsadmin->lang("misc_template");
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
                $HTML .= "<a href='{$url}&amp;orderby=$index&amp;orderway=DESC' title='{$this->idsadmin->lang("OrderDesc", array($val))}'>{$val}{$img}</a>";
            }
            else
            {
                $HTML .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc",array($val))}'>{$val}{$img}</a>";
            }
            $HTML .= "</td>";
        }
        if (!$this->idsadmin->isreadonly())
        {
        	$HTML .= "<td class='formsubtitle' align='center'></td>";
        }
        $HTML .= "</tr>";
        return $HTML;
    }

    function sysgentab_row_output($data)
    {
    		$this->idsadmin->load_lang("privileges");
	  	$tabname = trim($data['TABNAME']);
	  	if ($this->idsadmin->phpsession->instance->get_delimident() == "Y")
	  	{
	  		// need to enclose table name in quotes if DELIMIDENT=Y
			$tabname_r = explode(".",$tabname);
			$tabname_r[1] = "\"{$tabname_r[1]}";
			$tabname_r[count($tabname_r) -1 ] = "{$tabname_r[count($tabname_r) -1]}\"";
			$tabname = implode(".",$tabname_r);
	  	}
    	$grantee = trim($data['GRANTEE']);
    	$grantor = trim($data['GRANTOR']);
    	$tabauth = trim($data['TABAUTH']);
    	$tabid = $data['TABID'];
    	$dbname = trim($data['DBNAME']);
    	
		$HTML = <<<EOF
<tr>
<td class='center'>{$tabname}</td>
<td class='center'>{$grantee}</td>
<td class='center'>{$grantor}</td>
<td class='center'>
  <table border='0'><tr>
EOF;
    	
		$str = str_split($tabauth);
		$schecked = $uchecked = $ichecked = $dchecked = "";
		$xchecked = $achecked = $rchecked = $nchecked = "";
        if($str[0] == 's' || $str[0] == 'S') {
        	$HTML.= "<td class='color_select'>S</td>";
        	$schecked = "checked='checked'";
        }
        if($str[1] == 'u' || $str[1] == 'U') {
            $HTML.= "<td class='color_update'>U</td>";
            $uchecked = "checked='checked'";
        }
        // NOTE: $str[2] indicates column level privileges, so we'll
        // ignore it as we are only displaying table level privileges.
        if($str[3] == 'i' || $str[3] == 'I') {
            $HTML.= "<td class='color_insert'>I</td>";
            $ichecked = "checked='checked'";
        }
        if($str[4] == 'd' || $str[4] == 'D') {
            $HTML.= "<td class='color_delete'>D</td>";
            $dchecked = "checked='checked'";
        }                
        if($str[5] == 'x' || $str[5] == 'X') {
            $HTML.= "<td class='color_index'>X</td>";
            $xchecked = "checked='checked'";
        }
        if($str[6] == 'a' || $str[6] == 'A') {
            $HTML.= "<td class='color_alter'>A</td>";
            $achecked = "checked='checked'";
        }
        if($str[7] == 'r' || $str[7] == 'R') {
            $HTML.= "<td class='color_ref'>R</td>";
            $rchecked = "checked='checked'";
        }
        if($str[8] == 'n' || $str[8] == 'N') {
            $HTML.= "<td class='color_under'>N</td>";
            $nchecked = "checked='checked'";
        }

        $id = "{$tabid}_{$grantee}_{$grantor}";
        $HTML .= "</tr></table></td>";

        if (!$this->idsadmin->isreadonly())
        {
			$HTML .= <<<EOF
<td class='center'>
  <form name='modifyform_{$id}' method="post" action="index.php?act=privileges&amp;do=table&amp;dbname={$dbname}">
  <input type='button' id='modifybtn_{$id}' class='button' value="{$this->idsadmin->lang('Modify')}" 
         onclick='expand_collapse("modifytabpriv_{$id}");expand_collapse("modifybtn_{$id}");'/>
  <div id='modifytabpriv_{$id}' style='display:none'>
  <input type='hidden' name='tabname' value='{$tabname}' />
  <input type='hidden' name='grantee' value='{$grantee}' />
  <input type='hidden' name='grantor' value='{$grantor}' />
  <input type='hidden' name='save' value='save' />
<table class='gentab' style='text-align:left'>
<tr>
<td class='color_select'>S</td><td><input type='checkbox' name='selectbox' {$schecked}/>{$this->idsadmin->lang('Select')}</td>
<td class='color_update'>U</td><td><input type='checkbox' name='updatebox' {$uchecked}/>{$this->idsadmin->lang('Update')}</td>
</tr>
<tr>
<td class='color_insert'>I</td><td><input type='checkbox' name='insertbox' {$ichecked}/>{$this->idsadmin->lang('Insert')}</td>
<td class='color_delete'>D</td><td><input type='checkbox' name='deletebox' {$dchecked}/>{$this->idsadmin->lang('Delete')}</td>
</tr>
<tr>
<td class='color_index'>X</td><td><input type='checkbox' name='indexbox' {$xchecked}/>{$this->idsadmin->lang('Index')}</td>
<td class='color_alter'>A</td><td><input type='checkbox' name='alterbox' {$achecked}/>{$this->idsadmin->lang('Alter')}</td>
</tr>
<tr>
<td class='color_ref'>R</td><td><input type='checkbox' name='referencesbox' {$rchecked}/>{$this->idsadmin->lang('References')}</td>
<td class='color_under'>N</td><td><input type='checkbox' name='underbox' {$nchecked}/>{$this->idsadmin->lang('Under')}</td>
</tr>
</td></tr>
</table>
  <input type='submit' class='button' name='save' value='{$this->idsadmin->lang("Save")}' onclick='modifyform_{$id}.submit()'/>
  <input type='button' class='button' name='cancel' value='{$this->idsadmin->lang("Cancel")}' 
         onclick='expand_collapse("modifytabpriv_{$id}");expand_collapse("modifybtn_{$id}");'/>
  </div>
  </form>
</td>
EOF;
        }
        $HTML .= "</tr>";
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

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


class template_sqltracetype_order {

    public $idsadmin;
    private $sz=0;
     
    private $surl='

<form method="get" action="index.php">
<span title="Click for more details about this SQL statement"/>
<input type=submit class=button name="view" value="Drill Down" /></span>
<input type=hidden  name="act" value="sqltraceforreports"/>
<input type=hidden  name="do" value="sqllist"/>
<input type=hidden  name="id" value="
';

	private $eurl='
"/> </form>

';
    
    function sysgentab_start_output( $title, $column_titles, $pag="")
    {
        $this->idsadmin->load_lang('misc_template');
        $this->sz=sizeof($column_titles);

        $url=$this->idsadmin->removefromurl("orderby");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
       // $url = htmlentities($url);
        
        $HTML = <<<EOF
        $pag
<div class="borderwrap">
<table class="gentab" >
<tr>
<td class="tblheader" align="center" colspan="{$this->sz}">{$title}</td>
</tr>
EOF;
        $HTML .= "<TR>";
        foreach ($column_titles as $index => $val)
        {
            $HTML .= "<td class='formsubtitle' align='center'>";
            $img="";
            if ( isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
            {
                $img="<img src='images/arrow-up.gif' border='0' alt='{$this->idsadmin->lang('Ascending')}'/>";
                if (isset($this->idsadmin->in['orderway']))
                    $img="<img src='images/arrow-down.gif' border='0' alt='{$this->idsadmin->lang('Descending')}'/>";
            }

    if( ( isset($this->idsadmin->in['orderby'])==$index) && !(isset($this->idsadmin->in['orderway'])) ) {
        $HTML .= "<a href='{$url}&amp;orderby=$index&amp;orderway=DESC' title='{$this->idsadmin->lang("OrderDesc",array($val))}'>{$val}{$img}</a>";
    } else {
        $HTML .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc",array($val))}'>{$val}{$img}</a>";
    }
    $HTML .= "</td>
";
}
$HTML .= "</TR>
";
return $HTML;
    }

    function sysgentab_row_output($data)
    {
        $HTML = "<TR>";
        $cnt=1;
        foreach ($data as $index => $val)
        {
        	
            if ( strtolower($index) == "sql_statement")
            {
                $val = htmlentities($val,ENT_COMPAT,"UTF-8");
            } else if ( strtolower($index) == "url" ) {
            	$val = $this->surl . $val . $this->eurl;
            }
            
            $HTML .= "<td>";
            $HTML .= $val;
            $HTML .= "</td>";
            if ($cnt++ >= $this->sz )
            break;
        }
        $HTML .= "</TR>
         ";
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

} // end class 
?>

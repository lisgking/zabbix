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


class template_gentab_deletetask {

    public $idsadmin;
    private $sz=0;
    
    function __construct()
    {
       
    }
    
    function sysgentab_start_output( $title, $column_titles, $pag="")
    {
        $this->sz=sizeof($column_titles);

        $url=$this->idsadmin->removefromurl("orderby");
        $this->idsadmin->load_lang("misc_template");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
        $url = htmlentities($url);
        
        $HTML = <<<EOF
        $pag
<div class="borderwrap">
<table class="gentab">
<tr>
<td class="tblheader" align="center" colspan="{$this->sz}">{$title}</td>
</tr>
EOF;
        $HTML .= "<tr>";
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
        $HTML .= "<a href='{$url}&amp;orderby=$index&amp;orderway=DESC' title='{$this->idsadmin->lang("OrderDesc", array($val))}'>{$val}{$img}</a>";
    } else {
        $HTML .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc", array($val))}'>{$val}{$img}</a>";
    }
    $HTML .= "</td>
";
}
$HTML .= "</tr>
";
return $HTML;
    }

    function sysgentab_row_output($data)
    {
    	$this->idsadmin->load_lang("misc_template");
        $HTML = "<tr>";
        $cnt=1;
        foreach ($data as $index => $val)
        {
            if( $cnt == 4)
            {
                $id = $data['TK_ID'];
		$resulttab = $data['TK_RESULT_TABLE'];
                $buttonclass=($data['SERVER_BUILTIN_TASK']>0)?"disabledbutton":"button";
		$disabled=($data['SERVER_BUILTIN_TASK']>0)?"disabled='disabled'":"";
		

		if (!stristr($data['TK_TYPE'],"SENSOR") === false) { // task type is SENSOR/STARTUP SENSOR

			$buttonid  = 'DeleteSensor_'.$id;
			$expandid  = 'DeleteResultTable'.$id;
			$HTML .= <<<EOF

<script type="text/javascript">
function expand_collapse(id) {
    if (document.getElementById(id).style.display=="none") {
		document.getElementById(id).style.display = "";
	} else {
		document.getElementById(id).style.display = "none";
	}
}
</script>
<td align='center'>
<input type='button' class='{$buttonclass}' {$disabled} id='{$buttonid}' value='{$this->idsadmin->lang("Delete")}' onclick="expand_collapse('{$expandid}');expand_collapse('{$buttonid}');"/>
<div id='{$expandid}' style="display:none">
{$this->idsadmin->lang('CheckDeleteResultsTab')} {$resulttab}?<br/>
<input type='button' class='button' name='YesDeleteResultTab' value='{$this->idsadmin->lang("Yes")}' onclick="window.location='index.php?act=health&do=doDeleteTask&id={$id}&DeleteResultsTab=true'"/>
<input type='button' class='button' name='NoDeleteResultTab' value='{$this->idsadmin->lang("No")}' onclick="window.location='index.php?act=health&do=doDeleteTask&id={$id}&DeleteResultsTab=false'"/>
<input type='button' class='button' name='CancelDeleteResultTab' value='{$this->idsadmin->lang("Cancel")}' onclick="expand_collapse('{$expandid}');expand_collapse('{$buttonid}');"/>

</td>

EOF;

		}else{ // task type is TASK/STARTUP TASK
			$HTML .= "<td align='center'>";
		    	$HTML .= "<input type='button' class={$buttonclass} {$disabled} name='Delete' value='{$this->idsadmin->lang("Delete")}' onclick=\"window.location='index.php?act=health&amp;do=doDeleteTask&amp;id={$id}&amp;DeleteResultsTab=false'\"/>";
                	$HTML .= "</td>";
		    }
            }else{
            	$HTML .= "<td>";
            	$HTML .= $val;
            	$HTML .= "</td>";
            }
            if ($cnt++ >= $this->sz )
            break;
        }
        $HTML .= "</tr>
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

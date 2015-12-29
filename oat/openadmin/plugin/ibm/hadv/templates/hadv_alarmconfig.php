<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for Informix
 *  Copyright IBM Corporation 2011, 2012.  All rights reserved.
 **********************************************************************/

class hadv_alarmconfig{

    public $idsadmin;
    private $sz=0;
    private $allAlarmsEnabled = true;
    
    function __construct()
    {
       
    }
    
    function sysgentab_start_output( $title, $column_titles, $pag="")
    {
        $this->sz=sizeof($column_titles);
        $this->idsadmin->load_lang("misc_template");
        $url=$this->idsadmin->removefromurl("orderby");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
        // $url = htmlentities($url);
        
        if (!$this->idsadmin->isreadonly())
		{
			$save_btn = "<input type='submit' class='button' name='UpdateTask' value=\"{$this->idsadmin->lang('save')}\" />";
		}
        
        $HTML = <<<EOF
        $pag

<script type="text/javascript">
function enableAllClick(enableAllCheckbox) {

	var table = document.getElementById('alarmsTable');
	if (table != null) {
		var list = table.getElementsByTagName("input");
		for (var i=0; i < list.length; i++) {
			if (list[i].type == 'checkbox') {
				list[i].checked = enableAllCheckbox.checked;
			}
		}
	}
}
function alarmEnableClick(checkbox) {
	if (!checkbox.checked) {
		document.getElementById('enable_all_checkbox').checked = false;
	} 
}
</script>
<div class="borderwrap">
<table class="gentab" id="alarmsTable">
<tr>
<td class="tblheader" align="center" colspan="4">{$title}</td>
<td class="tblheader" align="center" colspan="1">{$save_btn}</td>
</tr>
EOF;
        $HTML .= "<tr>";
        $cnt=1;
        foreach ($column_titles as $index => $val)
        {
            if ($cnt == 1)
            {
               //$HTML .= "<td width='4%' class='formsubtitle' align='center'>";
               $cnt++;
               continue;
            }else if ($cnt == 2)
            {
               $HTML .= "<td width='9%' class='formsubtitle' align='center'>";
            }else if ($cnt == 3)
            {
               $HTML .= "<td width='20%' class='formsubtitle' align='center'>";
            }else if ($cnt == 4)
            {
               $HTML .= "<td width='57%' class='formsubtitle' align='center'>";
            }else if ($cnt == 5)
            {
               $HTML .= "<td width='9%' class='formsubtitle' align='center'>";
            }else if ($cnt == 6)
            {
               $HTML .= "<td width='5%' class='formsubtitle' align='center'>";
            }else 
            {
               $HTML .= "<td class='formsubtitle' align='center'>";
            }

            $img="";
            if ( isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
            {
                $img="<img src='images/arrow-up.gif' border='0' alt='{$this->idsadmin->lang('Ascending')}'/>";
                if (isset($this->idsadmin->in['orderway']))
                    $img="<img src='images/arrow-down.gif' border='0' alt='{$this->idsadmin->lang('Descending')}'/>";
            }

            if( ( isset($this->idsadmin->in['orderby'])==$index) && !(isset($this->idsadmin->in['orderway'])) ) 
            {
               $HTML .= "<a href='{$url}&amp;orderby=$index&amp;orderway=DESC' title='{$this->idsadmin->lang("OrderDesc", array($val))}'>{$val}{$img}</a>";
            } else 
            {
               $HTML .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc", array($val))}'>{$val}{$img}</a>";
            }
            
            if ($cnt == 6)
            {
            	// Add 'Enable all' check box to enable column header
            	$disabled = ($this->idsadmin->isreadonly()? "disabled":"");
            	$HTML .= "<br/><input type='checkbox' id='enable_all_checkbox' onclick='enableAllClick(this);' $disabled PLACEHOLDER_FOR_ENABLE_ALL_CHECKED> ";
            }
            $HTML .= "</td> ";
            $cnt++;
        }
        
        $HTML .= "</tr> ";
        return $HTML;
    }

    function sysgentab_row_output($data)
    {
        $HTML = "<tr>";
        foreach ($data as $index => $val)
        {
            if ($index == "ID")
            {
               $id=$val;
            } 
            else if ($index == "PROF_ID")
            {
            	$prof_id=$val;
            } 
            else if ($index == "GROUP")
            {
            	$HTML .= "<td>";
            	$HTML .= "{$this->idsadmin->lang($val)}";
            	$HTML .= "</td>";
            } 
            else if ($index == "DESC")
            {
            	$ldesc="msg_". preg_replace("/ /","_",  trim(preg_replace('/%/',"",trim($val),-1)) ,-1);
            	$desc = preg_replace("/msg_/","dsc_",$ldesc,-1);
            	$HTML .= "<td>";
            	$HTML .= "{$this->idsadmin->lang($desc)}";
            	$HTML .= "</td>";
            }
            else if ($index == "LDESC")
            {
            	$full_desc = $this->idsadmin->lang($ldesc);
            	$full_desc = preg_replace("/{$this->idsadmin->lang('Redalarm_')}/",
            		"{$this->idsadmin->lang('Redalarm_')}",
            		$full_desc,-1);
            	$full_desc = preg_replace("/{$this->idsadmin->lang('Yelalarm_')}/",
            		"<br><br>{$this->idsadmin->lang('Yelalarm_')}",
            		$full_desc,-1);
            	$HTML .= "<td>";
            	$HTML .= "{$full_desc}";
            	$HTML .= "</td>";
            } 
            else if ($index == "MODIFY")
            {
            	$HTML .= "<td class='center'>";
            	if (${val} > 0 && !$this->idsadmin->isreadonly())
            	{
            		$HTML .= "<a href='index.php?act=ibm/hadv/healthadv&amp;do=thresholds&amp;prof_id=${prof_id}&amp;id=${id}' style='text-decoration: none; border-bottom: 1px solid'>{$this->idsadmin->lang("modify_threshold")}</a>";
        		} else {
        			$HTML .= "&nbsp";
        		}
        		$HTML .= "</td>";
    		} 
            else if ($index == "ENABLE")
            {
            	$disabled = ($this->idsadmin->isreadonly()? "disabled":"");
            	if ($val == "Y")
            	{
            		$checked = "checked";
            	} else {
            		$checked = "";
            		$this->allAlarmsEnabled = false;
            	}
            	$HTML .= "<td class='center'>";
            	$HTML .= "<input type='hidden' name=totbox[] value=$id > ";
            	$HTML .= "<input type='checkbox' onclick='alarmEnableClick(this);' name=box[] $disabled value=$id $checked> ";
            	$HTML .= "</td>";
            }
    		else 
    		{
        		$HTML .= "<td>";
        		$HTML .= "${val}";
        		$HTML .= "</td>";
            }
        }
        $HTML .= "</tr>";
        return $HTML;
    }

    function sysgentab_end_output($pag="")
    {
    	// Set whether the enable all checkbox is checked when the page is loaded.
    	$this->idsadmin->html->to_render = preg_replace('/PLACEHOLDER_FOR_ENABLE_ALL_CHECKED/',(($this->allAlarmsEnabled)? "checked":""),$this->idsadmin->html->to_render);
    	
        $HTML = <<<EOF
</table>
</div>
        $pag
EOF;
        return $HTML;
    }

} // end class 
?>

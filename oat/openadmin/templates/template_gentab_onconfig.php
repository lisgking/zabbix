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


class template_gentab_onconfig {

    public $idsadmin;
    private $sz=0;
    private $url_options="";
    
    function __construct()
    {
    	require_once ROOT_PATH."lib/onconfig_param.php";
    }
    
    /**
     * So we can remember the place and sort options the user was at before drilling down
     * on an onconfig parameter, we need to retreive these URL options and include them
     * in the drill down urls  
     **/
    function set_url_options ()
    {
        if(isset($this->idsadmin->in['show']))
        {
           $this->url_options .= "&amp;show={$this->idsadmin->in['show']}";
        }
        if(isset($this->idsadmin->in['pos']))
        {
           $this->url_options .= "&amp;pos={$this->idsadmin->in['pos']}";
        }
        if(isset($this->idsadmin->in['orderby']))
        {
           $this->url_options .= "&amp;orderby={$this->idsadmin->in['orderby']}";
        }
        if(isset($this->idsadmin->in['orderway']))
        {
           $this->url_options .= "&amp;orderway={$this->idsadmin->in['orderway']}";
        }
        if(isset($this->idsadmin->in['perpage']))
        {
           $this->url_options .= "&amp;perpage={$this->idsadmin->in['perpage']}";
        }
    }
    
    function sysgentab_start_output( $title, $column_titles, $pag="")
    {
        // Since the $idsadmin object is not set until after the constructor,
        // we'll get the user's current url options now
        $this->set_url_options();
        $this->idsadmin->load_lang("misc_template");
    	$this->sz=sizeof($column_titles);
    	
        $url=$this->idsadmin->removefromurl("orderby");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
        $url = htmlentities($url);
        
        $HTML = <<<EOF
        $pag
<div class="borderwrap">
<table class="gentab" >
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
    	$param_id = trim($data['ID']);
    	$param_name = trim($data['NAME']);
    	$param_value = $data['EFFECTIVE'];
    	$param_flags = $data['FLAGS'];
    	$param_dynamic = $this->idsadmin->lang(preg_replace("/ /", "", $data['CONFIGURABLE']));
    	
    	// Report mode output
    	$report_mode = (isset($this->idsadmin->in["runReports"]) || isset($this->idsadmin->in["reportMode"]));
    	if ($report_mode)
    	{
    		$HTML .=<<<END
<td>{$param_name}</td>
<td>{$param_value}</td>
<td>{$param_dynamic}</td>
</tr>
END;
			return $HTML;
    	}
    	
    	// Non-report mode output
    	$onconfig_param = new onconfig_param($param_id,$param_name, $param_value, $param_flags, $this->idsadmin);
    	$compliance = $onconfig_param->checkRecommendation();
    	if (is_null($compliance)) $compliance = true;
    	
    	$HTML = ($compliance)? "<tr>":"<tr class='rowlogwarn'>";

    	$drill_down_url = "index.php?act=onstat&amp;do=config_details&amp;param_id={$param_id}{$this->url_options}";
    	$warning_icon = ($compliance)? "":"<a href='{$drill_down_url}'><image src='images/warning_16x.png' alt=\"{$this->idsadmin->lang('Recommendation_NO')}\" title=\"{$this->idsadmin->lang('Recommendation_NO')}\"/></a>"; 
    	
    	$HTML.=<<<END
<td><a href='{$drill_down_url}'>{$param_name}</a></td>
<td>{$param_value} {$warning_icon}</td>
<td>{$param_dynamic}</td>
</tr>
END;
    	
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

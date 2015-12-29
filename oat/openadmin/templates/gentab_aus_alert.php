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


class gentab_aus_alert {

    public $idsadmin;
    private $sz=0;

    function __construct()
    {
    }

    function sysgentab_start_output( $title, $column_titles, $pag="")
    {
        $url=$this->idsadmin->removefromurl("orderby");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
        //$url = htmlentities($url);

        $this->sz=sizeof($column_titles);
        $title_colspan = $this->sz + 1;
        
        $HTML = <<<EOF
        $pag
<div class="borderwrap">
<table class="gentab">
<tr>
<td class="tblheader" align="center" colspan="{$title_colspan}">{$title}</td>
</tr>
EOF;
	$this->idsadmin->load_lang("misc_template");
        $HTML .= "<TR>";
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
        $HTML .= "</TR>";
        return $HTML;
    }

    function sysgentab_row_output($data)
    {
    	$this->idsadmin->load_lang('health');
    	$HTML = "<tr>";
        $cnt=1;
        foreach ($data as $index => $val)
        {
        	$HTML .= "<td class='center'>";
        	if (strcasecmp($index,"ALERT_TYPE") == 0)
        	{ 
        		switch (trim($val))
        		{
        			case "WARNING";
        			    $HTML .= "<img src='images/warning.png' alt='{$this->idsadmin->lang('Warning')}' title='{$this->idsadmin->lang('Warning')}' />";
        			    break;	
        			case "ERROR";
        			    $HTML .= "<img src='images/error.png' alt='{$this->idsadmin->lang('Error')}' title='{$this->idsadmin->lang('Error')}' />";
        			    break;
        			case "INFO";
        			    $HTML .= "<img src='images/info.png' alt='{$this->idsadmin->lang('Info')}' title='{$this->idsadmin->lang('Info')}' />";
        			    break;	
        			default;
        			    $HTML .= $val;
        			    break;	
        		}
        	}
            else if (strcasecmp($index,"ALERT_COLOR") == 0) 
            {
                switch (trim($val))
        		{
        			case "red";
        			case "yellow";
        			case "green";
        			    $HTML .= "<img src='images/status_{$val}.png' alt='{$this->idsadmin->lang(trim($val))}' title='{$this->idsadmin->lang(trim($val))}' />";
        			    break;	
        			default;
        			    $HTML .= $val;
        			    break;	
        		}
            }
        	else 
        	{
	            $HTML .= $val;
        	}
        	$HTML .= "</td>";
            if ($cnt++ >= $this->sz )
            break;
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

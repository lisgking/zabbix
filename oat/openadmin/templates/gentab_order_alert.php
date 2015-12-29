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


class gentab_order_alert {

    public $idsadmin;
    private $sz=0;

    function __construct()
    {
    }

    function sysgentab_start_output( $title, $column_titles, $pag="")
    {

        $url=$this->idsadmin->removefromurl("orderby");
        $this->idsadmin->load_lang("misc_template");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
        $url = htmlentities($url);

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
                $HTML .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc", array($val))}'>{$val}{$img}</a>";
            }
            $HTML .= "</td>";
        }
        $HTML .= "<td class='formsubtitle'></td></tr>";
        return $HTML;
    }

    function sysgentab_row_output($data)
    {
        $correct_but = "";
        $recheckbutton = "";
        $ignorebutton = "";

        $isreadonly = $this->idsadmin->isreadonly();

        if ( $data['ALERT_ACTION']
        && ! $isreadonly )
        {
            $correct_but = "<input type='submit' class='button' name='button_correct' value='{$this->idsadmin->lang('correct')}'/>";
        }

        if ( ! $isreadonly )
        {
            $recheckbutton = "<input type=\"submit\" class=\"button\" name=\"button_recheck\" value=\"{$this->idsadmin->lang('recheck')}\"/>";
            $ignorebutton  = "<input type=\"submit\" class=\"button\" name=\"button_ignore\" value=\"{$this->idsadmin->lang('ignore')}\"/>";
        }

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
        			    $HTML .= "<img src='images/warning.png' alt='{$this->idsadmin->lang('Warning')}' title='{$this->idsadmin->lang('Warning')}'/>";
        			    break;	
        			case "ERROR";
        			    $HTML .= "<img src='images/error.png' alt='{$this->idsadmin->lang('Error')}' title='{$this->idsadmin->lang('Error')}'/>";
        			    break;
        			case "INFO";
        			    $HTML .= "<img src='images/info.png' alt='{$this->idsadmin->lang('Info')}' title='{$this->idsadmin->lang('Info')}'/>";
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
        			    $HTML .= "<img src='images/status_{$val}.png' alt='{$this->idsadmin->lang(trim($val))}' title='{$this->idsadmin->lang(trim($val))}'/>";
        			    break;	
        			default;
        			    $HTML .= $val;
        			    break;	
        		}
            }
            else if (strcasecmp($index,"ALERT_STATE") == 0) 
            {
                // Translate alert state into the user's language
                $HTML .= $this->idsadmin->lang($val);
            }
            else if (strcasecmp($index,"ALERT_MESSAGE") == 0) 
            {
                if (isset($data['ALERT_OBJECT_TYPE']) && strcasecmp($data['ALERT_OBJECT_TYPE'], "ALARM") == 0)
        	{
	            $HTML .= "<strong>" . $this->idsadmin->lang('ALARM',array($data['ALERT_OBJECT_INFO'])) . ":</strong> " . $val;
        	} else {
	            $HTML .= $val;
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

        if ( ! $isreadonly )
        {
            $HTML.=<<<END
   <td align="center">
   <form method="post" action="index.php?act=health&amp;do=showAlerts">
   {$recheckbutton}
   {$ignorebutton}
   {$correct_but}
   <input type="hidden" name="command" value="ExecTask"/>
   <input type="hidden" name="task_name" value="{$data['TASK_NAME']}" />
   <input type="hidden" name="alert_id" value="{$data['ALERT_ID']}" />
   <input type="hidden" name="act" value="health"/>
   <input type="hidden" name="do" value="showAlerts"/>
   </form>
   </td>
END;
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

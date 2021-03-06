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


class template_llogs {

    public $idsadmin;
    private $sz=0;

    function sysgentab_start_output( $title, $column_titles, $pag="")
    {
	      $this->idsadmin->load_lang("misc_template");
        $this->sz=sizeof($column_titles);


        $url=$this->idsadmin->removefromurl("orderby");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);


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
            if(isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
            {
                $img="<img src='images/arrow-up.gif' border='0' alt='{$this->idsadmin->lang('Ascending')}'/>";
                if (isset($this->idsadmin->in['orderway']))
                $img="<img src='images/arrow-down.gif' border='0' alt='{$this->idsadmin->lang('Descending')}'/>";
            }

            if( (isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
            && !(isset($this->idsadmin->in['orderway'])) )
            {
                $HTML .= "<a href='{$url}&amp;orderby=$index&amp;orderway=DESC' title='{$this->idsadmin->lang("OrderDesc", array($val))}'>{$val}{$img}</a>";
            } else {
                $HTML .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc", array($val))}'>{$val}{$img}</a>";
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
        $HTML = "<tr>";
        $cnt=1;
        foreach ($data as $index => $val)
        {
            switch(strtolower($index))
            {
                case 'filltime':
                    if ($val == "NotFull")
                    {
                        $HTML .= "<td>";
                        $HTML .= $this->idsadmin->lang("notFull");
                        $HTML .= "</td>";
                    } else {
                       $HTML .= "<td>$val</td>"; 
                    }
                    break;
                case 'flags':
                    if ($val == "UsedBackedUp" || $val == "UsedCurrent" || $val == "Used" || $val == "NewAdd")
                    {
                        $HTML .= "<td>";
                        $HTML .= $this->idsadmin->lang($val);
                        $HTML .= "</td>";
                    } else {
                       $HTML .= "<td>$val</td>"; 
                    }
                    break;
                default:
                    $HTML .= "<td>";
                    $HTML .= $val;
                    $HTML .= "</td>";
                    break;
            }
            if ($cnt++ >=$this->sz )
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



}

?>

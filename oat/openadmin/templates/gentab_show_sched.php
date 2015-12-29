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


class gentab_show_sched {

    public $idsadmin;
    private $sz=0;

    function sysgentab_start_output( $title, $column_titles, $pag="")
    {

        $this->sz=sizeof($column_titles);
        $this->idsadmin->load_lang("misc_template");
        $url=$this->idsadmin->removefromurl("orderby");
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
            if(isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
            {
                $img="<img src='images/arrow-up.gif' border='0' alt='{$this->idsadmin->lang('Ascending')}'/>";
                if (isset($this->idsadmin->in['orderway']))
                $img="<img src='images/arrow-down.gif' border='0' alt='{$this->idsadmin->lang('Descending')}'/>";
            }

            if( (isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby'] ==$index) && !(isset($this->idsadmin->in['orderway'])) ) {
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
        $HTML = "<tr>";
        $cnt=1;
        foreach ($data as $index => $val)
        {
            if( $cnt == 1)
            {
                $HTML .= "<td>";
                $HTML .= "<a href=\"index.php?act=health&amp;do=sched&amp;id=" .
                $data['TK_ID'] .
                "\">" .
                $data['TK_NAME'] .
                "</a>";
                $HTML .= "</td>";
            }
            else if( $cnt >=2 && $cnt <= 4)
            {
                $HTML .= "<td align='center'>";
                if ($cnt == 3 && trim($val) == "NEVER")
                {
                    $val = $this->idsadmin->lang("NEVER");
                }
                $HTML .= $val;
                $HTML .= "</td>";
            }
            else if( $cnt >=5 && $cnt <= 12)
            {
                $HTML .= "<td>";
                if ( $val == 0 )
                $HTML .= "<img src='images/cross.png' alt='{$this->idsadmin->lang('Disabled')}' title='{$this->idsadmin->lang('Disabled')}'/>";
                else
                $HTML .= "<img src='images/check.png' alt='{$this->idsadmin->lang('Enabled')}' title='{$this->idsadmin->lang('Enabled')}'/>";
                $HTML .= "</td>";
            }
            else
            {
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



}

?>

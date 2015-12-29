<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2007.  All Rights Reserved
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


class template_gentab {


private $sz=0;

function sysgentab_start_output( $title, $column_titles, $pag="")
{
$this->sz=sizeof($column_titles);
$HTML = <<<EOF
$pag
<div class="borderwrap">
<table class="gentab">
<tr>
<td class="tblheader" align="center" colspan="{$this->sz}">{$title}</td>
</tr>

EOF;
$HTML .= "<TR>";
foreach ($column_titles as $index => $val)
   {
    $HTML .= "<TH align='center'>";
    $HTML .= $val;
    $HTML .= "</TH>";
   }
$HTML .= "</TR>\n";
return $HTML;
}

function sysgentab_row_output($data)
{
$HTML = "<TR>";
$cnt=1;
foreach ($data as $index => $val)
   {
    $HTML .= "<TD>";
    $HTML .= $val;
    $HTML .= "</TD>";
    if ($cnt++ >= $this->sz )
       break;
   }
$HTML .= "</TR>\n";
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

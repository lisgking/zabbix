<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for Informix
 *  Copyright IBM Corporation 2011.  All rights reserved.
 **********************************************************************/


class hadv_temp_results {


private $sz=0;

function sysgentab_start_output( $title, $column_titles, $pag="")
{
$this->sz=sizeof($column_titles);
$HTML = <<<EOF
$pag
<div class="borderwrap">
<table width=60% class="gentab">
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

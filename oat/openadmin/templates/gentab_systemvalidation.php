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



class gentab_systemvalidation {
    
   private $rowcount;
    
   function __construct()
   {
       $this->rowcount = 0;
   }
	
   function sysgentab_start_output($title, $column_titles, $pag="")
   {
        $HTML .= <<<EOF
<div class="borderwrap">
<table class="gentab_nolines">
<tr>
<td class="tblheader" align="center" style="padding:6" >
{$title}
</td>
</tr>
EOF;

        return $HTML;

    }

    function sysgentab_row_output($data)
    {            
        $this->rowcount++;
          	
        foreach ($data as $index => $val)
        {
            $HTML .="<tr>";
            $HTML .= "<td>";
            $HTML .= $val;
            $HTML .= "</td>";
            $HTML .="</tr>";
        }
        return $HTML;
    }


    function sysgentab_end_output()
    {
        if ($this->rowcount == 0)
        {
            // If no results were returned (e.g. when a table format check was run on
            // a space with no tables), print a completed message so that the screen   
            // isn't blank.
            $HTML = "<tr><td>{$this->idsadmin->lang("oncheckComplete")}</td></tr>";       
        }
        $HTML .= <<<EOF
		</table>
		</div>
EOF;
        return $HTML;
    }
	
}

?>

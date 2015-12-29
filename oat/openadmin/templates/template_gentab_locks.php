<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2011.  All Rights Reserved
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

class template_gentab_locks{
	
	public $idsadmin;
	private $sz = 0;
	
	
	function sysgentab_start_output($title, $column_titles, $pag="")
	{
		$this->idsadmin->load_lang("misc_template");
		$this->sz = sizeof ( $column_titles);
		
		$url=$this->idsadmin->removefromurl("orderby");
        $url=preg_replace('/&'."orderway".'\=[^&]*/', '', $url);
		
        $html = <<<EOF
$pag
<div class="borderwrap">
<table class="gentab" >
<tr>
<td class="tblheader" align="center" colspan="{$this->sz}">{$title}</td>
</tr>
EOF;

		$html .= "<tr>";

		foreach ($column_titles as $index => $val)
        {
            $html .= "<td class='formsubtitle' align='center'>";
            $img="";
            if(isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index)
            {
                $img="<img src='images/arrow-up.gif' border='0' alt='{$this->idsadmin->lang('Ascending')}'/>";
                if (isset($this->idsadmin->in['orderway']))
                $img="<img src='images/arrow-down.gif' border='0' alt='{$this->idsadmin->lang('Descending')}'/>";
            }

            if( ( isset($this->idsadmin->in['orderby']) && $this->idsadmin->in['orderby']==$index) && !(isset($this->idsadmin->in['orderway'])) ) {
                $html .= "<a href='{$url}&amp;orderby=$index&amp;orderway=DESC' title='{$this->idsadmin->lang("OrderDesc", array($val))}'>{$val}{$img}</a>";
            } else {
                $html .= "<a href='{$url}&amp;orderby=$index' title='{$this->idsadmin->lang("OrderAsc", array($val))}'>{$val}{$img}</a>";
            }
            $html .= "</td>";
        }
        $html .= "</TR>";
        
        
        return $html;
    
	}
	
	
	function sysgentab_row_output($data)
	{
		$html = "<tr>";
		$cnt = 1;
				
		foreach ($data as $index => $val)
		{
			$val = trim($val);
			if(!isset($this->idsadmin->in["runReports"]) && ($index == "WAIT_SID" || $index == "OWNER_SID" || $index == "SID")) 
			{
				$html .= <<<EOF
<td>			
<a href="index.php?act=home&amp;do=sessexplorer&amp;sid={$val}">{$val}</a>
</td>
EOF;
			}
			else if(!isset($this->idsadmin->in["runReports"]) && $index == "LOCKCNT" && $val > 0) 
			{
				// If table lock count > 0, add link to the lock list for that table.
				$dbname = trim($data['DBSNAME']);
				$tabname = trim($data['TABNAME']);
				$html .= <<<EOF
<td>			
<a href="index.php?act=onstat&amp;do=locklist&amp;reportMode&amp;dbname={$dbname}&amp;tabname={$tabname}">{$val}</a>
</td>
EOF;
			} 
			else if(!isset($this->idsadmin->in["runReports"]) && $index == "SES_NUM_LOCKS" && $val > 0) 
			{
				// If session lock count > 0, add link to the lock list for that session.
				$sid = trim($data['SID']);
				$html .= <<<EOF
<td>			
<a href="index.php?act=onstat&amp;do=locklist&amp;reportMode&amp;sid={$sid}">{$val}</a>
</td>
EOF;
			}
			else
			{
				$html .= "<td>";
				$html .= $val;
				$html .= "</td>";
			}
			if ($cnt ++ >= $this->sz)
			{
				break;
			}
		}
		
		$html .= "</tr>";
		
		return $html;
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
	
}//end class

?>

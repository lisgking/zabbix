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


class template_space {

    public $idsadmin;

    function sysdbspace_start_output()
    {
        $HTML = "";
        $HTML .= <<<EOF
<div class="borderwrap">
<table width="100%" border="1" >
<tr>
<td class="tblheader" align="center" colspan="5">{$this->idsadmin->lang('DBSpaceInfo')}</td>
</tr>
<tr>
<TH>{$this->idsadmin->lang('DBSpaceNum')}</td>
<TH>{$this->idsadmin->lang('Name')}</tH>
<TH>{$this->idsadmin->lang('Size')}</tH>
<TH>{$this->idsadmin->lang('Free')}</tH>
<TH>{$this->idsadmin->lang('PageSize')}</tH>
</tr>
EOF;
return $HTML;
    }

    function sysdbspace_row_output($data)
    {
        $HTML = "";
        $HTML .= <<<EOF
<tr>
<td>{$data['DBSNUM']}</td>
<td>{$data['NAME']}</td>
<td>{$data['FREE_SIZE']}</td>
<td>{$data['DBS_SIZE']}</td>
<td>{$data['PGSIZE']}</td>
</tr>
EOF;
return $HTML;
    }

    function sysdbspace_end_output()
    {
        $HTML = "";
        $HTML .= <<<EOF
</table>
</div>
EOF;
        return $HTML;
    }

} // end class
?>

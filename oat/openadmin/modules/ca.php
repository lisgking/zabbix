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


/* Continuous Availability class */
class ca {

    public $idsadmin;
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_template("template_ca");
        $this->idsadmin->load_lang("ca");
    }

    /**
     * The run function is what index.php will call.
     * The decission of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     *
     */
    function run()
    {
         
        switch($this->idsadmin->in['do'])
        {
            default:
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('HighAvailClusters'));
                $this->idsadmin->setCurrMenuItem("mach11");
                $this->def();
                break;
        }
    } # end function run

    function def()
    {
    	// get information about the current OAT group
    	$grp_num = $this->idsadmin->phpsession->get_group();
    	$grp_name = $this->getGroupName($grp_num);
    	
    	$this->idsadmin->html->add_to_output($this->idsadmin->template["template_ca"]->renderCA($this->idsadmin->phpsession->get_lang(), $grp_num, $grp_name));
    }
    
    /**
     * Get the current OAT group name from the connections.db
     */
    function getGroupName($grp_num = "")
    {
    	if ($grp_num == "")
    	{
    		return "";
    	}
    	
    	require_once ( "lib/connections.php" );
    	$tempconndb = new connections($this->idsadmin);
        $db = $tempconndb->db;
    	
    	$qry = "SELECT group_name from groups where group_num = " . $grp_num;
		$stmt = $db->query($qry);
        $row = $stmt->fetch();
		
        $group_name = ""; 
		if (count($row) > 0)
		{
			$group_name = $row['GROUP_NAME'];
		}
		return $group_name;
    }

}
?>

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

class template_dashboard {

    public $idsadmin;

    function header($load_state="group", $lang="en_US")
    {
    	require_once ROOT_PATH."lib/feature.php";
    	$this->idsadmin->phpsession->set_serverInfo($this->idsadmin);
    	
    	$flashvars = "group_num={$this->idsadmin->phpsession->get_group()}"
    			   . "&conn_num={$this->idsadmin->phpsession->instance->get_conn_num()}"
    			   . "&load_state={$load_state}"
    			   . "&server_version={$this->idsadmin->phpsession->serverInfo->getVersion()}";
    			   
    	if ($this->idsadmin->phpsession->get_group() != 0)
    	{
    		$flashvars .= "&" . $this->getGroupSummaryTabFlashVars();
    	}
    	
    	$resourceModuleURLS = "swfs/Dashboard/Dashboard_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
        if ($lang == "en_US")
    	{
    	    $localeChain = "en_US";
    	} else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",swfs/Dashboard/Dashboard_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";  
    	}
    	$flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
        
        $HTML = "";

     $HTML .= <<< EOF
<script type="text/javascript" src="jscripts/dashboard.js"></script>
<div id="dash" style="width:100%;height:100%">
<table style="width:100%;height:100%">
<tr>
<td valign="top">
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="dashBoard"
				width="100%"
				height="500"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="swfs/Dashboard/DashBoard.swf" />
        	<param name="quality" value="high" />
        	<param name="allowFullScreen" value="true" />
             	<param name="bgcolor" value="#C2D0DD" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="flashvars" value="{$flashvars}" />

        	<embed src="swfs/Dashboard/DashBoard.swf"
        	    bgcolor="#C2D0DD"
    	    	quality="high"
				width="100%"
       		 	height="500"
        		name="dashBoard"
        		flashvars="{$flashvars}"
				align="middle"
				play="true"
				loop="false"
				quality="high"
				allowFullScreen="true"

				allowScriptAccess="sameDomain"
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer">
			</embed>
		</object>
</td>
</tr>
</table>
</div>
EOF;
        return $HTML;
    }

    function picker($lang="en_US")
    {
        $flashvars = "rooturl={$this->idsadmin->get_config('BASEURL')}";
        
        $resourceModuleURLS = "../swfs/Dashboard/Picker_en_US.swf,../swfs/lib/oat_en_US.swf,../swfs/lib/rdfwidgets_en_US.swf";
        if ($lang == "en_US")
    	{
    	    $localeChain = "en_US";
    	} else {
    	    $localeChain = "{$lang},en_US";
    	    $resourceModuleURLS .= ",../swfs/Dashboard/Picker_{$lang}.swf,../swfs/lib/oat_{$lang}.swf,../swfs/lib/rdfwidgets_{$lang}.swf";  
    	}
    	$flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
        
        $HTML = "";

     $HTML .= <<< EOF
<div id="dash" style="width:100%;height:100%">
<table style="width:100%;height:100%">
<tr>
<td valign="top">
		<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
				id="picker"
				width="100%"
				height="500"
				codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
        	<param name="movie" value="../swfs/Dashboard/Picker.swf" />
        	<param name="quality" value="high" />
        	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="flashvars" value="{$flashvars}" />
        	<embed src="../swfs/Dashboard/Picker.swf"
    	    	quality="high" bgcolor="#869ca7"
				width="100%"
       		 	height="500"
        		name="picker"
				align="middle"
				play="true"
				loop="false"
				quality="high"
     			flashvars="{$flashvars}"
				allowScriptAccess="sameDomain"
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer">
			</embed>
		</object>
</td>
</tr>
</table>
</div>
EOF;
        return $HTML;
    }
    
    /**
     * Get the flahsvars needed by the group summary tab.
     * 
     * The group summary tab needs to know if certain links 
     * on the menu are visible or not so that it only provides
     * drill-down links to pages that are enabled in this 
     * particular OAT installation. This funciton determines
     * that info and returns it in the format need to pass
     * it in the flashvars parameter.
     */
    private function getGroupSummaryTabFlashVars () 
    {
    	// Default everything to true
    	$showAlertsLink = "true";
    	$showOnlineLogLink = "true";
    	$showVPLink = "true";
    	$showMemoryLink = "true";
    	$showStorageLink = "true";
    	$showBackupLink = "true";
    	$showSessionLink = "true";
    	
    	// Find out from the connections.db if any of these menu items are not visible.
    	require_once ROOT_PATH."/lib/connections.php";
        $db = new connections($this->idsadmin);
        $qry = "select menu_name from oat_menu " 
        	 . "where menu_name in ('Alerts','Online Log','Virtual Processors','MemoryMgr','storage','Backup','Session Explorer') "
        	 . "and visible = 'false'";
        $stmt = $db->db->query($qry);
        $disabledItems = $stmt->fetchAll();
        foreach ($disabledItems as $menuItem)
        {
        	switch ($menuItem['MENU_NAME'])
        	{
        		case 'Alerts':
        			$showAlertsLink = "false";
        			break;
        		case 'Online Log':
        			$showOnlineLogLink = "false";
        			break;
        		case 'Virtual Processors':
        			$showVPLink = "false";
        			break;
        		case 'MemoryMgr':
        			$showMemoryLink = "false";
        			break;
        		case 'storage':
        			$showStorageLink = "false";
        			break;
        		case 'Backup':
        			$showBackupLink = "false";
        			break;
        		case 'Session Explorer':
        			$showSessionLink = "false";
        			break;
        	}
        }
       
        $flashvars = "showAlertsLink={$showAlertsLink}"
        		   . "&showOnlineLogLink={$showOnlineLogLink}"
        		   . "&showVPLink={$showVPLink}"
        		   . "&showMemoryLink={$showMemoryLink}"
        		   . "&showStorageLink={$showStorageLink}"
        		   . "&showBackupLink={$showBackupLink}"
        		   . "&showSessionLink={$showSessionLink}";
        return $flashvars;
    }

    function end()
    {
        $HTML = "";
        $HTML .= <<<EOF
        
EOF;
        return $HTML;
    }

}

?>
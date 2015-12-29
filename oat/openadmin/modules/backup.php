<?php
/*
 **************************************************************************   
 *  (c) Copyright IBM Corporation. 2011, 2012.  All Rights Reserved
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


/* Backup */
class backup {

    public $idsadmin;
    private $showInitialDialog = "false";
    private $showStatusTabIndSp = "false";
    private $oatConfigured = "false";
    private $storageManager = "Unknown";
    private $configuredTool = "None"; // onbar or ontape or None
    
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_template("template_backup");
        $this->idsadmin->load_lang("backup");
        $this->storageManager = $this->idsadmin->lang("Unknown");
    }

    /**
     * The run function is what index.php will call.
     * The decision of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     *
     */
    function run()
    {
        $this->checkVersion();
        
        switch($this->idsadmin->in['do'])
        {
        	// 'do' is backup
            default:
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('Backup'));
                $this->idsadmin->setCurrMenuItem("backup");
                /* Backup is supported only in Informix server types Standard, Primary or RSS
                 * and not supported in an SDS or HDR server.
                 */
                if ( $this->idsadmin->phpsession->serverInfo->isStandard() ||
                	 $this->idsadmin->phpsession->serverInfo->isPrimary() ||
                	 $this->idsadmin->phpsession->serverInfo->isRSS() )
                {
                	// get OAT Backup config, if any, from sysadmin
                	$this->getBackupConfig();
                	$this->def();
                } else {
                	$errmsg = $this->idsadmin->lang("noBackupSupport");
                	if ( $this->idsadmin->phpsession->serverInfo->isSecondary() ) {
                		$errmsg .= "<BR>" . $this->idsadmin->lang("hdrSecondary");
                		
                	} else if ( $this->idsadmin->phpsession->serverInfo->isSDS() ) {
                		$errmsg .= "<BR>" . $this->idsadmin->lang("sdsServer");
                	}
                	
                	$this->idsadmin->fatal_error($errmsg);
                }
                break;
                
        }
    } # end function run
    
    /**
     * Backup in OAT is only supported on 11.70.xC2 or later
     */
    function checkVersion() 
    {
    	require_once ROOT_PATH."lib/feature.php";
        if ( !Feature::isAvailable ( Feature::PANTHER_UC2, $this->idsadmin )  )
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang("noBackupSupportServerVersion"));
            return;
        }
    }
    
    function getBackupConfig()
    {
    	$qry = "SELECT name, value " .
               "FROM ph_threshold " .
               "WHERE task_name='OAT Backup task' AND name in ('OAT_INITIAL_DIALOG', 'OAT_BACKUP_CONFIGURED', 'BACKUP_TYPE')";
               
        $result = $this->idsadmin->doDatabaseWork($qry, 'sysadmin');
        
        // OAT_INITIAL_DIALOG = false, if user chooses not to see the dialog again ('true' value is not recorded)
        // OAT_BACKUP_CONFIGURED = true, if user has configured Backup from OAT
		
		if (count($result) == 0) {
        	$this->showInitialDialog = "true";
        } else {
        	foreach ($result as $row) {
			 	switch ($row['NAME']) {
			 		case 'OAT_INITIAL_DIALOG':
			 			$this->showInitialDialog = "false";
			 			break;
			 		case 'OAT_BACKUP_CONFIGURED':
			 			$this->oatConfigured = "true";
			 			break;
			 		case 'BACKUP_TYPE':
			 			$this->configuredTool = $row['VALUE'];
			 			break;
			 			
			 	} 
			}       	
        }
        
        // For server versions 12.10 and higher, we support configuring ON-Bar backups
        // from OAT, so get the ON-Bar storage manager information for the database server.
        require_once ROOT_PATH."lib/feature.php";
        if (Feature::isAvailable(Feature::CENTAURUS, $this->idsadmin))
        {
        	$this->getStorageManager();
        }
        
    }

     /*	This query was to show the individual space backup section on the Status Tab selectively - i.e only if
      * individual spaces were backed up separately (and not a whole system backup). Now we have decided
      * to show this section in all cases. One of the reasons being the Status tab is empty/bland without this.
      * Leaving this query here in case we decide the data in this section for a whole system backup is really not
      * useful and this section in the tab should be removed selectively.
      */
	// Get this during Backup startup to prevent another query from Flex.
	// Also in BackupServer.php:getConfigParams(): case "ontapeStatusSpaceSection" (for 'Refresh' from Flex)
	function showStatusTabSpSec()
	{
		$query = "select decode( count(unique level0) , count(*), 1, 0) as show_sp_sec " 
				  . "from sysmaster:sysdbstab where bitand(flags, '0x2000') = 0";
				  
		$showSp = $this->idsadmin->doDatabaseWork($query, 'sysmaster');
		
		$result = ($showSp[0]['SHOW_SP_SEC'] == '1')? "false" : "true";
		return $result;	
	}
	
	// get the storage manager that is configured for this Informix server
	function getStorageManager()
	{
		$task['COMMAND'] = "'onbar',";
		$task['PARAMETERS'] = "'-version all'";
		
		$onbarV = $this->idsadmin->executeSQLAdminAPICommand($task);
		
		// get the storage manager from "onbar -version all" output
		$this->parseOnbarV($onbarV['RESULT_MESSAGE']);
	}
	
	function parseOnbarV($onbarVersion)
	{
		$inpPattern = '/\nStorage Manager:.*\n/';
		
	    if (preg_match($inpPattern, $onbarVersion, $matches) == 1) {
		    $stmgr = preg_split('/:/',$matches[0]);
		    $stmgrVal = trim($stmgr[1]);
		    if (strlen($stmgrVal) > 0) {
		    	$this->storageManager = $stmgrVal;
		    } // else use default ('Unknown')
		} // else use default ('Unknown')
	
	}
	
    function def()
    {
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_backup"]->renderBackup($this->idsadmin->phpsession->get_lang(), 
        																								$this->showInitialDialog,
        																								$this->showStatusTabIndSp,
        																								$this->oatConfigured,
        																								$this->storageManager,
        																								$this->configuredTool));
    }

}
?>

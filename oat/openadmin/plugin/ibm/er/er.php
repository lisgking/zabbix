<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2008, 2012.  All rights reserved.
 ************************************************************************
 */



/* Enterprise Replication Plug-in */
class er {

    public $idsadmin;
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_lang("er");
        $this->idsadmin->load_template("template_er");
    }

    /**
     * The run function is what index.php will call.
     * The decission of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     *
     */
    function run()
    {
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("er"));
        $this->checkInstalled();
        $this->checkUser();
        
        // If this server does not yet participate in ER,
        // we need to render the swf in 'noER' state.
        $participatesInER = $this->checkForER();
        if (!$participatesInER && $this->idsadmin->in['do'] != "gridReplication")
        {
        	$this->idsadmin->in['do'] = "noER";
        }

        switch($this->idsadmin->in['do'])
        {
            case "node":
                $this->idsadmin->setCurrMenuItem("node");
                $this->renderER($this->idsadmin->in['do']);
                break;
            case "replicates":
                $this->idsadmin->setCurrMenuItem("replicates");
                $this->renderER($this->idsadmin->in['do']);
                break;
            case "gridReplication":
            	$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("GridRepl"));
                $this->idsadmin->setCurrMenuItem("gridRepl");
                if ( !Feature::isAvailable ( Feature::PANTHER, $this->idsadmin )  )
                {
                	$this->idsadmin->fatal_error($this->idsadmin->lang("noGridSupport"));
            		return;
                } else {
                	if ($participatesInER)
                	{
                		$this->renderGridRepl($this->idsadmin->in['do']);
                	} else {
                		$this->renderGridRepl("noER");
                	}
                }
                break;
            case "domain":
            case "noER":
            	default:
                $this->idsadmin->setCurrMenuItem("domain");
                $this->renderER($this->idsadmin->in['do']);
                break;
        }
    } # end function run

    /**
     * The ER plug-in can only be used if properly installed through the Plug-in 
     * Manager.  This method will verify that the ER plug-in was properly 
     * installed by looking for the file created during license acceptanace.
     */
    function checkInstalled() {
        $plugin_dir = substr($this->idsadmin->get_fullpath(),0,-6);
        if ( ! file_exists($plugin_dir. "99"))
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang('ERPluginNotInstalled'));
        }
    }
    
    /**
     * Replication plugin in OAT is only supported for informix or DBSA users.
     */
    function checkUser() 
    {
    	$valid_user = $this->idsadmin->checkForUserInformixOrDBSA();
    	if (!$valid_user)
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang("informixUserRequired"));
            return;
        }
    }
    
    /**
     * Check if the current server is participating in ER activity.
     * 
     * @return boolean indicating whether the server participates in ER
     */
    function checkForER()
    {
        $sysmaster = $this->idsadmin->get_database("sysmaster");
        $qry = "select count(*) as count from sysdatabases where name='syscdr'";
        $stmt = $sysmaster->query($qry);
        $count = 0;
        while ($res = $stmt->fetch(PDO::FETCH_ASSOC) )
        {
            $count = $res['COUNT'];
        }
        
        if ($count == 0)
        {
        	// Server does not participate in ER activity
            return false;
        } else {
        	// Server does participate in ER activity
        	return true;
        }
    }
    
    function renderER($do="domain")
    {
		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_er"]->renderER($do,$this->idsadmin->phpsession->get_lang()));
    }
    
    function renderGridRepl($do="domain")
    {
		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_er"]->renderGridRepl($do,$this->idsadmin->phpsession->get_lang()));
    }

}
?>

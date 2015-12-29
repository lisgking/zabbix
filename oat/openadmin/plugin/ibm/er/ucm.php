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


/* UCM (Unified Connection Manager - 11.70.xC3) */
class ucm {

    public $idsadmin;
    private $ifmx_dir;
    private $ifmx_serv;
    private $os_name;
    
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_template("template_ucm");
        $this->idsadmin->load_lang("ucm");
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
        $this->checkUser();
        
        switch($this->idsadmin->in['do'])
        {
        	// 'do' is ucm
            default:
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('ucm'));
                $this->idsadmin->setCurrMenuItem("ucm");
                $this->getServerInfo();
                $this->def();
                break;               
        }
    } # end function run
    
    /**
     * UCM is only supported on 11.70.xC3 or later
     */
    function checkVersion() 
    {
    	require_once ROOT_PATH."lib/feature.php";
        if ( !Feature::isAvailable ( Feature::PANTHER_UC3, $this->idsadmin )  )
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang("noCMSupportServerVersion"));
            return;
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
        	$this->idsadmin->load_lang("er");
            $this->idsadmin->fatal_error($this->idsadmin->lang("informixUserRequired"));
            return;
        }
    }
    
    function getServerInfo()
    {
    	$qry = "SELECT trim(env_name) as env_name, trim(env_value) as env_value, trim(os_name) as os_name " .
               "FROM sysenv, sysmachineinfo " .
               "WHERE env_name IN ('INFORMIXDIR', 'INFORMIXSERVER')";
               
        $result = $this->idsadmin->doDatabaseWork($qry, 'sysmaster');

        foreach ($result as $row) {
        	$this->os_name = $row['OS_NAME'];
		 	switch ($row['ENV_NAME']) {
		 		case 'INFORMIXDIR':
		 			$this->ifmx_dir = $row['ENV_VALUE'];
		 			break;
		 		case 'INFORMIXSERVER':
		 			$this->ifmx_serv = $row['ENV_VALUE'];
		 			break;
		 	} 
		}
    }    
    
    function def()
    {
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_ucm"]->renderUCM($this->idsadmin->phpsession->get_lang(),
        																						  $this->ifmx_dir,
        																						  $this->ifmx_serv,
        																						  $this->os_name));
    }

}
?>

<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2008, 2012.  All Rights Reserved
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

/**
 * This class is for switching to another user.
 * When user is using SQLToolbox module
 */

class switchuser {

    public $idsadmin;

    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_switchuser");
        $this->idsadmin->load_lang("switchuser");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('switchuser'));
    }

    function run()
    {
        // Based on the redirected "do" and "act", set menu highlighting to appropriate menu item 
        $last_url = $this->idsadmin->phpsession->get_lasturl();
        $last_do = "";
        $last_act = "";
        
        if(strpos($last_url,"do=")){
	        $last_do = substr($last_url,strpos($last_url, "do=") + 3);
	        if (strpos($last_do,"&"))
	        {
	            $last_do = substr($last_do,0,strpos($last_do, "&"));
	        }
        }
        
	    if(strpos($last_url,"act=")){
	    	$last_act = substr($last_url,strpos($last_url, "act=") + 4);
	        if (strpos($last_act,"&"))
	        {
	            $last_act = substr($last_act,0,strpos($last_act, "&"));
	        }
        }
        
        switch($last_act)
        {
        	case 'sqlwin':
		        switch ($last_do)
		        {
		            case 'schematab':
		            case 'tabletab':
		            case 'tableinfo':
		            case 'tablefrag':
		            case 'tablesel':
		            case 'spltab':
		                 $this->idsadmin->setCurrMenuItem("schemabrowser");
		                 break;
		            case 'sqltab':
		            case 'sqlrestab':
		            case 'sqltreetab':
		                 $this->idsadmin->setCurrMenuItem("sql");
		                 break;
			    	case 'connect':
		                 $this->idsadmin->setCurrMenuItem("databases");
		                 break;
		
		        }
        		break;
        	case 'qbe':
        		switch($last_do)
        		{
        			default:
        				$this->idsadmin->setCurrMenuItem("qbe");
        				break;
        		}
        		break;
        }
                
        switch($this->idsadmin->in['do'])
        {
            case 'showlogin':
                $this->showlogin();
                break;
            case 'dologin':
                $this->dologin();
                break;
            case 'logout':
            	$this->logout();
            	break;
	    default:
		$this->showlogin();
		break;
        }
    }
    
    function showlogin($err="")
    {
    	if($err!="")
    	{
    		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_switchuser"]->error($err));
    	}
    	$this->idsadmin->html->add_to_output($this->idsadmin->template["template_switchuser"]->showForm());
    }

    function logout()
    {
    	if( isset($_SESSION['SQLTOOLBOX_USERNAME'] )
    	 ||isset($_SESSION['SQLTOOLBOX_PASSWORD'] ) )
    	 {
        	unset($_SESSION['SQLTOOLBOX_USERNAME']);
			unset($_SESSION['SQLTOOLBOX_PASSWORD']);
         }
        
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect($this->idsadmin->lang('SwitchingUser'),"index.php?act=switchuser&do=showlogin"));
    }
    
    function dologin()
    {
    	$err = $this->sanity_check();
    	if ($err == ""){
    		$this->idsadmin->switch_user($this->idsadmin->in['username'],$this->idsadmin->in['passwd']);    		
    		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect($this->idsadmin->lang('SwitchingUser'),"{$this->idsadmin->phpsession->get_lasturl()}"));
    	}else{
    		$this->showlogin($err);
    	}
    }
    
    function sanity_check()
    {
    	
    	$username = isset($this->idsadmin->in['username'])?$this->idsadmin->in['username']:"";
    	$passwd = isset($this->idsadmin->in['passwd'])?$this->idsadmin->in['passwd']:"";
    	
    	if( ( $username == "" ) || ( $passwd == "" ) ) 
    	{
    		return $this->idsadmin->lang('empty');
    	}
    	
    	$dsn = "";
    	
    	try{
    		$host = $this->idsadmin->phpsession->instance->get_host();
    		$port = $this->idsadmin->phpsession->instance->get_port();
    		$server = $this->idsadmin->phpsession->instance->get_servername();
    		$protocol = $this->idsadmin->phpsession->instance->get_idsprotocol();
    		$dbname = "sysmaster";
    		
    		require_once (ROOT_PATH . "lib/PDO_OAT.php");
    		$db = new PDO_OAT($this->idsadmin,$server,$host,$port,$protocol,$dbname,$locale,$envvars,$username,$passwd);
    		
    		$db = null;
    	}catch(PDOException $e){
    		return $e->getMessage();
    	}
    		
    	return "";
    }
}
?>

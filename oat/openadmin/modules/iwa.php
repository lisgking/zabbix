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


/* IWA */
class iwa {

    public $idsadmin;
    
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_template("template_iwa");
        $this->idsadmin->load_lang("iwa");
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
        	// 'do' is iwa
            default:
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('iwa'));
                $this->idsadmin->setCurrMenuItem("iwa");
                $this->def();
                break;
                
        }
    } # end function run
    
    /**
     * IWA in OAT is only supported on 12.10 or later
     */
    function checkVersion() 
    {
    	require_once ROOT_PATH."lib/feature.php";
        if ( !Feature::isAvailable ( Feature::CENTAURUS, $this->idsadmin )  )
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang("notSupportedServerVersionIWA"));
            return;
        }
    }
	
    function def()
    {
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_iwa"]->renderIWA($this->idsadmin->phpsession->get_lang()));
    }

}
?>

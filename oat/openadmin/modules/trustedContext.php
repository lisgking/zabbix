<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2010.  All Rights Reserved
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
 * trustedContext
 */
class trustedContext {

    public  $idsadmin;

    /**
     * This class constructor sets
     * the default title and the
     * language files.
     *
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_trusted_context");
        $this->idsadmin->load_lang("trustedcontext");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle'));
    }

    /**
     * The run function is what index.php will call.
     * The decission of what to actually do is based
     * on the value of the $this->idsadmin->in['do']
     *
     */
    function run()
    {
        $this->idsadmin->setCurrMenuItem("trustedcontext");
        $this->def();
    }
	
    function def()
    {
    	$lang = $this->idsadmin->phpsession->get_lang();
    	if (Feature::isAvailable ( Feature::PANTHER, $this->idsadmin->phpsession->serverInfo->getVersion()))
    	{
    		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_trusted_context"]->render_trusted_context($lang));		
    	} else {
    		$this->idsadmin->fatal_error($this->idsadmin->lang("FeatureUnavailable"));
     	}
    }
     
} // end class
?>

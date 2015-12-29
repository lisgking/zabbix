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
 *  See the License for the specific language governing permissions andab
 *  limitations under the License.
 **************************************************************************
 */

/**
 * Memory Manager
 */
class memory {

	public $idsadmin;
	    
	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;
		$this->idsadmin->load_template("template_memory");
		$this->idsadmin->load_lang("memory");
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("MemoryMgr"));
	}

	/**
	 * The run function
	 */
	function run()
	{
		$this->idsadmin->setCurrMenuItem("MemoryMgr");
		
		if (!Feature::isAvailable(Feature::CHEETAH2, $this->idsadmin->phpsession->serverInfo->getVersion()))
		{
			$this->idsadmin->fatal_error($this->idsadmin->lang("MemoryFeatureUnavailable"));
		}
		
		$this->renderMemoryMgr();

	} // end function run
	
	/**
	 * Load the sqltrace swf using the template file
	 */
	function renderMemoryMgr()
	{
		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_memory"]->renderMemoryMgr($this->idsadmin->phpsession->get_lang()));
    }
}
?>

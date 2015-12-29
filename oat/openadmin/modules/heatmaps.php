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
 *  See the License for the specific language governing permissions andab
 *  limitations under the License.
 **************************************************************************
 */

/**
 * Heat Maps
 */
class heatmaps {

	public $idsadmin;
	    
	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;
		$this->idsadmin->load_template("template_heatmaps");
		$this->idsadmin->load_lang("onstat");
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang("HeatMaps"));
	}

	/**
	 * The run function
	 */
	function run()
	{
		include_once "lib/feature.php";
		if ( !Feature::isAvailable(Feature::PANTHER_UC4, $this->idsadmin))
		{
			$this->idsadmin->error($this->idsadmin->lang("HeatMaps_min_server_version"));
			return;
		}
		$this->renderHeatMaps();

	} // end function run
	
	/**
	 * Load the swf using the template file
	 */
	function renderHeatMaps()
	{
		$this->idsadmin->html->add_to_output($this->idsadmin->template["template_heatmaps"]->renderHeatMaps($this->idsadmin->phpsession->get_lang()));
    }
}
?>

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


class performance {

	public $idsadmin;

	# the 'constructor' function
	# called when the class "new'd"

	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;
		$this->idsadmin->load_lang("performance");
		$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('PerfPageTitle'));
	}

	/*
	 * the run function
	 * this is what index.php will call
	 * the decision of what to actually do is based on
	 * the value of 'act' which is either posted or getted
	 */

	function run()
	{
		switch($this->idsadmin->in['do'])
		{
			case "profilehistory":
			default:
				$this->idsadmin->setCurrMenuItem("PerformanceHist");
				$this->histgraphs();
				break;
		}
	} # end function run

	function histgraphs()
	{
		$flashvars = "servername={$this->idsadmin->phpsession->instance->get_servername()}";
		$conn_num = $this->idsadmin->phpsession->instance->get_conn_num();
		if ($conn_num == "")
		{
			// Use -1 to indicate that it is not a connection from the connections.db 
			// because user manually entered new connection details.
			$conn_num = -1;
		}
		$flashvars .= "&server_conn_num={$conn_num}";
		
		$lang = $this->idsadmin->phpsession->get_lang();
		$resourceModuleURLS = "swfs/performance/HistoryGraphs_en_US.swf,swfs/lib/oat_en_US.swf,swfs/lib/rdfwidgets_en_US.swf";
		if ($lang == "en_US")
		{
			$localeChain = "en_US";
		} else {
			$localeChain = "{$lang},en_US";
			$resourceModuleURLS .= ",swfs/performance/HistoryGraphs_{$lang}.swf,swfs/lib/oat_{$lang}.swf,swfs/lib/rdfwidgets_{$lang}.swf";
		}
		$flashvars .= "&localeChain={$localeChain}&resourceModuleURLs={$resourceModuleURLS}";
	    
		$blurb  = <<< EOF
		<div class="borderwrap">
<OBJECT CLASSID="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
 WIDTH="100%"
 HEIGHT="600"
 CODEBASE="http://active.macromedia.com/flash5/cabs/swflash.cab#version=9,0,0,0">
  <PARAM NAME="MOVIE" VALUE="swfs/performance/HistoryGraphs.swf">
  <PARAM NAME="PLAY" VALUE="true">
  <PARAM NAME="LOOP" VALUE="true">
  <PARAM NAME="QUALITY" VALUE="high">
          	<param name="bgcolor" value="#869ca7" />
        	<param name="allowScriptAccess" value="sameDomain" />
        	<param name="wmode" value="transparent" />
        	<param name="flashvars" value="{$flashvars}"/>
  <EMBED SRC="swfs/performance/HistoryGraphs.swf" WIDTH="100%" HEIGHT="500"
   PLAY="true" ALIGN="" LOOP="true" QUALITY="high"
   TYPE="application/x-shockwave-flash"
   flashvars="{$flashvars}"
   				quality="high"
				wmode="transparent"
				bgcolor="#869ca7"
   PLUGINSPAGE="http://www.macromedia.com/go/getflashplayer">
  </EMBED>
</OBJECT>
		</div>
EOF;
		$this->idsadmin->html->add_to_output ( $blurb );
		
	}

} // end class performance
?>

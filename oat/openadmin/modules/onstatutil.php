<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2009.  All Rights Reserved
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
 * This is the onstat utility module. This module allows you to run
 * onstat commands on IDS instances through OAT instead of logging in
 * interactively.  
 */

class onstatutil {

    /**
     * Each module should have an 'idsadmin' member , this gives access to the OAT API.
     */
    var $idsadmin;

    /**
     * Every class needs to have a constructor method that takes &$idsadmin as its argument
     * We are also going to load our 'language' file too.
     * @param Class $idsadmin
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        /**
         * load our language file.
         */
        $this->idsadmin->load_lang("onstatutil");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('mainTitle'));
    } // end of function __construct

    /**
     * Every class needs a 'run' method , this is the 'entry' point of your module.
     *
     */
    function run()
    {
            $this->idsadmin->setCurrMenuItem("onstat"); // set OAT menu item selection                

            // Onstat utility only available on server versions >= 11.50.xC3
            require_once("lib/feature.php");
            if ( ! Feature::isAvailable ( Feature::CHEETAH2_UC3, $this->idsadmin->phpsession->serverInfo->getVersion()  )  )
            {
                $this->idsadmin->fatal_error( $this->idsadmin->lang('OnstatFeatureNotAvail', array( Feature::getVersion ( Feature::CHEETAH2_UC3 ) ) ));
                $this->idsadmin->html->render();
                die();
            }

              

	    /*
	     * find out what the user wanted todo ..
	     */
	    $do = $this->idsadmin->in['do'];
	    
	    /**
	     * map our 'do' to a function.
	     */
	    switch ($do)
	    {
            default:
                $this->onstat($this->idsadmin->in['cmd']);
                break;
	    }
		
    } // end of function run

    function onstat($cmd="-g ses")
    {
        // defaults
        if ( isset( $this->idsadmin->in['cmd'] )  )
            {
            $cmd =  $this->idsadmin->in['cmd']; 
            }
        else
            {
            $cmd =  "-g ses";
            }

        // module header
$HTML .= <<<EOF
<TABLE CELLSPACING="3" CELLPADDING="0">
<TR>
<FORM method="post" action="index.php?act=onstatutil">
        <TD STYLE="vertical-align:middle;"><STRONG>onstat</STRONG></TD>
        <TD><INPUT type="text" NAME="cmd" VALUE="${cmd}" SIZE="20"/></TD>
        <TD><INPUT TYPE="submit" CLASS=button VALUE="{$this->idsadmin->lang('SubmitAction')}"/></TD>
</FORM>
<FORM method="post" action="index.php?act=onstatutil">
        <INPUT type="hidden" NAME="cmd" VALUE="--"/>
        <TD><INPUT TYPE="submit" CLASS=button VALUE="{$this->idsadmin->lang('OnstatOptions')}"/></TD>
</FORM>
</TR>
</TABLE>
EOF;
        $this->idsadmin->html->add_to_output( $HTML );
   
        // check constraints before running command
        if ( stristr($cmd, "-r") ) // catch -r
            {
            $this->idsadmin->html->add_to_output("<BR>");
            $this->idsadmin->html->add_to_output( $this->idsadmin->error("{$this->idsadmin->lang('ErrorPrepend')} {$this->idsadmin->lang('ErrorOnRepeat')}") );
            $cmd = "--"; // make onstat print out a list of available commands if an error occurred          
            } 
        else if ( stristr($cmd, "-i") ) // catch -i
            {
            $this->idsadmin->html->add_to_output("<BR>");
            $this->idsadmin->html->add_to_output( $this->idsadmin->error("{$this->idsadmin->lang('ErrorPrepend')} {$this->idsadmin->lang('ErrorOnInteractive')}") );
            $cmd = "--"; // make onstat print out a list of available commands if an error occurred          
            }   
        else if ( stristr($cmd, "-o") ) // catch -o
            {
            $this->idsadmin->html->add_to_output("<BR>");
            $this->idsadmin->html->add_to_output( $this->idsadmin->error("{$this->idsadmin->lang('ErrorPrepend')} {$this->idsadmin->lang('ErrorOnOutput')}") );
            $cmd = "--"; // make onstat print out a list of available commands if an error occurred          
            }                
        else if ( preg_match("/[^a-zA-Z0-9,\.\s-]/", $cmd) ) // catch any non-permitted characters (only alphanumeric, commas, dashes and periods allowed)
            {
            $this->idsadmin->html->add_to_output("<BR>");
            $this->idsadmin->html->add_to_output( $this->idsadmin->error("{$this->idsadmin->lang('ErrorPrepend')} {$this->idsadmin->lang('ErrorOnInvalidChar')} {$this->idsadmin->lang('ReportValidChars')}") );
            $cmd = "--"; // make onstat print out a list of available commands if an error occurred         
            }            
        else if ( strtolower(substr($cmd, 0, 6)) == "onstat" ) // inform user they don't have to redundantly enter onstat again
            {
            $this->idsadmin->html->add_to_output("<BR>");
            $this->idsadmin->html->add_to_output( $this->idsadmin->error("{$this->idsadmin->lang('ErrorPrepend')} {$this->idsadmin->lang('ErrorOnOnstat')}") );
            $cmd = "--"; // make onstat print out a list of available commands if an error occurred
            }

        // setup our query
        $qry  = "select task('onstat','{$cmd}')::char(32000) AS cmd from sysmaster:sysdual;";
        
        // set our database to sysadmin
        $db = $this->idsadmin->get_database("sysadmin");
        
        // get a handle to the query.
        $stmt = $db->query($qry);   
       
        // fetch the data from the select statement.
      	$res = $stmt->fetch();
      	
      	// print output
      	
      	$this->idsadmin->html->add_to_output( "<TABLE CELLSPACING=\"0\" CELLPADDING=\"0\"><TR><TD STYLE=\"padding-left:10px;\">" );
        $this->idsadmin->html->add_to_output( "<PRE>onstat {$cmd}</PRE>" );
        $this->idsadmin->html->add_to_output( "<PRE>" . htmlspecialchars($res['CMD']) . "</PRE>" );
      	$this->idsadmin->html->add_to_output( "</TR></TD></TABLE>" );        
    	
    } //end function onstat

    
} // end of class onstatutil
?>

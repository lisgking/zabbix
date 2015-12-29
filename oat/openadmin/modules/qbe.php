<?php
/***************************************************************************
 *  (c) Copyright IBM Corporation. 2009, 2012.  All Rights Reserved
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
 ***************************************************************************/


/* Query By Example */

class qbe {

    public $idsadmin;
    function __construct(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_lang("qbe");
        $this->idsadmin->load_template("template_qbe");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang("qbe"));
        $this->idsadmin->setCurrMenuItem("qbe");
    }

    /**
     * The run function is what index.php will call.
     */
    function run()
    {
        $this->idsadmin->setCurrMenuItem("qbe");
        if($this->idsadmin->get_config("SECURESQL","on") == "on"){
	        if ( ! isset( $_SESSION['SQLTOOLBOX_USERNAME'] )
		  	  || ! isset( $_SESSION['SQLTOOLBOX_PASSWORD'] )
		    )
			{
				$this->idsadmin->in['act']="switchuser";
				$this->idsadmin->in['do']="showlogin";	
				$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect("","index.php?act=switchuser&do=showlogin"));
			}
        }
        switch($this->idsadmin->in['do'])
        {
            default:
                $this->renderQBE();
                break;
        }
    } # end function run

    /**
     * Load the qbe swf using the template file
     */
    function renderQBE()
    {
       	$this->idsadmin->html->add_to_output($this->sqltoolbox_logout_button($_SESSION['SQLTOOLBOX_USERNAME']));
    	$this->idsadmin->html->add_to_output($this->idsadmin->template["template_qbe"]->renderQBE($this->idsadmin->phpsession->get_lang()));
    }
    
    /**
     * Outputs the html div section that prints username and logout button
     * param: $username - current user using the sqltoolbox. Should point to $this->idsadmin->phpsession->instance->get_username()
     */
	function sqltoolbox_logout_button($username)
    {
	    // Check if securesql config is set to on , if it's not set assume on by default.
	    // We only need to show sqltoolbox logout botton if securesql is on
	    if($this->idsadmin->get_config('SECURESQL' , "on") != "on" )
	    {
	    	return "";
	    }
	    
	    $this->idsadmin->load_lang("sqlwin");
	    $this->idsadmin->load_template("template_switchuser");
	    $html = $this->idsadmin->template["template_switchuser"]->sqltoolbox_logout_jscript();
    	$html .= <<<EOF
<table width="100%" role="presentation">
<tr>
	<td><strong>{$this->idsadmin->lang('qbe')}</strong></td>
	<td align="right">
		<form name="form_logoutbutton" method="post" action="index.php?act=switchuser&amp;do=logout">
			<strong>{$this->idsadmin->lang('LoggedInAs')}: {$username}</strong>
			<input type="button" class="button" value="{$this->idsadmin->lang('LogoutSQLToolbox')}"  onclick='sqltoolbox_logout();'/>
		</form>
	</td>
</tr>
</table>
EOF;
        return $html;
    }
}
?>

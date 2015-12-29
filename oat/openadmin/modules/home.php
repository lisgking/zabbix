<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007, 2012.  All Rights Reserved
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
 * The home page for IDSAdmin
 *
 */
class home {

    public $idsadmin;

    # the 'constructor' function
    # called when the class "new'd"

    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_lang("home");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('home'));
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
            case "piechart":
                $this->idsadmin->html->set_pagetitle ( $this->idsadmin->lang('ChartTest') );
                $this->doPieChart();
                break;
            case "chart":
                $this->idsadmin->html->set_pagetitle ( $this->idsadmin->lang('ChartTest') );
                $this->doChart();
                break;
            case "datachart":
                $this->idsadmin->html->set_pagetitle ( $this->idsadmin->lang('ChartTest') );
                $this->doDataChart();
                break;
            case "sessexplorer":
                $this->idsadmin->html->set_pagetitle( $this->idsadmin->lang('sessionexplorer') );
                $this->idsadmin->setCurrMenuItem("sessionexplorer");
                $this->doSessionExplorer();
                break;
            case "dashboard":
                $this->idsadmin->html->set_pagetitle ( $this->idsadmin->lang('dashboard') );
                $this->idsadmin->setCurrMenuItem("dashboard");
                $this->doDashboard();
                break;
            case "welcome":
                $this->idsadmin->setCurrMenuItem("home");
                $this->doWelcome();
                break;
            default:
                $this->idsadmin->setCurrMenuItem("home");
                $this->doHomePage();
                break;
        }
    } # end function run

    function doChart()
    {
        require_once("lib/Charts.php");

        $qry = "select  current::datetime  hour to second as category "
        .",lockreqs as series1, 'LOCK REQ' as series1_label"
        .",isreads as series2 , 'ISREADS' as series2_label"
        ." from syssesprof "
        ." order by 1";
        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setDbname("sysmaster");
        $this->idsadmin->Charts->setSelect(urlencode($qry));
        $this->idsadmin->Charts->setHeight(500);
        $this->idsadmin->Charts->Render();
    }

    function doPieChart()
    {
        require_once("lib/Charts.php");

        $qry  = " SELECT 'used' as SERIES1_LABEL , sum(chksize-nfree) as SERIES1 from syschktab ";
        $qry .= " WHERE bitval(flags, '0x200')=0 AND bitval(flags, '0x4000')=0 ";
        $qry .= " union ";
        $qry .= " select 'free' ,  sum(nfree)  FROM syschktab ";
        $qry .= " WHERE bitval(flags, '0x200')=0 AND bitval(flags, '0x4000')=0 ";


        $titles = array("PAGES","TYPE");

        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setDbname("sysmaster");
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('FlexChartDataSpaceTitle'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setDataTitles($titles);
        $this->idsadmin->Charts->setSelect(urlencode($qry));
        $this->idsadmin->Charts->Render();
    }

    function doDataChart()
    {
        require_once("lib/Charts.php");

        $arr = array("SERIES1" => "1" , "SERIES3" => "3");
        $this->idsadmin->Charts = new Charts($this->idsadmin);
        $this->idsadmin->Charts->setType("PIE");
        $this->idsadmin->Charts->setDbname("sysmaster");
        $this->idsadmin->Charts->setTitle($this->idsadmin->lang('FlexChartDataSpaceTitle'));
        $this->idsadmin->Charts->setLegendDir("vertical");
        $this->idsadmin->Charts->setWidth("100%");
        $this->idsadmin->Charts->setData($arr);
        $this->idsadmin->Charts->Render();

    }

    function doDashboard($load_state = null)
    {
    	// If load state was not passed in as a parameter, figure it out based 
    	// on the OAT home page configuration.
    	if ($load_state == null)
    	{
    		$home_page = $this->idsadmin->get_config("HOMEPAGE");
    		$load_state = "group";
    		if ($home_page == "dashboard_server")
    		{
    			$load_state = "server";
    		}
    	}
    	
        $this->idsadmin->load_template("template_dashboard");
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_dashboard"]->header($load_state, $this->idsadmin->phpsession->get_lang()));
    }

    function doSessionExplorer()
    {
        $this->idsadmin->load_template("template_sessions");
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_sessions"]->header($this->idsadmin->phpsession->get_lang()));
    }
    
    function doWelcome()
    {
    	$this->idsadmin->html->set_pagetitle($this->idsadmin->lang('welcome_page_title'));
        $this->idsadmin->load_template("template_welcome");
        $home_page = ($this->idsadmin->get_config("HOMEPAGE"))? $this->idsadmin->get_config("HOMEPAGE"):"welcome"; 
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_welcome"]->render_welcome($home_page, false, $this->idsadmin->phpsession->get_lang()));
    }

    /** Disabling this functionality.  See idsdb00232788
    function doMap()
    {
        $groupnum = $this->idsadmin->phpsession->get_group();

        // if we dont have a groupnum so show the dashboard..
        if ( $groupnum == "" )
        {
            return $this->doDashboard();
        }

        $this->idsadmin->load_template("template_map");

        require_once ROOT_PATH."/lib/connections.php";
        $grp = new connections($this->idsadmin);

        $stmt = $grp->db->query("select * from groups where group_num = {$groupnum} ");
        if ( $stmt )
        {
            $row = $stmt->fetchall();
            $this->idsadmin->html->add_to_output($this->idsadmin->template["template_map"]->header($row,1));
            $this->idsadmin->html->add_to_output($this->idsadmin->template["template_map"]->end());
        }
        else
        {
            // if we didnt get a stmt then show dashboard
            return $this->doDashBoard();
        }
    } // end doMap
    */
    
    /** 
     * Load the user's home page
     */
    function doHomePage()
    {
    	$homepage = "welcome";
        if ($this->idsadmin->get_config("HOMEPAGE") )
        {
            $homepage = $this->idsadmin->get_config("HOMEPAGE");
        }
        
        if ($homepage == "dashboard_group")
        {
        	$this->doDashBoard("group");
        } 
        else if ($homepage == "dashboard_server") 
        {
        	$this->doDashBoard("server");
        } 
        else if (substr($homepage,0,6) == "custom")
        {
        	$customHomePage = preg_replace('/_/',' ',substr($homepage,7));
        	
        	// Find the URL to the custom home page and redirect to that page.
        	require_once("lib/connections.php");
        	$conndb = new connections($this->idsadmin);
        	$stmt = $conndb->db->query("SELECT link FROM oat_menu WHERE menu_name = '{$customHomePage}' and visible = 'true'");
        	$rows = $stmt->fetchAll();
        	$stmt->closeCursor();
        	if (count($rows) > 0)
        	{
        		$url = html_entity_decode($rows[0]['LINK']);
        		$this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect("",$url));
        	} else {
        		// Now rows found!  It could be that the custom home page has since been disabled from the menu.
        		// If this happens, let's just take the user to the welcome page.
        		$this->doWelcome();
        	}
        }
        else 
        {
        	$this->doWelcome();
        }
    } // end doHomePage

} // end class home
?>

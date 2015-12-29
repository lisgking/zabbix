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
 * This class is used to track an individual link
 * in the information section
 *
 */
class prodlink {


    /**
     * the location of this link
     *
     * @var string
     */
    public $addr;
    /**
     * Short description of this link
     *
     * @var string
     */
    public $title;
    /**
     * Long description of this link
     *
     * @var string
     */
    public $desc;

    /**
     * The contstructor to create a link.
     * You must have at least the link location
     * and a short descirption.  The long desciption
     * will default to the short description.
     *
     * @param string $title
     * @param string $addr
     * @param string $desc
     * @return prodlink
     */
    function prodlink($title, $addr, $desc="")
    {
        $this->addr    = $addr;
        $this->title   = $title;
        if (empty($desc))
        $this->desc    = $this->title;
        else
        $this->desc    = $desc;
    }

    /**
     * Return the address of the link
     *
     * @return string
     */
    function getAddr() {
        return $this->addr;
    }

    /**
     * the long desciption of this link
     *
     * @return string
     */
    function getDesc() {
        return $this->desc;
    }
    /**
     * The short desciption or title of this link
     *
     * @return string
     */
    function getTitle() {
        return $this->title;
    }
}


/**
 * The main class for the info section.  It is here to
 * display a list of usefull information to the users.
 *
 */
class info {


    public $idsadmin;

    # the 'constructor' function
    # called when the class "new'd"

    /**
     * The "Constructor" function,  make sure
     * the title is set and the current language
     * is loaded
     *
     * @return info
     */
    function info(&$idsadmin)
    {
        $this->idsadmin = $idsadmin;
        $this->idsadmin->load_lang("info");
    }




    /**
     *    this is what index.php will call
     *    the decision of what to actually do is based on
     *    the value of 'act' which is either posted or getted
     *
     */
    function run()
    {
        switch($this->idsadmin->in['do'])
        {
            case 'ids';
                $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('linksTitle'));
                $this->idsadmin->html->add_to_output($this->setuptabs($this->idsadmin->in['do']));
                $this->idsadmin->setCurrMenuItem("UsefulLinks");
                $this->idspage();
            break;
            case 'iiug';
                 $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('linksTitle'));
                 $this->idsadmin->html->add_to_output($this->setuptabs($this->idsadmin->in['do']));
                 $this->idsadmin->setCurrMenuItem("UsefulLinks");
                 $this->iiugpage();
            break;
            case 'about';
                 $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('aboutTitle'));
                 $this->idsadmin->setCurrMenuItem("about");
                 $this->productinfo();
            break;
            default:
                $this->idsadmin->error( $this->idsadmin->lang('InvalidURL_do_param') );
                break;
        }
    }

    /**
     * Setup the Tabs on a page
     *
     * @param string $active
     * @return unknown
     */
    function setuptabs($active)
    {
         

        require_once ROOT_PATH."/lib/tabs.php";
        $t = new tabs();
        $t->addtab("index.php?act=info&amp;do=ids",$this->idsadmin->lang('IBMInfo'),
        ($active == "ids") ? 1 : 0 );
        $t->addtab("index.php?act=info&amp;do=iiug",$this->idsadmin->lang('IIUGInfo'),
        ($active == "iiug") ? 1 : 0 );

        #set the 'active' tab.
        $html  = ($t->tohtml());
        $html .= "<div class='borderwrapwhite'>";
        return $html;
    }


    /**
     * Display the main IDS page
     *
     */
    function idspage()
    {
        global  $p1list;

        $p1list = array();
        $p1list[] = new prodlink($this->idsadmin->lang('IBMInformixHomepage'),
        "http://www.ibm.com/software/data/informix/",
        $this->idsadmin->lang('IBMInformixProducts')
                );
        $p1list[] = new prodlink($this->idsadmin->lang('OATHomePage'),
        "http://www.openadmintool.org",
        $this->idsadmin->lang('OATHomePageDesc')
                );
        $p1list[] = new prodlink($this->idsadmin->lang('OATForum'),
        "http://www.iiug.org/forums/oat",
        $this->idsadmin->lang('OATForumDesc')
                );
        $p1list[] = new prodlink($this->idsadmin->lang('InfoCenter_v11.50'),
        "http://publib.boulder.ibm.com/infocenter/idshelp/v115/index.jsp",
        $this->idsadmin->lang('InfoCenter_v11.50_Desc')
                );
        $p1list[] = new prodlink($this->idsadmin->lang('InfoCenter_v11.70'),
        "http://publib.boulder.ibm.com/infocenter/idshelp/v117/index.jsp",
        $this->idsadmin->lang('InfoCenter_v11.70_Desc')
                );
        $p1list[] = new prodlink($this->idsadmin->lang('InfoCenter_v12.10'),
        "http://pic.dhe.ibm.com/infocenter/informix/v121/index.jsp",
        $this->idsadmin->lang('InfoCenter_v12.10_Desc')
                );
        $p1list[] = new prodlink($this->idsadmin->lang('InformixDevZone'),
        "http://www.ibm.com/developerworks/db2/zones/informix/",
        $this->idsadmin->lang('InformixDevZoneDesc')
                );
        $p1list[] = new prodlink($this->idsadmin->lang('IDSProdSupport'),
        "http://www-947.ibm.com/support/entry/portal/Overview/Software/Information_Management/Informix_Servers",
        $this->idsadmin->lang('IDSProdSupportPage')
                );
        $p1list[] = new prodlink($this->idsadmin->lang('InformixProdSupp'),
        "http://www-947.ibm.com/support/entry/portal/Overview/Software/Information_Management/Informix_Product_Family",
        $this->idsadmin->lang('InformixProdSuppPage')
                );

        $html='<div class="tabpadding">';
        $html.='<table width="90%" align="center">';

        foreach( $p1list as $val )
        {
            $html.=<<<END
         <tr>
            <td>
            <a href="{$val->getAddr()}"
               title="{$val->getDesc()}" target="_blank">{$val->getTitle()}</a>
            </td>
            <td> 
            {$val->getDesc()}
            </td>
         </tr>
END;
        }

        $html.='</table>';
        $html.='</div>';
        $html.='</div>';

        $this->idsadmin->html->add_to_output( $html );
    }


    /**
     * Display the main IIUG page
     *
     * To add another link add an entry onto the local
     * $iiug_list array.  The information will be
     * display on the page.
     *
     */
    function iiugpage()
    {
        global $iiug_list;


        $iiug_list = array();
        $iiug_list[] = new prodlink($this->idsadmin->lang('IIUGHome'),
        "http://www.iiug.org/",
        $this->idsadmin->lang('IIUGHomePage')
                );
        $iiug_list[] = new prodlink($this->idsadmin->lang('OATForum'),
        "http://www.iiug.org/forums/oat",
        $this->idsadmin->lang('IIUGSuppForumOAT')
				);
        $iiug_list[] = new prodlink($this->idsadmin->lang('IIUGForums'),
        "http://www.iiug.org/forums/informix-forum/",
        $this->idsadmin->lang('IIUGForumsDesc')
                );
        $iiug_list[] = new prodlink($this->idsadmin->lang('InformixEvents'),
        "http://www.iiug.org/calendar/events.html",
        $this->idsadmin->lang('InformixUserEventSchedule')
                );
        $iiug_list[] = new prodlink($this->idsadmin->lang('IIUGSponsorProgram'),
        "http://www.iiug.org/sponsors/index.php",
        $this->idsadmin->lang('IIUGSponsorProgramDesc')
                );

        $html='<div class="tabpadding">';
        $html.='<table width="90%" align="center">';

        foreach( $iiug_list as $val )
        {
            $html.=<<<END
         <tr>
            <td>
            <a href="{$val->getAddr()}" 
                 title="{$val->getDesc()}" target="_blank">{$val->getTitle()}</a>
            </td>
            <td> 
            {$val->getDesc()}
            </td>
         </tr>
END;
        }

        $html.='</table>';
        $html.='</div>';
        $html.='</div>';

        $this->idsadmin->html->add_to_output( $html );
    }

    /**
     * Displays OAT product information
     * including version and build timestamp
     */
    function productinfo()
    {
    	$html = <<<EOF
<div id='borderwrapwhite' align='center'>
<table class='productinfo'>
<tr>
<td class='tblheader' colspan='2' align="center">{$this->idsadmin->lang("productInfo")}</td>
</tr>
<tr>
<td class='formleft' align="center">{$this->idsadmin->lang("productName")}: </td>
<td class='formright'> {$this->idsadmin->lang('OATForIDSText')} </td>
</tr>
<tr>
<td class='formleft' align="center">{$this->idsadmin->lang("version")}: </td>
<td class='formright'> {$this->idsadmin->get_version()} </td>
</tr>
<tr>
<td class='formleft' align="center">{$this->idsadmin->lang("buildtimestamp")}: </td>
<td class='formright'> {$this->idsadmin->get_buildtime()} </td>
</tr>
<tr>
<td class='formright' align="center" colspan='2'>{$this->idsadmin->lang("copyright")}</td>
</tr>
<tr>
<td class='formright' align="center" colspan='2'><a href="index.php?act=help&amp;do=externalLinksNotice">{$this->idsadmin->lang("ExternalLinksNotice")}</a></td>
</tr>
<tr>
<td class='formright' align="center" colspan='2'><a href="index.php?act=help&amp;do=phpinfo" target="_blank">{$this->idsadmin->lang('PHPConfiguration')}</a></td>
</tr>
<tr>
</table>
</div>

EOF;
        $this->idsadmin->html->add_to_output($html);
    }
    
    /**
     * Displays PHP configuration information
     */
    function phpinfo() 
    {
    	echo <<< EOF

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html lang="{$this->idsadmin->html->get_html_lang()}" xml:lang="{$this->idsadmin->html->get_html_lang()}">
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<head>

<title>{$this->idsadmin->lang("PHPConfiguration")}</title>
</head>
<body>
EOF;
		phpinfo();
	
		echo <<< EOF
</body>
</html>
EOF;
		die();
    }
}
?>

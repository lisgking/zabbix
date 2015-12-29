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

/***********************************************
 * Class: output
 * Purpose: handles the displaying of html pages
 ************************************************/
class output {

    public  $idsadmin;       /* idsadmin class */
    public  $to_render;      /* what to display */

    public  $maintemplate;   /* the main template which denotes the page layout */
    private $pagetitle="";   /* the title of the page */
    private $debug;          /* debug output */
    private $error="";
    private $rss="";            /* RSS info for the link alternative */
    
    /******************************************
     * Constructor:
     *******************************************/
    function __construct($template="main.html")
    {
        $this->set_maintemplate($template);
    }#end __construct

    function add_to_rss($data)
    {
        $this->rss .= $data;
    }
    
    /******************************************
     * add_to_error:
     *  append to our error string..
     *******************************************/
    function add_to_error($data="")
    {
        $this->error .= $data;
    }#end add_to_output
    
    /******************************************
    * add_to_output:
    *  append html output to the web page
    *******************************************/
    function add_to_output($html="")
    {
        $this->to_render .= $html;
    }#end add_to_output

    /******************************************
     * set_pagetitle:
     *  set the browser page title
     *******************************************/
    function set_pagetitle($title="")
    {
        $this->pagetitle=$title;
    }

    /******************************************
     * get_pagetitle:
     *  return the current pagetitle
     *******************************************/
    function get_pagetitle()
    {
        return $this->pagetitle;
    }
    
    function get_rss()
    {
        return $this->rss;
    }
    
    /******************************************
     * get_html_lang:
     *  Get the setting for the HTML lang attribute.
     *  The HTML lang attribute only needs the primary
     *  language code (i.e. "en" instead of "en_US).
     *******************************************/
    public function get_html_lang() 
    {
    	$lang = $this->idsadmin->phpsession->get_lang();
    	$lang_arr = preg_split("/_/",$lang);
    	return $lang_arr[0];
    }

    /******************************************
     * set_maintemplate:
     *  setup the main output template
     *******************************************/
    function set_maintemplate($template)
    {
        $this->maintemplate=file_get_contents(ROOT_PATH."/templates/{$template}");
        if ( $this->maintemplate == "" )
        {
	    $this->idsadmin->load_lang("misc_template");
            die("{$this->idsadmin->lang('TemplateLoadError')}");
        }
    }

    /******************************************
     * render:
     *  output the page
     *******************************************/
    function render()
    {

        if ( $this->idsadmin->render === false )
        {
            return;
        }

        if ( ! $this->idsadmin->iserror )
        {
	        /* load the menu */
	        require_once(ROOT_PATH."/lib/menu.php");
	        $menu = new menu($this->idsadmin);
	        $this->maintemplate =
	        str_replace("<!--MENU-->",$menu->draw_menu(),$this->maintemplate);
        }
         

        /* display the http header */
        $this->idsadmin->template["template_global"]->httpheader();

        /* set the HTML lang */
        $this->maintemplate =
        str_replace("OAT_LANG",$this->get_html_lang(),$this->maintemplate);
        
        $this->maintemplate =
        str_replace("<!--TITLE-->",$this->get_pagetitle(),$this->maintemplate);

        if ($this->idsadmin->in['act'] == "admin")
        {
            $this->maintemplate =
            str_replace("favicon.ico","../favicon.ico",$this->maintemplate);
        }
 /*
        $this->maintemplate =
        str_replace("<!--RSS-->"
        ,$this->get_rss()
        ,$this->maintemplate);
*/
        $this->maintemplate =
        str_replace("<!--CSS-->"
        ,$this->idsadmin->template["template_global"]->css()
        ,$this->maintemplate);

        $this->maintemplate =
        str_replace("<!--JAVASCRIPT-->"
        ,$this->idsadmin->template["template_global"]->javascript()
        ,$this->maintemplate);

        $this->maintemplate =
        str_replace("<!--HEADER-->"
        ,$this->idsadmin->template["template_global"]->pageheader()
        ,$this->maintemplate);
        
        if ( $this->idsadmin->phpsession->isvalid() && ! IN_ADMIN )
        {
        $this->maintemplate = 
        str_replace("<!--COLLAPSE_MENU-->"
        ,$this->idsadmin->template["template_global"]->collapse_menu()
        ,$this->maintemplate);
        }
/*
        $this->maintemplate = 
        str_replace("<!--BREADCRUMB-->"
        ,$this->idsadmin->template["template_global"]->breadCrumb()
        ,$this->maintemplate);
*/       
        if ($this->idsadmin->phpsession->isValid()==true && $this->idsadmin->in['act'] != "admin")
        {
            $this->maintemplate =
            str_replace("<!--INFO-->"
       			 ,$this->idsadmin->template["template_global"]->connectedinfo()
            ,$this->maintemplate);

            /* display the ServerInfo block  only if this isnt an error */
            if ( $this->idsadmin->iserror  == false)
            {
                $this->maintemplate =
                str_replace("<!--BLOCK-->",$this->idsadmin->template["template_global"]->serverInfoBlock(),$this->maintemplate);
            }

        }

        $this->maintemplate =
        str_replace("<!--HELP-->"
        ,$this->idsadmin->template["template_global"]->help()
        ,$this->maintemplate);

        $this->maintemplate =
        str_replace("<!--CONTENT-->",$this->to_render,$this->maintemplate);

        /*
         $this->maintemplate = str_replace("<!--MENU-->",$menu->draw_menu(),$this->maintemplate);
         */
        $this->maintemplate =
        str_replace("<!--FOOTER-->"
             ,$this->idsadmin->template["template_global"]->pagefooter()
        ,$this->maintemplate);

        $this->maintemplate =
        str_replace("<!--STATS-->"
             ,$this->idsadmin->template["template_global"]->stats()
        ,$this->maintemplate);

        print $this->maintemplate;
         
        $this->render_error();
        $this->render_debug();
         
    }#end render
    
    //TODO : pretty the error display ..
    function render_error()
    {
         
        if ( $this->error != "")
        {
            print $this->error;
        }
    } // end render_error
     
    //TODO: pretty the debug display
    function render_debug()
    {
        if ( DEBUG )
        {
            print $this->debug;
        }
    } // end render_debug

    /******************************************
    * display_report: output a report
    *******************************************/
    function display_report()
    {
        /* display the http header */
        $this->idsadmin->template["template_global"]->httpheader();
        $this->to_render = $this->idsadmin->template["template_global"]->css()."</head>".$this->to_render;
        echo $this->to_render;
        $this->render_error();
        $this->render_debug();
        exit();
    }

    

    function debug($str="",$file="",$line="")
    {
        $this->debug .= "Debug: {$file}:{$line} - {$str}<br/>";
    }
    /******************************************
     * Destruct:
     *******************************************/
    function __destruct()
    {

    } #end __destruct

    // Creates a select box from the first two columns of
    // an sql statement
    // name		the lable of the data
    // sql          the sql statement to be executed
    // dbsname      the database to execute the select off of.
    // default      the default option
    // size         the list size
    //
    // NOTE You must end the SELECT tag

    function selectList($name, $sql, $dbsname="sysmaster",
    $default="", $size="1", $id="" )
    {

        $dbadmin = $this->idsadmin->get_database($dbsname);

        $stmt = $dbadmin->query($sql);

        $html=<<<END

<select id="{$id}" name="{$name}" size="{$size}">

END;

        while ($res = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $value = current( $res );
            $ptext = next( $res );
            if (  ( strcasecmp( $value, $default) == 0 ) ||
            ( strcasecmp( $ptext, $default) == 0 )  )
            {
            	$sel=" selected='selected' ";
            } else {
            	$sel=" ";
            }
            $html.=<<<END

        <option value="{$value}" {$sel} >{$ptext}</option>
END;
        }

        return $html;
    }


    // Creates a select box from the first two columns of
    // an sql statement
    // form		the name of the form
    // name		the lable of the data
    // sql          the sql statement to be executed
    // dbsname      the database to execute the select off of.
    // default      the default option
    // size         the list size
    //
    // NOTE You must end the SELECT tag

    function autoSelectList($formname, $name, $sql,
    $dbsname="sysmaster", $default="", $size="1" )
    {

        $dbadmin = $this->idsadmin->get_database($dbsname);

        $stmt = $dbadmin->query($sql);

        $html=<<<END

<select name="{$name}" size="{$size}" onchange="{$formname}.submit()">

END;

        while ($res = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $value = current( $res );
            $ptext = next( $res );
            if (  ( strcasecmp( $value, $default) == 0 ) ||
            ( strcasecmp( $ptext, $default) == 0 )  )
            {
            	$sel=" selected='selected' ";
            } else {
            	$sel=" ";
            }
            $html.=<<<END

        <option value="{$value}" {$sel} >{$ptext}</option>
END;
        }

        return $html;
    }
     
} //end class output
?>

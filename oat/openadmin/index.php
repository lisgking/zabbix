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

#error_reporting (E_WARNING);
#error_reporting (E_ALL);
define('IN_ADMIN',false);
/**
 * register a shutdown handler function - 
 */ 
register_shutdown_function("shutdownHandler");

$start_time = time();
require_once("lib/initialize.php");
require_once("lib/idsadmin.php");

$idsadmin = new IDSAdmin();
/* Presume path is valid until we find out otherwise below */
$idsadmin->set_fullpath(check_path_name( "login" , $idsadmin ));

/* Check if user changed UI language */
if ( isset($idsadmin->in['lang']) )
{
    $idsadmin->html->debug("LANG: {$idsadmin->in['lang']}");
    $idsadmin->phpsession->set_lang($idsadmin->in['lang']);
} 

/* Check if user change database locale settings (for Replication plug-in) */
if ( isset($idsadmin->in['dblclang']) )
{
    $idsadmin->html->debug("DBLCLANG: {$idsadmin->in['dblclang']}");
    $idsadmin->phpsession->set_dblclang($idsadmin->in['dblclang']);
    // When the user changes the locale language, the locale name should be reset
    $idsadmin->phpsession->set_dblcname(null);
    $idsadmin->phpsession->set_dblc_avail_locale_list(null);
} 
else if ( isset($idsadmin->in['dblcname']) )
{
    $idsadmin->html->debug("DBLCNAME: {$idsadmin->in['dblcname']}");
    $idsadmin->phpsession->set_dblcname($idsadmin->in['dblcname']);
}

/* Load language (requires set_fullpath to be called first) */
$idsadmin->load_lang("index");
if ( version_compare(PHP_VERSION, "5.2.4", "<") )
{
    $idsadmin->fatal_error ($idsadmin->lang('MinPHPVersion').PHP_VERSION,false);
}

if ( $idsadmin->in['act'] != "login"
&& $idsadmin->in['act'] != "help"
&& $idsadmin->in["act"] != "switchuser")
{
    $idsadmin->saveurl();
}

if ( ($idsadmin->phpsession->isValid() == false)
&& $idsadmin->in["act"] != "admin"
&& $idsadmin->in["act"] != "help"
&& $idsadmin->in["act"] != "switchuser")
{
    if ( $idsadmin->in['act'] != "login")
    {
        $idsadmin->saveurl();
    }
    $idsadmin->in["act"]="login";
}

/**
 * If we dont have any 'input' check our saveurl then default to the home page.
 */
if ( ! isset($idsadmin->in["act"]) || $idsadmin->in["act"]=="")
{
    $idsadmin->html->debug("Go to home");
    $idsadmin->in["act"]="home";
}


/**
* see if we can connect , but only if we are not logging out 
*/
if ( ($idsadmin->phpsession->isValid() === true)
&& (  isset($idsadmin->in['do']) && $idsadmin->in['do'] != "logout"
&& $idsadmin->in['act'] != "admin"
&& $idsadmin->in['act'] != "help"
&& $idsadmin->in['do'] != "loginnopass") )
{
    $db = $idsadmin->get_database("sysmaster");

    /* lets setup our serverInfo */
    $idsadmin->phpsession->set_serverInfo($idsadmin);

    /* Is the version of the server compatiable with OAT */
    if ( ! Feature::isAvailable ( Feature::CHEETAH, $idsadmin->phpsession->serverInfo->getVersion()  )  )
    {
        $idsadmin->fatal_error( $idsadmin->lang('OATRequiredVersionError') );
        $idsadmin->html->render();
        die();
    }
}

/**
 * Check the module exists.
 */
$pname = check_path_name( $idsadmin->in["act"] , $idsadmin );
if ( empty( $pname ) )
{
    $idsadmin->fatal_error($idsadmin->lang('SorryModule', array("{$idsadmin->in['act']}.php")));
    $idsadmin->html->render();
    die();
}
else
{
	$idsadmin->set_fullpath($pname);
}

$cname = $idsadmin->get_classname();
if ( method_exists($cname,"run") == false)
{
	$idsadmin->fatal_error( $idsadmin->lang('NoModuleRunFunction', array($idsadmin->in['act'])));
	$idsadmin->html->render();
    die();
}

// If the module is a plugin, check and enforce it's minimum server version now.
if ($idsadmin->isPlugin())
{
	$idsadmin->checkPluginMinServerVersion();
}


/******************************************
 * Load the module and run its run method.
 ******************************************/

$idsadmin->html->debug("{$idsadmin->lang('ACT')}: " . " {$idsadmin->in['act']} " . "");
$idsadmin->set_redirect("");
$runit = new $cname($idsadmin);
@$runit->run();

$idsadmin->html->debug("{$idsadmin->lang('DO')}: " . " {$idsadmin->in['do']} " . "");

if ( (isset($idsadmin->in["runReports"]))
||($idsadmin->in['act']=="sqlwin_pop") )
{
    $idsadmin->html->display_report();
}

$idsadmin->html->debug( "{$idsadmin->lang('GenTime')}: ".(time() - $start_time) );

/**
 * If not doing a graph or report then display the page.
 */
if ($idsadmin->in["act"] != "graph" && $idsadmin->in['do'] != "logout" )
{  
    $idsadmin->html->render();
}

/**
 * Cleanup the tmp directory.
 */

$idsadmin->cleanuptmp();

//Our register_shutdown_function handler
//In here we will check if the script aborted due to a timeout , if it did 
//lets logout the user and print a message saying so..
function shutdownHandler()
{
    
    //ignore_user_abort(true);
    
    if ( connection_status() == CONNECTION_TIMEOUT )
    {
       // Note: we cannot localize this message since we don't have access to the 
       // $idsadmin object in the shutdownHandler.

        $mess = <<< EOF
It would appear that OAT has received a script timeout while executing.
Click <a href="index.php?act=login&amp;do=logout">here</a> to continue.
EOF;
        echo $mess;
        return;
    }



}

/**
 * 1. check to see if we are walking backwards
 * 2. Check to see if this is an extend module
 * 3. Check to see if the file exists
 * 4. Load the code for this module.
 */
function check_path_name( $actname , &$idsadmin)
{
	$fullname="";

	 /* requires the three = check */
	 if ( strpos($actname,"..") === true)
	     return "";
	 if ( strpos($actname,"/") === false) 
		 {
		 $fullname = ROOT_PATH."modules/{$actname}.php";
		 }
	 else
		 {
		 $fullname = ROOT_PATH."plugin/{$actname}.php";
		 $idsadmin->set_isPlugin(1);	
		 }
		 
	 if ( ! file_exists($fullname) )
		{
		return "";	
		}
	require_once ( $fullname );
	return $fullname;
}

/**
 * From the fullpath build the class name
 * Check for the required run function
 */
function check_class_name( $fullpath )
{
	$cname = substr(strrchr($fullpath, "/"), 1);
	
	$cname = substr($cname,0,-4);   // remove the .php extention
	
	if ( method_exists($cname,"run") == false)
		return "";
	
	return $cname;
}

?>

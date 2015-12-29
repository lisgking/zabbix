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
 * Help class for the context sensitive help
 *
 */
class help {

	public $idsadmin;

	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;
		$this->idsadmin->load_template("template_help");
		$this->idsadmin->load_lang("help");
	}//end help - constructor

	function run()
	{
		switch($this->idsadmin->in['do'])
		{
			case 'aboutOAT':
				$this->doAboutOAT();
				break;
			case 'externalLinksNotice':
				$this->showExternalLinksNotice();
				break;
			case 'phpinfo':
				$this->phpinfo();
				break;
			case 'showDocument':
			    $this->showDocument();
			    break;			    
			default:
				$this->dohelp();
				break;
		}

	} // end run

	############################################################################
	# Function: dohelp
	# Looks up the actual help from the sqlite db and displays it.
	############################################################################

	function dohelp($lang="")
	{
		if ($lang == "")
		{
		    $lang = $this->idsadmin->phpsession->get_lang();
		}

		$helpact = $this->idsadmin->in['helpact'];
		$helpdo  = $this->idsadmin->in['helpdo'];
       
		$l = substr($helpact,0, strrpos($helpact, "/"));
		if ( ! strstr($helpact,"/") )
		{
			$dbpath = "{$this->idsadmin->get_config('HOMEDIR')}/lang/{$lang}";
		}
		else
		{
			$dbpath = "plugin/{$l}/lang/{$lang}";
			$helpact = substr($helpact,strrpos($helpact,"/")+1);
		}
		
		$dbfile = "{$dbpath}/idsadminHelp.db";
		if ( ! file_exists($dbfile) )
		{
			// If helpdb does not exist for non-English language, then open the English help db
			if ($lang != "en_US")
			{
			    $this->dohelp("en_US");
			    return;
			} else {
			    $this->idsadmin->fatal_error ($this->idsadmin->lang('ErrorOpeningDB') . ": {$dbfile}", false);
			}
		}
		
		$helpdb =  new PDO("sqlite:{$dbfile}");
		if ( $helpdb == "" )
		{
			$this->idsadmin->fatal_error ($this->idsadmin->lang('ErrorOpeningDB'), false);
		}
		
		$res = $helpdb->query("select desc, helpact, helpdo from help where helpact='{$helpact}' and helpdo='{$helpdo}'");
			
		$res = $res->fetch();
		if ($res['helpact']=="")
		{
		    // If help element does not exist for non-English language, then try opening this help element in English
			if ($lang != "en_US")
			{
			    $this->dohelp("en_US");
			    return;
			} else {
			    $res['desc']= $this->idsadmin->lang('SorryNoHelpFound');
			}
		}
		print($this->idsadmin->template["template_help"]->show_help($res));
		exit;
	}
	
	/**
	 * Function: doAboutOAT
	 * Displays the 'About OAT' product info in a help pop-up window.
	**/
	function doAboutOAT()
	{
		include_once("modules/info.php");
		$info = new info($this->idsadmin);
		$info->productinfo();
	}	
	
	/**
	 * Function: showExternalLinksNotice
	 * Shows notice about external links to non-IBM websites
	**/
	function showExternalLinksNotice()
	{
		$notice_html = file_get_contents("license/external_links_notice.html");
		$HTML = <<< EOF
<div class="borderwrap">
<table width="100%">
<tr>
<td class="formright">
{$notice_html}
</td>
</tr>
</table>
</div>
EOF;

		$this->idsadmin->html->add_to_output($HTML);
	}	
	
	/**
	 * Function: showDocument
	 * Displays the specified help HTML document.
	 */
	function showDocument()
	{
	    // Path will be to the English help file
	    $path = $this->idsadmin->in['document'];
	    
	    // If OAT is running in a language other than English, see if a translated document exists.
	    // If the translation exists, load that file.  If not, load the English version.
	    $lang = $this->idsadmin->phpsession->get_lang();
	    if ($lang != "en_US")
	    {
	        if (strpos($path,'/') === false)
	        {
	            // If there is no '/' character, we just need to append lang/{$lang}/ on to the front of the path
	            $temp_path = "lang/{$lang}/" . $path;
	        } else {
    	        // Otherwise, we need to insert lang/{$lang} as the last part of the path before .html file name
    	        // e.g. $path = plugin/ibm/er/howto_er.html --> $temp_path = plugin/ibm/er/lang/it_IT/howto_er.html
	            $temp_path2 = strrchr($path,'/');
    	        $temp_path1 = substr($path,0,strlen($path) - strlen($temp_path2));
    	        $temp_path = $temp_path1 . "/lang/{$lang}" . $temp_path2;
	        }
	        if (file_exists(ROOT_PATH."/$temp_path"))
	        {
	            $path=$temp_path;  
	        }
	    }
	    
	    // Load the document and print the contents
	    $doc = file_get_contents(ROOT_PATH."/{$path}");
	    print $doc;
	    exit;
	}
	
	/**
	 * Function: phpinfo
	 * Displays the PHP configuration information in a new tab.
	**/
	private function phpinfo() 
	{
		include_once("modules/info.php");
		$info = new info($this->idsadmin);
		$info->phpinfo();
	}

} // end class

?>

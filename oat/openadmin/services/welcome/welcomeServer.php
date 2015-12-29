<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation, 2012.  All Rights Reserved
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

/* Services for Welcome page */

class welcomeServer {

	var $idsadmin;

	function __construct()
	{
		define ("ROOT_PATH","../../");
		define( 'IDSADMIN',  "1" );
		define( 'DEBUG', false);
		define( 'SQLMAXFETNUM' , 100 );

		include_once("../serviceErrorHandler.php");
		set_error_handler("serviceErrorHandler");

		require_once(ROOT_PATH."lib/idsadmin.php");
		$this->idsadmin = new idsadmin(true);
	}
	
	/**
	 * Get the menu options for setting a custom home page.
	 */
	public function getCustomHomePageOptions()
	{
		require_once ( "../idsadmin/clusterdb.php" );
		$conndb = new clusterdb ( );
		
		// Load the menu lang files, including the plugin menu lang files
		$this->idsadmin->load_lang("menu");
		$stmt = $conndb->query("SELECT plugin_dir FROM plugins WHERE plugin_enabled = 1 GROUP BY plugin_id ");
		while ( $row = $stmt->fetch(PDO::FETCH_ASSOC) )
		{
			$this->idsadmin->load_plugin_menu_lang($row['plugin_dir']);
		}
		$stmt->closeCursor();
		
		// Query for home page options from the menu.
		$sql = <<<EOF
select menu.menu_name as name, menu.lang as lang, parent.lang as parent_lang 
from oat_menu menu, oat_menu parent
where menu.parent = parent.menu_id
and menu.link != '' 
and menu.visible = 'true' 
and menu.menu_name != 'Help' 
and menu.parent != (select menu_id from oat_menu where menu_name = 'Help') 
order by parent.menu_pos, menu.menu_pos;
EOF;
		
		$stmt = $conndb->query($sql);
		$res = $stmt->fetchAll();
		
		// Retrieve menu labels from lang files
		foreach ($res as &$row)
		{
			$row['label'] = $this->idsadmin->lang($row['parent_lang']) . " > " . $this->idsadmin->lang($row['lang']); 
		}
		
		return $res;
	}
	
	/**
	 * Save the selected home page in the config.php file.
	 */
	public function saveHomePage ($new_home_page) 
	{
		$this->idsadmin->load_lang("admin");
		$conf_vars = $this->idsadmin->get_config("*");
		
		// create backup of config file
		$src=$conf_vars['HOMEDIR']."/conf/config.php";
		$dest=$conf_vars['HOMEDIR']."/conf/BAKconfig.php";
		copy($src,$dest);
		
		// open the config file
		if (! is_writable($src))
		{
			trigger_error($this->idsadmin->lang("SaveCfgFailure"). " $src");
			return;
		}
		$fd = fopen($src,'w+');
		// write out the config
		fputs($fd,"<?php \n");
		foreach ($conf_vars as $k => $v)
		{
			if ($k == "HOMEPAGE")
			{
				$v = $new_home_page;
			}
			else if ($k == "CONNDBDIR" || $k == "HOMEDIR") 
			{
				// Replace backslashes in paths with forward slashes
				$this->idsadmin->in[$k] = str_replace('\\', '/', $this->idsadmin->in[$k]); 
			}
			$out = "\$CONF['{$k}']=\"{$v}\";#{$this->idsadmin->lang($k)}\n";
			fputs($fd,$out);
		}
		fputs($fd,"?>\n");
		fclose($fd);
		
		return $new_home_page;
	}
	
}

?>

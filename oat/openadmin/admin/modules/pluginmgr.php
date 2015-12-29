<?php
/*
 *************************************************************************
 *  (c) Copyright IBM Corporation. 2008, 2012.  All Rights Reserved
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

class pluginManager {

	public $idsadmin;
	private $conndb;
	private $oat_install_mode = false;
        private $silent = false;

	function __construct(&$idsadmin = null)
	{
	    // If $idsadmin is null, we are installing plugin in oat_install_mode
	    // (i.e. during OAT installation process).
	    if ($idsadmin == null)
	    {
	        $this->oat_install_mode = true;
	        return; 
	    }
	    
		$this->idsadmin = $idsadmin;

		require_once ROOT_PATH."/lib/connections.php";

		$this->idsadmin->load_template("template_pluginmgr");
		$this->idsadmin->load_lang("pluginmgr");
		$this->idsadmin->html->set_pagetitle( $this->idsadmin->lang('pluginmgr') );

		$cb = new connections($this->idsadmin);
		$this->conndb = $cb->db;
	}
	
	// This function is used when $this->oat_install_mode is true to set conndb
	function setConndb($conndb)
	{
	    $this->conndb = $conndb;
	    $this->conndb->setAttribute(PDO::ATTR_CASE,PDO::CASE_UPPER);
	}

	function run($silent = false)
	{
		$this->silent = $silent;
		// check the php zip module is loaded.
		if ( $this->has_zip() === false )
		{
			return $this->need_zip();
		}
        
	    if ( is_writable( "../tmp" ) === false )
	    {
	        return $this->idsadmin->error($this->idsadmin->lang('CantWriteToOATDir'));
	    }
		
		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->plugin_javascript() );
		switch($this->idsadmin->in['run'])
		{
			case "upgradeplugin":
			case "installplugin":
				$this->installplugin($this->silent);
				break;
			case "uninstallplugin":
				$this->uninstallplugin();
				$this->showPlugins();
				break;
			case "toggleEnabled":
				$this->toggleEnabled();
				break;
			case "accept":
				$this->accept();
				break;
			case "reject":
				$this->reject();
				break;
			default:
				$this->def();
				break;
		}
	}

	/**
	 * default function - a catch all
	 *
	 */
	function def()
	{
		$this->showPlugins();
	}

	/**
	 * display an error if the zip module isnt loaded.
	 *
	 */
	function need_zip()
	{
		$this->idsadmin->error($this->idsadmin->lang('PHPZipExtReqd'));
	}

	/**
	 * check if the php zip module is loaded
	 *
	 * @return boolean
	 */
	function has_zip()
	{
		$plugins = get_loaded_extensions();
		if (in_array("zip", $plugins ) )
		{
			unset($plugins);
			return true;
		}
		return false;
	}

	/**
	 * license has been accepted - lets install this plugin
	 *
	 */
	function accept()
	{
		$this->doInstallPlugin();
		echo $this->idsadmin->html->to_render;
		die();
	}

	/**
	 * license  was rejected .. :(
	 *
	 */
	function reject()
	{

		$this->idsadmin->status($this->idsadmin->lang('PluginNotInstalled'));
		$this->showPlugins();
		echo $this->idsadmin->html->to_render;
		die();
	}

	/**
	 * show the plugins.
	 *
	 */
	function showPlugins()
	{
		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_plugin_header( $this->idsadmin->lang('InstalledPlugins') ) );
		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_plugin_sub_installed( ) );

		$sql = "SELECT * FROM plugins ";
		$stmt = $this->conndb->query( $sql );

		while ( $row = $stmt->fetch( ) )
		{
			$row['PLUGIN_LATEST'] = "<img src='images/unknown.png' alt='{$this->idsadmin->lang('Unknown')}' title=\"{$this->idsadmin->lang('Unknown')}\" border='0'/>";
			$urlInfo = parse_url($row['PLUGIN_UPGRADE_URL']);

			if  ( ini_get('allow_url_fopen') == 1 && isset($urlInfo['scheme']) )
			{
					
				if ( $row['PLUGIN_UPGRADE_URL'] != ""  )
				{
					$data = file_get_contents($row['PLUGIN_UPGRADE_URL']);
					if ( $data != "" )
					{
						$vers = new SimpleXMLElement($data,null,false);

						if ( (string)$vers->version > $row['PLUGIN_VERSION'] )
						{
							$row['PLUGIN_LATEST'] = "<img src='images/cross.png' alt=\"{$this->idsadmin->lang('NoUpgAvail')}\" border='0' />";
						}
						else
						{
							$row['PLUGIN_LATEST'] = "<img src='images/check.png' alt=\"{$this->idsadmin->lang('CurrVerInstalled')}\" border='0' />";
						}
						unset($data);
					}
				}
			}

			$row['ENABLED'] = "<input type='checkbox'  onClick='toggleEnabled({$row['PLUGIN_ID']},1)' title=\"{$this->idsadmin->lang('ClickToEnable')}\"/>";
			if ( $row['PLUGIN_ENABLED'] == 1 )
			{
				$row['ENABLED'] = "<input type='checkbox' checked  title=\"{$this->idsadmin->lang('ClickToDisable')}\" onClick='toggleEnabled({$row['PLUGIN_ID']},0)'/>";
			}

			$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_plugin_row_installed( $row ) );
		}
		$stmt->closeCursor();

		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_plugin_footer( ) );

		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_plugin_header($this->idsadmin->lang('PluginsNotYetInstalled')) );
		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_plugin_sub_notinstalled( ) );

		$plugins = $this->getUninstalledPlugins();

		foreach ( $plugins as $k => $plugin )
		{
			$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_plugin_row_notinstalled( $plugin ) );
		}

		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_plugin_footer() );
	}

	/**
	 * check to make sure our installation directory is writeable, it should be .
	 *
	 * @return: boolean
	 */
	function checkInstallDir($dir = "../plugin/")
	{
		return is_writable($dir);
	}

	/**
	 * installplugin :  Install an OAT plugin.
	 *
	 */
	function installplugin($silent=false,$accepted=false)
	{
		if ( ! isset ( $this->idsadmin->in['file'] )
		|| $this->idsadmin->in['file'] == ""
		)
		{
			if ( $silent === true )
			{
				echo $this->idsadmin->lang('NoPluginToInstall');
				return false;
			}
			return  $this->idsadmin->error($this->idsadmin->lang('NoPluginToInstall'));
		}
		
		if ( $this->checkInstallDir() === false )
		{
			if ( $silent === true )
			{
				echo $this->idsadmin->lang('PluginDirNotWritable');
				return false;
			}
			return $this->idsadmin->error($this->idsadmin->lang('PluginDirNotWritable'));
		}

		$plugin_to_install = $this->idsadmin->in['file'];
		$zip = new ZipArchive;
		$zip->open( "../plugin_install/{$plugin_to_install}" );
		$tempdir = "../tmp/TEMP{$plugin_to_install}";
		if ( ! file_exists($tempdir) )
		{
			@mkdir( $tempdir );
		}
		$zip->extractTo( $tempdir );
		$zip->close();

		$pluginData = file_get_contents("{$tempdir}/plugin.xml");
		$plugin     = $this->pluginInfoFromXML($pluginData,$plugin_to_install);		
		
		// If the plugin has a minimum OAT version, check that now.
		if ($plugin->plugin_min_oat_version != "" && $plugin->plugin_min_oat_version != "--")
		{
			$min_oat_version = $plugin->plugin_min_oat_version;
			$oat_version = $this->idsadmin->get_version();
			
			$min_oat_version = preg_replace("/[^0-9B]/","",$min_oat_version);
			$split_ver = explode("B",$min_oat_version);
			$min_oat_version = $split_ver[0];
			$oat_version = preg_replace("/[^0-9B]/","",$oat_version);
			$split_ver = explode("B",$oat_version);
			$oat_version = $split_ver[0];
			
			if ($min_oat_version > $oat_version)
			{
				// Plugin requires a higher OAT version
				if ( $silent === true )
				{
					echo $this->idsadmin->lang('MinOATVersionError',array($plugin->plugin_min_oat_version));
					return false;
				}
				$this->idsadmin->error($this->idsadmin->lang('MinOATVersionError',array($plugin->plugin_min_oat_version)));
				$this->showPlugins();
				return;
			}
		}

		// if the plugin has a license then we need to
		// get user to agree to license.  Assume that if its a silent
		// install the 'license' is being done in the installer..
		if ( $plugin->plugin_license && ! $this->silent )
		{
			return $this->doLicense($plugin,$tempdir);
		}
		$this->doInstallPlugin($plugin,$tempdir,$plugin_to_install);

	}
	
	/**
	 * uninstallplugin :  Uninstall an OAT plugin.
	 *
	 */
	function uninstallplugin($silent=false,$accepted=false)
	{
		if ( !isset($this->idsadmin->in['pluginid']) )
		{
			return $this->idsadmin->error($this->idsadmin->lang('NoPluginToUninstall'));
		}
		
		$plugin_id = $this->idsadmin->in['pluginid'];
		$plugin_dir = "";
		
		// Check if plug-in id valid
		$sql = "select plugin_dir from plugins where plugin_id = {$plugin_id} ";
		$stmt = $this->conndb->query($sql);
		$res = $stmt->fetchAll();
		if (count($res) > 0)
		{
			$plugin_dir = "../plugin/" . $res[0]['PLUGIN_DIR'];
		} else {
			return $this->idsadmin->error($this->idsadmin->lang('ErrorOnUninstallInvalidPluginId'));
		}
		
		// Delete plug-in from plugins table
		$sql = "delete from plugins where plugin_id = {$plugin_id}";
		$this->conndb->exec($sql);
		if ($err[1] != 0)
		{
			$msg = $this->idsadmin->lang('ErrorOnUninstall') . "\n" . $err[1] . " - " . $err[2];
			return $this->idsadmin->error($msg);
		}
		
		// Delete plug-in menu items
		$sql = "delete from oat_menu where plugin_id = {$plugin_id}";
		$this->conndb->exec($sql);
		$err = $this->conndb->errorInfo();
		if ($err[1] != 0)
		{
			$msg = $this->idsadmin->lang('ErrorOnUninstall') . "\n" . $err[1] . " - " . $err[2];
			return $this->idsadmin->error($msg);
		}

		// Remove plug-in files
		$unremovedFiles = $this->removePluginFiles($plugin_dir);
		rmdir($plugin_dir);
		if (count($unremovedFiles) > 0 )
		{
			$msg = $this->idsadmin->lang('UninstallPluginDoneButUnremovedFiles') . "<br/><br/>";
			foreach ($unremovedFiles as $filename)
			{
				$msg .= "$filename <br/>";
			}
			$msg .= "<br/>" . $this->idsadmin->lang('YouCanRemoveFilesManually');
			$this->idsadmin->status($msg);
		} else {
			$this->idsadmin->status($this->idsadmin->lang('UninstallPluginSuccessful'));
		}
	}

	function doInstallPlugin($plugin="",$tempdir="",$ptoi="")
	{
		$plugin_to_install = $ptoi;
		$lic = false;  /* is this from a license agreement ? */
		if ( $plugin == "" )
		{
			if ( !$this->oat_install_mode && (! isset ( $this->idsadmin->in['file'] )
			|| $this->idsadmin->in['file'] == ""))
			{
				if ( $this->silent === true )
				{
					echo $this->idsadmin->lang('NoPluginToInstall');
					return false;
				}
				return  $this->idsadmin->error($this->idsadmin->lang('NoPluginToInstall'));
			}

			if (!$this->oat_install_mode)
			{
			    $plugin_to_install = $this->idsadmin->in['file'];
		    }
			$zip = new ZipArchive;
			$zip->open( "../plugin_install/{$plugin_to_install}" );
			$tempdir = "../tmp/TEMP{$plugin_to_install}";
			@mkdir( $tempdir );
			$zip->extractTo( $tempdir );
			$zip->close();

			$pluginData = file_get_contents("{$tempdir}/plugin.xml");
			$plugin     = $this->pluginInfoFromXML($pluginData,$plugin_to_install);
			$lic = true;
		}
		//find out where the toplevel directory is for this plugin ..
		// ie: the directory that contains the lang directory
		if ( ! $lic )
		{
			$pluginData = file_get_contents("{$tempdir}/plugin.xml");
		}
		$plugin->plugin_dir = $this->findLang($tempdir);
		$plugin->plugin_dir = substr($plugin->plugin_dir, strlen($tempdir)+1);
		//find out the top level directory for our plugin and check those permissions too ..
		$topdir = "../plugin/".basename(substr($plugin->plugin_dir, 0, strrpos($plugin->plugin_dir, "/")));
		while ($topdir != "../plugin" && !is_dir($topdir))
		{
			$topdir = substr($topdir,0,strrpos($topdir, "/"));
		}
		if ( $this->checkInstallDir($topdir) === false )
		{
			if ( $silent === true )
			{
				echo $this->idsadmin->lang('PluginDirNotWritable');
				return false;
			} else if ($this->oat_install_mode)
			{
			    return "Cannot install {$plugin->plugin_name} - The OAT plugin directory not writable.  "
			        . "Please check permissions and then use the Plug-in Manager to install plug-ins.";			
			}
			return $this->idsadmin->error("{$this->idsadmin->lang('PluginDirNotWritable')}");
		}


		if ( $this->silent === true || $this->oat_install_mode)
		{
			$plugin->plugin_enabled = 1;
		}

		// move the plugin into place
		$zip = new ZipArchive;
		$zip->open( "../plugin_install/{$plugin_to_install}" );
		$zip->extractTo("../plugin/");
		$zip->close();
		// create the license accepted file :) ..
		if ( $lic === true || $this->silent === true )
		{
			$fd = @fopen("../plugin/{$plugin->plugin_dir}/99","w");
			fclose($fd);
		}
		
		if ($this->oat_install_mode)
		{
		    // In OAT version 2.70 Enterprise Replication plug-in has been renamed to Replication
    		// plug-in. The following exception handles the upgrade.
    		if ( $plugin->plugin_name == 'Enterprise Replication' || $plugin->plugin_name == 'Replication' )
    		{
				$this->checkReplicationPlugin($plugin);
    		} else {
			    $qry = " SELECT * FROM plugins WHERE plugin_name = :pname AND plugin_author = :pauthor ";
				$stmt = $this->conndb->prepare($qry);
	            $stmt->bindParam('pname'   ,$plugin->plugin_name);
	        	$stmt->bindParam('pauthor' ,$plugin->plugin_author);     		
	        	$stmt->execute();
	        		
	        	while ( $row = $stmt->fetch() )
	        	{
	        		$plugin->plugin_installed = $row['PLUGIN_ID'];
	        	}
	        	$stmt->closeCursor();
	        }
		} else {
		    $plugin->plugin_installed = ( isset ( $this->idsadmin->in['pluginid'] ) ? $this->idsadmin->in['pluginid'] : 0 );
		}
		
        if ( $plugin->plugin_installed > 0 )
        {
        	$plugin->update($this->conndb);
        }
        else
        {
			$plugin->insert($this->conndb);
        }
        
        if ( $plugin->plugin_installed > 0 )
        {
			//lets delete the old menu items ..
			$this->conndb->query("delete from oat_menu where plugin_id = {$plugin->get_plugin_id()}");
        }
		// do the menu
		$xml = $pluginData;
		$this->menuFromXML( $xml,$plugin->get_plugin_id() );
        
		// cleanup
		$this->removedir($tempdir);
		unlink("../plugin_install/{$plugin_to_install}");

		if ( $this->silent )
		{
			echo $this->idsadmin->lang('PluginInstalled');
			return true;
		} else if ($this->oat_install_mode)
		{
			require_once("lib/message.php");
			$message = new message();
			return $message->lang('plugin_install_complete', array($plugin->plugin_name));
		}

		if ( $plugin->plugin_installed > 0 )
		{
			$this->idsadmin->status($this->idsadmin->lang('PluginUpgraded'));
		}
		else
		{
			$this->idsadmin->status($this->idsadmin->lang('PluginInstalled'));
		}
		$this->showPlugins();
	}

	/**
	 * display the license file along with 2 buttons - Agree / DisAgree
	 */
	function doLicense(&$plugin,&$tempdir)
	{
		$file = "{$tempdir}/{$plugin->plugin_license}";
		$pathinfo = pathinfo($file);
		if ( strtolower($pathinfo['extension']) == "html" )
		{
			$license = file_get_contents($file);
		}
		else
		{
			$license = nl2br(file_get_contents($file));
		}

		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_pluginmgr']->show_license($license ) );
	}

	/**
	 * find the lang directory
	 */
	function findLang($dir,$loc="")
	{
		$dir_handle = opendir($dir);
		while ( false !== ( $f = readdir($dir_handle) ) )
		{
			if ( $f == "." || $f == ".." )
			continue;

			if ( is_dir("{$dir}/{$f}") && $f == "lang" )
			{
				$loc = $dir;
				break;
			}

			if ( is_dir ( "{$dir}/{$f}" ) )
			{
				$loc = $this->findLang("{$dir}/{$f}",$loc);
			}
		}
		closedir($dir_handle);
		return $loc;
	}

	/**
	 * removes a directory and all its containing files.
	 *
	 */
	function removedir($dir)
	{

		if ( is_dir( $dir ) && !is_link( $dir ) )
		{
			if ($dir_handle = opendir($dir))
			{
				while ( ( $file = readdir( $dir_handle ) ) !== false )
				{
					if ( $file == '.' || $file == '..' )
					{
						continue;
					}
					if (! $this->removedir($dir.'/'.$file) )
					{
						throw new Exception($this->idsadmin->lang('CouldntBeDeleted', array($dir.'/'.$file)));
					}
				}
				closedir($dir_handle);
			}
			return rmdir($dir);
		}
		return unlink($dir);
	}

	/**
	 * get the plugins that have not yet been installed..
	 *
	 * @return array of plugin class
	 */
	function getUninstalledPlugins()
	{
		$plugins = array();
		$dir = opendir( "../plugin_install" );
		while ( ( $file = readdir( $dir ) ) !== false )
		{
			// only interested in zip files.
			$plugin = explode( '.',$file );
			if ( strtolower( $plugin[count($plugin) -1] ) == "zip" )
			{
				$zip = new ZipArchive;
				if ($zip->open("../plugin_install/{$file}") !== TRUE) {
					continue;
				}

				if ( $zip->locateName("plugin.xml") !== FALSE )
				{
					$zip->extractTo("../tmp/","plugin.xml");
					$pluginData = file_get_contents("../tmp/plugin.xml");
					$plugins[]  = $this->pluginInfoFromXML($pluginData,$file);
					unlink("../tmp/plugin.xml");
				}
				$zip->close();
			}
		}
		closedir($dir);

		if ( count($plugins) > 0 )
		{
            $qry = " SELECT * FROM plugins WHERE plugin_name = :pname AND plugin_author = :pauthor ";
			$stmt = $this->conndb->prepare($qry);
            foreach ( $plugins as $k => $v )
        	{
        		// In OAT version 2.70 Enterprise Replication plug-in has been renamed to Replication
        		// plug-in. The following exception handles the upgrade.
        		if ( $v->plugin_name == 'Enterprise Replication' || $v->plugin_name == 'Replication' )
        		{
					$this->checkReplicationPlugin($v);
        		}
        		else
        		{
	        		//error_log(var_export($v,true));
	        		$stmt->bindParam('pname'   ,$v->plugin_name);
	        		$stmt->bindParam('pauthor' ,$v->plugin_author);     		
	        		$stmt->execute();
	        		
	        		while ( $row = $stmt->fetch() )
	        		{
	        			//error_log(var_export($row,true));
	        			$v->plugin_installed = $row['PLUGIN_ID'];
	        		}
	        	}
        	}
        	$stmt->closeCursor();
		}
		return $plugins;

	}
	
	/**
	 * Recursively removes all plugin files from the specified directory
	 * 
	 * @return returns an array of files that could not be removed.
	 */
	function removePluginFiles($directory)
	{
		$unremovedFiles = array();
		
		$dir = opendir( $directory );
		while ( ( $file = readdir( $dir ) ) !== false )
		{
			if ( $file == '.' || $file == '..' )
			{
				continue;
			} 
			$file_path = $directory . "/" . $file;
			if (is_dir($file_path))
			{
				// If it is a directory, recursively remove files from the directory, then remove the directory.
				$unremovedFiles = array_merge($unremovedFiles,$this->removePluginFiles($file_path));
				$ret = rmdir($file_path);
				if (!$ret)
				{
					$unremovedFiles[] = $file_path;
				}
			} else {
				// Otherwise, it's a file, so just remove it.
				$ret = unlink($file_path);
				if (!$ret)
				{
					$unremovedFiles[] = $file_path;
				}
			}
		}
		closedir($dir);
		
		return $unremovedFiles;
	}

	/**
	 * get the plugin info from the plugin plugin.xml file
	 *
	 * @param  $xml
	 * @return plugin object.
	 */
	function pluginInfoFromXML($xml,$file)
	{
		$sxml = new SimpleXMLElement($xml,NULL,false);
		
		if ($this->oat_install_mode)
		{ 
		    require_once("../admin/lib/plugin.php");
		} else {
		    require_once("lib/plugin.php");
		}

		$plugin = new plugin($this->idsadmin);
		$pluginInfo = $sxml->plugin_info;
		$plugin->init(0,$file,$pluginInfo->plugin_name,$pluginInfo->plugin_desc
			,$pluginInfo->plugin_author,$pluginInfo->plugin_version
			,$pluginInfo->plugin_server_version , $pluginInfo->plugin_minimum_oat_version
			,$pluginInfo->plugin_upgrade_url
			,false,true, "" , $pluginInfo->plugin_license
			);

		return $plugin;
	}
	/**
	 * generate the menu entries from the xml in the plugin
	 */
	function menuFromXML(&$xml,$pluginid)
	{
		$add = "";

		$sxml = new SimpleXMLElement($xml,NULL,false);
		// do we have a menu placement ?
		if ( $sxml->plugin_menu->menu_pos != "" )
		{
			$add = "AND menu_name = '".(string)$sxml->plugin_menu->menu_pos."'";
		}

		$stmt = $this->conndb->query("SELECT MAX(menu_pos) AS max_menu_pos FROM oat_menu WHERE parent = 0 {$add}");
		$row = $stmt->fetch();
		$stmt->closeCursor();

		if ( $add = "")
		{
			$max_menu_pos = $row['MAX_MENU_POS'] + 1;
		}
		else
		{
			$max_menu_pos = $row['MAX_MENU_POS'];
		}

		$insstr  = "insert into oat_menu (menu_pos,menu_name,lang,link,title,cond,parent,plugin_id,expanded) ";
		$insstr .= "values (:menu_pos,:menu_name,:lang,:link,:title,:cond,:parent,:plugin_id,:expanded ); ";

		$insStmt = $this->conndb->prepare($insstr);

		foreach ($sxml->plugin_menu->menu as $m)
		{
			$menu_pos = $max_menu_pos;
			//lets deal with the toplevel 'menu' first ..
			$mname = htmlentities((string)$m->attributes()->name,ENT_COMPAT,"UTF-8");
			$mlang = (string)$m->attributes()->lang;
			$mlink = htmlentities((string)$m->attributes()->link,ENT_COMPAT,"UTF-8");
			
			$mqry_chklang = ($mlang == "")?"":"AND lang='{$mlang}'";
			$mqry = "SELECT COUNT(*) as cnt, MAX(menu_id) as last_id FROM oat_menu ";
			$mqry.= "WHERE parent=0 AND menu_name='{$mname}' {$mqry_chklang}";

			$mstmt = $this->conndb->query($mqry);
			$mrow = $mstmt->fetch();
			$mstmt->closeCursor();

			if($mrow['CNT']!=0)
			{
				$parent = $mrow['LAST_ID'];
				$menu_item_pos = 1;
			}else{
				$mstmt->closeCursor();
			
				$parent = 0;
				$menu_name = (string)$m->attributes()->lang;
	
				$insStmt->bindValue(":menu_pos"    , $menu_pos );
				$insStmt->bindValue(":menu_name"   , htmlentities( (string)$m->attributes()->name,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue(":lang"        , (string)$m->attributes()->lang);
				$insStmt->bindValue(":link"        , htmlentities( (string)$m->attributes()->link,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue(":title"       , htmlentities( (string)$m->attributes()->title,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue(":cond"        , htmlentities( (string)$m->attributes()->cond,ENT_COMPAT,"UTF-8") );
				$insStmt->bindValue(":parent"      , $parent );
				$insStmt->bindValue(":plugin_id"   , $pluginid );
				$insStmt->bindValue(":expanded"    , (string)$m->attributes()->expand );
	
				$insStmt->execute();
	
				$last_id_stmt = $this->conndb->query("SELECT last_insert_rowid() as last_id");
	
				$last_id = $last_id_stmt->fetch();
				$last_id_stmt->closeCursor();
	
				//now lets move onto the items for this menu.
				$parent = $last_id['LAST_ID'];
	
				$menu_item_pos = 1;
			}
		
			foreach ( $m  as $items )
			{
			
			    // save off these items because they may change depending upon position.
			    $save_menu_item_pos = $menu_item_pos;
			    $save_parent = $parent;
			    
			    $parent_menu = (string)$items->attributes()->parent;
			    if ( $parent_menu != "" )
			    {
			       //lets see if we can find our parent .. 
			       $pstmt = $this->conndb->query("SELECT MAX(menu_pos) AS max_menu_pos , menu_id AS parent FROM oat_menu WHERE parent = 0 AND menu_name = '{$parent_menu}';");
				   $prow  = $pstmt->fetch();
				   $pstmt->closeCursor();
				   
			       $parent        = $prow['PARENT'];
			       $menu_item_pos = $prow['MAX_MENU_POS'];
			    }
			    
				$menu_name = (string)$items->attributes()->name;
				$insStmt->bindValue( ":menu_pos"     , $menu_item_pos );
				$insStmt->bindValue( ":menu_name"    , htmlentities( (string)$items->attributes()->name,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue( ":lang"         , (string)$items->attributes()->lang);
				$insStmt->bindValue( ":link"         , htmlentities( (string)$items->attributes()->link,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue( ":title"        , htmlentities( (string)$items->attributes()->title,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue( ":cond"         , htmlentities( (string)$items->attributes()->cond,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue( ":parent"       , $parent );
				$insStmt->bindValue( ":plugin_id"    , $pluginid );
				$insStmt->bindValue( ":expanded"     , (string)$items->attributes()->expand );
				
				$insStmt->execute();
				
				//restore our items. 
				$parent = $save_parent;
				$menu_item_pos = $save_menu_item_pos;
				
				$menu_item_pos++;
			}

			$max_menu_pos++;
		}

	}

	/**
	 * toggle whether an plugin is enabled or disabled.
	 *
	 */
	function toggleEnabled()
	{
		$plugin_id = $this->idsadmin->in['plugin_id'];
		$enable = intval($this->idsadmin->in['enabled']);
		$stmt   = $this->conndb->query("UPDATE plugins SET plugin_enabled = {$enable} WHERE plugin_id = {$plugin_id} ");
		$stmt->execute();

		if ( $enable == 0 )
		{
			echo "<input type='checkbox'  onClick='toggleEnabled({$plugin_id},1)' title=\"{$this->idsadmin->lang('ClickToEnable')}\"/>";
		}
		else
		{
			echo "<input type='checkbox' checked  title=\"{$this->idsadmin->lang('ClickToDisable')}\" onClick='toggleEnabled({$plugin_id},0)'/>";
		}

		die();
	}
	
	/**
	 *  In OAT version 2.70 Enterprise Replication plug-in has been renamed to Replication
     *   plug-in. The following handles the upgrade.
	 */
	private function checkReplicationPlugin(&$replicationPluginInfo)
	{
		$qryRepl = " SELECT * FROM plugins WHERE (plugin_name = 'Enterprise Replication' "
				. "OR plugin_name = 'Replication') AND plugin_author = '$replicationPluginInfo->plugin_author'; ";
		
		$stmtRepl = $this->conndb->query($qryRepl);
		
		while ( $row = $stmtRepl->fetch() )
		{
			$replicationPluginInfo->plugin_installed = $row['PLUGIN_ID'];
		}
		$stmtRepl->closeCursor();
	}

} // end class pluginManager
?>

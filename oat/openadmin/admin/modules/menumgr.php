<?php
/*
 *************************************************************************
 *  (c) Copyright IBM Corporation. 2008, 2011.  All Rights Reserved
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

class menumgr {

	public $idsadmin;

	function __construct(&$idsadmin)
	{
		$this->idsadmin = $idsadmin;
		$this->idsadmin->html->set_pagetitle( $this->idsadmin->lang('menumgr') );
		$this->idsadmin->load_template("template_menumgr");
	}

	function run()
	{
		switch ( $this->idsadmin->in['run'] )
		{
			case "getXMLMenu":
				$this->getXMLMenu();
				break;
			default:
				$this->def();
				break;
		}
	}

	function getXMLMenu()
	{
		if ( isset($_POST['menuSave'])  )
		{
			// Save visible menu items
			$xml = stripslashes( "<menus>{$_POST['menuSave']}</menus>" );
			$this->saveMenu($xml, true);
			
			// Save hidden menu items
			$xml = stripslashes( "<menus>{$_POST['menuHiddenSave']}</menus>" );
			$this->saveMenu($xml, false);

			// Return after saving the menu items
			die("0");
		}
		require_once("../lib/menu.php");
		$m = new menu($this->idsadmin);
		$m->load_plugin_menu_lang_files();

		// Get visible menu items
		$menuxml = $m->getmenufromdb(true);
		//echo $xml;
		
		// Get hidden menu items
		$hiddenxml = $m->getmenufromdb(true, true);
		$xml = <<<EOM
<data>
{$menuxml}
{$hiddenxml}
</data>
EOM;
		
		echo $xml;
		
		die();
	}

	/**
	 * Save menu items
	 * 
	 * @param xml - menu items as xml 
	 * @param visible - boolean indicating whether menu items should be saved as visible or not
	 */
	function saveMenu($xml, $visible=true)
	{
		require_once ROOT_PATH."/lib/connections.php";

		$cb = new connections($this->idsadmin);
		$conndb = $cb->db;

		$conndb->beginTransaction();
		
		// Delete menu items (except for menu items belonging to disabled plugins)
		$sql = "DELETE FROM oat_menu WHERE menu_id in "
		     . "(SELECT menu_id from oat_menu LEFT OUTER JOIN plugins ON plugins.plugin_id = oat_menu.plugin_id "
		     . "WHERE (( oat_menu.plugin_id = '' OR oat_menu.plugin_id = 0 ) OR plugin_enabled = 1 ) "
		     . "AND visible = '" . (($visible)? 'true':'false') . "');";
		$conndb->query($sql);
		
		$max_menu_pos = 1;

		$sxml = new SimpleXMLElement($xml);

		$insstr  = "insert into oat_menu (menu_pos,menu_name,lang,link,title,cond,parent,plugin_id,expanded,linkid, visible) ";
		$insstr .= "values (:menu_pos,:menu_name,:lang,:link,:title,:cond,:parent,:plugin_id,:expanded , :linkid , '" . (($visible)? 'true':'false') . "'); ";
		
		$insStmt = $conndb->prepare($insstr);

		foreach ($sxml->menu as $m)
		{
			$menu_pos = $max_menu_pos;
			//let's deal with the top-level 'menu' first ..
			$parent = 0;
			$menu_name = (string)$m->attributes()->lang;
            
			$insStmt->bindValue(":menu_pos"    , $menu_pos);
			$insStmt->bindValue(":menu_name"   , htmlentities( (string)$m->attributes()->name,ENT_COMPAT,"UTF-8" ) );
			$insStmt->bindValue(":lang"        , (string)$m->attributes()->lang);
			$insStmt->bindValue(":link"        , htmlentities( (string)$m->attributes()->link,ENT_COMPAT,"UTF-8" ) );
			$insStmt->bindValue(":title"       , htmlentities( (string)$m->attributes()->title,ENT_COMPAT,"UTF-8" ) );
			$insStmt->bindValue(":cond"        , htmlentities( (string)$m->attributes()->cond,ENT_COMPAT,"UTF-8") );
			$insStmt->bindValue(":parent"      , $parent);
			$insStmt->bindValue(":plugin_id"   , (string)$m->attributes()->plugin_id);
			$insStmt->bindValue(":expanded"    , (string)$m->attributes()->expand);
			$insStmt->bindValue(":linkid"      , (string)$m->attributes()->linkId);

			$insStmt->execute();

			$last_id_stmt = $conndb->query("SELECT last_insert_rowid() as last_id");
			$last_id = $last_id_stmt->fetch();
			$last_id_stmt->closeCursor();

			//now let's move onto the items for this menu.
			$parent = $last_id['LAST_ID'];

			$menu_item_pos = 1;
			foreach ( $m  as $items )
			{
				$menu_name = (string)$items->attributes()->name;

				$insStmt->bindValue( ":menu_pos"     , $menu_pos );
				$insStmt->bindValue( ":menu_name"    , htmlentities( (string)$items->attributes()->name,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue( ":lang"         , (string)$items->attributes()->lang);
				$insStmt->bindValue( ":link"         , htmlentities( (string)$items->attributes()->link,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue( ":title"        , htmlentities( (string)$items->attributes()->title,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue( ":cond"         , htmlentities( (string)$items->attributes()->cond,ENT_COMPAT,"UTF-8" ) );
				$insStmt->bindValue( ":parent"       , $parent );
				$insStmt->bindValue( ":plugin_id"    , (string)$items->attributes()->plugin_id );
				$insStmt->bindValue( ":expanded"     , (string)$items->attributes()->expand );
				$insStmt->bindValue( ":linkid"       , (string)$items->attributes()->linkid );

				$insStmt->execute();
				$menu_item_pos++;
			}
			$max_menu_pos++;
		}
		$conndb->commit();
	}

	function def()
	{
		$this->idsadmin->html->add_to_output( $this->idsadmin->template['template_menumgr']->renderMenuManager($this->idsadmin->phpsession->get_lang()) );
	}
}
?>

<?php
/*
 *************************************************************************
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

class menu
{
	protected $whichmenu = "";
	public $idsadmin;

	function __construct(&$idsadmin)
	{
		$this->idsadmin = &$idsadmin;

		if ($this->idsadmin->in['act'] == "admin")
		{
			$this->whichmenu = ROOT_PATH."admin/menu.xml";
		}

		$this->idsadmin->load_lang("menu");

		
		// need to load the lang files for the plugins that are enabled .
		 
		if ( $this->whichmenu == "" )
		{
			$this->load_plugin_menu_lang_files();
		}

	} // end __construct
	
	/**
	 * Load the lang_menu.xml files for the plugins that are enabled.
	 */ 
	function load_plugin_menu_lang_files ()
	{
		if ($this->idsadmin->in['act'] == "admin")
		{
			require_once("../lib/connections.php");
		} else {
			require_once("lib/connections.php");
		}

		$conndb = new connections( $this->idsadmin );
		$stmt = $conndb->db->query("SELECT plugin_dir FROM plugins WHERE plugin_enabled = 1 GROUP BY plugin_id ");
		while ( $row = $stmt->fetch(PDO::FETCH_ASSOC) )
		{
			$this->idsadmin->load_plugin_menu_lang($row['PLUGIN_DIR']);
		}
		$stmt->closeCursor();
	}

	function draw_menu()
	{
		if ( $this->idsadmin->iserror === true )
		{
			return "";
		}
		
		if ($this->idsadmin->phpsession->isvalid())
		{
			return $this->render();
		}
		else
		{
			return "";
		}

	} // end draw_menu

	function render()
	{
		$menufile = true;  // whether the menu is a filename or a string.

		$images_dir = "images/";
		if ($this->idsadmin->in['act'] == "admin")
		{
			$images_dir = "../images/";
		}
		
		$cookie_path = $this->idsadmin->get_config("BASEURL");
		$pos = strpos($cookie_path, "//");
		if ($pos === false)
		{
			
		} 
		else
		{
			$pos = strpos($cookie_path, "/", $pos+2);
			if ($pos === false)
			{
				
			}
			else
			{
				$cookie_path = substr($cookie_path, $pos);
			}
		}
		$html = <<< EOF
		<script type="text/javascript">

		function setCookie(name,value,numdays)
		{
		var now = new Date();
		var expires = new Date();
		if (numdays==null || numdays==0) numdays=1;
		expires.setTime(now.getTime() + 3600000*24*numdays);
		document.cookie = name+"="+escape(value)
		+ ";expires="+expires.toGMTString() + "; path={$cookie_path}";   
	}

	function getCookie(c_name)
	{
	if (document.cookie.length>0)
	{
	c_start=document.cookie.indexOf(c_name + "=")
	if (c_start!=-1)
	{
	c_start=c_start + c_name.length+1
	c_end=document.cookie.indexOf(";",c_start)
	if (c_end==-1) c_end=document.cookie.length
	return unescape(document.cookie.substring(c_start,c_end))
	}
	}
	return ""
	}

	function removeFromCookie(c_name,rm)
	{
	val = getCookie(c_name);
	values = new Array();
	values = val.split(':');
	newCookie = '';
	for ( x = 0 ; x < values.length ; x++ )
	{
	if ( values[x] != '' && values[x] != rm )
	newCookie = newCookie + ":" + values[x] ;
	}
	setCookie(c_name,newCookie);
	}

	function menu_expand(id)
	{

	var elem = document.getElementById("menudiv_"+id);
	var link = document.getElementById("link_"+id);
	var val = null;

	if ( elem.style.display == "none" )
	{
	val = getCookie("menu_expand");
	if ( val == null )
	{
	val = id;
	}
	else
	{
	val = val + ":" + id;
	}
	setCookie("menu_expand",val,365);
	elem.style.display = "block";
	link.innerHTML = "<img border='0' align='top' style='cursor: pointer;' src='{$images_dir}/menu/twisty_expand.png'  alt='{$this->idsadmin->lang('collapse')}'/>";
	}
	else
	{
	elem.style.display = "none";
	removeFromCookie("menu_expand",id);
	link.innerHTML = "<img border='0' align='top' style='cursor: pointer;' src='{$images_dir}/menu/twisty_contract.png'  alt='{$this->idsadmin->lang('expand')}'/>";
        }
}


</script>
EOF;
	if ( $this->whichmenu == "" )
	{
		// get the menu from the database.

		$this->whichmenu = $this->getmenufromdb();

		$menufile = false;
	}

	$simp = new SimpleXMLElement($this->whichmenu,NULL,$menufile);
	$pos = 0;

	$html .=<<< EOF
	<table class='borderwrapmenu' cellpadding='0' cellspacing='0'>
<tr>
<td id='menucontents' align='left'>
EOF;

	$this->showit($simp,$html,$pos);
	$html .= "</td>\n</tr>\n</table>";

	return ($html);
	} // end render

	private function showit($node,&$html,&$pos,$recurse=false,$indent=0,$opendiv=0,&$passdepth=0,$cnt=0)
	{
		$images_dir = "images/";
		if ($this->idsadmin->in['act'] == "admin") {
			$images_dir = "../images/";
		}
			
		$add="";
		$needdiv=false;
		$x = count($node);
		foreach ($node as $k => $v)
		{

			$itemdepth = count($v->item) ;
			$add="";

			$cond = $this->getAttribute($v,"cond");
			if ( $cond != "")
			{
				if ( eval( " return ($cond); ") == false )
				{
					continue;
				}
			}

			$l = $this->getAttribute($v,"lang");
			if ( ! empty($l) && $l != "" )
			{
				$this->setAttribute($v,"name",$this->idsadmin->lang($l));
			}


			/**
			 * Is our option expanded ?
			 * ToDo:
			 *    Add cookie so that if user has changed , its kept between page refreshes
			 */
			if (isset($_COOKIE['menu_expand']))
			{
				$cookies = explode(":",$_COOKIE['menu_expand']);
			}
			else
			{
				$cookies = array();
			}

			if (in_array($this->getAttribute($v,"id"),$cookies) == true )
			{
				$this->setAttribute($v,"expand","true");
			}

			if ( strtolower( $this->getAttribute($v,"expand") )=="true")
			{
				$expand="block";
				$img="<img border='0' align='top' style='cursor: pointer;' src='{$images_dir}/menu/twisty_expand.png' alt='{$this->idsadmin->lang('collapse')}'/>";
			}
			else
			{
				$expand="none";
				$img="<img border='0' align='top' style='cursor: pointer;'src='{$images_dir}/menu/twisty_contract.png'  alt='{$this->idsadmin->lang('expand')}'/>";
			}
			if ( $pos == 0 )
			{

				if (count($v->item) >= 1 )
				$img = "<img border='0' align='top' style='cursor: pointer;' src='{$images_dir}/menu/twisty_contract.png' alt='{$this->idsadmin->lang('expand')}'/>";
				else
				$img = "<img border='0' width='20' height='20' align='top' style='cursor: pointer;' src='{$images_dir}/menu/top.gif' />";
			}

			if ($itemdepth > 0)
			{
				if ( $recurse==false && $opendiv>0)
				{
					$indent=0;
				}

				if ( $this->getAttribute($v,'id') != "" )
				{
					$id = $this->getAttribute($v,'id');
				}
				else
				{
					$id = str_replace(' ','_',$this->getAttribute($v,'name'));
				}

				$add.="<a href='javascript:void(0);' onclick='menu_expand(\"{$id}\")' id='link_{$id}'>{$img}</a>";
				$needdiv=true;
			}
			else
			{
				/*
				 if ($itemdepth == 0)
				 {
				 $cnt++;
				 if ( $cnt == $passdepth )
				 {
				 # $add  = "<img border='0' align='top' src='{$images_dir}/menu/line.gif' />";
				 $add .= "<img border='0' align='top' src='{$images_dir}/menu/branchbottom.gif' alt='-'/>";
				 }
				 else
				 {
				 $add ="<img border='0' width='20' height='20' align='top' style='cursor: pointer;' src='{$images_dir}/menu/branch.gif' alt='T'/>";
				 }
				 }*/
			}

			// Create and format menu item links
			$link = $this->makeLink($v);
			if (!$recurse && (strstr($link, "menuitem_highlight")==false))
			{
				// unhighlight menu header
				$html .= "<div class='menuhdrtxt'>";
				$html .= "{$add}".$link;
				$html .= "</div><div class='menuhdr'></div>";
			}
			else if (!$recurse)
			{
				// highlight menu header
				$html .= "<div class='menuhdr_highlight'>";
				$html .= "{$add}".$link;
				$html .= "</div>";
			}
			else
			{
				// sub-menu item
				$html .= "{$add}".$link;
			}

			$pos++;
			if ( $needdiv )
			{
				$opendiv++;
				$cnt=0;
				$html .= "<div id='menudiv_{$id}' class='menuitem' style='display:{$expand}'>";
				$needdiv=false;
			}
			if ( count($v->item) > 0)
			{
				$indent++;
				$passdepth = $itemdepth;

				$this->showit($v->item,$html,$pos,true,$indent,$opendiv,$passdepth,$cnt);
			}
			if ( count($v->item) > 0 )
			{
				$passdepth=0;
				$html .= "</div>";
				$indent--;
			}
		}
	} // end showit

	private function getAttribute($arr , $key)
	{

		if ($key == "" )
		{
			return "";
		}

		if ( isset($arr[$key]) )
		{
			return $arr[$key];
		}
		return "";
	}

	private function setAttribute(&$arr,$key,$value)
	{
		$arr[$key] = $value;
	}

	private function makeLink($v)
	{

		$x = $this->getAttribute($v,"link");
		eval("\$x = \"$x\";");
		$link = htmlspecialchars($x);
		$link_act =  strchr($link,"=");
		if($link_act)
		{
			$link_do = substr($link_act,strrpos($link_act,'=')+1);
			$pos = (int)strpos($link_act  ,'&');
			$link_do = substr($link_act,strrpos($link_act,'=')+1);

			if($pos!=0)
			$link_act = substr($link_act,1,$pos-1);
			else
			$link_tmp = $link_do;
		}
			
		$name = $this->getAttribute($v,"name");

		$linkId = $this->getAttribute($v,"linkId");
		if ( $linkId != "")
		{
			$linkId = "id='{$linkId}'";
		}

		if ( $link != "")
		{

		$link = htmlspecialchars($x,ENT_COMPAT,"UTF-8");
			$title = $this->getAttribute($v,'title');
			if ($this->idsadmin->phpsession->get_lang() != "en_US")
			{
			    // Title (the menu item hover help) is stored in connections.db in English.
			    // So when not running in English, make the title (hover help) the same as the menu item name. 
			    $title = $name;
			}
			
			$currMenuItem = $this->idsadmin->getCurrMenuItem();
			if ( $currMenuItem != "" 
			     && strcasecmp($this->idsadmin->lang($currMenuItem), $name) == 0)
			{
				return ("<div class='menuitem_highlight'><span>&nbsp;&nbsp;&nbsp;<a href=\"{$link}\" {$linkId} title='{$title}'>{$name}</a></span></div>");
			}
			elseif(strcmp($name,$this->idsadmin->lang("help")) == 0 
			       && isset($this->idsadmin->in['act']) &&
        			strcmp("admin",$this->idsadmin->in['act']) != 0)
			{
				return ("<span><a href=\"{$link}\" {$linkId} title='{$title}'>{$name}</a></span><br/>");
			}
			else
			{
				return ("<span>&nbsp;&nbsp;&nbsp;<a href=\"{$link}\" {$linkId} title='{$title}'>{$name}</a></span><br/>");
			}
		}
        
		return "<span title='{$name}'>{$name}</span>";
	}

	/**
	 * get the menu structure from the connections.db menu table.
	 *
	 * @param menumgr - boolean indicating if we are running this from the menu manager 
	 * @param hidden_items - false = only return visible menu items; true = only return hidden items
	 * @return string
	 */
	public function getmenufromdb($menumgr=false, $hidden_items=false)
	{
		// if $menumgr is != false then it's admin that's calling this function.
		if ( $menumgr == false)
		{
			require_once("lib/connections.php");
		}
		else
		{
			require_once("../lib/connections.php");
		}

		$conn = new connections($this->idsadmin);

		if ( $hidden_items == false )
		{
			// When getting the visible menu items, also exlude menu items from plugins that are disabled
			$qry  = " SELECT oat_menu.* , plugins.plugin_enabled FROM oat_menu ";
			$qry .= " LEFT OUTER JOIN plugins ON plugins.plugin_id = oat_menu.plugin_id ";
			$qry .= " WHERE parent = :parent_id AND ( ( oat_menu.plugin_id = '' OR oat_menu.plugin_id = 0 ) OR plugin_enabled = 1 ) ";
			$qry .= " AND visible = 'true' ";
			$qry .= " ORDER BY menu_pos ";
		}
		else
		{
			// Get hidden menu items, but still exclude menu items for plugins that are disabled.
			$qry  = " SELECT oat_menu.* , plugins.plugin_enabled , plugins.plugin_dir FROM oat_menu ";
			$qry .= " LEFT OUTER JOIN plugins ON plugins.plugin_id = oat_menu.plugin_id ";
			$qry .= " WHERE parent = :parent_id AND ( ( oat_menu.plugin_id = '' OR oat_menu.plugin_id = 0 ) OR plugin_enabled = 1 )";
			$qry .= " AND visible = 'false' ";
			$qry .= " ORDER BY menu_pos ";			
		}
		//lets first get all the parents ..
		$pid = 0;
		$stmt = $conn->db->prepare($qry);
		if ( $conn->db->errorCode() != "00000" )
		{
			return "<menus></menus>";
		}
		
		$stmt->bindParam(":parent_id" , $pid);
		$stmt->execute();
		$parents = $stmt->fetchAll();

		$stmt->closeCursor();

		foreach ($parents as $k => $parent)
		{
			$children = array();

			$pid = $parent['MENU_ID'];
				
			//get all the children for this parent.
			$stmt->bindParam(":parent_id" , $pid);
			$stmt->execute();
			$children = $stmt->fetchAll();
			$stmt->closeCursor();
				
			$parent['EXPANDED'] = ( $parent['EXPANDED'] == '1'  ) ? 'true' : 'false';
			//render the parent..
			$cond = ($parent['COND'] != "") ? "cond=\"{$parent['COND']}\"" : "";
            
			$plugin = "";
			$translation = "";
            if ( $menumgr != 0 )
            {
            	$plugin = "plugin_id=\"{$parent['PLUGIN_ID']}\" plugin_enabled=\"{$parent['PLUGIN_ENABLED']}\" ";
            	$this->idsadmin->load_plugin_menu_lang($parent['PLUGIN_DIR']);
            	$t = $this->idsadmin->lang($parent['LANG']);
            	if ( $t == "" )
            	{
            		$t = $parent['MENU_NAME'];
            	}
            	$translation = "translation=\"{$t}\"";
            }
            
			$xml .= <<<EOP
			<menu id = "{$parent['MENU_ID']}" name = "{$parent['MENU_NAME']}" lang = "{$parent['LANG']}" link = "{$parent['LINK']}" title = "{$parent['TITLE']}" {$cond} expand = "{$parent['EXPANDED']}" linkId="{$parent['LINKID']}" {$plugin} {$translation}>\n
EOP;

			//render the children
			foreach ($children as $c => $child)
			{
				if ( $menumgr != 0 )
				{
					if ($child['PLUGIN_ENABLED'] == "1")
					{
						$this->idsadmin->load_plugin_menu_lang($child['PLUGIN_DIR']);
					}
					$t = $this->idsadmin->lang($child['LANG']);
					if ( $t == "" )
					{
						$t = $child['MENU_NAME'];
					}
				$translation = "translation=\"{$t}\"";
				}
				$cond = ($child['COND'] != "") ? "cond=\"{$child['COND']}\"" : "";
				$xml .= <<<EOC
				<item name="{$child['MENU_NAME']}" lang="{$child['LANG']}" linkId="{$child['LINKID']}" link="{$child['LINK']}" title="{$child['TITLE']}" {$cond} plugin_id="{$child['PLUGIN_ID']}" plugin_enabled="{$child['PLUGIN_ENABLED']}" {$translation}/>\n
EOC;
			}
			$xml .= "</menu>\n";
		}

		$xmlParentTag = ($hidden_items)? "hidden_menus":"menus";
		$xml = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<{$xmlParentTag}>
		{$xml}
</{$xmlParentTag}>
EOF;

		return $xml;
	}
}// end class

?>

<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2008, 2010.  All Rights Reserved
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
 * extension class
 *    - holds information about an extension.
 */

class plugin {

	private $plugin_id             = 0;	
	public  $plugin_name           = "";
	public  $plugin_desc           = "";
	public  $plugin_author         = "";
	public  $plugin_version        = "";
	public  $plugin_server_version = "";
	public  $plugin_min_oat_version = "";
    public  $plugin_upgrade_url    = "";
    public  $plugin_dir            = "";
    public  $plugin_enabled        = "";
    public  $plugin_file_name      = ""; // the filename where the extension can be found.
    public  $plugin_installed      = false;
    public  $plugin_license        = ""; // the filename to a license ??
    
    function __construct(&$conndb)
    {
    	$this->conndb = $conndb;
    }
    
    function init($id,$filename,$name="unknown",$desc,$author="",$version="1.0",$server_version="11.10.UC1",$min_oat_version="--",$upgrade_url="",$installed=false,$enabled=false,$dir="",$license="")
    {
    	// we cast the values to string as they are probably coming
    	// from a SimpleXML Object.	
    	$this->set_plugin_id($id);
    	$this->plugin_file_name      = (string) $filename;
    	$this->plugin_desc			  = (string) $desc;
    	$this->plugin_name           = (string) $name;
    	$this->plugin_author         = (string) $author;
    	$this->plugin_version        = (string) $version;
    	$this->plugin_server_version = (string) $server_version;
    	$this->plugin_min_oat_version = (((string) $min_oat_version) == "")? "--":$min_oat_version;
    	$this->plugin_upgrade_url    = (string) $upgrade_url;
    	$this->plugin_dir            = $dir;
        $this->plugin_installed      = $installed;
        $this->plugin_enabled        = (boolean)$enabled;
        $this->plugin_license        = (string)$license;
    }
    
    function set_plugin_id($id)
    {
    	$this->plugin_id = $id;
    }
    
    function get_plugin_id()
    {
    	return $this->plugin_id;
    }
    
    /**
    * insert a plugin into the connections.db
    *
    */
    function insert(&$conndb)
    {
    	$insStmt  = " insert into plugins ( ";
    	$insStmt .= " plugin_name , plugin_desc, plugin_author, plugin_version , plugin_server_version, plugin_upgrade_url , plugin_enabled , plugin_dir ) ";
    	$insStmt .= " values (:plugin_name , :plugin_desc , :plugin_author , :plugin_version , :plugin_server_version , :plugin_upgrade_url , :plugin_enabled , :plugin_dir )";
    	
    	$stmt = $conndb->prepare($insStmt);
    	
    	$stmt->bindValue( ":plugin_name"           , $this->plugin_name );
    	$stmt->bindValue( ":plugin_desc"           , $this->plugin_desc );
    	$stmt->bindValue( ":plugin_author"         , $this->plugin_author );
    	$stmt->bindValue( ":plugin_version"        , $this->plugin_version );
    	$stmt->bindValue( ":plugin_server_version" , $this->plugin_server_version );
    	$stmt->bindValue( ":plugin_upgrade_url"    , $this->plugin_upgrade_url );
    	$stmt->bindValue( ":plugin_enabled"        , $this->plugin_enabled );
    	$stmt->bindValue( ":plugin_dir"            , $this->plugin_dir );
    	    	
    	$stmt->execute();
    	$stmt->closeCursor();
    	$stmt = $conndb->query("SELECT last_insert_rowid() as last_id");
    	$row  = $stmt->fetch();
    	$this->plugin_id = $row[0];
    	
    }
    
    /**
    * update a plugin in the connections.db
    *
    */
    function update(&$conndb)
    {
    	
        $this->plugin_id = $this->plugin_installed;
    	
        $updStmt  = " update plugins  set ";
    	$updStmt .= "  plugin_name           = :plugin_name ";
    	$updStmt .= ", plugin_desc           = :plugin_desc ";
    	$updStmt .= ", plugin_author         = :plugin_author ";
    	$updStmt .= ", plugin_version        = :plugin_version ";
    	$updStmt .= ", plugin_server_version = :plugin_server_version ";
    	$updStmt .= ", plugin_upgrade_url    = :plugin_upgrade_url ";
    	$updStmt .= ", plugin_enabled        = :plugin_enabled ";
    	$updStmt .= ", plugin_dir            = :plugin_dir ";
    	$updStmt .= " where plugin_id = :plugin_id ";
    	//$updStmt .= " values (:plugin_name , :plugin_desc , :plugin_author , :plugin_version , :plugin_server_version , :plugin_upgrade_url , :plugin_enabled , :plugin_dir )";
    	
    	$stmt = $conndb->prepare($updStmt);
    	
    	$stmt->bindValue( ":plugin_name"           , $this->plugin_name );
    	$stmt->bindValue( ":plugin_desc"           , $this->plugin_desc );
    	$stmt->bindValue( ":plugin_author"         , $this->plugin_author );
    	$stmt->bindValue( ":plugin_version"        , $this->plugin_version );
    	$stmt->bindValue( ":plugin_server_version" , $this->plugin_server_version );
    	$stmt->bindValue( ":plugin_upgrade_url"    , $this->plugin_upgrade_url );
    	$stmt->bindValue( ":plugin_enabled"        , $this->plugin_enabled );
    	$stmt->bindValue( ":plugin_dir"            , $this->plugin_dir );
    	$stmt->bindValue( ":plugin_id"             , $this->plugin_id );
    	    	
    	$stmt->execute();
    	$stmt->closeCursor();
    	//error_log("updated: {$this->plugin_id}");
    	//$stmt = $conndb->query("SELECT last_insert_rowid() as last_id");
    	//$row  = $stmt->fetch();
    	//$this->plugin_id = $row[0];
    	
    }
    
    
    function getPluginDetails(&$conndb)
    {
    
    }
}
?>
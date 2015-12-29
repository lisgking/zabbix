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


class phpsession {

    public $idsadmin;
    /* the INSTANCE we are connected to */
    public $instance;
    private $isvalid;
    private $connected=false;
    private $lang;
    public $group="";
    
    /* Data for locale drop-downs in top right corner */
    private $dblclang = "English";		// selected locale language
    private $dblcname = "en_US.8859-1";	// selected locale name
    private $dblc_avail_lang_list = null;	// list of available locale languages
    private $dblc_avail_locale_list = null;	// list of available locale names for the selected language
     
    public $sqltabinfo_db="";
    public $sqltabinfo_tab="";
    public $sqltabsel_tab="";
    public $sqlquery1="";
    public $sqlquery2="";
    public $sqlquery3="";
    public $sqlqval="";
    public $sqlqwarn="";
    public $sqlmaxfetnum=0;
    public $sqlid="";
    private $sqldbname;
    public $sqloptions=array("chk_spl" => "", "chk_c" => "",
    "chk_java" => "",
    "chk_dbcatalog" => "");
    public $sqltextoptions=array("selected", "", "", "", "");
    public $sqlbyteoptions=array("selected", "", "", "");
    public $sqlcnt=0;
    public $sqltabinfo_frag=0;
    public $sqlxtreepath="";

    public $alertoptions= "";

    public $url="";

    public $serverInfo = "";
     
    /******************************************
     * Constructor:
     *******************************************/
    function __construct(&$idsadmin)
    {
        // Set the cookie path to the install directory of the OpenAdmin Tool,
        // i.e. the part after Apache's htdocs.  This allows users to use
        // multiple installations of OAT on the same webserver with a single browser.
        $cookiePath = substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], "/", 1));
        $cookieParams = session_get_cookie_params();        
        session_set_cookie_params($cookieParams["lifetime"], $cookiePath);
    	
    	session_start();

        $this->idsadmin = &$idsadmin;
         
        $this->set_isvalid(false);
        $this->set_connected(false);

        require_once(ROOT_PATH."lib/instance.php");
        require_once(ROOT_PATH."lib/serverInfo.php");
        //$this->instance = new instance($this->idsadmin,"","","","");
        $this->idsadmin->html->debug(serialize($_SESSION));

        $this->initialize();
        $this->idsadmin->html->debug(serialize($_SESSION));
    }#end __construct
     
    /*****************************************
     * function: initialize
     *   Initialize the phpsession class with
     *   values from the $_SESSION.
     */
    private function initialize()
    {
        unset($this->instance);

        $host = isset($_SESSION['host']) ? $_SESSION['host'] : "" ;
        $port = isset($_SESSION['port']) ? $_SESSION['port'] : "";
        $servername = isset($_SESSION['servername']) ? $_SESSION['servername'] : "";
        $idsprotocol = isset($_SESSION['idsprotocol']) ? $_SESSION['idsprotocol'] : "";
        $this->instance = new instance($this->idsadmin,$host,$port,$servername,$idsprotocol);
         
        $this->set_host($host);
        $this->set_port($port);
        $this->set_servername($servername);
        $this->set_idsprotocol($idsprotocol);
        $this->set_passwd(isset($_SESSION['passwd']) ? $_SESSION['passwd'] : "");
        $this->set_username(isset($_SESSION['username']) ? $_SESSION['username'] : "");
        $this->set_conn_num(isset($_SESSION['conn_num']) ? $_SESSION['conn_num'] : "");
        $this->set_envvars(isset($_SESSION['envvars']) ? $_SESSION['envvars'] : null);
        $this->set_delimident(isset($_SESSION['DELIMIDENT']) ? $_SESSION['DELIMIDENT'] : "");
        
        $this->set_lang(isset($_SESSION['lang']) ? $_SESSION['lang'] : $this->idsadmin->get_config("LANG","en_US"));
        $this->set_dblclang(isset($_SESSION['dblclang']) ? $_SESSION['dblclang'] : "English");
        $this->set_dblcname(isset($_SESSION['dblcname']) ? $_SESSION['dblcname'] : "en_US.8859-1");
        $this->set_dblc_avail_lang_list(isset($_SESSION['dblc_avail_lang_list']) ? $_SESSION['dblc_avail_lang_list'] : null);
        $this->set_dblc_avail_locale_list(isset($_SESSION['dblc_avail_locale_list']) ? $_SESSION['dblc_avail_locale_list'] : null);
        
        $this->set_sqltabinfo_db( isset($_SESSION['sqltabinfo_db']) ? $_SESSION['sqltabinfo_db'] : "" );
        $this->set_sqltabinfo_tab(isset($_SESSION['sqltabinfo_tab']) ? $_SESSION['sqltabinfo_tab'] : "");
        $this->set_sqltabsel_tab(isset($_SESSION['sqltabsel_tab']) ? $_SESSION['sqltabsel_tab'] : "");
        $this->set_sqlquery1(isset($_SESSION['sqlquery1']) ? $_SESSION['sqlquery1'] : "" );
        $this->set_sqlquery2(isset($_SESSION['sqlquery2']) ? $_SESSION['sqlquery2'] : "");
        $this->set_sqlquery3(isset($_SESSION['sqlquery3']) ? $_SESSION['sqlquery3'] : "");
        $this->set_sqlqval(isset($_SESSION['sqlqval']) ? $_SESSION['sqlqval'] : "");
        $this->set_sqlqwarn(isset($_SESSION['sqlqwarn']) ? $_SESSION['sqlqwarn'] : "");
        $this->set_sqlmaxfetnum(isset($_SESSION['sqlmaxfetnum']) ? $_SESSION['sqlmaxfetnum'] : "");
        $this->set_sqlid(isset($_SESSION['sqlid']) ? $_SESSION['sqlid'] : "");
        $this->set_sqloptions(isset($_SESSION['sqloptions']) ? $_SESSION['sqloptions'] : "");
        $this->set_sqltextoptions(isset($_SESSION['sqltextoptions']) ? $_SESSION['sqltextoptions'] : "" );
        $this->set_sqlbyteoptions(isset($_SESSION['sqlbyteoptions']) ? $_SESSION['sqlbyteoptions'] : "" );
        $this->set_sqlcnt(isset($_SESSION['sqlcnt']) ? $_SESSION['sqlcnt'] : "" );
        $this->set_sqltabinfo_frag(isset($_SESSION['sqltabinfo_frag']) ? $_SESSION['sqltabinfo_frag'] : "" );
        $this->set_sqlxtreepath(isset($_SESSION['sqlxtreepath']) ? $_SESSION['sqlxtreepath'] : "" );
        $this->set_sqldbname(isset($_SESSION['sqldbname']) ? $_SESSION['sqldbname'] : "");
        $this->set_alertoptions(isset($_SESSION['alertoptions']) ? $_SESSION['alertoptions'] : "");
        $this->set_lasturl(isset($_SESSION['lasturl']) ? $_SESSION['lasturl'] : "");

        $this->set_group( isset( $_SESSION['group'] ) ? $_SESSION['group'] : "" );
    }

    function set_group($g)
    {
        if ( !isset ( $g ) )
        {
            $g = $_SESSION['group'];
        }
        $_SESSION['group'] = $g;
        $this->group = $g;
    }

    function set_serverInfo()
    {

        if ( isset($_SESSION['serverInfo']) )
        {
            $this->serverInfo = unserialize($_SESSION['serverInfo']);

            /* Is it time to refresh our serverInfo ? */
            $now = time();
            $diff = $now - $this->serverInfo->getLastUpdate();
            //error_log("using existing sessions - {$diff} ");
            if ( $diff > 60 )
            {
                //error_log("new serverInfo - diff {$diff} ");
                $this->serverInfo = new serverInfo($this->idsadmin);
            }
        }
        else
        {
            //error_log("new serverInfo");
            $this->serverInfo = new serverInfo($this->idsadmin);
        }

        $_SESSION['serverInfo'] = serialize($this->serverInfo);

    }

    function set_isvalid($valid)
    {
        $this->isvalid=$valid;
        $_SESSION['isvalid']=$valid;
    }
     
    function set_lang($lang)
    {
        if ( $lang == "")
        {
            $lang = "en_US";
        }
        $_SESSION['lang']=$lang;
        $this->lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : "en_US";

    } // end set_lang

    function get_group()
    {
        return $this->group;
    }

    function set_connected($connected)
    {
        $this->connected = $connected;
    }
     
    function get_connected()
    {
        return $this->connected;
    }

    function set_dblcname($dblcname)
    {
        $this->dblcname = $dblcname;
        $_SESSION['dblcname']=$dblcname;
    }
     
    function get_dblcname()
    {
        return $this->dblcname;
    }

    function set_dblclang($dblclang)
    {
        $this->dblclang = $dblclang;
        $_SESSION['dblclang']=$dblclang;
    }
     
    function get_dblclang()
    {
        return $this->dblclang;
    }

    function set_dblc_avail_lang_list($dblc_avail_lang_list)
    {
        $this->dblc_avail_lang_list = $dblc_avail_lang_list;
        $_SESSION['dblc_avail_lang_list']=$dblc_avail_lang_list;
    }

    function get_dblc_avail_lang_list()
    {
    	return $this->dblc_avail_lang_list;
    }
     
    function set_dblc_avail_locale_list($dblc_avail_locale_list)
    {
        $this->dblc_avail_locale_list = $dblc_avail_locale_list;
        $_SESSION['dblc_avail_locale_list']=$dblc_avail_locale_list;
    }

    function get_dblc_avail_locale_list()
    {
    	return $this->dblc_avail_locale_list;
    }
    
    function reset_dblocales_to_default()
    {
    	$this->set_dblcname("en_US.8859-1");
    	$this->set_dblclang("English");
    	$this->set_dblc_avail_locale_list(null);
    	$this->set_dblc_avail_lang_list(null);
    }

    function get_lang()
    {
        return $this->lang;
    } // end get_lang
     
    function get_sqldbname()
    {
        return $this->sqldbname;
    }

    function get_sqltabinfo_db()
    {
        return $this->sqltabinfo_db;
    }
    function get_sqltabinfo_tab()
    {
        return $this->sqltabinfo_tab;
    }
    function get_sqltabsel_tab()
    {
        return $this->sqltabsel_tab;
    }
    function get_sqlquery1()
    {
        return $this->sqlquery1;
    }
    function get_sqlquery2()
    {
        return $this->sqlquery2;
    }
    function get_sqlquery3()
    {
        return $this->sqlquery3;
    }
    function get_sqlqval()
    {
        return $this->sqlqval;
    }
    function get_sqlqwarn()
    {
        return $this->sqlqwarn;
    }
    function get_sqlmaxfetnum()
    {
        return $this->sqlmaxfetnum;
    }
    function get_sqlid()
    {
        return $this->sqlid;
    }
    function get_sqloptions()
    {
        return $this->sqloptions;
    }

    function get_sqltextoptions()
    {
        return $this->sqltextoptions;
    }

    function get_sqlbyteoptions()
    {
        return $this->sqlbyteoptions;
    }

    function get_sqlcnt()
    {
        return $this->sqlcnt;
    }

    function get_sqltabinfo_frag()
    {
        return $this->sqltabinfo_frag;
    }

    function get_sqlxtreepath()
    {
        return $this->sqlxtreepath;
    }

    function get_alertoptions()
    {
        return $this->alertoptions;
    }

    function get_lasturl()
    {
        return $this->url;
    }

    function set_sqldbname($str)
    {
        $this->sqldbname=$str;
        $_SESSION['sqldbname']=$str;
        $this->idsadmin->html->debug("setting sqldbname = {$str}");
    }

    function set_sqltabinfo_db($str)
    {
        $this->sqltabinfo_db=$str;
        $_SESSION['sqltabinfo_db']=$str;
    }

    function set_sqltabinfo_tab($str)
    {
        $this->sqltabinfo_tab=$str;
        $_SESSION['sqltabinfo_tab']=$str;
    }

    function set_sqltabsel_tab($str)
    {
        $this->sqltabsel_tab=$str;
        $_SESSION['sqltabsel_tab']=$str;
    }

    function set_sqlquery1($str)
    {
        $this->sqlquery1=$str;
        $_SESSION['sqlquery1']=$str;
    }

    function set_sqlquery2($str)
    {
        $this->sqlquery2=$str;
        $_SESSION['sqlquery2']=$str;
    }

    function set_sqlquery3($str)
    {
        $this->sqlquery3=$str;
        $_SESSION['sqlquery3']=$str;
    }

    function set_sqlqval($str)
    {
        $this->sqlqval=$str;
        $_SESSION['sqlqval']=$str;
    }

    function set_sqlqwarn($str)
    {
        $this->sqlqwarn=$str;
        $_SESSION['sqlqwarn']=$str;
    }
    
    function set_sqlmaxfetnum($num)
    {
        if ( $num <= 0 )
        $num = SQLMAXFETNUM;
        $this->sqlmaxfetnum=$num;
        $_SESSION['sqlmaxfetnum']=$num;
    }

    function set_sqlid($str)
    {
        $this->sqlid=$str;
        $_SESSION['sqlid']=$str;
    }

    function set_sqloptions($var_r)
    {
        $this->sqloptions=$var_r;
        $_SESSION['sqloptions']=$var_r;
    }

    function set_sqltextoptions($var_r)
    {
        if ( $var_r == "" )
        $var_r=array("selected", "", "", "", "");

        $this->sqltextoptions=$var_r;
        $_SESSION['sqltextoptions']=$var_r;
    }

    function set_sqlbyteoptions($var_r)
    {
        if ( $var_r == "" )
        $var_r=array("selected", "", "", "");

        $this->sqlbyteoptions=$var_r;
        $_SESSION['sqlbyteoptions']=$var_r;
    }

    function set_sqlcnt($num)
    {
        $this->sqlcnt=$num;
        $_SESSION['sqlcnt']=$num;
    }

    function set_sqltabinfo_frag($num)
    {
        $this->sqltabinfo_frag=$num;
        $_SESSION['sqltabinfo_frag']=$num;
    }

    function set_sqlxtreepath($str)
    {
        $this->sqlxtreepath=$str;
        $_SESSION['sqlxtreepath']=$str;
    }

    function set_alertoptions($var_r)
    {
        if ( $var_r == "" )
        $var_r=array("RED"=>"checked='checked'",
        	"YELLOW"=>"checked='checked'",
        	"GREEN"=> "checked='checked'",
        	"ERROR"=> "checked='checked'",
        	"WARNING"=> "checked='checked'",
        	"INFO"=> "checked='checked'",
        	"NEW"=> "checked='checked'",
        	"ADDRESSED"=> "",
        	"ACKNOWLEDGED"=> "",
        	"IGNORED"=> "");

        $this->alertoptions=$var_r;
        $_SESSION['alertoptions']=$var_r;
    }

    function set_lasturl($url="")
    {
        $this->url = $url;
        $_SESSION['lasturl']=$url;
    }

    function isValid()
    {
        if ( (isset($this->idsadmin->in['act']))
        && ($this->idsadmin->in['act']=="admin") )
        {
            return true;
        }
         
        if ( isset($_SESSION['username']) && $_SESSION['username'] != ""
        && isset($_SESSION['host']) && $_SESSION['host'] != ""
        && isset($_SESSION['port']) && $_SESSION['port'] != ""
        && isset($_SESSION['servername']) && $_SESSION['servername'] != ""
        && isset($_SESSION['passwd']) && $_SESSION['passwd'] != "")
        {
            $this->idsadmin->html->debug("Valid Session");
            $this->valid=true;
            return true;
        } else {
            $this->idsadmin->html->debug("Not Valid - ".var_export($_SESSION,1));
            $this->valid=false;
        }
        return false;
         
    }
     
    function set_host($host)
    {
        $this->idsadmin->html->debug("SET HOST = {$host}",__file__,__line__);
        $this->instance->set_host($host);
        $_SESSION['host'] = $host;
    }
     
    function set_port($port)
    {
        $this->instance->set_port($port);
        $_SESSION['port'] = $port;
    }
     
    function set_servername($servername)
    {
        $this->instance->set_servername($servername);
        $_SESSION['servername'] = $servername;
    }
    
    function set_idsprotocol($idsprotocol)
    {
        $this->instance->set_idsprotocol($idsprotocol);
        $_SESSION['idsprotocol'] = $idsprotocol;
    }
     
    function set_username($username)
    {
        $this->instance->set_username($username);
        $_SESSION['username'] = $username;
    }

    function set_passwd($passwd)
    {
        $this->instance->set_passwd($passwd);
        $_SESSION['passwd'] = $passwd;
    }
    
    function set_envvars($envvars)
    {
        $this->instance->set_envvars($envvars);
        $_SESSION['envvars'] = $envvars;
    }
    
    function set_delimident($delimident)
    {
        $this->instance->set_delimident($delimident);
        $_SESSION['DELIMIDENT'] = $delimident;
    }
    
    function set_conn_num($conn_num)
    {
        $this->instance->set_conn_num($conn_num);
        $_SESSION['conn_num'] = $conn_num;
    }

    function destroy_session()
    {
        session_destroy();
    } // destroy_session
     
    function __destruct()
    {
        $sid = session_id();
        $this->valid=false;
        $this->isconnected=false;

        /* rm any tmp directory created for this session */
        chdir(ROOT_PATH);
        $dir = "tmp/$sid";
        if (  !empty($sid) && is_dir($dir) )
        {
            /* rm any files in the directory */
            $str = "$dir"."/"."*";
            foreach (glob($str) as $fname )
            {
                unlink($fname);
            }
            rmdir($dir);
        }
    } // __destruct
     
} // end class
?>

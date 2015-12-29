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
 * This class is used for logging into OAT.
 */

class login {

    public $idsadmin;
    public $connectiondb;

    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_template("template_login");
        $this->idsadmin->load_lang("login");
        $this->idsadmin->html->set_pagetitle($this->idsadmin->lang('login'));

        require_once ROOT_PATH."/lib/connections.php";
        $tempconndb = new connections($this->idsadmin);
        $this->connectiondb = $tempconndb->db;
    }

    function run()
    {
        switch($this->idsadmin->in['do'])
        {
            case 'connectgroup':
                $this->connectgroup();
                break;
            case 'popserver':
                $this->popserver();
                break;
            case 'dologin':
                $this->dologin();
                break;
            case 'login':
                $this->getlogin();
                break;
            case 'loginnopass':
                $this->loginnopass();
                break;
            case 'testconn':
            	$this->testconn();
            	break;
            case 'logout':
                $this->dologout();
            default:
                $this->getlogin();
                break;
        }

    }

    function dologout()
    {
        $this->idsadmin->phpsession->destroy_session();
        $this->idsadmin->html->add_to_output($this->idsadmin->template['template_global']->global_redirect($this->idsadmin->lang("loggingout"),"index.php"));
        $this->idsadmin->html->render();
    }

    function getlogin($err="")
    {
        if ( $err != "" )
        {
            $this->idsadmin->html->add_to_output($this->idsadmin->template["template_login"]->error($err));
        }

        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_login"]->showForm());
         
        return;
    }
    
	/**
	 * Test a connection.
	 */
	function testconn()
	{
		$state = 1;
		$statemessage="Online";
		
		$servername = $this->idsadmin->in['SERVER'];
		$host = $this->idsadmin->in['HOST'];
		$port = $this->idsadmin->in['PORT'];
		$protocol = $this->idsadmin->in['IDSPROTOCOL'];
		$dbname = "sysmaster";
		$user = $this->idsadmin->in['USERNAME'];
		$passwd = $this->idsadmin->in['PASSWORD'];
		$envvars = (isset($this->idsadmin->in['ENVVARS']))? $this->idsadmin->in['ENVVARS'] : null; 

		require_once (ROOT_PATH."lib/PDO_OAT.php");
		try {
			$tdb = new PDO_OAT($this->idsadmin,$servername,$host,$port,$protocol,$dbname,"",$envvars,$user,$passwd);
		} catch(PDOException $e) {
			$message=preg_split("/:/",$e->getMessage());
			$statemessage= $message[sizeof($message)-1];
			$statemessage="{$this->idsadmin->lang('ConnectionFailed')} {$statemessage}";
			$state=3;
		}
		$tdb=null;
		echo $statemessage;
		die();
	}
    
    // validate group password and populate server connection drop down list
    function connectgroup()
    {
        $grpnum = $this->idsadmin->in['group_num'];
        $grppass = $this->idsadmin->in['group_pass'];
        require_once ROOT_PATH."/lib/connections.php";
        $grp = new connections($this->idsadmin);
        $sql = "select password from groups where group_num = {$grpnum}";
        $stmt = $grp->db->query($sql);
        $row = $stmt->fetch();
        if ( ( strcmp($row['PASSWORD'], $grppass ) ) != 0 )
        {
            echo "<font color='red'>{$this->idsadmin->lang('InvalidGroupPwd')}</font>";
        }
        else
        {
            $sql = "select conn_num , server, host from connections where group_num = {$grpnum} order by server";
            $stmt = $grp->db->query($sql);
            $conns = $stmt->fetchAll();
            echo "<select name='connlist' onChange='populateconnection(this)'>";
            $myhtml = $this->idsadmin->template["template_login"]->showconns($conns);
            echo $myhtml;
            echo "</select>";
        }
        die();
    }

    // populate Server Details once Server connection has been selected
    function popserver()
    {

        $conn_num = intval($this->idsadmin->in['conn_num']);
        $group_pass = $this->idsadmin->in['group_pass'];

        require_once ROOT_PATH."/lib/connections.php";
        $grp = new connections($this->idsadmin);
        $sql  = "select connections.* from connections,groups where conn_num = {$conn_num} ";
        $sql .= "and connections.group_num = groups.group_num and groups.password = '{$group_pass}'";
        $stmt = $grp->db->query($sql);



        header("Content-Type: text/xml");
        print ("<connections>");

        $row = $stmt->fetch();
        //$decoded_password = htmlentities($grp->decode_password( $row['PASSWORD'] ));
        $decoded_password = htmlspecialchars($grp->decode_password($row['PASSWORD']),ENT_COMPAT,"UTF-8");
        
        print ("<connection>\n");
        print ("<conn_num>{$row['CONN_NUM']}</conn_num>\n");
        print ("<host>{$row['HOST']}</host>\n");
        print ("<port>{$row['PORT']}</port>\n");
        print ("<username>{$row['USERNAME']}</username>\n");
        print ("<password>{$decoded_password}</password>\n");
        print ("<server>{$row['SERVER']}</server>\n");
        print ("<idsprotocol>{$row['IDSPROTOCOL']}</idsprotocol>\n");
        print ("</connection>\n");
        print ("</connections>");
        die();
    } #end popserver

    function dologin()
    {
        if ( !isset($this->idsadmin->in['informixserver'])
        || $this->idsadmin->in['informixserver'] == "" )  {
            $this->getlogin($this->idsadmin->lang("missinginformixserver"));
            return;
        }
        $this->idsadmin->phpsession->set_servername($this->idsadmin->in['informixserver']);

        if ( !isset($this->idsadmin->in['host']) || $this->idsadmin->in['host'] == "" )  {
            $this->getlogin($this->idsadmin->lang("missinghost"));
            return;
        }
        $this->idsadmin->phpsession->set_host($this->idsadmin->in['host']);

        if ( !isset($this->idsadmin->in['port']) || $this->idsadmin->in['port'] == "" )  {
            $this->getlogin($this->idsadmin->lang("missingport"));
            return;
        }
        $this->idsadmin->phpsession->set_port($this->idsadmin->in['port']);

        if ( !isset($this->idsadmin->in['username']) || $this->idsadmin->in['username'] == "" )  {
            $this->getlogin($this->idsadmin->lang("no_user"));
            return;
        }
        $this->idsadmin->phpsession->set_username($this->idsadmin->in['username']);

        if ( !isset($this->idsadmin->in['userpass']) || $this->idsadmin->in['userpass'] == "" )  {
            $this->getlogin($this->idsadmin->lang("missingpassword"));
            return;
        }
        $this->idsadmin->phpsession->set_passwd($this->idsadmin->in['userpass']);
        $this->idsadmin->phpsession->set_idsprotocol($this->idsadmin->in['idsprotocol']);

        if ( isset($this->idsadmin->in['conn_num']) ) {
            $this->idsadmin->phpsession->set_conn_num($this->idsadmin->in['conn_num']);
        }
        
        $this->idsadmin->phpsession->set_isvalid(true);

        //only set the group if the group password is valid
        $grppass = $this->idsadmin->in['grouppass'];
        require_once ROOT_PATH."/lib/connections.php";
        $grp = new connections($this->idsadmin);
        $sql = "select password from groups where group_num = {$this->idsadmin->in['groups']} ";
        $stmt = $grp->db->query($sql);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        
        if ( strcmp($row['PASSWORD'], $grppass) != 0 )
        {
            $this->idsadmin->phpsession->set_group("");
        }
        else
        {
            $this->idsadmin->phpsession->set_group($this->idsadmin->in['groups']);
        }
        
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect($this->idsadmin->lang('LoginRedirectDesc'),"{$this->idsadmin->phpsession->get_lasturl()}"));
    }

    /*
     * Login into a server by only supplying the connection number.
     */
    function loginnopass()
    {
        if ( ! isset($this->idsadmin->in['conn_num'])
        || $this->idsadmin->in['conn_num'] == "" )
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang('ErrorNoConnSpecified'));
            return;
        }

        if ( $this->idsadmin->phpsession->get_group() == "" )
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang('ErrorNoGroup'));
            return;
        }

        $conn_num = $this->idsadmin->in['conn_num'];
        $sql = " select * from connections where conn_num = {$conn_num} AND group_num = {$this->idsadmin->phpsession->get_group()}";

        $stmt = $this->connectiondb->query($sql);

        $row = $stmt->fetch();

        if ( ! isset( $row['HOST'] )
        || $row['HOST'] == "" )
        {
            $this->idsadmin->fatal_error($this->idsadmin->lang('InvalidLoginInfo'));
            return;
        }

        $redirect = $this->idsadmin->phpsession->get_lasturl();
        parse_str(  parse_url( $redirect,PHP_URL_QUERY ) );
         
        if ( $act == "sqlwin" )
        {
            $this->idsadmin->phpsession->set_sqldbname("sysmaster");
        }
        
        // see if there is a redirect for us , if not then use the previous url.
        $rdo = $this->idsadmin->in['rdo'];
        if ( isset($rdo) && $rdo != "" )
        {
            $do = $rdo;
        }
        
        $ract = $this->idsadmin->in['ract'];
        if ( isset($ract) && $ract != "" )
        {
            $act = $ract;
        }
        
        ( isset( $act ) ) ? $act = "?act={$act}" : $act = "";
        ( isset( $do  ) ) ? $do  = "&do={$do}"   : $do  = "";

        $redirect = "index.php{$act}{$do}";
        
        $decoded_password = connections::decode_password( $row['PASSWORD'] );
        
        $this->idsadmin->phpsession->instance = new instance($this->idsadmin,$row['HOST'],$row['POST'],$row['SERVERNAME'],$row['IDSPROTOCOL']);
        $this->idsadmin->phpsession->set_conn_num($conn_num);
        $this->idsadmin->phpsession->set_servername($row['SERVER']);
        $this->idsadmin->phpsession->set_host($row['HOST']);
        $this->idsadmin->phpsession->set_port($row['PORT']);
        $this->idsadmin->phpsession->set_idsprotocol($row['IDSPROTOCOL']);
        $this->idsadmin->phpsession->set_username($row['USERNAME']);
        $this->idsadmin->phpsession->set_passwd($decoded_password);
        $this->idsadmin->phpsession->set_isvalid(true);

		if(   isset( $_SESSION['SQLTOOLBOX_USERNAME'] )
		   || isset( $_SESSION['SQLTOOLBOX_PASSWORD'] ) )
		{
        	unset($_SESSION['SQLTOOLBOX_USERNAME']);
			unset($_SESSION['SQLTOOLBOX_PASSWORD']);
        }

        unset ( $_SESSION['serverInfo'] );
        unset ( $_SESSION['envvars'] );
        unset ( $_SESSION['DELIMIDENT'] );
            
        $this->idsadmin->html->add_to_output($this->idsadmin->template["template_global"]->global_redirect($this->idsadmin->lang('LoginRedirectDesc'),"{$redirect}"));
    }


}
?>

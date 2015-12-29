<?php
/*
 **************************************************************************
 *  (c) Copyright IBM Corporation. 2007 , 2008.  All Rights Reserved
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
 * This is the pinger code , essentially it's loaded on every page as part of the page header.
 * All the script outputs is a blank image , but should continue to run even if the user goes
 * to another page.
 */

register_shutdown_function("shutdownHandler",$db);

ini_set("max_execution_time", -1);

#set the maxexecution time..
set_time_limit(-1);

ignore_user_abort(TRUE);

@header( 'Content-Type: image/gif' );
print base64_decode( 'R0lGODlhAQABAID/AMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' );
ob_flush();

/**
 * pinger
 * get / update the status of each server in the connections db.
 */

# set the CONFDIR
define(CONFDIR,"../conf/");

require_once(CONFDIR."config.php");
$pinginterval=isset($CONF["PINGINTERVAL"]) ? $CONF["PINGINTERVAL"] : 300;

if ( ! isset($CONF['CONNDBDIR']) )
{
	// error_log("Please check config.php param CONNDBDIR - it doesnt seem to be set.");
	return;
}

if ( ! is_dir($CONF['CONNDBDIR']) )
{
	error_log("Please check config.php param CONNDBDIR - it doesnt seem to be set to a directory.");
	return;
}

$dbfile="{$CONF['CONNDBDIR']}/connections.db";

$informixdir=getenv("INFORMIXDIR");

if ( ! file_exists($dbfile) )
{
	// error_log("*** Cannot find connections.db - {$dbfile} ****");
	die();
}

unset($CONF);

# connect to the sqlite database.
$db = new PDO ("sqlite:{$dbfile}");
$db->setAttribute(PDO::ATTR_CASE,PDO::CASE_UPPER);

/**
 * lets get our last runtime and if we are running ..
 */ 

$qry  = "select lastrun , isrunning from pingerinfo";
$stmt = $db->query($qry);
$row  = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor();

if ( $row['ISRUNNING'] > 0 )
{
	 $timenow = time();
        if ( $timenow - $row['LASTRUN'] > 3000 )
        {
                error_log( "Reset pinger - should run next time ");
                $db->query("update pingerinfo set isrunning = 0");
        }
    
	/* we are already running so lets just quit now */
	die();
}

$timenow = time();
if ( $timenow - $row['LASTRUN'] < $pinginterval )
{
	// error_log( "no need to run "."Last: ".($timenow - $row['LAST'])." - {$pinginterval}" );
	die();
}

$db->query("update pingerinfo set isrunning = {$timenow} ");

// error_log ( "we better run "."Last: ".($timenow - $row['LAST'])." - {$pinginterval}" );

putenv("INFORMIXCONTIME=5");
putenv("INFORMIXCONRETRY=1");


/**
 * prepare the update string.
 */
$update = $db->prepare("update connections set lastpingtime=:now, laststatus=:state , laststatusmsg=:statemsg where conn_num = :conn_num");
$update2 = $db->prepare("update connections set lastpingtime=:now, laststatus=:state , laststatusmsg=:statemsg, lastonline=:lastonline where conn_num = :conn_num");

/**
 * we need to include the lib/connections.php
 * so we can access the password hooks functions.
 */

require_once 'connections.php';
/**
 * lets get all our defined connections.
 */ 
$sql = "select * from connections order by server";
$stmt = $db->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$starttime=time();
$status = "Start Time: {$starttime}\n";

foreach ( $rows as $k=>$row )
{
    
	$now = time();
	$dsn = <<<EOF
informix:host={$row['HOST']};service={$row['PORT']};database=sysmaster;server={$row['SERVER']};protocol={$row['IDSPROTOCOL']};
EOF;

	if ( substr(PHP_OS,0,3) != "WIN" )
	{
		$libsuffix = (strtoupper(substr(PHP_OS,0,3)) == "DAR")? "dylib":"so";
		$dsn .= ";TRANSLATIONDLL={$informixdir}/lib/esql/igo4a304.".$libsuffix;
		$dsn .= ";Driver={$informixdir}/lib/cli/libifdmr.".$libsuffix.";";
	}

	$statemessage="Online";
	$state=1;

	$user   = $row['USERNAME'];
	$passwd = connections::decode_password( $row['PASSWORD'] );
	

	try
	{
		$pingdb = new PDO($dsn,$user,utf8_decode($passwd));
	}
	catch(PDOException $e)
	{
		// error_log( $e->getMessage() );
		$message=preg_split("/:/",$e->getMessage());
		$statemessage= preg_replace("#\[.+\]#","",$message[1]);
		$statemessage.=" Last Online:".lastonlineconv($row['LASTONLINE']);
		$state=3;
	}

	$pingdb = null;

	if ( $state == 1 || $state == 4 )
	{
		$update2->bindValue(":conn_num",$row['CONN_NUM']);
		$update2->bindValue(":now",$now);
		$update2->bindValue(":state",$state);
		$update2->bindValue(":statemsg",$statemessage);
		$update2->bindValue(":lastonline",$now);
		$update2->execute();
		$status .= "updated {$row['SERVER']} {$statemessage}\n";
		// error_log("{$status} - ");
		continue;
	}
	$update->bindValue(":conn_num",$row['CONN_NUM']);
	$update->bindValue(":now",$now);
	$update->bindValue(":state",$state);
	$update->bindValue(":statemsg",$statemessage);
	$update->execute();

	$status .= "updated {$row['SERVER']} {$statemessage}\n";
		// error_log("{$status} - ");

}#end foreach

$endtime=time();
$stmt->closeCursor();
$status .= "End Time: {$endtime} - RunTime: ".($endtime-$starttime)." seconds \n";
$db->query("UPDATE pingerinfo SET lastrun=$endtime, isrunning = 0 , result='$status' ");
// error_log($status);

$db=null;


function lastonlineconv($lastonline=0)
{
	if ( $lastonline == 0 )
	return "NEVER";
	return date("m-d-Y H:m:s",$lastonline);

} #end lastonlineconv

function shutdownHandler($db)
{
    // error_log("here in the shutdownhandler");
     // error_log(connection_status());
    //ignore_user_abort(true);
    if ( connection_status() == CONNECTION_TIMEOUT )
    {
    $db->query("UPDATE pingerinfo SET lastrun=$endtime, isrunning = 0 , result='$status' ");
        $mess = <<< EOF
        It would appear that OAT has received a script timeout while executing.
        Click <a href="index.php?act=login&amp;do=logout">here</a> to continue.
EOF;
        echo $mess;
        return;
    }



}
?>

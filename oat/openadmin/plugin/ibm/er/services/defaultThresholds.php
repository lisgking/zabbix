<?php
/*
 ************************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation, 2008.  All rights reserved.
 ************************************************************************
 */

/** 
 * This file stores the default thresholds used by the ER plug-in's
 * Routing Topology screen (for server versions 11.50xC2 or later).
 * 
 * Important Note: The descriptions stored here are what is inserted 
 * into the sysadmin:php_threshold table (since the sysadmin database
 * is en_US.819, we cannot insert translated strings in there).  What
 * is shown in the UI, however, is the strings from the lang_er.xml
 * file to incorporate translations.
 **/

$defaultThresholds = array(
    array(  'NAME' => "OAT_ER_DDR_STATE",
            'TASK_NAME' => "enabled",
            'VALUE' => "",
            'VALUE_TYPE' => "STRING",
            'DESCRIPTION' => "Current state of the ER capture proccess" ),    
    array(  'NAME' => "OAT_ER_PROXIMITY_TO_DDRBLOCK",
            'TASK_NAME' => "enabled",
            'VALUE' => "10000",
            'VALUE_TYPE' => "NUMERIC",
            'DESCRIPTION' => "Number of logical log pages until DDRBLOCK" ),
    array(  'NAME' => "OAT_ER_SPOOLED_TXNS",
            'TASK_NAME' => "enabled",
            'VALUE' => "100",
            'VALUE_TYPE' => "NUMERIC",
            'DESCRIPTION' => "Number of spooled transactions" ),          
    array(  'NAME' => "OAT_ER_ROWDATA_SBSPACE_USED",
            'TASK_NAME' => "enabled",
            'VALUE' => "90",
            'VALUE_TYPE' => "NUMERIC",
            'DESCRIPTION' => "Percentage of disk space used for the Row Data" ),
    array(  'NAME' => "OAT_ER_NETWORK_STATE",
            'TASK_NAME' => "enabled",
            'VALUE' => "",
            'VALUE_TYPE' => "STRING",
            'DESCRIPTION' => "Current state of the ER network" ),   
    array(  'NAME' => "OAT_ER_DISCONNECTED_NODES",
            'TASK_NAME' => "enabled",
            'VALUE' => "1",
            'VALUE_TYPE' => "NUMERIC",
            'DESCRIPTION' => "Number of disconnected nodes" ),                         
    array(  'NAME' => "OAT_ER_PENDING_TXNS",
            'TASK_NAME' => "enabled",
            'VALUE' => "100",
            'VALUE_TYPE' => "NUMERIC",
            'DESCRIPTION' => "Number of pending transactions" ),     
    array(  'NAME' => "OAT_ER_APPLY_STATE",
            'TASK_NAME' => "enabled",
            'VALUE' => "",
            'VALUE_TYPE' => "STRING",
            'DESCRIPTION' => "Current state of the ER apply process" ),            
    array(  'NAME' => "OAT_ER_APPLY_AVG_LATENCY",
            'TASK_NAME' => "enabled",
            'VALUE' => "30",
            'VALUE_TYPE' => "NUMERIC",
            'DESCRIPTION' => "Average latency to receive a transaction (in seconds)" ), 
    array(  'NAME' => "OAT_ER_APPLY_FAIL_RATE",
            'TASK_NAME' => "enabled",
            'VALUE' => "1.0",
            'VALUE_TYPE' => "NUMERIC(6.2)",
            'DESCRIPTION' => "Rate of failure applying transactions"),                                    
);


?>

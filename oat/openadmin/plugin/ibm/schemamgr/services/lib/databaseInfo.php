<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for IDS
 *  Copyright IBM Corporation 2009, 2011.  All rights reserved.
 **********************************************************************/

class databaseInfo
{
    var $name;
    var $owner;
    var $dbspace;
    var $locale;
    var $logging;
    var $space_used;
    var $creation_date;
    var $case_insensitive;
    var $numloadjobs;
    var $numunloadjobs;
    var $grid; 				//tables in this db are 'gridtables' of this grid
}
?>
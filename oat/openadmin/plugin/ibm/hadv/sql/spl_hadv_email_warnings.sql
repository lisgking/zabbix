-- file: spl_hadv_email_warnings.sql
-- auth: Jeff Cauhape
-- date: 04/27/2011
-- desc: Send HCL warnings by email.
-- MOD   DATE   DESC
-- 001 05/05/11 Data is now stored in a file instead of passed on
--              the command line, so we pass the file name instead
--              of the data.
-- 002 05/27/11 The location of hadv_email.php is now retrieved from
--              from the database instead of hard coded.
-- 003 06/02/11 Added 'from' email address as a parameter. Changed
--              to and from types to char(30)
-- 004 06/02/11 Removed 'drop procedure' and trace directive.
-- 005 06/02/11 Renamed source to spl_...
-- 006 07/14/11 Renamed spl to include hcl_
-- 007 08/15/11 Renamed spl to inlcude hadv_
-- 008 08/25/11 Use email command line from ph_threshold.
-- 009 08/26/11 Removed 3rd param p_file 
-- 010 08/26/11 Modified to generate file name in DUMPDIR 
-- 011 08/26/11 Made create table dynamic, and put in drop and rm file 
-- 012 08/26/11 Added trim() around p_* variables 
-- 013 08/26/11 Added code to delete table if exist, and rm file 
-- 014 08/26/11 Added on exception in case rm file -> file doesn't exist 

--drop procedure hadv_email_warnings(int, varchar(255), varchar(255) );

CREATE PROCEDURE hadv_email_warnings(p_id int,
				p_to varchar(255),
                                p_from varchar(255));

define p_command lvarchar(500);
define p_scratch    char(128);
define p_message lvarchar(32739);

define p_sel     char(80);
define p_file	 char(132);
define p_tmpdir  char(132);
define p_drop    lvarchar;
define p_rm      lvarchar;
define p_cr_ext  lvarchar;
define p_tabname char(128);
define p_osname  char(128);
define p_tab_exist  integer;

on exception
end exception with resume;

trace on;
--
-- We need to determine if we can make the exernal table
-- temporary. Would solve concurrency issues.
--
--drop table hadv_report;

select cf_effective into p_tmpdir from sysmaster:syscfgtab 
       where cf_name = "DUMPDIR";
select os_name into p_osname from sysmaster:sysmachineinfo;

let p_tmpdir   = trim(p_tmpdir);
let p_osname   = trim(p_osname);
let p_tabname  = "hadv_report_"||p_id;
let p_drop     = "drop table "||trim(p_tabname);

select count(*) into p_tab_exist from sysadmin:systables where tabname=p_tabname;


-- if windows use other slash
if p_osname = "Windows" then
  let p_file     =  trim(p_tmpdir) || "\" || trim(p_tabname);
  let p_rm       =  "del "|| trim(p_file);
else
  let p_file     =  trim(p_tmpdir) || "/" || trim(p_tabname);
  let p_rm       =  "rm "|| trim(p_file);
end if

if p_tab_exist = "1" then
  execute immediate p_drop;
  system p_rm;
end if

let p_cr_ext = "select email from hadv_emails where run_id=" || p_id ||
               " into external " || p_tabname ||
               " using (DATAFILES ('DISK:" || trim(p_file) || "'))";

execute immediate p_cr_ext;

{ 
select email from hadv_emails
  into external hadv_report
  using (DATAFILES ("DISK:/tmp/hadv_report"));
}


select value into p_command
  from ph_threshold where name = "Email Program";

let p_command = trim(p_command);

let p_command = replace(p_command, "[SUBJECT]", "Health Advisor Report - " || DBSERVERNAME);

let p_command = replace(p_command, "[TO]", trim(p_to));

let p_command = replace(p_command, "[FROM]", trim(p_from));

let p_scratch = " < " || p_file;

--let p_command = replace(p_command, "[MESSAGE]", p_scratch);
let p_command = replace(p_command, "[MESSAGE]", trim(p_file));

system p_command;

execute immediate p_drop;

system p_rm;

END PROCEDURE;

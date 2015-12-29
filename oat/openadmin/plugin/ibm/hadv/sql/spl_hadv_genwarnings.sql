-- file: spl_hadv_genwarnings.sql
-- auth: Jeff Cauhpae
-- date: 04/27/2011
-- desc: Check hcl_temp_res table and generate warnings in email.
-- Mod    Date
-- 001  04/27/2011  Using 'EOL' as a tag that will be replaced
--                  by a line feed in the PHP code before mailing.
-- 002  04/29/2011  Remove case where alarm = 'N' for the time being.
-- 003  04/29/2011  Change table refernce from tgen to hcl_temp_res.
-- 004  05/05/2011  Changed from building a string to pass to the php mail
--                  program to calling a procedure which calls php and writes
--                  the data to a text file 1 line at a time. The mail program
--                  will then open the text file and build an email message.
-- 005  05/27/2011  Retrieve output location for alarm_log from database.
-- 006  06/02/2011  Retrieve email to/from addresses from hcl_sched_prof.
-- 007  06/02/2011  Removed drop procedure and trace directive.
-- 008  06/02/2011  Renamed source file spl_*
-- 009  06/03/2011  Added call to remove_file() to remove old version
--                  of alarm_log.txt file, if it exists.
-- 010  06/07/2011  Need to add a check for hcl_sched_prof.send_when column.
--                  "Always" - send report regardless if there are any
--                             Red or Yellow alarms to report.
--                  "Red"    - Report ONLY Red alarms.
--                  "Any"    - Report if their are any Red or Yellow alarms.
-- 011  06/07/2011  Add the name of the hcl profile to the report.
-- 012  06/15/2011  Add start and stop times of the process which creates
--                  the warnings. Times are in hcl_run table.
-- 013  07/14/2011  Changed spl names to include hcl_ 
-- 014  07/15/2011  Added input param v_prof_id to hcl_genwarnings 
--                  Changed query to read hcl_sched_prof instead of hcl_sched 
-- 015  08/15/2011  Changed name from spl_hcl_genwarnings.sql to spl_hadv...
--                  Also made other name changes to be consistent with the
--                  new name approved by Legal.
-- 016  08/18/2011  Replaced calls to write_alarms() with calls to hadv_format_text().
-- 017  08/28/2011  Modified Report to Not Format Alarm Name & Action 

-- drop procedure hadv_genwarnings;

create procedure hadv_genwarnings(v_prof_id integer);

define r_count      integer;  -- Count of Red level warnings.
define y_count      integer;  -- Count of Yellow level warnings.
-- define message      char(132);
define message      varchar(255);
define p_desc       lvarchar(50); -- Hold test name
define p_name       lvarchar(50); -- Hold test name
define p_action     lvarchar(2048); -- Hold long description
define p_command    lvarchar(3000);
define p_report     lvarchar(32739); -- Accumulates report text
define p_prof_id    like hadv_sched_prof.prof_id;
define p_from       like hadv_sched_prof.from_email;
define p_to         like hadv_sched_prof.to_email;
define p_send_when  like hadv_sched_prof.send_when;
define p_prof_name  like hadv_profiles.name;
define p_start_time like hadv_run.start;
define p_stop_time  like hadv_run.stop;
define p_outfile    char(132); -- path to temp file.

--
-- Remove old report from hadv_emails table.
--
  delete from hadv_emails where run_id = v_prof_id;

--
-- Select header info for email.
--
  select prof_id, from_email, to_email, send_when into
       p_prof_id, p_from,   p_to,     p_send_when
    from hadv_sched_prof where prof_id=v_prof_id;

  let p_to        = trim(p_to);
  let p_from      = trim(p_from);
  let p_send_when = trim(p_send_when);

-- With the current design of hadv_run, there should only
-- be 1 record in the table. However, if we later change
-- the design to hold start/stop times from various profiles,
-- we've already protected the report code from returning
-- incorrect values.

  select start, stop into p_start_time, p_stop_time
  from hadv_run
  where hadv_run.prof_id = p_prof_id and hadv_run.start = (select max(start) from hadv_run t1 where t1.prof_id=p_prof_id);

  let r_count = 0;
  let y_count = 0;

  select count(*) into r_count from hadv_temp_res where alarm = 'R';
  select count(*) into y_count from hadv_temp_res where alarm = 'Y';

--
-- DEBUG
--
-- set debug file to "hadv_genwarnings.debug";
-- trace on;

-- 
-- Look for cases for not running the report
--

  if p_send_when = "Red" and r_count = 0 then
    goto no_report;
  end if

  if p_send_when = "Any" and r_count = 0 and y_count = 0 then
    goto no_report;
  end if

--
-- The third case is send_when = "Always", which is default behavior.
--

--
-- Start report
--

  select name into p_prof_name
    from hadv_profiles
   where prof_id = p_prof_id;

  let message = "Health Advisor Alarm Report for " || DBSERVERNAME;
  let p_report = message;
  let p_report = p_report || hadv_format_text(" ");
  let p_report = p_report || hadv_format_text(" ");

  let message = "Profile ..... " || p_prof_name;
  let message = trim(message);
  let p_report = p_report || hadv_format_text(message);

  let message = "Start time .. " || p_start_time;
  let message = trim(message);
  let p_report = p_report || hadv_format_text(message);

  let message = "Stop time ... " || p_stop_time;
  let message = trim(message);
  let p_report = p_report || hadv_format_text(message);
  let p_report = p_report || hadv_format_text(" ");
  


  if r_count + y_count = 0 then

    let message = "There are no Red or Yellow alerts at this time.";
    let p_report = p_report || hadv_format_text(message);
    let p_report = p_report || hadv_format_text(" ");
    let p_report = p_report || hadv_format_text(" ");

    goto all_done;
    
  else  -- We have something in hadv_temp_res to process
        -- Prioritize, Red, then Yellow, then Issues
--
-- RED ALERTS
--
    if r_count = 0 then

      let message = "There are no Red Alarms at this time.";
      let p_report = p_report || hadv_format_text(message);
      let p_report = p_report || hadv_format_text(" ");
      let p_report = p_report || hadv_format_text(" ");

    else

      let message = "These are the Current Red Alarms";
      let p_report = p_report || hadv_format_text(message);
      let p_report = p_report || hadv_format_text(" ");
      let p_report = p_report || hadv_format_text(" ");
      
        foreach
        select name, action into p_name, p_action
          from hadv_temp_res
            where alarm = 'R'
        let p_name = trim(p_name);

        let message = p_name;
        let message = trim(message);
        let p_report = p_report || hadv_format_text(message);

        let message = p_action;
        let message = trim(message);
        --let p_report = p_report || hadv_format_text(message);
        let p_report = p_report || hadv_format_text(" ");

        let p_report = p_report || message;
        let p_report = p_report || hadv_format_text(" ");
        let p_report = p_report || hadv_format_text(" ");
        --let p_report = p_report || " ";

      end foreach; 
    end if  -- end of RED

--
-- YELLOW ALERTS
--
    if p_send_when != "Red" then

      if y_count = 0 then

        let message = "There are no Yellow Alarms at this time. ";
        let p_report = p_report || hadv_format_text(message);
        let p_report = p_report || hadv_format_text(" ");
        let p_report = p_report || hadv_format_text(" ");

      else

        let message = "These are the Current Yellow Alarms ";
        let p_report = p_report || hadv_format_text(message);
        let p_report = p_report || hadv_format_text(" ");
        let p_report = p_report || hadv_format_text(" ");

        foreach
          select name, action into p_name, p_action
            from hadv_temp_res
              where alarm = 'Y'

          let p_name = trim(p_name);

          let message = p_name;
          let message = trim(message);
          let p_report = p_report || hadv_format_text(message);
          let p_report = p_report || hadv_format_text(" ");

          let message = p_action;
          let message = trim(message);
          let p_report = p_report || message;
          let p_report = p_report || hadv_format_text(" ");
          let p_report = p_report || hadv_format_text(" ");

        end foreach;
      end if 

    end if -- end of Yellow

  end if -- there were records in hadv_temp_res

--
-- Send email
--
<<all_done>>   -- Target for goto


--
-- Store the accumulated report.
--
  insert into hadv_emails values (v_prof_id, p_report);

--
-- Send the report by email.
--
  let p_outfile = "/tmp/hadv_report";
-- Change to use systemt tmp or $INFORMIXDIR/tmp
-- select cf_effective from sysmaster:syscfgtab where cf_name="DUMPDIR";




  execute procedure hadv_email_warnings(v_prof_id, p_to, p_from );

<<no_report>>  -- Target for not genearting a report

end procedure

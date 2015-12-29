-- file: spl_hadv_gen_check.sql
-- auth: Darin Tracy
-- date: 06-01-2011
-- desc: Execute the Health Check 
--   in: Profile ID 
--  out: Temp table hadv_temp_res populated with alarms, 
--       t_yel/t_red Results tables populated 
--
-- Mod   Date        Description
--
-- 001   06/01/2011  Initial Draft

create procedure hadv_gen_check(v_prof_id integer) 

define v_alarm_id      integer;
define v_desc          char(50);
define v_name          char(50);
define v_temp_tab      char(20);

-- Red Alarm Information
define v_red_lvalue_type     char(10);
define v_red_lvalue          lvarchar;
define v_red_lvalue_param1   char(20);

define v_red_op              char(5);

define v_red_rvalue_type     char(10);
define v_red_rvalue          lvarchar;
define v_red_rvalue_param1   char(20);

define v_red_action          lvarchar;

-- Yellow Alarm Information
define v_yel_lvalue_type     char(10);
define v_yel_lvalue          lvarchar;
define v_yel_lvalue_param1   char(20);

define v_yel_op              char(5);

define v_yel_rvalue_type     char(10);
define v_yel_rvalue          lvarchar;
define v_yel_rvalue_param1   char(20);

define v_yel_action          lvarchar;

define v_o_param4            char(20);
define v_o_param5            char(20);
define v_o_param6            char(20);
define v_o_param7            char(20);
define v_exc_desc            lvarchar;


define l_lvalue_type     char(10);
define l_lvalue          lvarchar;
define l_lvalue_param1   char(20);
define l_op              char(5);
define l_rvalue_type     char(10);
define l_rvalue          lvarchar;
define l_rvalue_param1   char(20);

define l_action          lvarchar;
define l_tmp             lvarchar;

define l_lvalue_data     decimal(20,2); 
define l_rvalue_data     decimal(20,2); 

define l_alerted       char(1);
define l_alerttype     char(1);
define i               integer;

define v_run_id        integer;
define v_current       datetime year to second;

define l_lparam1	char(20);
define l_param1		char(20);
define l_param3		char(20);

on exception
end exception with resume;

trace on;

   select dbinfo('UTC_TO_DATETIME',sh_curtime) into v_current from sysmaster:sysshmvals;
   insert into hadv_run values(0,v_prof_id,v_current,null);
   select run_id into v_run_id from hadv_run where start = v_current and prof_id=v_prof_id;



/*
   create temp table hadv_temp_res(alarm_id integer, alarm char(1), 
                         desc char(50), temp_tab char(20), 
                         op_trigger lvarchar, action lvarchar);
*/
   create temp table hadv_temp_res(alarm_id integer, alarm char(1), 
                         desc char(50), name char(50), temp_tab char(20), 
                         op_trigger lvarchar, action lvarchar,
                         lparam1 char(20),param1 char(20), param3 char(20));
   create temp table hadv_results_collist(name char(50), col_num integer, 
                         collist char(250));



foreach 
   select    
             id,desc, name, temp_tab, 
             red_lvalue_type, red_lvalue, red_lvalue_param1,
             red_op, 
             red_rvalue_type, red_rvalue, red_rvalue_param1,
             red_action,
             yel_lvalue_type, yel_lvalue, yel_lvalue_param1,
             yel_op, 
             yel_rvalue_type, yel_rvalue, yel_rvalue_param1, 
             yel_action,
             o_param4, o_param5, o_param6, o_param7, exc_desc
   into
             v_alarm_id,v_desc,v_name, v_temp_tab, 
             v_red_lvalue_type, v_red_lvalue, v_red_lvalue_param1,
             v_red_op, 
             v_red_rvalue_type, v_red_rvalue, v_red_rvalue_param1,
             v_red_action, 
             v_yel_lvalue_type, v_yel_lvalue, v_yel_lvalue_param1,
             v_yel_op, 
             v_yel_rvalue_type, v_yel_rvalue, v_yel_rvalue_param1, 
             v_yel_action, 
             v_o_param4, v_o_param5, v_o_param6, v_o_param7,v_exc_desc
   from hadv_gen_prof where prof_id = v_prof_id and enable="Y"

   let l_lvalue_type     = v_red_lvalue_type;
   let l_lvalue          = v_red_lvalue;
   let l_lvalue_param1   = v_red_lvalue_param1;
   let l_op              = v_red_op;
   let l_rvalue_type     = v_red_rvalue_type;
   let l_rvalue          = v_red_rvalue;
   let l_rvalue_param1   = v_red_rvalue_param1;
   let l_action          = v_red_action;

   let l_alerted="N";
   let l_alerttype="R";

   for i = 1 to 2

-- Construct l_lvalue with param value
--  & run l_lvalue
   if l_lvalue_type == "SQL" then
      select replace(l_lvalue,"%lparam1%",l_lvalue_param1) into l_tmp from 
                sysmaster:sysdual;
      let l_lvalue = l_tmp;
      select replace(l_lvalue,"%v_prof_id%",v_prof_id) into l_tmp from
                 sysmaster:sysdual;
      let l_lvalue = l_tmp;

      let l_lvalue_data=null;
      prepare lstmt from l_lvalue;
      declare lcurs cursor for lstmt;
      open lcurs;
      fetch lcurs into l_lvalue_data;
   elif l_lvalue_type="VALUE" then
      let l_lvalue_data = l_lvalue;
   elif l_lvalue_type="FUNC" then
   end if 

-- Construct l_rvalue with param value
-- & run l_rvalue
   if l_rvalue_type == "SQL" then
      select replace(l_rvalue,"%rparam1%",l_rvalue_param1) into l_tmp from sysmaster:sysdual;
      let l_rvalue = l_tmp;
      let l_lvalue_data=null;
         prepare rstmt from l_rvalue;
         declare rcurs cursor for rstmt;
         open rcurs;
         fetch rcurs into l_rvalue_data;
   elif l_rvalue_type == "VALUE" then
     let l_rvalue_data = l_rvalue;
   end if


   let l_lparam1 = l_lvalue_param1;
   let l_param1 = l_lvalue_data;
   let l_param3 = l_rvalue_data;


-- Construct action message with param values

      select replace(l_action,"%lparam1%",trim(l_lvalue_param1)) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;
      select replace(l_action,"%rparam1%",trim(l_rvalue_param1)) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;
      select replace(l_action,"%param1%",l_lvalue_data) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;
      select replace(l_action,"%param2%",trim(l_op)) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;
      select replace(l_action,"%param3%",l_rvalue_data) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;

      select replace(l_action,"%param4%",trim(v_o_param4)) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;
      select replace(l_action,"%param5%",trim(v_o_param5)) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;
      select replace(l_action,"%param6%",trim(v_o_param6)) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;
      select replace(l_action,"%param7%",trim(v_o_param7)) into l_tmp from sysmaster:sysdual;
      let l_action = l_tmp;


-- Perform (lvalue op rvalue) checks

      if l_op == ">" then
         if l_lvalue_data > l_rvalue_data then
         insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger > alert",l_action,l_lparam1,l_param1,l_param3);
         --insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger > alert",l_action);
         let l_alerted="Y";
         end if
      elif l_op == "<" then
         if l_lvalue_data < l_rvalue_data then
         insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger < alert",l_action,l_lparam1,l_param1,l_param3);
         --insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger < alert",l_action);
         let l_alerted="Y";
         end if
      elif l_op == "=" then
         if l_lvalue_data == l_rvalue_data then
         insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger = alert",l_action,l_lparam1,l_param1,l_param3);
         --insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger = alert",l_action);
         let l_alerted="Y";
         end if
      elif l_op == "<=" then
         if l_lvalue_data <= l_rvalue_data then
         insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger <= alert",l_action,l_lparam1,l_param1,l_param3);
         --insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger <= alert",l_action);
         let l_alerted="Y";
         end if
      elif l_op == ">=" then
         if l_lvalue_data >= l_rvalue_data then
         insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger >= alert",l_action,l_lparam1,l_param1,l_param3);
         --insert into hadv_temp_res values(v_alarm_id,l_alerttype,v_desc,v_name,v_temp_tab,"trigger >= alert",l_action);
         let l_alerted="Y";
         end if


      end if

--  Debug Code to print out when alert no hit
      if i==2 and l_alerted== "N" then
         insert into hadv_temp_res values(v_alarm_id,"N",v_desc,v_name,v_temp_tab,"NO ALERT",l_action,l_lparam1,l_param1,l_param3);
         --insert into hadv_temp_res values(v_alarm_id,"N",v_desc,v_name,v_temp_tab,"NO ALERT",l_action);
      end if

      if l_lvalue_type == "SQL" then
         close lcurs;
         free lcurs;
         free lstmt;
      end if
      if l_rvalue_type == "SQL" then
         close rcurs;
         free rcurs;
         free rstmt;
      end if

-- if Alerted on Red, don't do Yellow

      if l_alerted == "Y" then
         exit for;      
      end if;

      let l_lvalue_type     = v_yel_lvalue_type;
      let l_lvalue          = v_yel_lvalue;
      let l_lvalue_param1   = v_yel_lvalue_param1;
      let l_op              = v_yel_op;
      let l_rvalue_type     = v_yel_rvalue_type;
      let l_rvalue          = v_yel_rvalue;
      let l_rvalue_param1   = v_yel_rvalue_param1;
      let l_action          = v_yel_action;

      let l_alerttype="Y";

   end for;


end foreach;


select dbinfo('UTC_TO_DATETIME',sh_curtime) into v_current from sysmaster:sysshmvals;
update hadv_run set stop = v_current where run_id=v_run_id;

end procedure;




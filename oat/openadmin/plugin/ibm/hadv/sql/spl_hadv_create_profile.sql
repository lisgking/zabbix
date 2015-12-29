-- file: spl_hadv_create_profile.sql
-- auth: Darin Tracy
-- date: 06-01-2011
-- desc: To create a a profile 
--   in: Profile name
--  out: NA  
--
-- Mod   Date        Description
--
-- 001   06/01/2011  Initial Draft

create procedure hadv_create_profile(v_prof_name char(30)) 

define v_prof_id 	integer;
define v_task_name 	char(50);
define v_spl_call 	char(50);



on exception
end exception with resume;

trace on;

   let v_task_name = "HADV " || trim(v_prof_name) || " Profile";
   let v_spl_call = "execute procedure hadv_gen_task('" || trim(v_prof_name) || "');";

--------------------------------------------------------------
-- Insert into hadv_profiles
--------------------------------------------------------------
   insert into hadv_profiles values(0,v_prof_name,'I');
   select prof_id into v_prof_id from hadv_profiles where name = v_prof_name;

--------------------------------------------------------------
-- Insert into hadv_sched_prof
--------------------------------------------------------------
   insert into hadv_sched_prof values(v_prof_id,'to@company.org','from@company.org','Always');


--------------------------------------------------------------
-- Insert Task into dbscheduler 
--------------------------------------------------------------
insert into ph_task values(0, v_task_name, 'To run the health check lite on scheduled intervals', 'TASK', 1,'','', 'sysadmin',v_spl_call,'0 01:00:00','23:55:00','', '1 00:00:00','2011-04-12 23:55:00',0,0.0,'t','t','t','t','t','t','t',400,'USER','f',0);

end procedure;




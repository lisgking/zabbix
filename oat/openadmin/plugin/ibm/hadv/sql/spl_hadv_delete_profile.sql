-- file: spl_hadv_delete_profile.sql
-- auth: Darin Tracy
-- date: 06-01-2011
-- desc: To delete a a profile 
--   in: Profile name
--  out: NA  
--
-- Mod   Date        Description
--
-- 001   06/01/2011  Initial Draft

create procedure hadv_delete_profile(v_prof_name char(30)) 

define v_prof_id 	integer;
define v_tk_name 	char(30);



on exception
end exception with resume;

trace on;

--------------------------------------------------------------
-- Get v_prof_id  from hadv_profiles
--------------------------------------------------------------
   select prof_id into v_prof_id from hadv_profiles where name = v_prof_name and name != "Default";

--------------------------------------------------------------
-- Delete from ph_task 
--------------------------------------------------------------
   let v_tk_name = "HADV " || trim(v_prof_name) || " Profile";  
   delete from ph_task where tk_name = v_tk_name;

--------------------------------------------------------------
-- Delete from hadv_sched_prof
--------------------------------------------------------------
   delete from hadv_sched_prof where prof_id = v_prof_id;


--------------------------------------------------------------
-- Delete from hadv_exception_prof 
--------------------------------------------------------------
   delete from hadv_exception_prof where prof_id = v_prof_id;

--------------------------------------------------------------
-- Delete from hadv_gen_prof 
--------------------------------------------------------------
   delete from hadv_gen_prof where prof_id = v_prof_id;

--------------------------------------------------------------
-- Delete from hadv_profiles
--------------------------------------------------------------
   delete from hadv_profiles where prof_id = v_prof_id;



end procedure;




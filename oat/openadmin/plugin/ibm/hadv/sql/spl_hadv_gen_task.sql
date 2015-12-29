-- file: spl_hadv_gen_task.sql
-- auth: Darin Tracy
-- date: 06-01-2011
-- desc: A task is created in ph_task that calls this function to run a profile's HADV 
--       This will run hadv_gen_check() and hadv_genwarnigns
--   in: Profile name
--  out: NA  
--
-- Mod   Date        Description
--
-- 001   06/01/2011  Initial Draft

create procedure hadv_gen_task(profile_name char(30));
define v_save_prof char(30);
define v_prof_id integer;

on exception
end exception with resume;

---------------------------------------------------------
-- Get prof_id 
---------------------------------------------------------
select prof_id into v_prof_id from hadv_profiles t1 where t1.name =profile_name;

---------------------------------------------------------
-- Execute Health Check  & Send Email
---------------------------------------------------------
execute procedure hadv_gen_check(v_prof_id);
execute procedure hadv_genwarnings(v_prof_id);


end procedure;

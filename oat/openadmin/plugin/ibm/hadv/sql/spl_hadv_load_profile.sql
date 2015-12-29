-- file: spl_hadv_load_profile.sql
-- auth: Darin Tracy
-- date: 06-01-2011
-- desc: To Load  a profile 
--   in: Profile name
--  out: NA  
--
-- Mod   Date        Description
--
-- 001   06/01/2011  Initial Draft

create procedure hadv_load_profile(v_prof_name char(30)) 

define v_prof_id integer;



on exception
end exception with resume;

trace on;

--------------------------------------------------------------
-- Get v_prof_id  from hadv_profiles
-- Make Active
--------------------------------------------------------------
   select prof_id into v_prof_id from hadv_profiles where name = v_prof_name ;
   update hadv_profiles set status = 'I';
   update hadv_profiles set status = 'A' where prof_id = v_prof_id;


end procedure;




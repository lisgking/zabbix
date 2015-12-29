-- file: spl_hadv_update_profile_os_info.sql
-- auth: Darin Tracy
-- date: 09-08-2011
-- desc: Update Specific OS information within the Profile
--   in: Profile ID
--
-- Mod   Date        Description
--
-- 001   09/08/2011  Initial Draft

create procedure hadv_update_profile_os_info(v_prof_id integer)

define p_osname char(128);



select os_name into p_osname from sysmaster:sysmachineinfo;
let p_osname    = trim(p_osname);

if p_osname = "Windows" then
   update hadv_gen_prof set yel_lvalue_param1 = "c:\temp" where
        prof_id=v_prof_id and desc="DUMPDIR";
   update hadv_gen_prof set yel_lvalue_param1 = "c:\temp" where
        prof_id=v_prof_id and desc="TAPEDEV";
   update hadv_gen_prof set yel_lvalue_param1 = "c:\temp" where
        prof_id=v_prof_id and desc="LTAPEDEV";
else
   update hadv_gen_prof set yel_lvalue_param1 = "/tmp" where
        prof_id=v_prof_id and desc="DUMPDIR";
   update hadv_gen_prof set yel_lvalue_param1 = "/tmp" where
        prof_id=v_prof_id and desc="TAPEDEV";
   update hadv_gen_prof set yel_lvalue_param1 = "/tmp" where
        prof_id=v_prof_id and desc="LTAPEDEV";

   if p_osname = "Linux" then

   else

   end if
end if

end procedure;

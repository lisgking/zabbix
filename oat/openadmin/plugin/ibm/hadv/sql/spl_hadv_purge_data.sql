-- file: spl_hadv_purge_data.sql
-- auth: Darin Tracy
-- date: 06-01-2011
-- desc: To create a a profile 
--   in: Profile name
--  out: NA  
--
-- Mod   Date        Description
--
-- 001   06/01/2011  Initial Draft

-- Tables to Purge 
--
-- hadv_run

create procedure hadv_purge_data()

define v_numdays 	integer;

   -- For now we will purge data older than 30 days
   -- In the future, we could configure this, via a table
   --
   let v_numdays=30;


---------------------------------------------------
-- Purge hadv_run
---------------------------------------------------
   delete from hadv_run where date(start) < today- v_numdays;  


end procedure;

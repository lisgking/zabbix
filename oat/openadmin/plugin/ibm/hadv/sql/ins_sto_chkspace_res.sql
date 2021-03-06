insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Storage",
-- Short Description
         "Dbspace Free Space",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "sto_chkspace",
----------------------------------------------------------------
-- Red threshold column name          -- column name of configurable threshold
         "red_lvalue_param1",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value       -- %param1%
         "execute function hadv_stage_data_exception('dbspace size nfree per_free',' select t1.name dbspace, sum(t2.chksize) size, sum(t2.nfree) nfree, round((sum(t2.nfree)/sum(t2.chksize))*100,0) per_free from sysmaster:sysdbspaces t1, sysmaster:syschunks t2 where t1.dbsnum=t2.dbsnum and t1.is_blobspace !=1 and t1.is_sbspace !=1 group by 1 having round((sum(t2.nfree)/sum(t2.chksize)) * 100,0) < %lparam1% ','t_red_sto_chkspace','dbspace',%v_prof_id%)", 
-- Red Alarm - Left param1            -- %lparam1%
         "5",

-- Red Alarm - Operand                -- %param2%
         ">",

-- Red Alarm - Right check type
         "VALUE",
-- Red Alarm - Right check value      -- %param3%
         "0",
-- Red Alarm - Right param1           -- %rparam1%
         "",

--Red Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Yellow threshold column name       -- column name of configurable threshold
         "yel_lvalue_param1",

-- Yellow Alarm - Left check type
         "SQL",
-- Yellow Alarm - Left check value    -- %param1% 
         "execute function hadv_stage_data_exception('dbspace size nfree per_free',' select t1.name dbspace, sum(t2.chksize) size, sum(t2.nfree) nfree, round((sum(t2.nfree)/sum(t2.chksize))*100,0) per_free from sysmaster:sysdbspaces t1, sysmaster:syschunks t2 where t1.dbsnum=t2.dbsnum and t1.is_blobspace !=1 and t1.is_sbspace !=1 group by 1 having round((sum(t2.nfree)/sum(t2.chksize)) * 100,0) < %lparam1% ','t_yel_sto_chkspace','dbspace',%v_prof_id%)", 
-- Yellow Alarm - Left param1         -- %lparam1%
         "10",

-- Yellow Alarm - Operand             -- %param2%
         ">",

-- Yellow Alarm - Right check type
         "VALUE",
-- Yellow Alarm - Right check value   -- %param3%
         "0",
-- Yellow Alarm - Right param1        -- %rparam1%
         "",

--Yellow Alarm - Action 
         "Updated from Message Files", 



----------------------------------------------------------------
-- Opearnds 4 - 7
null,null,null,null,

--Exception list description 
         "Updated from Message Files"



);



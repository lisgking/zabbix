insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Storage",
-- Short Description
         "Chunk IO OPS",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "sto_chkio_ops",
----------------------------------------------------------------
-- Red threshold column name          -- column name of configurable threshold
         "red_lvalue_param1",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value       -- %param1%
         "execute function hadv_stage_data_exception('dbspace chknum ops total_ops','select t2.name dbspace, t1.chknum, t1.pagesread+t1.  pageswritten ops, (select sum(t3.pagesread+t3.pageswritten) total_ops from sysmaster:syschktab t3) total_ops from sysmaster:syschktab t1, sysmaster:sysdbspaces t2 where t2.dbsnum=t1.dbsnum and t1.pagesread+t1.pageswritten > ( (select sum(t4.pagesread+t4.pageswritten) total_ops from sysmaster:syschktab t4) * ( %lparam1% /100)) group by 1,2,3','t_red_sto_chkio_ops','chknum',%v_prof_id%)", 
-- Red Alarm - Left param1            -- %lparam1%
         "40",

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
         "execute function hadv_stage_data_exception('dbspace chknum ops total_ops','select t2.name dbspace, t1.chknum, t1.pagesread+t1.  pageswritten ops, (select sum(t3.pagesread+t3.pageswritten) total_ops from sysmaster:syschktab t3) total_ops from sysmaster:syschktab t1, sysmaster:sysdbspaces t2 where t2.dbsnum=t1.dbsnum and t1.pagesread+t1.pageswritten > ( (select sum(t4.pagesread+t4.pageswritten) total_ops from sysmaster:syschktab t4) * ( %lparam1% /100)) group by 1,2,3','t_yel_sto_chkio_ops','chknum',%v_prof_id%)", 
-- Yellow Alarm - Left param1         -- %lparam1%
         "25",

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
null

);



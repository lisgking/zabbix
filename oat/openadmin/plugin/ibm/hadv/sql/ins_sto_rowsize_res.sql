insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Storage",
-- Short Description
         "Rowsize",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "sto_rowsize",
----------------------------------------------------------------
-- Red threshold column name          -- column name of configurable threshold
         "red_lvalue_param1",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value       -- %param1%
         "execute function hadv_stage_data_exception('partnum dbsname tabname pagesize rowsize','select t1.partnum, t1.dbsname, t1.tabname, t2.pagesize,t2.rowsize from sysmaster:systabnames t1, sysmaster:sysptnhdr t2 where t1.dbsname not in (""sysmaster"",""sysutils"",""system"",""sysusers"",""sysadmin"") and bitand(t2.flags,""0x8000004"") = 0 and t1.partnum=t2.partnum and t2.rowsize < t2.pagesize and mod((t2.pagesize-28),t2.rowsize) > ((t2.pagesize -28) * (%lparam1%/100) ) and mod(t1.partnum,1045876) <> 1 and t1.partnum <> 1048578','t_red_sto_rowsize','partnum',%v_prof_id%)", 
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
         "execute function hadv_stage_data_exception('partnum dbsname tabname pagesize rowsize','select t1.partnum, t1.dbsname, t1.tabname, t2.pagesize,t2.rowsize from sysmaster:systabnames t1, sysmaster:sysptnhdr t2 where t1.dbsname not in (""sysmaster"",""sysutils"",""system"",""sysusers"",""sysadmin"") and bitand(t2.flags,""0x8000004"") = 0 and t1.partnum=t2.partnum and t2.rowsize < t2.pagesize and mod((t2.pagesize-28),t2.rowsize) > ((t2.pagesize -28) * (%lparam1%/100) ) and mod(t1.partnum,1045876) <> 1 and t1.partnum <> 1048578','t_yel_sto_rowsize','partnum',%v_prof_id%)", 
-- Yellow Alarm - Left param1         -- %lparam1%
         "30",

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



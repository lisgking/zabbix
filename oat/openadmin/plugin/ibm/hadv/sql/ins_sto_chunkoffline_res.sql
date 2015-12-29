insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Storage",
-- Short Description
         "Offline Chunks",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "sto_chunkoffline",
----------------------------------------------------------------
-- Red threshold column name          -- column name of configurable threshold
         "",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value       -- %param1%
         "execute function hadv_stage_data('chknum dbsnum is_offline','select chknum,dbsnum,is_offline from sysmaster:syschunks where is_offline <> 0 ','t_red_sto_chunkoffline')", 
-- Red Alarm - Left param1            -- %lparam1%
         "",

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
         "",

-- Yellow Alarm - Left check type
         "VALUE",
-- Yellow Alarm - Left check value    -- %param1%
         "1", 
-- Yellow Alarm - Left param1         -- %lparam1%
         "",

-- Yellow Alarm - Operand             -- %param2% 
         "=",

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



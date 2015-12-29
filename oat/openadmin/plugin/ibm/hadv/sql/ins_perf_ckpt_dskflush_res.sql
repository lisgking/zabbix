insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Perf",
-- Short Description
         "Diskflush",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "ckpt_dskflush",
----------------------------------------------------------------
-- Red threshold column name          -- column name of configurable threshold
         "red_lvalue_param1",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value       -- %param1%
         "execute function hadv_stage_data('intvl type caller dskflush_per_sec n_dirty_buffs','select intvl,type,caller,dskflush_per_sec, n_dirty_buffs from sysmaster:syscheckpoint where n_dirty_buffs > dskflush_per_sec and dskflush_per_sec < %lparam1%','t_red_ckpt_dskflush')", 
-- Red Alarm - Left param1            -- %lparam1%
         200,

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
         "execute function hadv_stage_data('intvl type caller dskflush_per_sec n_dirty_buffs ','select intvl,type,caller,dskflush_per_sec, n_dirty_buffs  from sysmaster:syscheckpoint where n_dirty_buffs > dskflush_per_sec and dskflush_per_sec < %lparam1%','t_yel_ckpt_dskflush')", 
-- Yellow Alarm - Left param1         -- %lparam1%
         500,

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



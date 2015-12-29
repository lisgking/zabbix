insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "OS",
-- Short Description
         "OS Online Procs",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "",
----------------------------------------------------------------
-- Red threshold column name
         "",

-- Red Alarm - Left check type
         "VALUE",
-- Red Alarm - Left check value 
         "1",
-- Red Alarm - Left param1
         "",

-- Red Alarm - Operand 
         "=",

-- Red Alarm - Right check type
         "VALUE",
-- Red Alarm - Right check value 
         "0",
-- Red Alarm - Right param1
         "",

--Red Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Yellow threshold column name
         "",

-- Yellow Alarm - Left check type
         "SQL",
-- Yellow Alarm - Left check value   -- %param1%
         "select os_num_procs from sysmaster:sysmachineinfo",
-- Yellow Alarm - Left param1        -- %lparam1%
         "",

-- Yellow Alarm - Operand            -- %param2% 
         ">",

-- Yellow Alarm - Right check type
         "SQL",
-- Yellow Alarm - Right check value  -- %param3%
         "select os_num_olprocs from sysmaster:sysmachineinfo",
-- Yellow Alarm - Right param1       -- %rparam1%
         "",

--Yellow Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Opearnds 4 - 7
null,null,null,null,
null


);



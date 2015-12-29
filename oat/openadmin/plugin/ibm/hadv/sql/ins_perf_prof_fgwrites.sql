insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Perf",
-- Short Description
         "Foreground Writes",
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
         "red_rvalue",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value 
         "select ( t1.value / ( t1.value + t2.value + t3.value) ) *  100 from sysmaster:sysprofile t1, sysmaster:sysprofile t2, sysmaster:sysprofile t3 where t1.name='fgwrites' and t2.name='lruwrites' and t3.name='chunkwrites' ", 
-- Red Alarm - Left param1
         "",

-- Red Alarm - Operand 
         ">",

-- Red Alarm - Right check type
         "VALUE",
-- Red Alarm - Right check value 
         "5",
-- Red Alarm - Right param1
         "",

--Red Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Yellow threshold column name
         "yel_rvalue",

-- Yellow Alarm - Left check type
         "SQL",
-- Yellow Alarm - Left check value 
         "select ( t1.value / ( t1.value + t2.value + t3.value) ) *  100 from sysmaster:sysprofile t1, sysmaster:sysprofile t2, sysmaster:sysprofile t3 where t1.name='fgwrites' and t2.name='lruwrites' and t3.name='chunkwrites' ", 
-- Yellow Alarm - Left param1
         "",

-- Yellow Alarm - Operand 
         ">",

-- Yellow Alarm - Right check type
         "VALUE",
-- Yellow Alarm - Right check value 
         "0",
-- Yellow Alarm - Right param1
         "",

--Yellow Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Opearnds 4 - 7
null,null,null,null,
null



);



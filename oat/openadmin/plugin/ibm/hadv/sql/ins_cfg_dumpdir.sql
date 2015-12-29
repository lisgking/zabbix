insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Configuration",
-- Short Description
         "DUMPDIR",
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
         "yel_lvalue_param1",

-- Yellow Alarm - Left check type
         "SQL",
-- Yellow Alarm - Left check value 
         "select count(*)  from sysmaster:syscfgtab  where cf_name='DUMPDIR' and cf_original like '%lparam1%%' ", 
-- Yellow Alarm - Left param1
         "/tmp",

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



insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type
         "Configuration",
-- Short Description
         "LTXHWM",
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
         "red_lvalue_param1",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value 
         "select count(*) from sysmaster:syscfgtab t1, sysmaster:syscfgtab t2  where t1.cf_name='DYNAMIC_LOGS' and t1.cf_original = 0 and t2.cf_name='LTXHWM' and t2.cf_original > %lparam1% ", 
-- Red Alarm - Left param1
         "70",

-- Red Alarm - Operand 
         ">",

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
         "select count(*) from sysmaster:syscfgtab t1, sysmaster:syscfgtab t2  where t1.cf_name='DYNAMIC_LOGS' and t1.cf_original = 0 and t2.cf_name='LTXHWM' and t2.cf_original > %lparam1% ", 
-- Yellow Alarm - Left param1
         "50",

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



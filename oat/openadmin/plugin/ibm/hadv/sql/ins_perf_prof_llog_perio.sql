insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Perf",
-- Short Description
         "LLOG Pages Per IO",
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
         "select ( (t1.value  /  t2.value) / ( (t4.cf_original::int) / (t3.pagesize / 1024 )  ) ) *  100 from sysmaster:sysprofile t1, sysmaster:sysprofile t2, sysmaster:sysdbspaces t3, sysmaster:syscfgtab t4  where t1.name='llgpagewrites' and t2.name='llgwrites' and t4.cf_name='LOGBUFF' and t3.dbsnum=1", 
-- Red Alarm - Left param1
         "",

-- Red Alarm - Operand 
         ">",

-- Red Alarm - Right check type
         "VALUE",
-- Red Alarm - Right check value 
         "95",
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
         "select ( (t1.value  /  t2.value) / ( (t4.cf_original::int) / (t3.pagesize / 1024 )  ) ) *  100 from sysmaster:sysprofile t1, sysmaster:sysprofile t2, sysmaster:sysdbspaces t3, sysmaster:syscfgtab t4  where t1.name='llgpagewrites' and t2.name='llgwrites' and t4.cf_name='LOGBUFF' and t3.dbsnum=1", 
-- Yellow Alarm - Left param1
         "",

-- Yellow Alarm - Operand 
         "<",

-- Yellow Alarm - Right check type
         "VALUE",
-- Yellow Alarm - Right check value 
         "50",
-- Yellow Alarm - Right param1
         "",

--Yellow Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Opearnds 4 - 7
null,null,null,null,
null


);



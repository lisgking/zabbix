insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "Configuration",
-- Short Description
         "Physdbs",
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
         "SQL",
-- Red Alarm - Left check value 
         "select t1.dbsnum from sysmaster:syschunks t1, sysmaster:sysplog t2 where t1.chknum=t2.pl_chunk", 
-- Red Alarm - Left param1
         "",

-- Red Alarm - Operand 
         "=",

-- Red Alarm - Right check type
         "VALUE",
-- Red Alarm - Right check value 
         "1",
-- Red Alarm - Right param1
         "",

--Red Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Yellow threshold column name
         "",

-- Yellow Alarm - Left check type
         "SQL",
-- Yellow Alarm - Left check value 
         "select count(*) from sysmaster:syschunks t1, sysmaster:sysplog t2, sysmaster:systabnames t3 where t1.chknum=t2.pl_chunk and t3.partnum > (t1.dbsnum*1048576)+1 and t3.partnum < ((t1.dbsnum+1)*1048576)+1", 
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



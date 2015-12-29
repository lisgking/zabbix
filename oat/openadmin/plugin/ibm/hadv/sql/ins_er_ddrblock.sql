insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "ER",
-- Short Description
         "ER DDRBlock",
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
         "select ( ( ddr_logpage2block/ddr_total_logspace) * 100) from sysmaster:syscdr_ddr ",
-- Red Alarm - Left param1
         "",

-- Red Alarm - Operand 
         "<",

-- Red Alarm - Right check type
         "VALUE",
-- Red Alarm - Right check value 
         "25",
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
         "select ( ( ddr_logpage2block/ddr_total_logspace) * 100) from sysmaster:syscdr_ddr ",
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



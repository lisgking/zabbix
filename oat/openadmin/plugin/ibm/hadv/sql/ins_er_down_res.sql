insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0,
-- Group Type 
         "ER",
-- Short Description
         "ER Down Node",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "er_down",
----------------------------------------------------------------
-- Red threshold column name
         "red_rvalue",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value 
"execute function hadv_stage_data_exception('nif_connid nif_conname nif_state nif_connstate',' select nif_connid,nif_connname,nif_state,nif_connstate from sysmaster:syscdr_nif where nif_state != ""Connected"" or nif_connstate != ""RUN""', 't_red_er_down','nif_connname',%v_prof_id%)", 

-- Red Alarm - Left param1
         "",

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
         "",

-- Yellow Alarm - Left check type
         "VALUE",
-- Yellow Alarm - Left check value 
         "1",
-- Yellow Alarm - Left param1
         "",

-- Yellow Alarm - Operand 
         "=",

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

--Exception list description 
         "Updated from Message Files"


);



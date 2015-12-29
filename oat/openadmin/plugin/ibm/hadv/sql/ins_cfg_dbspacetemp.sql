insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
	0,
-- Group Type 
	"Configuration",
-- Short Description
         "DBSPACETEMP",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "",
----------------------------------------------------------------
-- Red threshold column name              -- column name of configurable threshold
         "",                    

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value           -- %param1%
         "select count(*) from sysmaster:syscfgtab where cf_name='DBSPACETEMP' and cf_original <> ''", 
-- Red Alarm - Left param1                -- %param1%
         "",

-- Red Alarm - Operand                    -- %param2%
         "=",

-- Red Alarm - Right check type
         "VALUE",
-- Red Alarm - Right check value          -- %param3%
         "0",
-- Red Alarm - Right param1               -- %rparam1%
         "",

--Red Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Yellow threshold column name           -- column name of configurable threshold
         "",

-- Yellow Alarm - Left check type
         "SQL",
-- Yellow Alarm - Left check value        -- %param1%
         "select count(*)  from sysmaster:syscfgtab  where cf_name='DBSPACETEMP' and substr(cf_original,length(cf_original),1) in (',',':')", 
-- Yellow Alarm - Left param1             -- %param1%
         "",

-- Yellow Alarm - Operand                 -- %param2%
         ">",

-- Yellow Alarm - Right check type
         "VALUE",
-- Yellow Alarm - Right check value      -- %param3%
         "0",
-- Yellow Alarm - Right param1           -- %rparam1%
         "",

--Yellow Alarm - Action 
         "Updated from Message Files", 

----------------------------------------------------------------
-- Opearnds 4 - 7
null,null,null,null,
null


);



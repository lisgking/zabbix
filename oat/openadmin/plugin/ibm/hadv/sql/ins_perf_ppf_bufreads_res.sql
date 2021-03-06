insert into hadv_gen_prof values (
-- Prof id
        -1,
-- Alarm id
         0, 
-- Group Type 
         "Perf", 
-- Short Description
         "Buffreads Per Table",
-- Name 
         null, 
-- Long Description 
         "Updated from Message Files",
-- Enable
         "Y",
-- Temp Table Results  - to be appended with t_red or t_yel, in code
         "ppf_bufreads",
----------------------------------------------------------------
-- Red threshold column name          -- column name of configurable threshold
         "red_lvalue_param1",

-- Red Alarm - Left check type
         "SQL",
-- Red Alarm - Left check value       -- %param1%
	"execute function hadv_stage_data_exception('dbsname tabname partnum bufreads total_bufreads', 'select t1.dbsname ,t1.tabname ,t1.partnum ,t1.bufreads , (select sum(t2.bufreads) from sysmaster:sysptprof t2 where t2.dbsname not in (""sysmaster"",""sysutils"" ,""system"",""sysuser"",""sysadmin"")  AND t2.tabname != ""TBLSpace"") total_bufreads from sysmaster:sysptprof t1 where t1.dbsname not in (""sysmaster"",""sysutils"",""system"",""sysuser"","" sysadmin"") AND t1.tabname != ""TBLSpace"" group by 1,2,3,4 having trunc( (t1.bufreads / (select sum(t2.bufreads) from sysmaster:sysptprof t2 where t2.dbsname not in (""sysmaster"",""sysutils"" ,""system"",""sysuser"",""sysadmin"")  AND t2.tabname != ""TBLSpace"") * 100),0) >  %lparam1%', 't_red_ppf_bufreads','partnum',%v_prof_id%)",

-- Red Alarm - Left param1            -- %lparam1%
         "40",

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
	"execute function hadv_stage_data_exception('dbsname tabname partnum bufreads total_bufreads', 'select t1.dbsname ,t1.tabname ,t1.partnum ,t1.bufreads , (select sum(t2.bufreads) from sysmaster:sysptprof t2 where t2.dbsname not in (""sysmaster"",""sysutils"" ,""system"",""sysuser"",""sysadmin"")  AND t2.tabname != ""TBLSpace"") total_bufreads from sysmaster:sysptprof t1 where t1.dbsname not in (""sysmaster"",""sysutils"",""system"",""sysuser"","" sysadmin"") AND t1.tabname != ""TBLSpace"" group by 1,2,3,4 having trunc( (t1.bufreads / (select sum(t2.bufreads) from sysmaster:sysptprof t2 where t2.dbsname not in (""sysmaster"",""sysutils"" ,""system"",""sysuser"",""sysadmin"")  AND t2.tabname != ""TBLSpace"") * 100),0) >  %lparam1%', 't_yel_ppf_bufreads','partnum',%v_prof_id%)",

-- Yellow Alarm - Left param1         -- %lparam1%
         "25",

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

--Exception list description 
         "Updated from Message Files"

);



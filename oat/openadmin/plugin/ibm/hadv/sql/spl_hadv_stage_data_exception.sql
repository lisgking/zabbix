
create function hadv_stage_data_exception(collist char(250), sql char(1000),tabname char(128), colname char(30), v_prof_id integer 
) returning integer;
define l_count             integer;
define l_ins_sql           lvarchar;
define l_sql               lvarchar;
define l_del_sql           lvarchar;
define l_base_tab          lvarchar;

on exception
end exception with resume;

trace on;
   let l_base_tab=tabname;
   select replace(l_base_tab,"t_yel_",'') into l_base_tab from sysmaster:sysdual;
   select replace(l_base_tab,"t_red_",'') into l_base_tab from sysmaster:sysdual;

   let l_count=0;
   let l_ins_sql = sql || " into temp " || trim(tabname);  
   let l_del_sql = "delete from " || trim(tabname) || " where " || trim(colname) || " in (select value from hadv_exception_prof t2 where t2.prof_id= "||v_prof_id||" and t2.tabname='"||trim(l_base_tab)||"')";


   execute immediate l_ins_sql;
   execute immediate l_del_sql;

   let l_sql = "select count(*) from "||trim(tabname);

   prepare lstmt from l_sql;
   declare lcurs cursor for lstmt;
   open lcurs;
   fetch lcurs into l_count;

   return l_count;
end function;



create function hadv_stage_data(collist char(250), sql char(500),tabname char(128)) returning integer;
define l_count             integer;
define l_ins_sql           lvarchar;
define l_sql               lvarchar;

on exception
end exception with resume;

trace on;
   let l_count=0;
   let l_ins_sql = sql || " into temp " || tabname;  

   execute immediate l_ins_sql;

   let l_sql = "select count(*) from "||tabname;

   prepare lstmt from l_sql;
   declare lcurs cursor for lstmt;
   open lcurs;
   fetch lcurs into l_count;

   return l_count;
end function;


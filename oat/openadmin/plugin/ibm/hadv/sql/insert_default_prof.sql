insert into hadv_profiles values(0,"Default",'A');
insert into hadv_sched_prof values(0,'to@company.org','from@company.org','Always');
update hadv_sched_prof set prof_id = (select t1.prof_id from hadv_profiles t1 where name='Default' ) where prof_id=0;

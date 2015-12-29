-- file: spl_hadv_remove_file.sql
-- auth: Jeff Cauhape
-- date: 06/03/2011
-- desc: Remove a temp file created by an HADV program.
-- Mod    Date     Description
--
-- 001    07/14/2011  Changed spl names to include hadv_

create procedure hadv_remove_file(p_prog_name char(128), p_file_type int DEFAULT 2);

define p_command     lvarchar(2048);
define p_file_path   char(128);

  let p_prog_name = "*" || trim(p_prog_name);

  if p_file_type = 1 then

    select input_file into p_file_path 
      from hadv_progsupp
     where prog_path matches p_prog_name;

  elif p_file_type = 2 then

    select output_file into p_file_path 
      from hadv_progsupp
     where prog_path matches p_prog_name;

  else

    select trace_file into p_file_path 
      from hadv_progsupp
     where prog_path matches p_prog_name;

  end if

  let p_file_path = trim(p_file_path);

  let p_command = "touch " || p_file_path;
  system p_command;

  let p_command = "rm -f " || p_file_path;
  system p_command;

end procedure;


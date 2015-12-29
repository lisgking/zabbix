-- file: spl_hadv_format_text.sql 
-- auth: Jeff Cauhape 
-- date: 07-27-2011 
-- desc: Receive a line of text and fold and format it to fit 
--       in the HCL Alarms Rerport. 
-- Mod    Date     Description 
-- 
-- 001  08/15/2011 Modified to change all instances of hcl_ to hadv_ 
-- 002  08/22/2011 Changed control-m to ascii \n
-- 003  08/26/2011 Moved IFX_ALLOW_NEWLINE to php code, can't run multiple 
--                 commands from *.sql file via php
-- 004  08/28/2011 Changed in param from char(255) to lvarchar 
-- 005  08/28/2011 Changed in param from char(255) to lvarchar 
 
-- drop function hadv_format_text; 
 
CREATE FUNCTION hadv_format_text(in_data lvarchar)  
  returning lvarchar; 
 
define out_data        lvarchar(2048);
define line_header     lvarchar; 
define work_length     int; 
define indent          varchar(80); 
define eol_char	       char(2);
-- 
-- Tracing 
-- 
-- set debug file to "hadv_format_text.debug"; 
-- trace on; 
 
-- 
-- Allow embedded new lines. 
-- 
execute procedure IFX_ALLOW_NEWLINE('t'); 

  let out_data = "";
  let line_header = "";
  let indent = "";
  let eol_char = ' 
';
 
  if length(in_data) > (75) then 
-- 
-- Save the first 9 chars as the line header 
-- 
    let work_length = length(in_data); 
    let line_header = in_data[1,9]; 
    let in_data     = substr(in_data,10); 
    let work_length = length(in_data); 
    let indent = "         "; 

-- 
-- Process the rest of it. 
-- 
    while (work_length > 0) 
      let out_data = out_data || indent || in_data[1,66] || eol_char;
      let in_data  = substr(in_data,66); 
      let work_length = length(in_data); 
    end while 
 
    let out_data = line_header || trim(out_data) || eol_char;
 
  else 
    let out_data = in_data || eol_char; 
  end if 
 
  return out_data; 
 
END FUNCTION; 
 

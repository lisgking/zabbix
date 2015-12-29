create table hadv_gen_prof( 
   prof_id              integer,
   id                   serial,
   group                char(15),
   desc                 char(50),
   name         	char(50),
   ldesc                lvarchar,
   enable               char(1),
   temp_tab             char(20),

-- Red alarm information
   red_threshold        char(20),   -- Column name containing red threshold 

   red_lvalue_type      char(10),
   red_lvalue           lvarchar,   -- o_param1 - l_value1 (local)
   red_lvalue_param1    char(20),

   red_op               char(5),    -- o_param2 - l_value2 (local) 

   red_rvalue_type      char(10),
   red_rvalue           lvarchar,   -- o_param3 - l_value3 (local)
   red_rvalue_param1    char(20),

   red_action           lvarchar,   -- l_action (local)

-- Yellow alarm information
   yel_threshold        char(20),   -- Column name containing yellow threshold 

   yel_lvalue_type      char(10),
   yel_lvalue           lvarchar,   -- o_param1 - l_value1 (local)
   yel_lvalue_param1    char(20),

   yel_op               char(5),    -- o_param2 - l_value2 (local) 

   yel_rvalue_type      char(10),
   yel_rvalue           lvarchar,   -- o_param3 - l_value3 (local)
   yel_rvalue_param1    char(20),

   yel_action           lvarchar,   -- l_action (local)

-- Additional Parameters that can be used during Display of Action
   o_param4             char(20),
   o_param5             char(20),
   o_param6             char(20),
   o_param7             char(20), 
   exc_desc             lvarchar
);


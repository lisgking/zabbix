<?php
/*********************************************************************
 *  Licensed Materials - Property of IBM
 *
 *  "Restricted Materials of IBM"
 *
 *  OpenAdmin Tool for Informix
 *  Copyright IBM Corporation 2011.  All rights reserved.
 **********************************************************************/


/**
 * This class has functions for displaying rows of data
 * returned from a query in a table format or another format
 * specified by a template having the following functions
 * defined -- sysgentab_start_output, sysgentab_row_output, and
 * sysgentab_end_output.
 *
 */
class hadvlibs {


    public $idsadmin;
    private $language = array();

    /**
     * constructor.
     *
     * @param idsadmin object
     * @return gentab object
     */
    function __construct(&$idsadmin)
    {
        $this->idsadmin = &$idsadmin;
        $this->idsadmin->load_lang("hadv");
        $this->idsadmin->load_lang("msg");
        $this->idsadmin->load_lang("act");
        $this->idsadmin->load_lang("exc");
    }


    /**
     * check_installed()
     *
     */
    function check_installed()
    {
        


      $db_sysadmin = $this->idsadmin->get_database("sysadmin");




      $vqry = "select count(*) as HCLEXISTS from systables where tabname='hadv_gen_prof';";
       $stmt=$db_sysadmin->query($vqry);
       $row2=$stmt->fetch();
       $stmt->closeCursor();

       if (isset($row2['HCLEXISTS']) && $row2['HCLEXISTS'] == 0)
       {

            if ( $this->idsadmin->isreadonly() )
            {
               $this->idsadmin->fatal_error($this->idsadmin->lang('noauth'));
            }

/*
$html .= <<<EOF
  Installing Heath Check Lite:  <img src='images/spinner.gif'/>
EOF;
*/

        $this->load_tab_files();
        $this->load_idx_files();
        $this->load_spl_files();
        $this->load_ins_files();
        $this->load_insert_files();


         $stmt = $db_sysadmin->query("select prof_id from hadv_profiles where name='Default'" );
         $res  = $stmt->fetch();
         $prof_id = $res['PROF_ID'];

        /*
         * Update hadv_gen_prof with prof_id 
         */ 

         $db_sysadmin->exec("update hadv_gen_prof set prof_id = ${prof_id} ".
                 " where prof_id=-1 ;");

        //$this->update_tempdir($prof_id);
        $this->update_profile_per_msg_files($prof_id);
 
        $sql_stmt="execute procedure hadv_update_profile_os_info(${prof_id})";
        $db_sysadmin->exec($sql_stmt);


       }  // End if not exists

 
        $this->idsadmin->html->add_to_output($html);
        
    }







    function load_spl_files()
    {

        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /*
         * Create SPL 
         */ 
         $db_sysadmin->exec("execute procedure IFX_ALLOW_NEWLINE('t')");
         $spl_files = glob("plugin/ibm/hadv/sql/spl_*.sql");
         foreach( $spl_files as $file)
         {
            ob_start();
            include($file);
            $file_var= ob_get_contents(); 
            ob_end_clean();
            $stmt=$db_sysadmin->query($file_var);

         } 

    }

    function load_tab_files()
    {

        $db_sysadmin = $this->idsadmin->get_database("sysadmin");
        
        /*
         * Create Tables
         */ 
         $tab_files = glob("plugin/ibm/hadv/sql/tab_*.sql");
         foreach( $tab_files as $file)
         {
            ob_start();
            include($file);
            $file_var= ob_get_contents(); 
            ob_end_clean();
            $stmt=$db_sysadmin->query($file_var);

         } 

    }

    function load_idx_files()
    {

        $db_sysadmin = $this->idsadmin->get_database("sysadmin");
        
        /*
         * Create Indexes 
         */ 
         $idx_files = glob("plugin/ibm/hadv/sql/idx_*.sql");
         foreach( $idx_files as $file)
         {
            ob_start();
            include($file);
            $file_var= ob_get_contents(); 
            ob_end_clean();
            $stmt=$db_sysadmin->query($file_var);

         } 


    }

    function load_ins_files()
    {

        $db_sysadmin = $this->idsadmin->get_database("sysadmin");
        

        /*
         * Insert rows into hadv_gen_prof using ins_*.sql files 
         */ 
         $ins_files = glob("plugin/ibm/hadv/sql/ins_*.sql");
         foreach( $ins_files as $file)
         {
            ob_start();
            include($file);
            $file_var= ob_get_contents(); 
            ob_end_clean();
            $stmt=$db_sysadmin->query($file_var);

         } 

    }

    function load_insert_files()
    {

        $db_sysadmin = $this->idsadmin->get_database("sysadmin");
        
        /*
         * Insert rows other rows insert_* 
         */ 
         $insert_files = glob("plugin/ibm/hadv/sql/insert_*.sql");
         foreach( $insert_files as $file)
         {
            ob_start();
            include($file);
            $file_var= ob_get_contents(); 
            ob_end_clean();
            $stmt=$db_sysadmin->query($file_var);

         } 
   }

// This is client side code, needs to be changed to run server side
//  IE, call spl to do this, don't call this until changed to server side
    function update_tempdir($prof_id)
    {

        $db_sysadmin = $this->idsadmin->get_database("sysadmin");
        
         $tmpdir = sys_get_temp_dir();
         $db_sysadmin->exec("update hadv_gen_prof set yel_lvalue_param1='{$tmpdir}' " .
                 " where prof_id=${prof_id}  and " .
                 " desc in ('DUMPDIR','TAPEDEV','LTAPEDEV');");
   }



    function update_profile_per_msg_files($prof_id)
    {

        $db_sysadmin = $this->idsadmin->get_database("sysadmin");

        /*
         * Update hadv_gen_prof with msg files 
         */ 

         $fname = "plugin/ibm/hadv/lang/en_US/lang_dsc.xml";
         if ( file_exists($fname) )
         {
               $xml = simplexml_load_file( $fname );
               if (! is_null($xml))
               {
                       foreach ( $xml as $k )
                       {
                               $name = (string)$k->getName();
                               $this->language[$name]=(string)$xml->$name;
                       }
               }
               unset($xml);
         }
         $fname = "plugin/ibm/hadv/lang/en_US/lang_msg.xml";
         if ( file_exists($fname) )
         {
               $xml = simplexml_load_file( $fname );
               if (! is_null($xml))
               {
                       foreach ( $xml as $k )
                       {
                               $name = (string)$k->getName();
                               $this->language[$name]=(string)$xml->$name;
                       }
               }
               unset($xml);
         }
         $fname = "plugin/ibm/hadv/lang/en_US/lang_act.xml";
         if ( file_exists($fname) )
         {
               $xml = simplexml_load_file( $fname );
               if (! is_null($xml))
               {
                       foreach ( $xml as $k )
                       {
                               $name = (string)$k->getName();
                               $this->language[$name]=(string)$xml->$name;
                       }
               }
               unset($xml);
         }
         $fname = "plugin/ibm/hadv/lang/en_US/lang_exc.xml";
         if ( file_exists($fname) )
         {
               $xml = simplexml_load_file( $fname );
               if (! is_null($xml))
               {
                       foreach ( $xml as $k )
                       {
                               $name = (string)$k->getName();
                               $this->language[$name]=(string)$xml->$name;
                       }
               }
               unset($xml);
         }




         $updqrycnt = $db_sysadmin->query("select count(*) as count ". 
                 "from hadv_gen_prof where prof_id = ${prof_id}");       
         $rescnt = $updqrycnt->fetch();
         $count = $rescnt['COUNT'];
         $updqry = $db_sysadmin->query("select replace(desc,'Alarm Check', " .
                 " '') desc from hadv_gen_prof where prof_id = ${prof_id}");       
         for($i=1; $i <= $count; $i++)
         {
            $res = $updqry->fetch();
            $desc = $res['DESC'];
     
            $msg_desc = "msg_". preg_replace('/ /','_',$desc,-1);
            $act_r_desc = preg_replace('/msg_/','act_r_',$msg_desc,-1);
            $act_y_desc = preg_replace('/msg_/','act_y_',$msg_desc,-1);
            $msg_full_desc = $this->language["{$msg_desc}"];
            $act_r_full_desc = $this->language["{$act_r_desc}"];
            $act_y_full_desc = $this->language["{$act_y_desc}"];
            $msg_full_desc = preg_replace("/'/", "''",$msg_full_desc,-1);
            $act_r_full_desc = preg_replace("/'/", "''",$act_r_full_desc,-1);
            $act_y_full_desc = preg_replace("/'/", "''",$act_y_full_desc,-1);
            $dsc_desc = preg_replace('/msg_/','dsc_',$msg_desc,-1);
            $dsc_full_desc = $this->language["{$dsc_desc}"];

            $updstmt="update hadv_gen_prof set (name,ldesc,red_action,yel_action) ".
                 "= ( '{$dsc_full_desc}', ".
                 " '{$msg_full_desc}', ".
                 " '{$act_r_full_desc}', ".
                 " '{$act_y_full_desc}' ) ".
                 "where prof_id=${prof_id} and desc = '${desc}'";
            $db_sysadmin->exec($updstmt);
            //$html .="updstmt=  ${updstmt} <br> " ;
         }


         $updqrycnt = $db_sysadmin->query("select count(*) as count from ".
                "hadv_gen_prof where prof_id = ${prof_id} and exc_desc ".
                "is not null");       
         $rescnt = $updqrycnt->fetch();
         $count = $rescnt['COUNT'];
         $updqry = $db_sysadmin->query("select replace(desc,'Alarm Check', ".
                " '') desc from hadv_gen_prof where prof_id = ${prof_id} ".
                "and exc_desc is not null");       
         for($i=1; $i <= $count; $i++)
         {
            $res = $updqry->fetch();
            $desc = $res['DESC'];
     
            $exc_desc = "exc_". preg_replace('/ /','_',$desc,-1);
            $exc_full_desc = $this->language["{$exc_desc}"];
            $exc_full_desc = preg_replace("/'/", "''",$exc_full_desc,-1);
            $updstmt="update hadv_gen_prof set (exc_desc) ".
                 "= ('{$exc_full_desc}') where " .
                 "prof_id=${prof_id} and desc = '{$desc}'";
            $db_sysadmin->exec($updstmt);
            //$html .="updstmt=  ${updstmt} <br> " ;
         }

   }
}
?>

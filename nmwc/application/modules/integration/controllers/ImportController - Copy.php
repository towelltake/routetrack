<?php
/**
* @name       ImportController
* @since      
* @version    Release: 1
* @author     Mayur Bhayani <mayur.b@elantechnologies.com>
* @copyright  Elan Technologies
* @param   	
*
* This controller is used to import csv files and store to database
*/
class Integration_ImportController extends Integration_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      24 July, 2012
    * @version    Release: 1
    * @author     Mayur Bhayani <mayur.b@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    public function init()
    {
        $this->translate 	= Zend_Registry::get('Zend_Translate');
        $this->SFA_Comman	= new SFA_Comman();
        
        $this->currentUser = SFA_Loginauth::getIdentity();
        if(!isset($this->currentUser) || empty($this->currentUser))
        {
            SFA_Message::setMsg($this->translate->_('Do Login'));
            //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }        
    }
    
    /**
    * @name       importcsvAction
    * @since      24 July, 2012
    * @version    Release: 1
    * @author     Mayur Bhayani <mayur.b@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is used to import CSV file to database
    *
    */
    public function importcsvAction()
    {
        // CREATE A SESSION NAMESPACE
        $Common_NameSpace = new Zend_Session_Namespace('Common');
        
        // Get Database tables
        $param_array[1] = 1;
        $param_array[2] = '';
        
        $result = $this->SFA_Comman->executequery('CALL sp_datamanagement_import_importcsv(?,?)',$param_array,'');
        $this->view->tables = $result[0];
        
        $this->view->formdata = $formdata = $this->_request->getPost();
        
        // Verify Data
        if(count($formdata) > 0 && isset($formdata['verify']) && $formdata['verify'] !='' )
        {
            if(!$_FILES['importcsv']['name']) return;
            
            $this->view->showgrid = true;
            
            $handle = fopen($_FILES['importcsv']['tmp_name'],'r');
            if(!$handle) die('Cannot open uploaded file.');
            
            //Read the file as csv
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $col_count = 0;
                foreach($data as $key=>$value) {                    
                    $col_count++;
                }
                break;
            }            
            fclose($handle);            
            
            $param_array[1] = 2;
            $param_array[2] = $formdata['tablename'];
            
            $result = $this->SFA_Comman->executequery('CALL sp_datamanagement_import_importcsv(?,?)',$param_array,'');
            $tableinfo = $result[0];
            $totalfields = count($tableinfo);
            
            if($totalfields != $col_count) {
                $this->view->errormessage = 'Error: CSV columns and database table column\'s must be same.';
            }
        }
        
        // Submit data & import CSV to database
        if(count($formdata) > 0 && isset($formdata['submit_file']) && $formdata['submit_file'] !='' )
        {
            $param_array[1] = 2;
            $param_array[2] = $formdata['tablename'];
           
            $result = $this->SFA_Comman->executequery('CALL sp_datamanagement_import_importcsv(?,?)',$param_array,'');
            
            $tableinfo = $result[0];
            $totalfields = count($tableinfo);
            
            $fields = array();
            if($totalfields > 0)
            {
                foreach($tableinfo as $key => $val) {
                    $fields[] = $val['Field'];
                }
                $file_extension = $_REQUEST['file_extension'];
                if($file_extension == "CSV")
                    $this->CSVImport($formdata['tablename'], $fields, 'importcsv');
                else
                    $this->XLSImport($formdata['tablename'], $fields, 'importcsv');
            }
            
            $Common_NameSpace->showgrid     = true;
            $Common_NameSpace->tablename    = $formdata['tablename'];
            $this->_helper->redirector("importcsv", "import", "integration");
        }
        
        // Display grid
        if($Common_NameSpace->showgrid == true && $Common_NameSpace->tablename != "")
        {
            $this->view->showgrid = $Common_NameSpace->showgrid;
            
            $cols_array    = $this->SFA_Comman->display_table_data[$Common_NameSpace->tablename];
            $columns_show  = $this->SFA_Comman->display_table_data[$Common_NameSpace->tablename];
            
            $pagingparams = array(
                         "show_grid_heading" => true,
                         "grid_heading_message" => $this->translate->_('Overview'),
                         "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                         "show_searchbox" => false,
                         "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                         "show_selectbox" => false,
                         "show_editlink" => false,
                         "show_deletelink" => false,
                         "deletelink" => array(""),
                         "show_deleteall" => false,
                         "primaryid" => $this->SFA_Comman->display_table_data[$Common_NameSpace->tablename][0],
                         //"editlink" => array(""),
                         "fetch_columns_inquery" => $cols_array,
                         "show_columns" => $columns_show,
                         "no_search_fields" => array(""),
                         "nodata_message" => $this->translate->_('No Record(s) Found')
                         );
            
            // create grid class object
            $pagingshow = new SFA_Paging($pagingparams);
            
            // call common function of grid class
            $get_return_vals = $pagingshow->commnfunc();
                
            // call the stored procedure for fetch the data  
            $param_array[1] = '1';
            $param_array[2] = '';
            $param_array[3] = $this->SFA_Comman->display_table_data[$Common_NameSpace->tablename][0];
            $param_array[4] = $get_return_vals['order_type'];
            $param_array[5] = $get_return_vals['offset'];
            $param_array[6] = (int)$get_return_vals['show_records_per_page'];
            $param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
            $param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
            $param_array[9] = $Common_NameSpace->tablename;

            $result = $this->SFA_Comman->executequery('CALL sp_datamanagement_import_get(?,?,?,?,?,?,?,?,?)',$param_array,'');         
            
            $data_arr["count"]      = $result[0][0]['counter'];
            $data_arr["data"][0]    = $result[1];
            
            // pass the data in summary_showdatagrid() function & create a final variable for view
            $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
            
            $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
        }        
    }
    
    
    /**
    * @name       CSVImport
    * @since      24 July, 2012
    * @version    Release: 1
    * @author     Mayur Bhayani <mayur.b@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to create query to insert in database
    *
    */
    public function CSVImport($table, $fields, $csv_fieldname='csv')
    {
        if(!$_FILES[$csv_fieldname]['name']) return;
        
        $this->view->showgrid = true;
        
        $handle = fopen($_FILES[$csv_fieldname]['tmp_name'],'r');
        if(!$handle) die('Cannot open uploaded file.');
    
        $row_count = 0;
        $sql_query = "INSERT INTO $table(". implode(',',$fields) .") VALUES(";
    
        $rows = array();
        
        //Read the file as csv
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_count++;
            if($row_count > 1)
            {
                foreach($data as $key=>$value)
                {
                    //if($key > 0) // MB - To skip first heading row
                        $data[$key] = '"'.addslashes($value).'"';
                }
                $rows[] = implode(",",$data);
            }
        }
        $sql_query .= implode("),(", $rows);
        $sql_query .= ")";
        fclose($handle);
    
        if(count($rows)) { //If some recores  were found,
            //Replace these line with what is appropriate for your DB abstraction layer
            
            //echo $sql_query;exit;
           
            //$db = Zend_Db_Table::getDefaultAdapter();
            //$db->query($sql_query);
            
            $param_array[1] = $sql_query;
            $result = $this->SFA_Comman->executequery('CALL sp_datamanagement_import(?)',$param_array,'');
            
            print 'Successfully imported '.$row_count.' record(s)';
        }
        else
        {
            print 'Cannot import data - no records found.';
        }
    }
    
    /**
    * @name       exportxls
    * @since      12 Oct, 2012
    * @version    Release: 1
    * @author     Sharad Garach <sharad@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to export excelsheet
    *
    */
    public function exportxlsAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        
        $tablename = (isset($_REQUEST['tablename']) && $_REQUEST['tablename'] != "") ? $_REQUEST['tablename']: "usermaster";
        $flag = 0;
        //$model = new SFA_Model_Sample();
        $param_array[1] = 2;
        $param_array[2] = $_REQUEST['tablename'];
        
        $result = $this->SFA_Comman->executequery('CALL sp_datamanagement_import_importcsv(?,?)',$param_array,'');
        $data = $result[0];
        
        $filename = BASE_PATH . "/data/excel/".$tablename."-" . date( "m-d-Y" ) . ".xls";
        $realPath = realpath( $filename );
        if( false === $realPath )
        {
            touch( $filename );
            chmod( $filename, 0777 );
        }
        
        $file_extension = $_REQUEST['file_extension'];
        if($file_extension == "CSV")
        {   // File will export as csv
            $csv_terminated = "\n";
            $csv_separator = ",";
            $csv_enclosed = '"';
            $csv_escaped = "\\";
            $fields_cnt = count($data);
            $schema_insert = '';
            
            for ($i = 0; $i < $fields_cnt; $i++)
            {
                $l = $csv_enclosed . str_replace($csv_enclosed, $csv_escaped . $csv_enclosed,
                    stripslashes($data[$i]['Field'])) . $csv_enclosed;
                $schema_insert .= $l;
                $schema_insert .= $csv_separator;
            } // end for
         
            $out = trim(substr($schema_insert, 0, -1));
            $out .= $csv_terminated;
            
            $exportfilename = $tablename."-" . date( "m-d-Y" ) . ".csv";
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Content-Length: " . strlen($out));
            header("Content-type: text/x-csv");
            //header("Content-type: text/csv");
            //header("Content-type: application/csv");
            header("Content-Disposition: attachment; filename=$exportfilename");
            echo $out;
            $flag = 1;
        }
        else if($file_extension == "XLS" || $file_extension == "XLSX")
        {   // File will export as xls
            $excel = new SFA_ExcelWriter($filename);
            if( false == $excel )
            {
                print_r($excel->error);
                exit();
            }
            
            $final_arr = array();
            foreach ( $data AS $row )
            {
                $final_arr[] = "<b><font size='3'>".$row['Field']."</font></b>";
            }
            
            $excel->writeLine($final_arr);
            $excel->writeRow();
            $excel->close();
            
            if((isset($filename)) && (file_exists($filename)))
            {
                $exportfilename = $tablename."-" . date( "m-d-Y" ) . ".xls";
                header("Content-type: application/force-download");
                header('Content-Disposition: inline; filename="' .$filename . '"');
                header("Content-Transfer-Encoding: Binary");
                header("Content-length: ".filesize($filename));
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $exportfilename . '"');
                readfile("$filename");
                $flag = 1;
            }
        }
		else
		{
		   echo "No file selected";
		}
        if($flag == 1)
		{
            unlink( $filename );
        }
        exit();
    }
    
    /**
    * @name       XLSImport
    * @since      25 Sep, 2012
    * @version    Release: 1
    * @author     Pankil Thakkar <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to import excelsheet
    *
    */
    function XLSImport($table, $fields, $csv_fieldname='csv')
    {
        if(!$_FILES[$csv_fieldname]['name']) return;
        
        //$this->view->showgrid = true;
        
        //$handle = fopen($_FILES[$csv_fieldname]['tmp_name'],'r');
        //if(!$handle) die('Cannot open uploaded file.');
        //
        //$row_count = 0;
        //$sql_query = "INSERT INTO $table(". implode(',',$fields) .") VALUES(";
        
        $filename = BASE_PATH . "/data/excel/".$table."-" . date( "m-d-Y" ) . ".xls";
        
        if(move_uploaded_file($_FILES[$csv_fieldname]['tmp_name'], $filename))
        {
            touch( $filename );
            chmod( $filename, 0777 );
            $excel = new SFA_Excelreader();
            // Set output Encoding.
			$excel->setOutputEncoding('CP1251');
            $excel->read($filename);
            
            // Added by Sharad Garach
            $model_importexport = new SFA_Model_Importexport();
            $import_status = $model_importexport->saveXlsTabledata($excel->sheets, $table);
            //var_dump($adapter->getMessages());
            if($import_status)
                $sucsMsg = "Your file has been imported successfully!";
            else
                $errMsg = "ERROR: while importing the file!";
            
            //echo "<table>";
            //while($x<=$excel->sheets[0]['numRows']) {
            //  echo "\t<tr>\n";
            //  $y=1;
            //  while($y<=$excel->sheets[0]['numCols']) {
            //    $cell = isset($excel->sheets[0]['cells'][$x][$y]) ? $excel->sheets[0]['cells'][$x][$y] : '';
            //    echo "\t\t<td>$cell</td>\n";  
            //    $y++;
            //  }  
            //  echo "\t</tr>\n";
            //  $x++;
            //}
            //echo "</table>";
        }
        exit();
    }
}
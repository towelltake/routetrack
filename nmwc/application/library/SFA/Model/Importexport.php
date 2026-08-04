<?php
/**
 * @name       SFA_Model_Importexport
 * @since      12-10-2012
 * @version    Release: 1
 * @author     SG <sharad@elantechnologies.com>
 * @copyright  Elan Technologies
 * @param       
 * This Class contains all the General functions which are used for import/export file actions.
 */

class SFA_Model_Importexport extends Zend_Db_Table_Abstract
{
    /**
    * @name       saveXlsTabledata
    * @since      12-10-2012
    * @version    Release: 1
    * @author     SG <sharad@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param       
    *
    * This function saves user xls data imported from excel file.
    */
    public function saveXlsTabledata($sheets = array(), $tablename)
    {
        $this->_db->query('TRUNCATE Table '.$tablename);
		
		
        if(!empty($sheets))
        {
            $query = "";
            $insert_data = "";
            for($i=0; $i<count($sheets); $i++)
            {
                if(!empty($sheets[$i]['cells']))
                {
                    $start = 2;
                    $end = count($sheets[$i]['cells']);
                    while ($start < $end) {
                        $query = "";
                        $insert_data = "";
                        $heads = $sheets[$i]['cells'][1];
						//if()
                        if(!empty($heads))
                        {
                            $query = "INSERT INTO ".$tablename." (";
                            for($h=1; $h<=count($heads); $h++)
                            {
                                $query .= "`".$heads[$h]."`, ";
                            }
                            $query = substr($query, 0, -2).") VALUES (";
                        }
                        for($x=$start; $x<=($start+250); $x++)
                        {
                            $cells      = $sheets[$i]['cells'][$x];
                            $cellsInfo  = $sheets[$i]['cellsInfo'][$x];
                            if(!empty($cells))
                            {
                                for($r=1; $r<=count($heads); $r++)
                                {
                                    if(isset($cellsInfo[$r]['raw']) && trim($cellsInfo[$r]['raw']) != "" && $cellsInfo[$r]['type'] == "date")
                                        $insert_data .= "'".date("Y-m-d", (trim($cellsInfo[$r]['raw']) - 24 * 60 * 60))."', ";
                                    else if(trim($cells[$r]) != "")
                                    {
                                        //$split_dt = explode("/", trim($cells[$r]));
                                        //$query .= "'".date("Y-m-d", mktime("0", "0", "0", trim($split_dt[0]), trim($split_dt[1]), trim($split_dt[2])))."', ";
                                        $insert_data .= "'".trim($cells[$r])."', ";
                                    }
                                    else
                                        $insert_data .= "'', ";
                                }
                                $insert_data = substr($insert_data, 0, -2)."),(";
                            }
                            
                        }
                        $start = $start + 251;
                        $query .= substr($insert_data, 0, -2);
                        try
                        {
                            if($query != "") {
                                $query = str_replace("'", "\'", $query);
								$param_array = array();
                                $param_array[1] = $query;
                                $param_array[2] = "From Import " .$tablename;
								
								//echo str_repeat(" ",1024*1024*4) .$query."<br>";ob_flush();
								
                                $this->SFA_Comman    = new SFA_Comman();
                                $result = $this->SFA_Comman->executequery('CALL int_sp_execute_query()', $param_array);
                                $param_array = array();
                                $param_array[1] = $tablename;
                                $result = $this->SFA_Comman->executequery('CALL sp_import_post_maintable_fromtemptable(?)',$param_array,'');
                            }
                        }
                        catch(Exception $e)
                        {
                            //echo "Error : " .$e->getMessage() ."<br>";
                            throw new Exception($e->getMessage());
                        }
                    }
                }
            }
            return true;
        }
        else
            return false;
    }
}
?>
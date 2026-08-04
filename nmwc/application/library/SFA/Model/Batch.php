<?php
/**
 * @name       SFA_Model_Batch
 * @since      30-10-2011
 * @version    Release: 8
 * @author     PT
 * @copyright  Elan Technologies
 * @param   	
 * This Class contains all the batch related  query
 */

class SFA_Model_Batch extends Zend_Db_Table_Abstract
{

    protected $_batchexpirydetail_temp 	= 'batchexpirydetail_temp';

    public function update_batch_entry($data = array(),$code_arr = array(),$name_arr = array())
    {
        if(count($code_arr) > 0)
        {
            $ids = implode(",",$code_arr);
            $delete_query ="DELETE FROM
                                batchexpirydetail_temp
                            WHERE  
                                transactiontypecode IN(".$ids.")
                                AND itemcode = '".$data['itemcode']."'
                                AND visitkey = '".$data['visitkey']."'";
                            
            $this->_db->query($delete_query);
            
            for($i = 0; $i < count($name_arr); $i++)
            {
                $update_field .= $name_arr[$i]." = 0,";
            }
            $update_field = rtrim($update_field,",");
            
            $update_query ="UPDATE batchmaster_temp SET $update_field
                            WHERE  
                                itemcode = '".$data['itemcode']."'
                                AND visitkey = '".$data['visitkey']."'";
                            
            $this->_db->query($update_query);
        }
    }
    /**
    * @name       check_item_exist
    * @since      30-10-2011
    * @version    Release: 8
    * @author     PT
    * @copyright  Elan Technologies
    * @param   	
    * This Class contains all the batch related  query
    */
    public function check_item_exist($data = array())
    {
        $select_query = "SELECT count(*) as cnt FROM invoicedetail_temp where itemcode = '".$data['itemcode']."' AND visitkey = '".$data['visitkey']."'";
        $result = $this->getAdapter()->fetchAll($select_query);
        return $result;
    }
    
    /**
    * @name       check_salesorder_item_exist
    * @since      30-10-2011
    * @version    Release: 8
    * @author     PT
    * @copyright  Elan Technologies
    * @param   	
    * This Class contains salesorder item exist or not
    */
    public function check_salesorder_item_exist($data = array())
    {
        $select_query = "SELECT count(*) as cnt FROM salesorderdetail_temp where itemcode = '".$data['itemcode']."' AND visitkey = '".$data['visitkey']."'";
        $result = $this->getAdapter()->fetchAll($select_query);
        return $result;
    }
}
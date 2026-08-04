<?php
/**
 * @name       Reason
 * @since      16-03-2012
 * @version    Release: 1
 * @author     HD
 * @copyright  Elan Technologies
 * @param
 * This Class contains all the Basic module
 */

class SFA_Model_Reason extends Zend_Db_Table_Abstract
{
    /**
    * @name       getgoodreason
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function is giving an output of the good reason
    */
    public function getgoodreason($id = '')
    {
	$select = $this->_db->select()
                ->from(array('reason' => 'retitmreasons') , array('reason.*'));
	if($id > 0){
	    $select->where('Code = '.$id);
	}	
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller	
	return $result;	
    }
    /**
    * @name       addeditbank
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function contain add and edit good reason
    */
    public function addeditgoodreason($formdata)
    {
	if($formdata['hdnid'] > 0){
	    
	   $updateData = array(
		'Description'		=> trim($formdata["txtdesc"]),
		'ArbDescription'	=> trim($formdata["txtdesc_arb"]),
		'HHCDescription'	=> ''
	    );
    
	    $this->_db->update("retitmreasons",$updateData," Code = '".$formdata['hdnid']."'");
	    
	    return $formdata['hdnid'];
	}
	else
	{
	    $insertData = array(
		'Description'		=> trim($formdata["txtdesc"]),
		'ArbDescription'	=> trim($formdata["txtdesc_arb"]),
		'HHCDescription'	=> ''
	    );
    
	    $this->_db->insert('retitmreasons',$insertData);
	    
	    return $this->_db->lastInsertId();
	}
    }
    /**
    * @name       deletegoodreason
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function for deletegoodreason
    */
    public function deletegoodreason($id)
    {
	$this->_db->delete("retitmreasons","Code IN (".$id.")");
	return '1';
    }    
    
    /**
    * @name       getbadreason
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function is giving an output of the bad reason
    */
    public function getbadreason($id = '')
    {
	$select = $this->_db->select()
                ->from(array('reason' => 'expiryreturnreasons') , array('reason.*'));
	if($id > 0){
	    $select->where('Code = '.$id);
	}	
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller	
	return $result;	
    }
    /**
    * @name       addeditbadreason
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function contain add and edit bad reason
    */
    public function addeditbadreason($formdata)
    {
	if($formdata['hdnid'] > 0){
	    
	   $updateData = array(
		'Description'		=> trim($formdata["txtdesc"]),
		'ArbDescription'	=> trim($formdata["txtdesc_arb"]),
		'HHCDescription'	=> ''
	    );
    
	    $this->_db->update("expiryreturnreasons",$updateData," Code = '".$formdata['hdnid']."'");
	    
	    return $formdata['hdnid'];
	}
	else
	{
	    $insertData = array(
		'Description'		=> trim($formdata["txtdesc"]),
		'ArbDescription'	=> trim($formdata["txtdesc_arb"]),
		'HHCDescription'	=> ''
	    );
    
	    $this->_db->insert('expiryreturnreasons',$insertData);
	    
	    return $this->_db->lastInsertId();
	}
    }
    /**
    * @name       deletebadreason
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function for deletebadreason
    */
    public function deletebadreason($id)
    {
	$this->_db->delete("expiryreturnreasons","Code IN (".$id.")");
	return '1';
    }
    
    /**
    * @name       getfocreason
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function is giving an output of the bad reason
    */
    public function getfocreason($id = '')
    {
	$select = $this->_db->select()
                ->from(array('reason' => 'expiryreturnreasons') , array('reason.*'));
	if($id > 0){
	    $select->where('Code = '.$id);
	}	
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller	
	return $result;	
    }
    /**
    * @name       addeditfocreason
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function contain add and edit foc reason
    */
    public function addeditfocreason($formdata)
    {
	if($formdata['hdnid'] > 0){
	    
	   $updateData = array(
		'Description'		=> trim($formdata["txtdesc"]),
		'ArbDescription'	=> trim($formdata["txtdesc_arb"]),
		'HHCDescription'	=> ''
	    );
    
	    $this->_db->update("expiryreturnreasons",$updateData," Code = '".$formdata['hdnid']."'");
	    
	    return $formdata['hdnid'];
	}
	else
	{
	    $insertData = array(
		'Description'		=> trim($formdata["txtdesc"]),
		'ArbDescription'	=> trim($formdata["txtdesc_arb"]),
		'HHCDescription'	=> ''
	    );
    
	    $this->_db->insert('expiryreturnreasons',$insertData);
	    
	    return $this->_db->lastInsertId();
	}
    }
    /**
    * @name       deletefocreason
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function for deletefocreason
    */
    public function deletefocreason($id)
    {
	$this->_db->delete("expiryreturnreasons","Code IN (".$id.")");
	return '1';
    }
}
<?php
/**
 * @name       ELAN_General
 * @since      16-03-2012
 * @version    Release: 1
 * @author     HD
 * @copyright  Elan Technologies
 * @param
 * This Class contains all the Basic module
 */

class SFA_Model_Basic extends Zend_Db_Table_Abstract
{
    /**
    * @name       getbankdata
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function is giving an output of the bankdata
    */
    public function getbankdata($id = '')
    {
	$select = $this->_db->select()
                ->from(array('bank' => 'bankmaster') , array('bank.*'));
	if($id > 0){
	    $select->where('BankCode = '.$id);
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
    * This function contain add and edit bank detail.
    */
    public function addeditbank($formdata)
    {
	if($formdata['hdnid'] > 0){
	    
	   $updateData = array(
		'BankName'	=> trim($formdata["txtname"]),
		'ArbBankName'	=> trim($formdata["txtname_arb"]),
		'BankBalance' 	=> '0.0000',		
		'Modified' 	=> trim($formdata["Modified"]),
		'MDat'		=> new Zend_Db_Expr('NOW()'),
		'ActiveStatus'	=> trim($formdata["ddlstatus"]),
		'AlternateCode' => trim($formdata["txtaltcode"]),
		'Type'		=> trim($formdata["ddltype"]),
		'ACNumber'	=> trim($formdata["txtacno"])	
	    );
    
	    $this->_db->update("bankmaster",$updateData," BankCode = '".$formdata['hdnid']."'");
	    
	    return $formdata['hdnid'];
	}
	else
	{
	    $insertData = array(
		'BankName'	=> trim($formdata["txtname"]),
		'ArbBankName'	=> trim($formdata["txtname_arb"]),
		'BankBalance' 	=> '0.0000',
		'Created'	=> trim($formdata["Created"]),
		'CDat'		=> new Zend_Db_Expr('NOW()'),
		'Modified' 	=> '',
		'MDat'		=> '',
		'ActiveStatus'	=> trim($formdata["ddlstatus"]),
		'AlternateCode' => trim($formdata["txtaltcode"]),
		'Type'		=> trim($formdata["ddltype"]),
		'ACNumber'	=> trim($formdata["txtacno"])	
	    );
    
	    $this->_db->insert('bankmaster',$insertData);
	    
	    return $this->_db->lastInsertId();
	}
    }
    /**
    * @name       deletebank
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function for deletebank
    */
    public function deletebank($id)
    {
	$this->_db->delete("bankmaster","BankCode IN (".$id.")");
	return '1';
    }
    
    /**
    * @name       getcashdesc
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function is giving an output of the cash description
    */
    public function getcashdesc($id = '')
    {
	$select = $this->_db->select()
                ->from(array('cash' => 'cashdesc') , array('cash.*'));
	if($id > 0){
	    $select->where('Code = '.$id);
	}	
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller	
	return $result;	
    }
    /**
    * @name       addeditcashdesc
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function contain add and edit cash description.
    */
    public function addeditcashdesc($formdata)
    {
	if($formdata['hdnid'] > 0){
	    
	   $updateData = array(
		'Description'		=> trim($formdata["txtdesc"]),
		'ArbDescription'	=> trim($formdata["txtdesc_arb"]),
		'HHCDescription'	=> ''
	    );
    
	    $this->_db->update("cashdesc",$updateData," Code = '".$formdata['hdnid']."'");
	    
	    return $formdata['hdnid'];
	}
	else
	{
	    $insertData = array(
		'Description'		=> trim($formdata["txtdesc"]),
		'ArbDescription'	=> trim($formdata["txtdesc_arb"]),
		'HHCDescription'	=> ''
	    );
    
	    $this->_db->insert('cashdesc',$insertData);
	    
	    return $this->_db->lastInsertId();
	}
    }
    /**
    * @name       deletecashdesc
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function for delete cash descroption
    */
    public function deletecashdesc($id)
    {
	$this->_db->delete("cashdesc","Code IN (".$id.")");
	return '1';
    }
    
    
    /**
    * @name       getcurrency
    * @since      19-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function is giving an output of the currency
    */
    public function getcurrency($id = '')
    {
	$select = $this->_db->select()
                ->from(array('currency' => 'currencymaster') , array('currency.CurrencyCode','currency.CurrencyName','currency.ArbCurrencyName','DATE_FORMAT(currency.StartDate,"%d/%m/%Y") AS StartDate','DATE_FORMAT(currency.EndDate,"%d/%m/%Y") AS EndDate'));
	if($id > 0){
	    $select->where('CurrencyCode = '.$id);
	}
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller
	return $result;	
    }
    /**
    * @name       addeditcurrency
    * @since      19-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function contain add and edit currency
    */
    public function addeditcurrency($formdata)
    {
	if($formdata['hdnid'] > 0){
	    
	    $startDate 		= $formdata["txtstartdate"];
	    $startDate_arr 	= explode("/",$startDate);
	    $start_date 	= date("Y-m-d H:i:s", mktime(0, 0, 0, $startDate_arr[1], $startDate_arr[0], $startDate_arr[2]));
	    
	    $endDate 		= $formdata["txtenddate"];
	    $endDate_arr 	= explode("/",$endDate);
	    $end_date 		= date("Y-m-d H:i:s", mktime(0, 0, 0, $endDate_arr[1], $endDate_arr[0], $endDate_arr[2]));
	    
	    $updateData = array(
		'CurrencyName'		=> trim($formdata["txtcurrname"]),
		'ArbCurrencyName'	=> trim($formdata["txtarbcurname"]),
		'StartDate'		=> trim($start_date),
		'EndDate'		=> trim($end_date)
	    );
    
	    $this->_db->update("currencymaster",$updateData," CurrencyCode = '".$formdata['hdnid']."'");
	    
	    return $formdata['hdnid'];
	}
	else
	{
	    $startDate 		= $formdata["txtstartdate"];
	    $startDate_arr 	= explode("/",$startDate);
	    $start_date 	= date("Y-m-d", mktime(0, 0, 0, $startDate_arr[1], $startDate_arr[0], $startDate_arr[2]));

	    $insertData = array(
		'CurrencyName'		=> trim($formdata["txtcurrname"]),
		'ArbCurrencyName'	=> trim($formdata["txtarbcurname"]),
		'StartDate'		=> $start_date,
		'EndDate'		=> trim($formdata["txtenddate"])
	    );
    
	    $this->_db->insert('currencymaster',$insertData);
	    
	    return $this->_db->lastInsertId();
	}
    }
    /**
    * @name       deletecurrency
    * @since      19-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function for delete currency
    */
    public function deletecurrency($id)
    {
	$this->_db->delete("currencymaster","CurrencyCode IN (".$id.")");
	return '1';
    }
    /**
    * @name       addcurrencydetail
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    * This function contain add currency detail
    */
    public function addcurrencydetail($formdata)
    {
	$insertData = array(
	    'CurrencyCode'	=> trim($formdata["hidnCurrencyCode"]),
	    'CurrencyDetailCode'=> trim($formdata["txtdetcode"]),
	    'ExchangeRate' 	=> trim($formdata["txtexcrate"])
	);	
	
	$this->_db->insert('currencydetail',$insertData);
	
	return $this->_db->lastInsertId();	
    }
}
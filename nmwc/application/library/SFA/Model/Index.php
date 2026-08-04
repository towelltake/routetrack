<?php
/**
 * @name       ELAN_General
 * @since      19-09-2011
 * @version    Release: 1
 * @author     HD
 * @copyright  Elan Technologies
 * @param
 * This Class contains all the General functions which are used through out the site.
 */

class SFA_Model_Index extends Zend_Db_Table_Abstract
{
    /**
     * By Hiren dave for prototype purpose only
     */
    public function getcomboboxdata($tabelname,$id,$value,$where = '')
    {
	$select = $this->_db->select()
                ->from(array('tbl' => $tabelname) , array($id,$value));
	if($where !='')
	{
	    $select->where($where);
	}
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller
	return $result;
    }
    /**
     * By Hiren dave for getform and its module details
     */

     /**
     * By Pankit Mehta for prototype purpose only
     */
    public function getcompanydataByID($id,$fields)
    {
        if($id > 0 && is_array($fields))
	{
	$select = $this->_db->select()
                ->from(array('tbl' => 'company'), $fields );

	    $select->where('tbl.CmpyCode = ?',$id);
            $result = $this->getAdapter()->fetchRow($select);
            //return result set to controller
            return $result;
	}else{
            return false;
        }
    }
    /**
     * By Hiren dave for getform and its module details
     */

    public function getformdata()
    {
	$select = $this->_db->select()
                ->from(array('md' => 'moduledetail') , array('*'))
		->joinLeft(array('mh'=>'moduleheader'),'mh.ModuleID = md.ModuleID',array('mh.ModuleName'))
		->where("FormName NOT LIKE '%**%'")
		->order('md.ModuleID ASC');

	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller
	return $result;
    }
    /**
    * @name       salescalendar
    * @since      06-04-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add sales calendar inforamtion
    *
    */
    public function salescalendar($array,$year,$created)
    {
		$query = " INSERT INTO salescalender (salesyear,weekstartdate,weekenddate,weeknumber,salesperiod,rp32weeknumber,created,modified,cdat,mdat)VALUES ";
		for($i=0;$i<count($array);$i++)
		{
			$query .= "(".$year.",STR_TO_DATE('".$array[$i]['weekstartdate']."','%d-%m-%Y'),STR_TO_DATE('".$array[$i]['weekenddate']."','%d-%m-%Y'),
				".$array[$i]['weeknumber'].",".$array[$i]['salesperiod'].",".$array[$i]['rp32weeknumber'].",'".$created."','".$created."',NOW(),NOW()),";
		}		
	    $query = substr_replace($query ,"",-1);
	    $this->_db->query($query);
    }

    /**
     * This function all fields of faq_categories
     */
    public function getallfaqcat()
    {
	$select = $this->_db->select()
                ->from(array('faqcat' => 'faq_categories') , array('*'));

	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller
	return $result;
    }

    /**
     * This function all fields of faq_categories for a perticular id
     */
    public function getfaqcat($id)
    {
	$select = $this->_db->select()
			->from(array('faqcat' => 'faq_categories') , array('*'))
			->where("id=?",$id);

	//call select query
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller
	return $result;
    }

    /**
     * This function add category in faq category
     */
    public function addfaqcat($formdata)
    {
	$insertData = array(
	    'name'		=> trim($formdata["txtcat"]),
	    'description'	=> trim($formdata["txtdesc"]),
	    'status' 		=> trim($formdata["rbtst"])
	);

	$this->_db->insert('faq_categories',$insertData);

	return $this->_db->lastInsertId();
    }
    /**
     * Update faq and this function will return faq category id.
    */
    public function updatefaqcatstatus($status,$id)
    {

	    $updateData = array(
		'status'		=> $status
	    );
	    $this->_db->update("faq_categories",$updateData," id = '".$id."'");
	    //$this->_db->update("tbl_cart",$updateData,'intid = '.$item_id))
	    return $id;
    }
    /**
     * Update faq category and this function will return faq category id.
    */
    public function editfaqcat($formdata)
    {

	    $updateData = array(
		'name'		=> trim($formdata["txtcat"]),
		'description'	=> trim($formdata["txtdesc"]),
		'status' 	=> trim($formdata["rbtst"])
	    );
	    $this->_db->update("faq_categories",$updateData," id = '".$formdata['catid']."'");
	    return $id;

    }
    /**
     *Delete faq category.
    */
    public function deletefaqcat($catid)
    {
	$this->_db->delete("faq_categories","id='".$catid."'");
	return $catid;
    }

    /**
     * This function add faq
     */
    public function addfaq($formdata)
    {
	$addedDate 	= new Zend_Db_Expr('NOW()');

	$insertData = array(
	    'faq_categories_id'		=> $formdata["ddlfaqcat"],
	    'question'			=> trim($formdata["txtque"]),
	    'answer' 			=> trim($formdata["txtans"]),
	    'status'			=> $formdata["rbtst"],
	    'question_date'		=> $addedDate,
	    'answer_date'		=> $addedDate,
	    'created'			=> $addedDate,
	    'modified'			=> $addedDate
	);

	$this->_db->insert('faq',$insertData);

	return $this->_db->lastInsertId();
    }
    /**
     * This function add faq
     */
    public function editfaq($formdata)
    {
	$addedDate 	= new Zend_Db_Expr('NOW()');

	$updateData = array(
	    'faq_categories_id'		=> $formdata["ddlfaqcat"],
	    'question'			=> trim($formdata["txtque"]),
	    'answer' 			=> trim($formdata["txtans"]),
	    'status'			=> $formdata["rbtst"],
	    'modified'			=> $addedDate
	);

	$this->_db->update("faq",$updateData," id = '".$formdata['faqid']."'");

	return $this->_db->lastInsertId();
    }
    /**
     * This function all fields of faq for a perticular id
     */
    public function getfaq($id = 0)
    {
	//apply select query with parameter
	$select = $this->_db->select()
			->from(array('fq' => 'faq') , array('*'))
			->joinLeft(array('faqcat'=>'faq_categories'),'fq.faq_categories_id =faqcat.id',array('faqcat.name'));

	if($id > 0)
	    $select ->where("fq.id=?",$id);


	//call select query
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller
	return $result;
    }
    /**
     * Update faq and this function will return faq id.
    */
    public function updatefaqstatus($status,$id)
    {
	$updateData = array(
	    'status'		=> $status
	);
	$this->_db->update("faq",$updateData," id = '".$id."'");
	return $id;
    }
    /**
     *Delete faq.
    */
    public function deletefaq($faqid)
    {
	$this->_db->delete("faq","id='".$faqid."'");
	return $id;
    }
     /**
     *
     */

      /**
    * @name       delete_row
    * @since      20-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function  is for deleting any row of the table
    *
    */
    public function delete_row($table,$pid,$value)
    {
	$this->_db->delete($table,$pid."='".$value."'");
	return $id;
    }

    public function edit_row($table,$pid,$value,$updateData)
    {

	$this->_db->update($table,$updateData,$pid."='".$value."'");

	return $this->_db->lastInsertId();
    }
}
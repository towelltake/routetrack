<?php
/**
 * @name       Reason
 * @since      19-03-2012
 * @version    Release: 1
 * @author     PM <pankit@elantechnologies.com>
 * @copyright  Elan Technologies
 * @param
 * This Class contains Inventory module
 */

class SFA_Model_Inventory extends Zend_Db_Table_Abstract
{
    /**
    * @name       getitemgroup
    * @since      19-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    * This function is giving an output of the item group
    */
    public function getitemgroup($id = '')
    {
	$select = $this->_db->select()
                ->from(array('itemgroup' => 'itemgroup') , array('itemgroup.*'));
	if($id > 0){
	    $select->where('ItemGroupCode = '.$id);
	}
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller
	return $result;
    }
    /**
    * @name       addedititemgroup
    * @since      19-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    * This function contain add and edit item group
    */
    public function addedititemgroup($formdata)
    {
        $addedDate 	= new Zend_Db_Expr('NOW()');
	if($formdata['hdnid'] > 0){

	   $updateData = array(
		'AlternateItemGroupCode'    => trim($formdata["txtaltcode"]),
		'SubMajorCategoryCode'      => trim($formdata["ddlsubmajcat"]),
		'ItemGroupName'             => trim($formdata["txtname"]),
                'ARBItemGroup'              => trim($formdata["txtnamearb"]),
                'ActiveStatus'              => ($formdata["ddlstatus"] == 'Active' ? 1 : 0),
                'MDat'                      => $addedDate
	    );

	    $this->_db->update("itemgroup",$updateData," ItemGroupCode = '".$formdata['hdnid']."'");

	    return $formdata['hdnid'];
	}
	else
	{
	    $insertData = array(
                'ItemGroupCode'             => trim($formdata["txtcode"]),
		'AlternateItemGroupCode'    => trim($formdata["txtaltcode"]),
		'SubMajorCategoryCode'      => trim($formdata["ddlsubmajcat"]),
		'ItemGroupName'             => trim($formdata["txtname"]),
                'ARBItemGroup'              => trim($formdata["txtnamearb"]),
                'ActiveStatus'              => ($formdata["ddlstatus"] == 'Active' ? 1 : 0),
                'Created'                   => date('M d Y h:iA'),
                'CDat'                      => $addedDate,
                'MDat'                      => $addedDate
	    );
	    $this->_db->insert('itemgroup',$insertData);

	    return $formdata["txtcode"];
	}
    }

    /**
    * @name       deleteitemgroup
    * @since      19-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    * This function for deleteitemgroup
    */
    public function deleteitemgroup($id)
    {
	$result = $this->_db->delete("itemgroup","ItemGroupCode IN (".$id.")");
	return '1';
    }


    /**
    * @name       addedititem
    * @since      19-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    * This function contain add and edit item
    */
    public function addedititem($formdata)
    {
        $addedDate 	= new Zend_Db_Expr('NOW()');
	if($formdata['hdnid'] > 0){

	   $updateData = array(
		'AlternateItemGroupCode'    => trim($formdata["txtaltcode"]),
		'SubMajorCategoryCode'      => trim($formdata["ddlsubmajcat"]),
		'ItemGroupName'             => trim($formdata["txtname"]),
                'ARBItemGroup'              => trim($formdata["txtnamearb"]),
                'ActiveStatus'              => ($formdata["ddlstatus"] == 'Active' ? 1 : 0),
                'MDat'                      => $addedDate
	    );

	    $this->_db->update("itemgroup",$updateData," ItemGroupCode = '".$formdata['hdnid']."'");

	    return $formdata['hdnid'];
	}
	else
	{
	    $insertData = array(
                'ItemGroupCode'             => trim($formdata["txtcode"]),
		'AlternateItemGroupCode'    => trim($formdata["txtaltcode"]),
		'SubMajorCategoryCode'      => trim($formdata["ddlsubmajcat"]),
		'ItemGroupName'             => trim($formdata["txtname"]),
                'ARBItemGroup'              => trim($formdata["txtnamearb"]),
                'ActiveStatus'              => ($formdata["ddlstatus"] == 'Active' ? 1 : 0),
                'Created'                   => date('M d Y h:iA'),
                'CDat'                      => $addedDate,
                'MDat'                      => $addedDate
	    );
	    $this->_db->insert('itemgroup',$insertData);

	    return $formdata["txtcode"];
	}
    }

}
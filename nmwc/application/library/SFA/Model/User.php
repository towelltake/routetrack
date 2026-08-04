<?php
/**
 * @name       SFA_Model_User
 * @since      19-09-2011
 * @version    Release: 1
 * @author     HD
 * @copyright  Elan Technologies
 * @param   	
 * This Class contains all the General functions which are used through out the site.
 */

class SFA_Model_User extends Zend_Db_Table_Abstract
{

    protected $_name 	= 'user';

    /**
    * @name       userdetail
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	
    *
    * This function return user details from user table
    */
    
    public function userdetail($username)
    {
	//apply select query with parameter
	$select = $this->_db->select()
                            ->from(array('usr' => $this->_name) , array('usr.id','usr.title','usr.first_name','usr.last_name','usr.email','usr.username','usr.photo','usr.user_type_id','usr.phone','usr.company','usr.status'))
			    ->where("usr.username = '$username->username'")
			    ->where("usr.status = '1'");	
	
	$result = $this->getAdapter()->fetchAll($select);	
	return $result;
    }
    
    /**
    * @name       checkuserdetail
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	
    *
    * This function is check userdetails @ user add time
    */
    public function checkuserdetail($email,$username)
    {
	//apply select query with parameter
	$select = $this->_db->select()
                            ->from(array('usr' => $this->_name) , array('usr.id','usr.title','usr.first_name','usr.last_name','usr.email','usr.username','usr.photo','usr.user_type_id','usr.phone','usr.company','usr.status'))
			    ->where("usr.username = '$username'")
			    ->orWhere("usr.email = '$email'");
	
	$result = $this->getAdapter()->fetchAll($select);
	
	return $result;
    }    

    /**
    * @name       getusersdetail
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	user id and user type
    *
    * This function return all user details from user table     
    */
    public function getusersdetail($id = 0,$user_type = '')
    {
	$select = $this->_db->select()
                            ->from(array('usr' => $this->_name) , array('usr.id','usr.title','usr.first_name','usr.last_name','usr.email','usr.username','usr.user_type_id','usr.linkedin_id','usr.phone','usr.company','usr.status','usr.register_where'))
			    ->joinLeft(array('type'=>'user_type'),'usr.user_type_id =type.id',array('type.user_type'));
	if($id > 0 && $id !='')
	    $select->where("usr.id = '$id'");
	if($user_type > 0 && $user_type !='')
	    $select->where('type.is_admin ='.$user_type);
	    
    

	$result = $this->getAdapter()->fetchAll($select);

	return $result;
    }

    /**
    * @name       getusertype
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	  isadmin
    *
    * This function return usertype
    */
    public function getusertype($is_admin)
    {

	$select = $this->_db->select()
			->from(array('user' => 'user_type') , array('*'))
			->where("status = '1'")
			->where("is_admin = '$is_admin'")
			->order('user_type');
	

	$result = $this->getAdapter()->fetchAll($select);

	return $result;
    }
    
    /**
    * @name       changepassword
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	  isadmin
    *
    * This function return user id
    * Mainly use for change password
    */
    public function changepassword($formdata)
    {
	$updateData = array(
	    'password'	=> trim($formdata["password"])
	);
	$this->_db->update("user",$updateData," id = '".$formdata['userid']."'");
	return $formdata['userid'];
    }

    /**
    * @name       adduser
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	  all user information
    *
    * This function return user id
    * Mainly use for add user
    */
    public function adduser($formdata)
    {
	$addedDate 	= new Zend_Db_Expr('NOW()');
	
	$auth = new SFA_Loginauth($u = '',$p='');
        $checkUser = $auth->getIdentity();
	
	$reg_whr = $checkUser->user_type_id == '4' ? '2' : '1';
	$status  = $formdata["rbtst"] == '' ? '1' : $formdata["rbtst"];
	
	$link = isset($formdata['activatelink']) ? $formdata['activatelink'] : '';
	
	$insertData = array(
	    'title'		=> $formdata["ddltitle"],
	    'first_name'	=> trim($formdata["txtfirstname"]),
	    'last_name' 	=> trim($formdata["txtlastname"]),
	    'email'		=> trim($formdata["txtemailid"]),
	    'username'		=> trim($formdata["txtusername"]),
	    'password'		=> trim($formdata["txtpassword"]),
	    'photo'		=> $formdata["flimage"],
	    'user_type_id'	=> $formdata["ddltype"],
	    'linkedin_id'	=> $formdata["txtlink"],
	    'phone'		=> trim($formdata["txtphone"]),
	    'company'		=> trim($formdata["txtcompany"]),
	    'status'		=> $status,
	    'activatelink'	=> $link,
	    'created'		=> $addedDate,
	    'modified'		=> $addedDate,
	    'register_where'	=> $reg_whr
	);
	
	$this->_db->insert('user',$insertData);
	
	return $this->_db->lastInsertId();
    }

    /**
    * @name       updateuser
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	  all user information
    *
    * This function return user id
    * Mainly use for update user profile
    */
    public function updateuser($formdata)
    {
	$addedDate 	= new Zend_Db_Expr('NOW()');
	
	if($formdata["txtpassword"] != '') {
	    $updateData = array(
		'title'		=> $formdata["ddltitle"],
		'first_name'	=> trim($formdata["txtfirstname"]),
		'last_name' 	=> trim($formdata["txtlastname"]),
		'email'		=> trim($formdata["txtemailid"]),
		'username'	=> trim($formdata["txtusername"]),
		'password'	=> trim($formdata["txtpassword"]),
		'photo'		=> $formdata["flimage"],
		'user_type_id'	=> $formdata["ddltype"],
		'linkedin_id'	=> $formdata["txtlink"],
		'phone'		=> trim($formdata["txtphone"]),
		'company'	=> trim($formdata["txtcompany"]),
		'status'	=> $formdata["rbtst"],
		'created'	=> $addedDate,
		'modified'	=> $addedDate
	    );
	}
	else
	{
	    $addedDate 	= new Zend_Db_Expr('NOW()');
	    
	    $updateData = array(
		'title'		=> $formdata["ddltitle"],
		'first_name'	=> trim($formdata["txtfirstname"]),
		'last_name' 	=> trim($formdata["txtlastname"]),
		'email'		=> trim($formdata["txtemailid"]),
		'username'	=> trim($formdata["txtusername"]),
		'photo'		=> $formdata["flimage"],
		'user_type_id'	=> $formdata["ddltype"],
		'linkedin_id'	=> $formdata["txtlink"],
		'phone'		=> trim($formdata["txtphone"]),
		'company'	=> trim($formdata["txtcompany"]),
		'status'	=> $formdata["rbtst"],
		'created'	=> $addedDate,
		'modified'	=> $addedDate
	    );
	}	
	
	$this->_db->update("user",$updateData," id = '".$formdata['uid']."'");
	
	return $this->_db->lastInsertId();
    }
    

    /**
    * @name       updateuserstatus
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	  all user information
    *
    * MUpdate user status
    */
    public function updateuserstatus($status,$userid)
    {
	$updateData = array(
	    'status'		=> $status
	);
	$this->_db->update("user",$updateData," id = '".$userid."'");	
	return $userid;
    }

    /**
    * @name       comparepwd
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	  all user information
    *
    * Compare two password
    */
    public function comparepwd($pwd,$userid)
    {
	$select = $this->_db->select()
                            ->from(array('user' => $this->_name) , array('*'))
			    ->where("password = '$pwd'")
			    ->where("id	 = '$userid'")
			    ->where("status = '1'");
	
	$result = $this->getAdapter()->fetchAll($select);
	//return result set to controller	
	return $result;	
    }

    /**
    * @name       checkactivation
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	  all user information
    *
    * check activate link   
    */
    public function checkactivation($key)
    {
	$select = $this->_db->select()
                            ->from(array('usr' => $this->_name) , array('usr.id'))
			    ->where("usr.status = '2'")
			    ->where("usr.activatelink = '$key'");
	
	$result = $this->getAdapter()->fetchOne($select);
	//return result set to controller	
	return $result;	
    }

    /**
    * @name       checkactivation
    * @since      19-09-2011
    * @version    Release: 1
    * @author     HD
    * @copyright  Elan Technologies
    * @param   	  all user information
    *
    * update activate key
    */
    public function updatekey($userid)
    {
	$updateData = array(
	    'activatelink'	=> ''
	);
	$this->_db->update("user",$updateData," id = '".$userid."'");
	return $userid;
    }
}
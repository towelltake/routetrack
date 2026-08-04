<?php 

/**
 * @package   SFA_Core_Library
 * @version    $Id: Auth.php
 */

class SFA_Message 
{
	
	public $_message;
	
	public function __construct()
	{
		$this->_storage = new Zend_Session_Namespace('SFASession');
	}
	
	// success msg functions
	static function setMsg($value)
	{
		$storage = new Zend_Session_Namespace('SFASession');
		$storage->msg = $value;
	}
	
	static function isSetMsg()
	{
		$storage = new Zend_Session_Namespace('SFASession');
		if(strlen($storage->msg)>0)
			return true;
		else
			return false;
	}
	
	
	static function getMsg()
	{
		$storage = new Zend_Session_Namespace('SFASession');
		if(isset($storage->msg)){
		  $message =  $storage->msg;
		  unset($storage->msg);
		}
		return $message;
	}
	
	// error msg functions
	static function setErrorMsg($value)
	{
		$storage = new Zend_Session_Namespace('SFASession');
		$storage->errmsg = $value;
	}
	
	static function isErrorMsg()
	{
		$storage = new Zend_Session_Namespace('SFASession');
		if(strlen($storage->errmsg)>0)
			return true;
		else
			return false;
	}
	
	static function getErrorMsg()
	{
		$storage = new Zend_Session_Namespace('SFASession');
		if(isset($storage->errmsg)){
		  $message =  $storage->errmsg;
		  unset($storage->errmsg);
		}
		return $message;
	}
	
	/**
	 * destroys the current user session
	 *
	 */
	static function destroy()
	{
		$storage = new Zend_Session_Namespace('SFASession');
		$storage->unsetAll();
	}

}
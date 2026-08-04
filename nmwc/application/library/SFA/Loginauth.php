<?php 

/**
 * @package   ELAN_Core_Library
 * @version    $Id: Auth.php
 */

class SFA_Loginauth
{
	/**
	 * auth adapter
	 *
	 * @var zend_auth_adapter
	 */
	private $_authAdapter;
	
	private $db = "";
	
	private $_dbAdapter;
	
	/**
	 * the passed username
	 *
	 * @var string
	 */
	private $_username;
	
	/**
	 * the passed password
	 *
	 * @var string
	 */
	private $_password;
	
	/**
	 * the user session storage
	 *
	 * @var zend_session_namespace
	 */
	private $_storage;
	
	/**
	 * the table that contains the user credentials
	 *
	 * @var string
	 */
	private $_userTable = "usermaster";
	
	/**
	 * the indentity column
	 *
	 * @var string
	 */
	private $_identityColumn = "username";
	
	/**
	 * the credential column
	 *
	 * @var string
	 */
	private $_credentialColumn = "password";	
	
	/**
	 * build the login request
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function __construct($username, $password)
	{
	    //set up the db authentication           
	    
	    $this->_dbAdapter = clone (Zend_Db_Table::getDefaultAdapter());
	    $this->_dbAdapter->setFetchMode(zend_db::FETCH_ASSOC ); 
	    
	    $this->_authAdapter = new Zend_Auth_Adapter_DbTable($this->_dbAdapter,$this->_userTable, 'username', 'password');
	    
	    $this->_username = $username;
	    
	    $this->_password = $password;

	    //set up storage
	    // @todo: i can not get zend to persist the identities for some reason .. figure out why
	    $this->_storage = new Zend_Session_Namespace('SFA_User_Auth');
	}
	
	/**
	 * authenticate the request
	 *
	 * @return zend_auth_response
	 */
	public function authenticate()
	{
	
	    //authenticate the user
	    $this->_authAdapter->setIdentity($this->_username);
	    
	    $this->_authAdapter->setCredential($this->_password);		
	    
	    $result = $this->_authAdapter->authenticate();
	    
	    
	    
	    if($result->isValid())
	    {
		//save the user and return the result
		$row = $this->_authAdapter->getResultRowObject(array('userid', 'username','usertypeid'));
		$this->_storage->user = $row;
		
		return $result;
	    }
	}
	
	/**
	 * get the current user identity if it exists
	 *
	 * @return zend_auth_response
	 */
	static function getIdentity()
	{ 
	    $storage = new Zend_Session_Namespace('SFA_User_Auth');

	    if(isset($storage->user)){
	      return $storage->user;
	    }
	}
	
	/**
	 * destroys the current user session
	 *
	 */
	static function destroy()
	{
	    $storage = new Zend_Session_Namespace('SFA_User_Auth');
	    $storage->unsetAll();
	}
    
    /**
	 * get the current user identity if it exists
	 *
	 * @return zend_auth_response
	 */
	static function hasIdentity()
	{ 
	    $storage = new Zend_Session_Namespace('SFA_User_Auth');
        
	    if(isset($storage->user)){
            return true;
	    } else {
            return false;
        }
	}
}
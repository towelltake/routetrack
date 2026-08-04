<?php

class SFA_Model_Core extends Zend_Db_Table_Abstract
{
    public $_user = 'usermaster';
    public $_usertypedetail = 'usertypedetail';
    public $_userdetail = 'userdetail';
    public $_moduledetail = 'moduledetail';
    
    public function gettypedetail($userid = "0",$typeid = "0")
    {
        $zend_db_cache = Zend_Registry::get('Zend_DB_Cache');
        $cachefile = 'SFA_Model_Core_gettypedetail_u_'.$userid."_r_".$typeid;
        
        if(($data = $zend_db_cache->load($cachefile)) === false)
        {
            $cachefile_user = 'SFA_Model_Core_gettypedetail_user_'.$userid;
            
            if(($data_user = $zend_db_cache->load($cachefile_user)) === false)
            {
                $select = $this->_db->select()
                                    ->from(array("ud" => $this->_userdetail),array("ud.readdata as read","ud.updatedata as update","ud.insertdata as insert","ud.deletedata as delete","ud.allpermissions as all","ud.formid"))
                                    ->joinLeft(array("md" => $this->_moduledetail),'ud.formid = md.formid',array('REPLACE(md.formname," ","_") as formname'))
                                    ->where("ud.userid = ?",$userid);
                
                $data_user = $this->getAdapter()->fetchAll($select);
                
                $zend_db_cache->save($data_user, $cachefile_user);
            }
            
            
            if(count($data_user) <= 0)
            {
                $cachefile_type = 'SFA_Model_Core_gettypedetail_role_'.$typeid;
            
                if(($data_type = $zend_db_cache->load($cachefile_type)) === false)
                {
                    $select = $this->_db->select()
                                        ->from(array("ud" => $this->_usertypedetail),array("ud.readdata as read","ud.updatedata as update","ud.insertdata as insert","ud.deletedata as delete","ud.allpermissions as all","ud.formid"))
                                        ->joinLeft(array("md" => $this->_moduledetail),'ud.formid = md.formid',array('REPLACE(md.formname," ","_") as formname'))
                                        ->where("ud.usertypeid = ?",$typeid);
                    
                    $datatype = $this->getAdapter()->fetchAll($select);
                    
                    $zend_db_cache->save($datatype, $cachefile_type);
                
                }
                $zend_db_cache->save($datatype, $cachefile);
                $data = $datatype;
            }
            else
            {
               $zend_db_cache->save($data_user, $cachefile);
               $data = $data_user;
            }
        }        
        return $data;
        
    }
    
    public function executeaclquery($query = "")
    {
        try {
            $this->_db->query($query);
        } catch (Zend_Db_Exception $e) {
            echo $e->getMessage();exit;
        }
        
        
    }
}
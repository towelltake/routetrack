<?php

class Api_ImageController extends Api_Library_Controller_Action_Abstract
{

    //Initilize var for App Model
    private $index = "";

    /**
    * @name       init
    * @since      16-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    public function init()
    {
        $this->SFA_Comman = new SFA_Comman();
		parent::init();
    }
	public function uploadAction()
	{
		// print_r($_FILES);
		echo  $_FILES['file']['error'];
	    	    
		
		//$destination	="C:/wamp/www/sfa/nmwc/public/customerimage/".$_FILES['file']['name'];
		$baseUrl            = Zend_Controller_Front::getInstance()->getBaseUrl();
		$destination =str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].$baseUrl.'/public/customerimage/'.$_FILES['file']['name']);
		//$destination	="D:/RoutePro_Images/customerimage/".$_FILES['file']['name'];
		//echo $destination ;exit;
		if(move_uploaded_file($_FILES["file"]["tmp_name"], $destination))
		//if(move_uploaded_file($_FILES["file"]["tmp_name"], "D:/RoutePro_Images/customerimage/".$_FILES['file']['name']))
			{
			echo "The file ".  basename( $_FILES['file']['name']). 	" has beenuploaded";
			} else
			{
			echo "There was an error uploading the file, please try again!";
			}
		
			exit;
    }
}
<?php

class Api_CustomerController extends Api_Library_Controller_Action_Abstract
{

    //Initilize var for App Model
    private $index = "";

    /**
    * @name       init
    * @since      16-03-2012
    * @version    Release: 1
    * @author     Jinal <jinal@elantechnologies.com>
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

     /**
    * @name       customermasterAction
    * @since      02-04-2012
    * @version    Release: 1
    * @author     Jinal <jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    * @example1 api/customer/customermaster
    * This is the customermaster Action.
    */
    public function customermasterAction(){
        //Request Parameter
        $this->view->params = $params = $this->getRequest()->getParams();

        $param_array = array();
	//Route Code
	$param_array[1] = $params['routecode'];
	//Customer Code
        $param_array[2] = $params['customercode'];
	//print_r($param_array);
        //Call stored procedure for get result data
        $result = $this->SFA_Comman->executequery('CALL sp_ws_getcustormaster(?,?)',$param_array,'');

        $resultdata = $result[0];
	$resultdata = (count($resultdata[0]) > 0) ? $resultdata[0]:array();
	//json output
        header("Access-Control-Allow-Origin: *");
        echo json_encode($resultdata);
    }

}
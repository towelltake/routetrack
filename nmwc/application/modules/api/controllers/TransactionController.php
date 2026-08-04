<?php

class Api_TransactionController extends Api_Library_Controller_Action_Abstract
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


     /**
    * @name       companyidbydeviceAction
    * @since      23-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param      deviceid
    * @example1 api/index/trandata/routekey/2
    * This is the salesmanmessages Action.
    */
    public function trandataAction(){
        //Request Parameter
        $this->view->params = $params = $this->getRequest()->getParams();

        $param_array = array();
	//Route id
        $param_array[1] = $params['routekey'];

	$result = $this->SFA_Comman->executequery('CALL sp_ws_transactiondata(?)',$param_array,'');
        $resultdata = $result[0];

         //json output
        if(count($resultdata) <= 0){
            $resultdata = array();
        }
        header("Access-Control-Allow-Origin: *");
        echo json_encode($resultdata);
    }
   
    function stripslashes_deep($value)
    {
    $value = is_array($value) ?
                array_map('stripslashes_deep', $value) :
                stripslashes($value);

    return $value;
    }
}
function replacenul(&$item, $key)
    {
        if($item == null|| $item=='null')
            $item = "";
    } 
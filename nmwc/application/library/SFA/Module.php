<?php

class SFA_Module {
    
    public $module = array(
        "account" =>
            array("customer" =>
                array("addauthorizegroup" => "Route",
                        "addcustcat" => "Route",
                        "addchannel" => "Route",
                        "addcustomer" => "Route",
                        "addmessages"=> "Route",
                        "addtemplate"=> "Route",
                        "ajaxgrid"=> "Route",
                        "authorizegroup"=> "Route",
                        "contact"=> "Route",
                        "custcat"=> "Route",
                        "channel"=> "Route",
                        "customer"=> "Route",
                        "general"=> "Route",
                        "messages"=> "Route",
                        "route"=> "Route",
                        "setting1"=> "Route",
                        "setting2"=> "Route",
                        "template"=> "Route",
                        "templategeneral"=> "Route",
                        "tempsett1"=> "Route",
                        "tempsett2"=> "Route"
                ),
            "customerseq" =>
                array(
                    "addcustomer" => "Route",
                    "ajaxgrid" => "Route",
                    "arrangecustomer" => "Route",
                    "copysequence" => "Route",
                    "index" => "Route",
                    "routesequence" => "Route",
                    "salescalender" => "Route"
                ),
            "index" =>
                array(
                    "addoutletproduct" => "Route",
                    "ajaxgrid" => "Route",
                    "expirymanagement" => "Route",
                    "journeyplan" => "Route",
                    "outletproduct" => "Route"
                ),
            "notes" =>
                array(
                    "addcreditcustomer" => "Route",
                    "addcreditroute" => "Route",
                    "adddebitcustomer" => "Route",
                    "adddebitroute" => "Route",
                    "ajaxgrid" => "Route",
                    "creditcustomer" => "Route",
                    "creditroute" => "Route",
                    "debitcustomer" => "Route",
                    "debitroute" => "Route"
                ),
            "salesman" =>
                array(
                    "addsalesman" => "Route",
                    "addsalesmanmsgs" => "Route",
                    "salesman" => "Route",
                    "salesmanmsgs" => "Route"                    
                ),
            "settlement" =>
                array(
                    "addcashreceipt" => "Route",
                    "addpdc" => "Route",
                    "ajaxgrid" => "Route",
                    "cashreceipt" => "Route",
                    "pdc" => "Route",
                    "viewcollection" => "Route"
                ),
            "transaction" =>
                array(
                    "addgccollection" => "Route",
                    "addhocollection" => "Route",
                    "addopeningbal" => "Route",
                    "ajaxgrid" => "Route",
                    "gccollection" => "Route",
                    "hocollection" => "Route",
                    "monthclose" => "Route",
                    "openingbal" => "Route",
                )
        ),
        "admin" =>
            array("cpanel" =>
                array("customer" => "Route",
                      "general" => "Route",
                      "index" => "Route",
                      "item" => "Route",
                      "route" => "Route"
                ),
            "index" =>
                array(
                    "archivedata" => "Route",
                    "index" => "Route",
                    "setupset" => "Route"
                ),
            "security" =>
                array(
                    "adduser" => "Route",
                    "addusertype" => "Route",
                    "filtermodule" => "Route",
                    "pwdgenerate" => "Route",
                    "user" => "Route",
                    "userpermission" => "Route",
                    "usertype" => "Route"
                )
        ),
        "api" =>
            array("index" =>
                array("home" => "Route")
        ),
        "basic" =>
            array("index" =>
                array(
                    "addareamanager" => "Route",
                    "addbank" => "Route",
                    "addcashdesc" => "Route",
                    "addcompany" => "Route",
                    "addcurrency" => "Route",
                    "adddepotmanager" => "Route",
                    "addinventoryloc" => "Route",
                    "addregionmanager" => "Route",
                    "addsalesmanager" => "Route",
                    "addsupervisor" => "Route",
                    "ajaxgrid" => "Route",
                    "areamanager" => "Route",
                    "bank" => "Route",
                    "cashdesc" => "Route",
                    "company" => "Route",
                    "currency" => "Route",
                    "depotmanager" => "Route",
                    "index" => "Route",
                    "inventoryloc" => "Route",
                    "regionmanager" => "Route",
                    "salesmanager" => "Route",
                    "supervisor" => "Route"
                ),
            "reasons" =>
                array(
                    "addbadreturn" => "Route",
                    "addexpensereason" => "Route",
                    "addfocreason" => "Route",
                    "addgoodreturn" => "Route",
                    "addnonservice" => "Route",
                    "addvoidreason" => "Route",
                    "badreturn" => "Route",
                    "expensereason" => "Route",
                    "focreason" => "Route",
                    "goodreturn" => "Route",
                    "nonservice" => "Route",
                    "voidreason" => "Route"
                )
        ),
        "datamanagement" =>
            array("index" =>
                array(
                    "deletedata" => "Route",
                    "deletetrxndata" => "Route",
                    "viewlogdata" => "Route"
                )
        ),
        "hhctransaction" =>
            array("adcollection" =>
                array(
                    "adcollectionadd" => "Route",
                    "ajaxgrid" => "Route"
                ),
            "ajaxdata" =>
                array(
                    "cashcollection" => "Route",
                    "checkpromotionreview" => "Route",
                    "getvalidation" => "Route",
                    "invoicesummery" => "Route",
                    "promotion0" => "Route",
                    "promotion1" => "Route",
                    "promotion2" => "Route",
                    "promotion5" => "Route",
                    "promotion6" => "Route",
                    "promotion7" => "Route"
                ),
            "arcollection" =>
                array(
                    "ajaxgrid" => "Route",
                    "arcollectionadd" => "Route"
                ),
            "cloudtran" =>
                array(
                    "addinvoice" => "Route",
                    "addinvoiceinfo" => "Route",
                    "addprocessload" => "Route",
                    "advpayment" => "Route",
                    "advpaymentdetail" => "Route",
                    "advpaymentheader" => "Route",
                    "advpaymentmodedetail" => "Route",
                    "ajaxgrid" => "Route",
                    "arcollection" => "Route",
                    "arheader" => "Route",
                    "arheaderdetail" => "Route",
                    "arheaderpaymentmodedetail" => "Route",
                    "detailinvoice" => "Route",
                    "generalinvoice" => "Route",
                    "generalsalesorder" => "Route",
                    "invoice" => "Route",
                    "invoicepaymentmode" => "Route",
                    "processload" => "Route",
                    "salesorder" => "Route",
                    "salesorderdetail" => "Route",
                    "salesorderheader" => "Route"
                ),
            "index" =>
                array(
                    "addarcollection" => "Route",
                    "addbrancharcollection" => "Route",
                    "addcustomersalesorder" => "Route",
                    "addinvoice" => "Route",
                    "addpayment" => "Route",
                    "addreason" => "Route",
                    "addtransferin" => "Route",
                    "addtransferout" => "Route",
                    "addunloadendinginventory" => "Route",
                    "addunloadreturnstock" => "Route",
                    "addunloadvariance" => "Route",
                    "ajaxgrid" => "Route",
                    "ajaxgridextra" => "Route",
                    "arcollection" => "Route",
                    "brancharcollection" => "Route",
                    "customersalesorder" => "Route",
                    "index" => "Route",
                    "invoice" => "Route",
                    "print" => "Route",
                    "printinvoice" => "Route",
                    "report-grid" => "Route",
                    "startofday" => "Route",
                    "transferin" => "Route",
                    "transferout" => "Route",
                    "unloadendinginventory" => "Route",
                    "unloadreturnstock" => "Route",
                    "unloadvariance" => "Route"
                ),
            "invoice" =>
                array(
                    "addinvoice" => "Route",
                    "addinvoiceinfo" => "Route",
                    "advpayment" => "Route",
                    "advpaymentdetail" => "Route",
                    "advpaymentheader" => "Route",
                    "advpaymentmodedetail" => "Route",
                    "ajaxgrid" => "Route",
                    "arcollection" => "Route",
                    "arheader" => "Route",
                    "arheaderdetail" => "Route",
                    "arheaderpaymentmodedetail" => "Route",
                    "detailinvoice" => "Route",
                    "generalinvoice" => "Route",
                    "generalsalesorder" => "Route",
                    "invoice" => "Route",
                    "invoicepaymentmode" => "Route",
                    "salesorder" => "Route",
                    "salesorderdetail" => "Route",
                    "salesorderheader" => "Route"
                ),
            "loadstock" =>
                array(
                    "ajaxgrid" => "Route",
                    "ajaxgridextra" => "Route",
                    "beginstock" => "Route",
                    "load" => "Route",
                    "loadrequest" => "Route",
                    "viewbeginstock" => "Route",
                    "viewload" => "Route",
                    "viewloadrequest" => "Route"
                ),
            "loadtransfer" =>
                array(
                    "ajaxgrid" => "Route",
                    "ajaxgridextra" => "Route",
                    "damageret" => "Route",
                    "loadtransfer" => "Route",
                    "viewdamageret" => "Route",
                    "viewloadtransfer" => "Route"
                ),
            "orderajaxdata" =>
                array(
                    "cashcollection" => "Route",
                    "checkpromotionreview" => "Route",
                    "getvalidation" => "Route",
                    "invoicesummery" => "Route",
                    "promotion0" => "Route",
                    "promotion1" => "Route",
                    "promotion2" => "Route",
                    "promotion5" => "Route",
                    "promotion6" => "Route",
                    "promotion7" => "Route"
                ),
            "ordertransaction" =>
                array(
                    "ajaxgrid" => "Route",
                    "ajaxgridinvoice" => "Route",
                    "common_step" => "Route",
                    "detail1" => "Route",
                    "detail2" => "Route",
                    "detail3" => "Route",
                    "detail4" => "Route",
                    "detail5" => "Route",
                    "promotiondetailview" => "Route",
                    "promotionplan" => "Route",
                    "salesorderadd" => "Route"
                ),
            "processload" =>
                array(
                    "ajaxgrid" => "Route"
                ),
            "routeendday" =>
                array(
                    "editroute" => "Route",
                    "routeendday" => "Route"
                ),
            "routestartday" =>
                array(
                    "addroute" => "Route",
                    "routestartday" => "Route"
                ),
            "transaction" =>
                array(
                    "addinvoice" => "Route",
                    "ajaxgrid" => "Route",
                    "ajaxgridinvoice" => "Route",
                    "cashcollection" => "Route",
                    "common_step" => "Route",
                    "invoice" => "Route",
                    "invoiceadd" => "Route",
                    "invoiceitem" => "Route",
                    "promotion" => "Route",
                    "promotiondetailview" => "Route",
                    "promotionplan" => "Route",
                    "promotionreview" => "Route",
                    "signature" => "Route"
                ),
            "unload" =>
                array(
                    "ajaxgrid" => "Route",
                    "ajaxgridextra" => "Route",
                    "unloadret" => "Route",
                    "unloadvar" => "Route",
                    "viewunloadret" => "Route",
                    "viewunloadvar" => "Route"
                ),
        ),
        "api" =>
            array("index" =>
                array("home" => "Route")
        ),
        "inventory" =>
            array("index" =>
                array(
                    "addcompanygroup" => "Route",
                    "additemgrp" => "Route",
                    "additems" => "Route",
                    "additionalitembarcode" => "Route",
                    "addmajorcat" => "Route",
                    "addsubmajorcat" => "Route",
                    "companygroup" => "Route",
                    "general" => "Route",
                    "itemgrp" => "Route",
                    "items" => "Route",
                    "itemset1" => "Route",
                    "majorcat" => "Route",
                    "submajorcat" => "Route"
                ),
            "pos" =>
                array(
                    "addcustomerposlimit" => "Route",
                    "addpos" => "Route",
                    "addposinstruction" => "Route",
                    "ajaxgrid" => "Route",
                    "customerposlimit" => "Route",
                    "index" => "Route",
                    "posinstruction" => "Route"
                ),
            "target" =>
                array(
                    "addcompanymonthlytarget" => "Route",
                    "adddepotmonthlytarget" => "Route",
                    "additempackage" => "Route",
                    "addquota" => "Route",
                    "addregionmonthlytarget" => "Route",
                    "addsalesmantarget" => "Route",
                    "ajaxgrid" => "Route",
                    "companymonthlytarget" => "Route",
                    "depotmonthlytarget" => "Route",
                    "itempackage" => "Route",
                    "quota" => "Route",
                    "regionmonthlytarget" => "Route",
                    "salesmantarget" => "Route"
                ),
            "transaction" =>
                array(
                    "adddailysalesmanload" => "Route",
                    "adddepotdamageexpiry" => "Route",
                    "adddepotinventory" => "Route",
                    "addgrn" => "Route",
                    "addrouteitemgroup" => "Route",
                    "ajaxgrid" => "Route",
                    "batchdetails" => "Route",
                    "dailysalesmanload" => "Route",
                    "depotdamageexpiry" => "Route",
                    "depotinventory" => "Route",
                    "depotstock" => "Route",
                    "endofday" => "Route",
                    "grn" => "Route",
                    "routeitemgroup" => "Route",
                    "startofday" => "Route"
                )
        ),
        "links" =>
            array("customer" =>
                array(
                    "customercatlink" => "Route",
                    "surveylink" => "Route"
                ),
            "promo" =>
                array(
                    "discountlink" => "Route",
                    "distributionlink" => "Route",
                    "pricelink" => "Route",
                    "promotionlink" => "Route"
                ),
            "route" =>
                array(
                    "activenonactive" => "Route",
                    "outletproductlink" => "Route",
                    "routeitemlink" => "Route"
                )
        ),
        "merchandize" =>
            array("index" =>
                array(
                    "addlookupindex" => "Route",
                    "addselectedsurveyplan" => "Route",
                    "addsurvey" => "Route",
                    "addsurveykey" => "Route",
                    "addsurveyplan" => "Route",
                    "ajaxgrid" => "Route",
                    "ajaxgrid2" => "Route",
                    "survey" => "Route",
                    "surveydefinition" => "Route",
                    "surveykey" => "Route",
                    "surveyplan" => "Route",
                    "surveyplangrid" => "Route",
                    "viewcustomerimages" => "Route"
                )
        ),
        "organization" =>
            array("index" =>
                array(
                    "addarea" => "Route",
                    "addbranch" => "Route",
                    "addbusinesstype" => "Route",
                    "addcountry" => "Route",
                    "addregion" => "Route",
                    "addroutecat" => "Route",
                    "addsubarea" => "Route",
                    "addvan" => "Route",
                    "area" => "Route",
                    "branch" => "Route",
                    "businesstype" => "Route",
                    "country" => "Route",
                    "region" => "Route",
                    "routecat" => "Route",
                    "subarea" => "Route",
                    "van" => "Route"
                ),
            "route" =>
                array(
                    "addroute" => "Route",
                    "addroutetemplate" => "Route",
                    "addroutetmpl" => "Route",
                    "general" => "Route",
                    "generaltmpl" => "Route",
                    "reports" => "Route",
                    "route" => "Route",
                    "routetmpl" => "Route",
                    "routetmplgeneral" => "Route",
                    "setting1" => "Route",
                    "setting2" => "Route",
                    "tmplsetting1" => "Route",
                    "tmplsetting2" => "Route"
                )
        ),
        "promotions" =>
            array("advance" =>
                array(
                    "addpromotionkey" => "Route",
                    "addpromotionplan" => "Route",
                    "ajaxgrid" => "Route",
                    "ajaxgrid2" => "Route",
                    "groupdetail" => "Route",
                    "promotionkey" => "Route",
                    "promotionkeyitemgrid" => "Route",
                    "promotionkeyplan" => "Route",
                    "promotionplan" => "Route",
                    "promotionplanselectionitemgrid" => "Route",
                    "promotionrange" => "Route"
                ),
            "advancepricing" =>
                array(
                    "addpricingkey" => "Route",
                    "addpricingplan" => "Route",
                    "ajaxgrid" => "Route",
                    "ajaxgrid2" => "Route",
                    "pricingkey" => "Route",
                    "pricingkeyitemgrid" => "Route",
                    "pricingkeyplan" => "Route",
                    "pricingplan" => "Route",
                    "pricingplanselectionitemgrid" => "Route"
                ),
            "customer" =>
                array(
                    "addfreegoods" => "Route",
                    "ajaxgrid" => "Route",
                    "customerfreegoodsdetails" => "Route",
                    "freegoods" => "Route"
                ),
            "discount" =>
                array(
                    "adddiscountkey" => "Route",
                    "adddistributionkey" => "Route",
                    "ajaxgrid" => "Route",
                    "discountkey" => "Route",
                    "distributionkey" => "Route"
                ),
            "index" =>
                array(
                    "addassignmentgrp" => "Route",
                    "addcustomerpricing" => "Route",
                    "addcustomerpromo" => "Route",
                    "addqualificationgrp" => "Route",
                    "ajaxgrid" => "Route",
                    "assignmentgrp" => "Route",
                    "customerpriceheader" => "Route",
                    "customerpriceplan" => "Route",
                    "customerpricing" => "Route",
                    "customerpromo" => "Route",
                    "customerpromoplan" => "Route",
                    "promotionrange" => "Route",
                    "qualificationgrp" => "Route"
                )
        ),
        "report" =>
            array("account" =>
                array(
                    "ajaxdata" => "Route",
                    "ajaxdatagrid" => "Route",
                    "ajaxgrid" => "Route",
                    "customeraginganalysis" => "Route",
                    "pdcreports" => "Route",
                    "pendinginvoicebycustomer" => "Route",
                    "pendinginvoicebyroute" => "Route",
                    "routeaccountability" => "Route",
                    "routeaginganalysis" => "Route",
                    "salesmanaccountability" => "Route",
                    "settlementforcahierreceipt" => "Route",
                    "tcreports" => "Route",
                    "totalsalesbycustomer" => "Route",
                    "totalsalesbyhierarchy" => "Route"
                ),
            "accountreport" =>
                array(
                    "customeraginganalysis" => "Route",
                    "pdcreports" => "Route",
                    "pendinginvoicebycustomer" => "Route",
                    "pendinginvoicebyroute" => "Route",
                    "routeaccountability" => "Route",
                    "routeaginganalysis" => "Route",
                    "salesmanaccountability" => "Route",
                    "settlementforcahierreceipt" => "Route",
                    "tcreports" => "Route",
                    "totalsalesbycustomer" => "Route",
                    "totalsalesbyhierarchy" => "Route"
                ),
            "accountstatement" =>
                array(
                    "ajaxdatagrid" => "Route",
                    "bycustomer" => "Route",
                    "byroute" => "Route",
                    "report-grid" => "Route",
                    "setcustomerdata" => "Route",
                    "settlementforcahierreceipt" => "Route"
                ),
            "accountstatementreport" =>
                array(
                    "bycustomer" => "Route",
                    "byroute" => "Route",
                    "report-grid" => "Route",
                    "setcustomerdata" => "Route"
                ),
            "basic" =>
                array(
                    "ajaxdatagrid" => "Route",
                    "commstatus" => "Route",
                    "coverageandproductivity" => "Route",
                    "dailysalesreport" => "Route",
                    "discountgiven" => "Route",
                    "itemledger" => "Route",
                    "logdetail" => "Route",
                    "routerecon" => "Route",
                    "routestartenddays" => "Route",
                    "salesmanrecon" => "Route"
                ),
            "basicreport" =>
                array(
                    "commstatus" => "Route",
                    "coverageandproductivity" => "Route",
                    "dailysalesreport" => "Route",
                    "discountgiven" => "Route",
                    "itemledger" => "Route",
                    "logdetail" => "Route",
                    "report-grid" => "Route",
                    "routerecon" => "Route",
                    "routestartenddays" => "Route",
                    "salesmanrecon" => "Route"
                ),
            "export" =>
                array(
                    "index" => "Route"
                ),
            "hhc" =>
                array(
                    "ajaxdatagrid" => "Route",
                    "goodsbycustomer" => "Route",
                    "goodsbyhierarchy" => "Route",
                    "routeactivity" => "Route",
                    "salessummary" => "Route"
                ),
            "hhcreport" =>
                array(
                    "goodsbycustomer" => "Route",
                    "goodsbyhierarchy" => "Route",
                    "routeactivity" => "Route",
                    "salessummary" => "Route"
                ),
            "hhcsummary" =>
                array(
                    "ajaxdatagrid" => "Route",
                    "buybacksummary" => "Route",
                    "collectionsummary" => "Route",
                    "depositreport" => "Route",
                    "routereview" => "Route",
                    "temporarycredit" => "Route"
                ),
            "hhcsummaryreport" =>
                array(
                    "buybacksummary" => "Route",
                    "collectionsummary" => "Route",
                    "depositreport" => "Route",
                    "routereview" => "Route",
                    "temporarycredit" => "Route"
                ),
            "index" =>
                array(
                    "index" => "Route",
                    "loctrnsbyprd" => "Route",
                    "prdtrnsbyloc" => "Route",
                    "prductivitycoverage" => "Route",
                    "productdetailbycustomer" => "Route",
                    "productdetailbylocation" => "Route",
                    "routebycustspedr" => "Route",
                    "routevisitwkday" => "Route",
                    "routwisesummaryspew" => "Route",
                    "salessummary" => "Route",
                    "searchcriteria" => "Route",
                    "totalsales" => "Route"
                ),
            "indexreport" =>
                array(
                    "loctrnsbyprd" => "Route",
                    "prdtrnsbyloc" => "Route",
                    "prductivitycoverage" => "Route",
                    "productdetailbycustomer" => "Route",
                    "productdetailbylocation" => "Route",
                    "report-grid" => "Route",
                    "routebycustspedr" => "Route",
                    "routevisitwkday" => "Route",
                    "routwisesummaryspew" => "Route",
                    "salessummary" => "Route",
                    "totalsales" => "Route"
                ),
            "transaction" =>
                array(
                    "ajaxdatagrid" => "Route",
                    "dailysalessheet" => "Route",
                    "datasummaryreport" => "Route",
                    "returnssummaryreport" => "Route",
                    "skudiscountreport" => "Route"
                ),
            "transactionreport" =>
                array(
                    "dailysalessheet" => "Route",
                    "datasummaryreport" => "Route",
                    "report-grid" => "Route",
                    "returnssummaryreport" => "Route",
                    "skudiscountreport" => "Route"
                )
        )
    );
    
    public function __construct($current_module ="home", $current_controller = "index" ,$current_action = "index")
	{
		echo $this->module[$current_module][$current_controller][$current_action];
	}
	
}  
?>
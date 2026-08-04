<?php

class SFA_Moduleacl {
    
    public $module = array(
        "account" =>
            array("customer" =>
                array("addauthorizegroup" => "Customer_Authorize_Group",
                        "addcustcat" => "Customer_Category",
                        "addchannel" => "Channel",
                        "addcustomer" => "Customer",
                        "addmessages"=> "Customer_Message",
                        "addtemplate"=> "Customer_Template",
                        "additemmust"=> "Item_Must_List",
                        "authorizegroup"=> "Customer_Authorize_Group",
                        "custcat"=> "Customer_Category",
                        "channel" => "Channel",
                        "customer"=> "Customer",
                        "messages"=> "Customer_Message",
                        "template"=> "Customer_Template",
                        "itemmust"=> "Item_Must_List"
                ),
            "customerseq" =>
                array(
                    "addcustomer" => "Customer_Sequence",
                    "arrangecustomer" => "Customer_Sequence",
                    "copysequence" => "Customer_Sequence",
                    "index" => "Customer_Sequence",
                    "routesequence" => "Customer_Sequence",
                    "salescalender" => "Customer_Sequence"
                ),
            "index" =>
                array(
                    "addoutletproduct" => "Outlet_Product_Code",
                    "outletproduct" => "Outlet_Product_Code",
                    "outletproductgrid" => "Outlet_Product_Code",
                    "addoutletproductcode" => "Outlet_Product_Code",
                    "expirymanagement" => "Expiry_Management",
                    "expirymanagementgrid" => "Expiry_Management",
                    "journeyplan" => "Journey_Plan_Credit_Limit",
                    "deletejourneyplan" => "Journey_Plan_Credit_Limit"
                ),
            "notes" =>
                array(
                    "addcreditcustomer" => "Credit_Note_Customer",
                    "addcreditroute" => "Credit_Note_Route",
                    "adddebitcustomer" => "Debit_Note_Customer",
                    "adddebitroute" => "Debit_Note_Route",
                    "creditcustomer" => "Credit_Note_Customer",
                    "creditroute" => "Credit_Note_Route",
                    "debitcustomer" => "Debit_Note_Customer",
                    "debitroute" => "Debit_Note_Route",
                    "creditcustomergrid" => "Credit_Note_Customer"
                ),
            "salesman" =>
                array(
                    "addsalesman" => "Salesman",
                    "addsalesmanmsgs" => "Salesman_Message",
                    "salesman" => "Salesman",
                    "salesmanmsgs" => "Salesman_Message"                    
                ),
            "settlement" =>
                array(
                    "addcashreceipt" => "Cashier_Receipt",
                    "addpdc" => "PDC_Cheque",
                    "cashreceipt" => "Cashier_Receipt",
                    "pdc" => "PDC_Cheque",
                    "cashreceiptgrid" => "Cashier_Receipt"
                ),
            "transaction" =>
                array(
                    "addgccollection" => "GC_Collection",
                    "addhocollection" => "HO_Collection",
                    "addopeningbal" => "Opening_Balance",
                    "gccollection" => "GC_Collection",
                    "hocollection" => "HO_Collection",
                    "openingbal" => "Opening_Balance",
                    "monthclose" => "Month_Close",
                )
        ),
        "admin" =>
            array("cpanel" =>
                array(
                    "index" => "Control_Panel"
                ),
            "index" =>
                array(
                    "index" => "Basic_Setup"
                ),
            "security" =>
                array(
                    "adduser" => "User_Master",
                    "addusertype" => "User_Type",
                    "pwdgenerate" => "Password_Generator",
                    "user" => "User_Master",
                    "userpermission" => "User_Permission",
                    "usertype" => "User_Type"
                )
        ),
        "api" =>
            array("index" =>
                array("home" => "Route")
        ),
        "basic" =>
            array("index" =>
                array(
                    "addareamanager" => "Area_Manager",
                    "addbank" => "Bank",
                    "addcashdesc" => "Cash_Description",
                    "addcompany" => "Company",
                    "addcurrency" => "Currency",
                    "adddepotmanager" => "Depot_Manager",
                    "addinventoryloc" => "Inventory_Location",
                    "addregionmanager" => "Region_Manager",
                    "addsalesmanager" => "National_Sales_Manager",
                    "addsupervisor" => "Supervisor",
                    "areamanager" => "Area_Manager",
                    "bank" => "Bank",
                    "cashdesc" => "Cash_Description",
                    "company" => "Company",
                    "currency" => "Currency",
                    "depotmanager" => "Depot_Manager",
                    "inventoryloc" => "Inventory_Location",
                    "regionmanager" => "Region_Manager",
                    "salesmanager" => "National_Sales_Manager",
                    "supervisor" => "Supervisor"
                ),
            "reasons" =>
                array(
                    "addbadreturn" => "Bad_Return",
                    "addexpensereason" => "Expenses",
                    "addfocreason" => "Free",
                    "addgoodreturn" => "Good_Return",
                    "addnonservice" => "Non_Serviced",
                    "addvoidreason" => "Void",
                    "badreturn" => "Bad_Return",
                    "expensereason" => "Expenses",
                    "focreason" => "Free",
                    "goodreturn" => "Good_Return",
                    "nonservice" => "Non_Serviced",
                    "voidreason" => "Void"
                )
        ),
        "datamanagement" =>
            array("index" =>
                array(
                    "deletedata" => "Delete_Data",
                    "deletetrxndata" => "Delete_Transaction_By_Route",
                    "viewlogdata" => "View_Activity_Log",
                    "upsync" => "UpSync Transaction By Route",
                    "downsync" => "Down Sync and Update DB"
                )
        ),
        "hhctransaction" =>
            array("arcollection" =>
                array(
                    "arcollectionadd" => "Cloud_Transaction_AR_Collection"
                ),
            "cloudtran" =>
                array(
                    "advpayment" => "Advance_Collection",
                    "arcollection" => "Receipts",
                    "invoice" => "Sales",
                    "salesorder" => "Order"
                ),
            "invoice" =>
                array (
                    "advpayment" => "Advance_Payment",
                    "arcollection" => "AR_Collection",
                    "invoice" => "Invoice",
                    "salesorder" => "Sales_Order"
                ),
            "loadstock" =>
                array(
                    "beginstock" => "Begin/Opening_Stock",
                    "load" => "Load",
                    "loadrequest" => "Load_Request",
                    "viewbeginstock" => "Begin/Opening_Stock",
                    "viewbeginstockgrid" => "Begin/Opening_Stock",
                    "viewload" => "Load",
                    "viewloadgrid" => "Load",
                    "viewloadrequest" => "Load_Request",
                    "viewloadrequestgrid" => "Load_Request",
                ),
            "loadtransfer" =>
                array(
                    "damageret" => "Damage_Return",
                    "loadtransfer" => "Load_Transfer",
                    "viewdamageret" => "Damage_Return",
                    "viewdamageretgrid" => "Damage_Return",
                    "viewloadtransfer" => "Load_Transfer",
                    "viewloadtransfergrid" => "Load_Transfer"
                ),
            "ordertransaction" =>
                array(
                    "salesorderadd" => "Sales_Order"
                ),
            "processload" =>
                array(
                    "addprocessload" => "Process_Load",
                    "editprocessload" => "Process_Load",
                    "processload" => "Process_Load",
                   // "loaditemdetail" => "Route"
                ),
            "routeendday" =>
                array(
                    "editroute" => "Route_End_Day",
                    "routeendday" => "Route_End_Day"
                ),
            "routestartday" =>
                array(
                    "addroute" => "Route_Start_Day",
                    "routestartday" => "Route_Start_Day"
                ),
            "transaction" =>
                array(
                    "invoiceadd" => "Cloud_Transaction_Invoice"
                ),
            "unload" =>
                array(
                    "unloadret" => "Unload_Inventory",
                    "unloadvar" => "Unload_Variance"
                ),
            "inventory" =>
                array(
                    "inventorysummary" => "Inventory_Summary",
                    "customerinventory" => "Customer Inventory"
                )
                
        ),
        "inventory" =>
            array("index" =>
                array(
                    "addcompanygroup" => "Company_Group",
                    "additemgrp" => "Item_Group",
                    "additems" => "Items",
                    "addmajorcat" => "Major_Category",
                    "addsubmajorcat" => "Sub_Major_Category",
                    "companygroup" => "Company_Group",
                    "itemgrp" => "Item_Group",
                    "items" => "Items",
                    "majorcat" => "Major_Category",
                    "submajorcat" => "Sub_Major_Category"
                ),            
            "target" =>
                array(
                    //"addcompanymonthlytarget" => "Route",
                    //"adddepotmonthlytarget" => "Route",
                    "additempackage" => "Item_Package",
                    "addquota" => "Quota",
                    //"addregionmonthlytarget" => "Route",
                    "addsalesmantarget" => "Salesman_Target",
                    //"companymonthlytarget" => "Route",
                    //"depotmonthlytarget" => "Route",
                    "itempackage" => "Item_Package",
                    "quota" => "Quota",
                    //"regionmonthlytarget" => "Route",
                    "salesmantarget" => "Salesman_Target"
                ),
            "transaction" =>
                array(
                    "adddailysalesmanload" => "Daily_Salesman_Load",
                    "adddepotdamageexpiry" => "Depot_Damage/Expiry",
                    "adddepotinventory" => "Depot_Inventory",
                    "addgrn" => "Goods_Receipt_Note",
                    "addrouteitemgroup" => "Route_Item_Group",
                    //"batchdetails" => "Route",
                    "dailysalesmanload" => "Daily_Salesman_Load",
                    "depotdamageexpiry" => "Depot_Damage/Expiry",
                    "depotinventory" => "Depot_Inventory",
                    "depotstock" => "Depot_Stock",
                    "grn" => "Goods_Receipt_Note",
                    "routeitemgroup" => "Route_Item_Group",
					"adddelivery" => "Delivery",
					"delivery" => "Delivery",
                )
        ),
        "links" =>
            array("customer" =>
                array(
                    "customercatlink" => "Category_Key",
                    "surveylink" => "Survey_Key"
                ),
            "promo" =>
                array(
                    "discountlink" => "Link_Discount_Key_",
                    "distributionlink" => "Link_Distribution_Key",
                    "pricelink" => "Special_Price",
                    "promotionlink" => "Promotion"
                ),
            "route" =>
                array(
                    "activenonactive" => "Active/InActive_Items",
                    "outletproductlink" => "Outlet_Product_Code",
                    "routeitemlink" => "Route_Item_Group",
					"itemgroup" => "Items_Group"
                )
        ),
        "merchandize" =>
            array("index" =>
                array(
                    //"addselectedsurveyplan" => "Survey_Plan",
                    "addsurvey" => "Survey_Definition",
                    "addsurveykey" => "Survey_Key",
                    "addsurveyplan" => "Survey_Plan",
                    "survey" => "Survey_Definition",
                    "surveykey" => "Survey_Key",
                    "surveyplan" => "Survey_Plan",
                    "surveyplangrid" => "Survey_Plan",
                    "customerimages" => "Customer_Images"
                ),
                "pos" =>
                array(
                    "addcustomerposlimit" => "Customer_POS_Limit",
                    "addpos" => "POS_Master",
                    "addposinstruction" => "POS_Instruction",
                    "customerposlimit" => "Customer_POS_Limit",
                    "index" => "POS_Master",
                    "posinstruction" => "POS_Instruction"
                ),
        ),
        "organization" =>
            array("index" =>
                array(
                    "addarea" => "Area",
                    "addbranch" => "Branch/Depot",
                    "addcountry" => "Country",
                    "addregion" => "Region",
                    "addroutecat" => "Route_Category",
                    "addsubarea" => "Sub_Area",
                    "addvan" => "Van",
                    "area" => "Area",
                    "branch" => "Branch/Depot",
                    "country" => "Country",
                    "region" => "Region",
                    "routecat" => "Route_Category",
                    "subarea" => "Sub_Area",
                    "van" => "Van"
                ),
            "route" =>
                array(
                    "addroute" => "Route",
                    "addroutetmpl" => "Route_Template",
                    "route" => "Route",
                    "routetmpl" => "Route_Template"                    
                )
        ),
        "promotions" =>
            array("advance" =>
                array(
                    "addpromotionkey" => "Promo_Key",
                    "addpromotionplan" => "Promo_Plan",
                    //"groupdetail" => "Route",
                    "promotionkey" => "Promo_Key",
                    //"promotionkeyitemgrid" => "Route",
                    //"promotionkeyplan" => "Route",
                    "promotionplan" => "Promo_Plan",
                    //"promotionplanselectionitemgrid" => "Route"
                ),
            "advancepricing" =>
                array(
                    "addpricingkey" => "Pricing_Key",
                    "addpricingplan" => "Pricing_Plan",
                    "pricingkey" => "Pricing_Key",
                    "pricingkeyitemgrid" => "Pricing_Key",
                    "pricingkeyplan" => "Pricing_Key",
                    "pricingplan" => "Pricing_Plan",
                    "pricingplanselectionitemgrid" => "Pricing_Plan"
                ),
            "customer" =>
                array(
                    "addfreegoods" => "Customer_Free_Contract",
                    "customerfreegoodsdetails" => "Customer_Free_Contract",
                    "freegoods" => "Customer_Free_Contract",
                    "freegoodsgrid" => "Customer_Free_Contract"
                ),
            "discount" =>
                array(
                    "adddiscountkey" => "Discount_Key",
                    "adddistributionkey" => "Distribution_Key",
                    "discountkey" => "Discount_Key",
                    "distributionkey" => "Distribution_Key",
                    "discountkeyitemgrid" => "Discount_Key",
                    "distributionkeyitemgrid" => "Distribution_Key",
                ),
            "index" =>
                array(
                    "addassignmentgrp" => "Assignment_Group",
                    "addqualificationgrp" => "Qualification_Group",
                    "assignmentgrp" => "Assignment_Group",
                    "qualificationgrp" => "Qualification_Group"
                )
        ),
        "reports" =>
            array("routesyncstatus" =>
                    array(
                        "routesyncstatus" => "Route_Sync_Status"
                    ),
                "routesummary" =>
                    array(
                        "routesummary" => "Route_Summary"
                    ),
                "dailyrouteactivity" =>
                    array(
                        "dailyrouteactivity" => "Daily_Route_Activity"
                    ),
                "routeinventory" =>
                    array(
                        "routeinventory" => "Route_Inventory"
                    ),
                "routetripanalysis" =>
                    array(
                        "routetripanalysis" => "Route_Trip_Analysis"
                    ),
                 "routedepositsummary" =>
                    array(
                        "routedepositsummary" => "Route_Deposit_Summary"
                    ),
                  "discountsummary" =>
                    array(
                        "discountsummary" => "Discount_Summary"
                    ),
                  "pricingsummary" =>
                    array(
                        "pricingsummary" => "Pricing_Summary"
                    ),
                  "salessummary" =>
                    array(
                        "salessummary"  => "Sales_Summary"
                    ),
                  "ordersummary" =>
                    array(
                        "ordersummary"  => "Order_Summary"
                    ),
                    "transactionroutereview" =>
                    array(
                        "routereview"  => "Transaction_Route_View"
                    ),
                   "returnsummary" =>
                    array(
                        "returnsummary"  => "Return_Summary"
                    ),
                    "transactionfoc" =>
                    array(
                        "focsummary"  => "FOC_Summary"
                    ),
                    "transactioncollectionsummary" =>
                    array(
                        "collectionsummary"  => "Collection_Summary"
                    ),
                    "transactionpaymentsummary" =>
                    array(
                        "paymentsummary"  => "Payment_Summary"
                    ),
                    "transactiondepositsummary" =>
                    array(
                        "depositsummary" => "Deposit_Summary"
                    ),
                    "transactionitemhistory" =>
                    array(
                        "itemhistory"  => "Item_History"
                    ),
                    "merchandizingpos" =>
                    array(
                        "posstatus"  => "POS_Tracking"
                    ),
                    "merchandizingsurvey" =>
                    array(
                        "surveytracking"  => "Survey_Tracking"
                    ),
                    "accountrouteageing" =>
                    array(
                        "routeageing"  => "Route_Ageing"
                    ),
                    "accountcustomerageing" =>
                    array(
                        "customerageing"  => "Customer_Ageing"
                    ),
                    "routependingbalance" =>
                    array(
                        "routependingbalance"  => "Route_Pending_Balance"
                    ),
                    "customerbalance" =>
                    array(
                        "customerpendingbalance"  => "Customer_Pending_Balance"
                    ),
                    "soa" =>
                    array(
                        "soa"  => "SOA"
                    ),
                    "pdc" =>
                    array(
                        "pdc" => "PDC"
                    ),
                    "tabletsalessummary" =>
                    array(
                        "salessummary" => "Tablet_Sales_Summary"
                    ),
                    "tabletreport" =>
                    array(
                        "tabletreport" => "Tablet_Report"
                    ),
                    "cashchequecollection" =>
                    array(
                        "cashchequecollection" => "Cash_Cheque_Collection"
                    ),
                    "returnorbuybacksummary" =>
                    array(
                        "returnorbuybacksummary" => "Return/Buy_Back_Summary"
                    ),
                     "tabletfreesummary" =>
                    array(
                        "freesummary" => "Free_Summary"
                    ),
                    "tabletvoidtransaction" =>
                    array(
                        "voidtransaction"  => "Void_Transaction"
                    ),
                    "datamonthlyrevenue" =>
                    array(
                        "datamonthlyrevenue"  => "Route_Monthly_Revenue"
                    ),
                    "datasalesfree" =>
                    array(
                        "salesfreesummary"   => "Sales_Free_Summary"
                    ),
                    "itemsalessummary" =>
                    array(
                        "itemsalessummary"   => "Item_Sales_Summary"
                    ),
                    "dataitemdistribution" =>
                    array(
                        "itemdistribution" => "Item_Distribution"
                    ),
                    "itemgroupwisesales" =>
                    array(
                        "itemgroupwisesales" => "Item_Sales_Summary"
                    ),
                    "strikeratecallrateavgtime" =>
                    array(
                        "strikeratecallrateavgtime" => "StrikeRate_CallRate_Discipline_Length_AvgTime"
                    ),
                    "salesmanproductivity" =>
                    array(
                        "salesmanproductivity"  => "Salesman_Productivity"
                    ),
                    "averagedropsize" =>
                    array(
                        "averagedropsize"  => "Average_Drop_Size"
                    ) 
            )
    );
    
    public $acl_module;
   
   
   /******************************************************************************************************************/
   
   
   /******************************************************************************************************************/
   
    
    public $read_delete_arr = array(
        "account" =>
            array("customer" =>
                array(
                    "authorizegroup",
                    "custcat",
                    "customer",
                    "messages",
                    "template",
                    "channel",
                    "itemmust"
                ),
            "customerseq" =>
                array(
                    "arrangecustomer",
                    "copysequence",
                    "index",
                    "routesequence",
                    "salescalender"
                ),
            "index" =>
                array(
                    "expirymanagement",
                    "journeyplan",
                    "outletproduct",
                    "outletproductgrid",
                    "expirymanagementgrid"
                ),
            "notes" =>
                array(
                    "creditcustomer",
                    "creditroute",
                    "debitcustomer",
                    "debitroute"
                ),
            "salesman" =>
                array(
                    "salesman",
                    "salesmanmsgs"                    
                ),
            "settlement" =>
                array(
                    "cashreceipt",
                    "pdc",
                    "viewcollection"
                ),
            "transaction" =>
                array(
                    "gccollection",
                    "hocollection",
                    "monthclose",
                    "openingbal",
                )
        ),
        "admin" =>
            array("cpanel" =>
                array(
                      "index"
                ),
            "index" =>
                array(
                    "index"
                ),
            "security" =>
                array(
                    "user",
                    "userpermission",
                    "usertype"
                )
        ),
        "basic" =>
            array("index" =>
                array(
                    "areamanager",
                    "bank",
                    "cashdesc",
                    "company",
                    "currency",
                    "depotmanager",
                    "inventoryloc",
                    "regionmanager",
                    "salesmanager",
                    "supervisor"
                ),
            "reasons" =>
                array(
                    "badreturn",
                    "expensereason",
                    "focreason",
                    "goodreturn",
                    "nonservice",
                    "voidreason"
                )
        ),
        "datamanagement" =>
            array("index" =>
                array(
                    "deletedata",
                    "deletetrxndata",
                    "viewlogdata",
                    "upsync",
                    "downsync"
                )
        ),
        "hhctransaction" =>
            array(
            "cloudtran" =>
                array(
                    "advpayment",
                    "arcollection",
                    "invoice",
                    "salesorder"
                ),
            "invoice" =>
                array(
                    "advpayment",
                    "arcollection",
                    "invoice",
                    "salesorder"
                ),
            "loadstock" =>
                array(
                    "beginstock",
                    "load",
                    "loadrequest",
                    "viewbeginstock",
                    "viewbeginstockgrid",
                    "viewload",
                    "viewloadgrid",
                    "viewloadrequest",
                    "viewloadrequestgrid"
                ),
            "loadtransfer" =>
                array(
                    "damageret",
                    "loadtransfer",
                    "viewdamageret",
                    "viewdamageretgrid",
                    "viewloadtransfer",
                    "viewloadtransfergrid",
                    "viewloadrequest"
                ),
            "ordertransaction" =>
                array(
                    "salesorderadd"
                ),
            "processload" =>
                array(
                    "processload"
                ),
            "routeendday" =>
                array(
                    "routeendday"
                ),
            "routestartday" =>
                array(
                    "routestartday"
                ),
            "transaction" =>
                array(
                    "invoiceadd"
                ),
            "unload" =>
                array(
                    "unloadret",
                    "unloadvar"
                ),
            "inventory" =>
                array(
                    "inventorysummary",
                    "customerinventory"
                )
        ),
        "inventory" =>
            array("index" =>
                array(
                    "companygroup",
                    "itemgrp",
                    "items",
                    "majorcat",
                    "submajorcat"
                ),           
            "target" =>
                array(
                    "itempackage",
                    "quota",
                    "salesmantarget"
                ),
            "transaction" =>
                array(
                    "dailysalesmanload",
                    "depotdamageexpiry",
                    "depotinventory",
                    "depotstock",
                    "endofday",
                    "grn",
                    "routeitemgroup",
					"delivery"
                )
        ),
        "links" =>
            array("customer" =>
                array(
                    "customercatlink",
                    "surveylink"
                ),
            "promo" =>
                array(
                    "discountlink",
                    "distributionlink",
                    "pricelink",
                    "promotionlink"
                ),
            "route" =>
                array(
                    "activenonactive",
                    "outletproductlink",
                    "routeitemlink",
					"itemgroup"
                )
        ),
        "merchandize" =>
            array("index" =>
                array(
                    "survey",
                    "surveykey",
                    "surveyplan",
                    "surveyplangrid",
                    "viewcustomerimages"
                ),
            "pos" =>
                array(
                    "customerposlimit",
                    "index",
                    "posinstruction"
                ),
        ),
        "organization" =>
            array("index" =>
                array(
                    "area",
                    "branch",
                    "country",
                    "region",
                    "routecat",
                    "subarea",
                    "van"
                ),
            "route" =>
                array(
                    "route",
                    "routetmpl"
                )
        ),
        "promotions" =>
            array("advance" =>
                array(
                    "promotionkey",
                    "promotionplan"
                ),
            "advancepricing" =>
                array(
                    "pricingkey",
                    "pricingkeyitemgrid",
                    "pricingkeyplan",
                    "pricingplan",
                    "pricingplanselectionitemgrid"
                ),
            "customer" =>
                array(
                    "customerfreegoodsdetails",
                    "freegoods",
                    "freegoodsgrid"
                ),
            "discount" =>
                array(
                    "discountkey",
                    "distributionkey",
                    "discountkeyitemgrid",
                    "distributionkeyitemgrid"
                ),
            "index" =>
                array(
                    "assignmentgrp",
                    "qualificationgrp"
                )
        ),
        "reports" =>
          array("routesyncstatus" =>
                    array(
                        "routesyncstatus"
                    ),
                "routesummary" =>
                    array(
                        "routesummary"
                    ),
                "dailyrouteactivity" =>
                    array(
                        "dailyrouteactivity"
                    ),
                "routeinventory" =>
                    array(
                        "routeinventory"
                    ),
                "routetripanalysis" =>
                    array(
                        "routetripanalysis"
                    ),
                "routedepositsummary" =>
                    array(
                        "routedepositsummary"
                    ),
                "discountsummary" =>
                    array(
                        "discountsummary"
                    ),
                "pricingsummary" =>
                    array(
                        "pricingsummary"
                    ),
                "salessummary" =>
                    array(
                        "salessummary"
                    ),
                "transactionroutereview" =>
                    array(
                        "routereview"
                    ),
                "ordersummary" =>
                    array(
                        "ordersummary"
                    ),
                "returnsummary" =>
                    array(
                        "returnsummary"
                    ),
                "transactionfoc" =>
                    array(
                        "focsummary"
                    ),
                "transactioncollectionsummary" =>
                    array(
                        "collectionsummary"
                    ),
                "transactionpaymentsummary" =>
                    array(
                        "paymentsummary"
                    ),
                "transactiondepositsummary" =>
                    array(
                        "depositsummary"
                    ),
                "transactionitemhistory" =>
                    array(
                        "itemhistory"
                    ),
                "merchandizingpos" =>
                    array(
                        "posstatus"
                    ),
                "merchandizingsurvey" =>
                    array(
                        "surveytracking"
                    ),
                 "accountrouteageing" =>
                    array(
                        "routeageing"
                    ),
                 "accountcustomerageing" =>
                    array(
                        "customerageing"
                    ),
                 "routependingbalance" =>
                    array(
                        "routependingbalance"
                    ),
                 "customerbalance" =>
                    array(
                        "customerpendingbalance"
                    ),
                 "soa" =>
                    array(
                        "soa"
                    ),
                  "pdc" =>
                    array(
                        "pdc"
                    ),
                "tabletsalessummary" =>
                    array(
                        "salessummary"
                    ),
                "tabletreport" =>
                    array(
                        "tabletreport"
                    ),
                "cashchequecollection" =>
                    array(
                        "cashchequecollection"
                    ),
                "returnorbuybacksummary" =>
                    array(
                        "returnorbuybacksummary"
                    ),
                "tabletfreesummary" =>
                    array(
                        "freesummary"
                    ),
                "tabletvoidtransaction" =>
                    array(
                        "voidtransaction"
                    ),
                "datamonthlyrevenue" =>
                    array(
                        "datamonthlyrevenue"
                    ),
                "datasalesfree" =>
                    array(
                        "salesfreesummary"
                    ),
                "itemsalessummary" =>
                    array(
                        "itemsalessummary"
                    ),
                "dataitemdistribution" =>
                    array(
                        "itemdistribution"
                    ),
                "itemgroupwisesales" =>
                    array(
                        "itemgroupwisesales"
                    ),
                "strikeratecallrateavgtime" =>
                    array(
                        "strikeratecallrateavgtime" 
                    ),
                "salesmanproductivity" =>
                    array(
                        "salesmanproductivity" 
                    ),
                "averagedropsize" =>
                    array(
                        "averagedropsize"
                    )    
                    
            )
    );
    
    
    /******************************************************************************************************************/
   
   
    /******************************************************************************************************************/
   
    
    
    
    public $insert_update_arr = array(
        "account" =>
            array("customer" =>
                array("addauthorizegroup",
                        "addcustcat",
                        "addcustomer",
                        "addmessages",
                        "addtemplate",
                        "addchannel",
                        "additemmust"
                ),
            "customerseq" =>
                array(
                    "addcustomer",
                    "arrangecustomer",
                    "copysequence",
                    "index",
                    "routesequence",
                    "salescalender"
                ),
            "index" =>
                array(
                    "addoutletproduct",
                    "addoutletproductcode"
                ),
            "notes" =>
                array(
                    "addcreditcustomer",
                    "addcreditroute",
                    "adddebitcustomer",
                    "adddebitroute"
                ),
            "salesman" =>
                array(
                    "addsalesman",
                    "addsalesmanmsgs"
                ),
            "settlement" =>
                array(
                    "addcashreceipt",
                    "addpdc"
                ),
            "transaction" =>
                array(
                    "addgccollection",
                    "addhocollection",
                    "addopeningbal"
                )
        ),
        "admin" =>
            array("cpanel" =>
                array(
                    "index"
                ),
            "index" =>
                array(
                    "index"
                ),
            "security" =>
                array(
                    "pwdgenerate",
                    "adduser",
                    "addusertype"
                )
        ),
        "basic" =>
            array("index" =>
                array(
                    "addareamanager",
                    "addbank",
                    "addcashdesc",
                    "addcompany",
                    "addcurrency",
                    "adddepotmanager",
                    "addinventoryloc",
                    "addregionmanager",
                    "addsalesmanager",
                    "addsupervisor"
                ),
            "reasons" =>
                array(
                    "addbadreturn",
                    "addexpensereason",
                    "addfocreason",
                    "addgoodreturn",
                    "addnonservice",
                    "addvoidreason"
                )
        ),
        "hhctransaction" =>
            array("arcollection" =>
                array(
                    "arcollectionadd"
                ),
            "ordertransaction" =>
                array(
                    "salesorderadd"
                ),
            "processload" =>
                array(
                    "addprocessload",
                    "editprocessload"
                ),
            "routeendday" =>
                array(
                    "editroute",
                    "routeendday"
                ),
            "routestartday" =>
                array(
                    "addroute"
                ),
            "transaction" =>
                array(
                    "invoiceadd"
                ),
            "unload" =>
                array(
                    "unloadret",
                    "unloadvar"
                ),
            "loadrequest" => array(
                    "viewloadrequest"
                )
                
        ),
        "inventory" =>
            array("index" =>
                array(
                    "addcompanygroup",
                    "additemgrp",
                    "additems",
                    "addmajorcat",
                    "addsubmajorcat"
                ),            
            "target" =>
                array(
                    "additempackage",
                    "addquota",
                    "addsalesmantarget"
                ),
            "transaction" =>
                array(
                    "adddailysalesmanload",
                    "adddepotdamageexpiry",
                    "adddepotinventory",
                    "addgrn",
                    "addrouteitemgroup",
					"adddelivery"
					
                )
        ),
        "links" =>
            array("customer" =>
                array(
                    "customercatlink",
                    "surveylink"
                ),
            "promo" =>
                array(
                    "discountlink",
                    "distributionlink",
                    "pricelink",
                    "promotionlink"
                ),
            "route" =>
                array(
                    "activenonactive",
                    "outletproductlink",
                    "routeitemlink",
					"itemgroup"
                )
        ),
        "merchandize" =>
            array("index" =>
                array(
                    "addsurvey",
                    "addsurveykey",
                    "addsurveyplan",
                    "viewcustomerimages"
                ),
            "pos" =>
                array(
                    "addcustomerposlimit",
                    "addpos",
                    "addposinstruction"
                ),
        ),
        "organization" =>
            array("index" =>
                array(
                    "addarea",
                    "addbranch",
                    "addcountry",
                    "addregion",
                    "addroutecat",
                    "addsubarea",
                    "addvan"
                ),
            "route" =>
                array(
                    "addroute",
                    "addroutetmpl"
                )
        ),
        "promotions" =>
            array("advance" =>
                array(
                    "addpromotionkey",
                    "addpromotionplan"
                ),
            "advancepricing" =>
                array(
                    "addpricingkey",
                    "addpricingplan"
                ),
            "customer" =>
                array(
                    "addfreegoods"
                ),
            "discount" =>
                array(
                    "adddiscountkey",
                    "adddistributionkey"
                ),
            "index" =>
                array(
                    "addassignmentgrp",
                    "addqualificationgrp"
                )
        )
    );
    
    public $acl_read_delete_arr;
    public $acl_insert_update_arr;
    
    public function __construct($current_module ="home", $current_controller = "index" ,$current_action = "index")
	{
		$this->acl_module = $this->module[$current_module][$current_controller][$current_action];
        $this->acl_read_delete_arr = $this->read_delete_arr[$current_module][$current_controller];
        $this->acl_insert_update_arr = $this->insert_update_arr[$current_module][$current_controller];
	}
	
}  
?>
<?php

namespace Modules\IcommerceAuthorize\Http\Controllers;

use Mockery\CountValidator\Exception;


use Modules\Core\Http\Controllers\BasePublicController;
use Route;
use Session;

use Modules\User\Contracts\Authentication;
use Modules\User\Repositories\UserRepository;
use Modules\Icommerce\Repositories\CurrencyRepository;
use Modules\Icommerce\Repositories\ProductRepository;
use Modules\Icommerce\Repositories\OrderRepository;
use Modules\Icommerce\Repositories\Order_ProductRepository;
use Modules\Setting\Contracts\Setting;
use Illuminate\Http\Request as Requests;
use Illuminate\Support\Facades\Log;

use Modules\IcommerceAuthorize\Entities\Authorizeconfig;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class PublicController extends BasePublicController
{
  
    private $order;
    private $setting;
    private $user;
    protected $auth;
    
    public function __construct(Setting $setting, Authentication $auth, UserRepository $user,  OrderRepository $order)
    {

        $this->setting = $setting;
        $this->auth = $auth;
        $this->user = $user;
        $this->order = $order;

    }

    /**
     * Show Index
     * @param Requests request
     * @return  
     */
    public function index(Requests $request)
    {
        
        //if($request->session()->exists('orderID')) {

            //$orderID = session('orderID');
            $orderID = 151;
            $order = $this->order->find($orderID);

            //$restDescription = "Order:{$orderID} - {$order->email}";

            $config = new Authorizeconfig();
            $config = $config->getData();

            if($config->url_action==0){
                $acceptJS = "https://jstest.authorize.net/v3/AcceptUI.js";
                //$acceptJS = "https://jstest.authorize.net/v1/Accept.js";
                
            }else{
                $acceptJS = "https://js.authorize.net/v3/AcceptUI.js";
                //$acceptJS = "https://js.authorize.net/v1/Accept.js";
            }

            $apiLogin = $config->api_login;
            $clientKey = $config->client_key;

            $tpl = 'icommerceauthorize::frontend.index';

            return view($tpl, compact('acceptJS','apiLogin','clientKey','order'));

        /*
        }else{
            return redirect()->route('homepage');
        }
        */
       

    }

     /**
     * Send Information
     * @param Requests request
     * @return redirect
     */
    public function send($oval,$odes,Requests $request2){
        
        //Log::info('Authorize Response - Recibiendo Respuesta '.time());

       //if($request->session()->exists('orderID')) {

        //$orderID = session('orderID');
        $orderID = 151;
        $order = $this->order->find($orderID);

        $config = new Authorizeconfig();
        $config = $config->getData();

        try{

            $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();

            $merchantAuthentication->setName($config->api_login);
            $merchantAuthentication->setTransactionKey($config->transaction_key);

            // Set the transaction's refId
            $refId = $orderID."-".time();
            //$refId = 'ref' . time();

            $restDescription = "Order:{$orderID} - {$order->email}";

            // Create the payment object for a payment nonce
            $opaqueData = new AnetAPI\OpaqueDataType();
            $opaqueData->setDataDescriptor($odes);
            $opaqueData->setDataValue($oval);


            // Add the payment data to a paymentType object
            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setOpaqueData($opaqueData);

            // Create order information
            $orderInfor = new AnetAPI\OrderType();
            $orderInfor->setInvoiceNumber($refId);
            $orderInfor->setDescription($restDescription);

             // Set the customer's Bill To address
            $customerAddress = new AnetAPI\CustomerAddressType();
            $customerAddress->setFirstName($order->payment_firstname);
            $customerAddress->setLastName($order->payment_lastname);
            $customerAddress->setCompany($order->payment_company);
            $customerAddress->setAddress($order->payment_address_1);
            $customerAddress->setCity($order->payment_city);
            $customerAddress->setState($order->payment_zone);
            $customerAddress->setZip($order->payment_postcode);
            $customerAddress->setCountry($order->payment_country);

            // Set the customer's identifying information
            $customerData = new AnetAPI\CustomerDataType();
            $customerData->setType("individual");
            $customerData->setId($order->user_id);
            $customerData->setEmail($order->email);

            // Add values for transaction settings
            $duplicateWindowSetting = new AnetAPI\SettingType();
            $duplicateWindowSetting->setSettingName("duplicateWindow");
            $duplicateWindowSetting->setSettingValue("60");

           
            // Create a TransactionRequestType object and add the previous objects to it
            $transactionRequestType = new AnetAPI\TransactionRequestType();
            $transactionRequestType->setTransactionType("authCaptureTransaction"); 
            $transactionRequestType->setAmount($order->total);
            $transactionRequestType->setOrder($orderInfor);
            $transactionRequestType->setPayment($paymentOne);
            $transactionRequestType->setBillTo($customerAddress);
            $transactionRequestType->setCustomer($customerData);
            $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
           
             // Assemble the complete transaction request
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication($merchantAuthentication);
            $request->setRefId($refId);
            $request->setTransactionRequest($transactionRequestType);

             // Create the controller and get the response
            $controller = new AnetController\CreateTransactionController($request);
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);

            


        }catch(Exception $e){
            Log::info('Authorize Error:  Exception'.time());
             //echo $e->getMessage();
        }

        /*
        }else{
            return redirect()->route('homepage');
        }
        */
       

    }

    

}
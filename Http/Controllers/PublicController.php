<?php

namespace Modules\Icommerceauthorize\Http\Controllers;

// Requests & Response
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Base
use Modules\Core\Http\Controllers\BasePublicController;

// Base Api
use Modules\Icommerceauthorize\Http\Controllers\Api\IcommerceAuthorizeApiController;

// Repositories
use Modules\Icommerceauthorize\Repositories\IcommerceAuthorizeRepository;
use Modules\Icommerce\Repositories\PaymentMethodRepository;
use Modules\Icommerce\Repositories\TransactionRepository;
use Modules\Icommerce\Repositories\OrderRepository;
use Modules\Icommerce\Repositories\CurrencyRepository;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class PublicController extends BasePublicController
{
    
    private $icommerceauthorize;
    private $paymentMethod;
    private $order;
    private $transaction;
    private $currency;
    private $authorizeApiController;

    protected $urlSandbox;
    protected $urlProduction;

    public function __construct(
        IcommerceAuthorizeRepository $icommerceauthorize,
        PaymentMethodRepository $paymentMethod,
        OrderRepository $order,
        TransactionRepository $transaction,
        CurrencyRepository $currency,
        IcommerceAuthorizeApiController $authorizeApiController
    )
    {
        $this->icommerceauthorize = $icommerceauthorize;
        $this->paymentMethod = $paymentMethod;
        $this->order = $order;
        $this->transaction = $transaction;
        $this->currency = $currency;
        $this->authorizeApiController = $authorizeApiController;

        $this->urlSandbox = "https://jstest.authorize.net/v3/AcceptUI.js";
        $this->urlProduction = "https://js.authorize.net/v3/AcceptUI.js";
    }


    /**
     * Index data
     * @param Requests request
     * @return route
     */
    public function index($eURL){

        try {

            // Decr
            $infor = $this->icommerceauthorize->decriptUrl($eURL);
            $orderID = $infor[0];
            $transactionID = $infor[1];
            $currencyID = $infor[2];

            \Log::info('Module Icommerceauthorize: Index-ID:'.$orderID);
            
            // Validate get data
            $order = $this->order->find($orderID);
            $transaction = $this->transaction->find($transactionID);
            $currency = $this->currency->find($currencyID);

            $paymentName = config('asgard.icommerceauthorize.config.paymentName');

            // Configuration
            $attribute = array('name' => $paymentName);
            $paymentMethod = $this->paymentMethod->findByAttributes($attribute);


            if($paymentMethod->options->mode=="sandbox")
                $acceptJS = $this->urlSandbox;
            else
                $acceptJS = $this->urlProduction;

            $apiLogin = $paymentMethod->options->apilogin;
            $clientKey = $paymentMethod->options->clientkey;

            $tpl = 'icommerceauthorize::frontend.index';

            return view($tpl, compact('acceptJS','apiLogin','clientKey','order','transaction'));
           

        } catch (\Exception $e) {

            \Log::error('Module Icommerceauthorize-Index: Message: '.$e->getMessage());
            \Log::error('Module Icommerceauthorize-Index: Code: '.$e->getCode());

            //Message Error
            $status = 500;
            $response = [
              'errors' => $e->getMessage(),
              'code' => $e->getCode()
            ];

            //return response()->json($response, $status ?? 200);

            return redirect()->route("homepage");

        }
   
    }

     /**
     * Send Information
     * @param Requests request
     * @return redirect
     */
    public function send($orderID,$transactionID,$oval,$odes,Request $request2){
        
        $order = $this->order->find($orderID);
        $transaction = $this->transaction->find($transactionID);
        
        $paymentName = config('asgard.icommerceauthorize.config.paymentName');

        // Configuration
        $attribute = array('name' => $paymentName);
        $paymentMethod = $this->paymentMethod->findByAttributes($attribute);

         try{
 
             $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
 
             $merchantAuthentication->setName($paymentMethod->options->apilogin);
             $merchantAuthentication->setTransactionKey($paymentMethod->options->transactionkey);
 
             // Set the transaction's refId
             $refId = $order->id."-".$transaction->id;
            
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
             
             if($paymentMethod->options->mode=="sandbox")
                 $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
             else
                 $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
             
             
            return $this->authorizeApiController->response(new Request([
                    'response' => $response,
                    'orderID' => $order->id,
                    'transactionID' => $transaction->id,
                    'paymentMethodID' => $paymentMethod->id
            ]));
            
 
 
         }catch(Exception $e){

            \Log::error('Module Icommerceauthorize-Send: Message: '.$e->getMessage());
            \Log::error('Module Icommerceauthorize-Send: Code: '.$e->getCode());

         }
       
    }

   
}
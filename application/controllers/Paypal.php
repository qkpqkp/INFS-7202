<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'libraries/PayPal-PHP-SDK/paypal/rest-api-sdk-php/sample/bootstrap.php'; // require paypal files

use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payee;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\OpenIdSession;
use PayPal\Api\OpenIdTokeninfo;
use PayPal\Api\OpenIdUserinfo;
use PayPal\Exception\PayPalConnectionException;
class Paypal extends CI_Controller
{
    public $_api_context;

    function  __construct()
    {
        parent::__construct();
        $this->load->model('paypal_model', 'paypal');
        $this->load->model('login_database');
        // paypal credentials
        $this->config->load('paypal');

        $this->_api_context = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $this->config->item('client_id'), $this->config->item('secret')
            )
        );
    }

    function index(){
        $this->load->view('paypal/buy_form');
    }

    function get_consent(){
        $baseUrl = getBaseUrl() . '/Paypal/UserConsentRedirect?success=true';
        $redirectUrl = OpenIdSession::getAuthorizationUrl(
            $baseUrl,
            array('openid', 'email',
                'https://uri.paypal.com/services/invoicing'),
            $this->config->item('client_id'),
            null,
            null,
            $this->_api_context
        );
        redirect($redirectUrl,'refresh');
    }

    function UserConsentRedirect(){
        if (isset($_GET['success']) && $_GET['success'] == 'true') {
            if(array_key_exists('code',$_GET)){
                $code = $_GET['code'];
            } else{
                redirect('Auth/accountPage','refresh');
                return;
            }
            try {
                // Obtain Authorization Code from Code, Client ID and Client Secret
                $accessToken = OpenIdTokeninfo::createFromAuthorizationCode(array('code' => $code), $this->config->item('client_id'), $this->config->item('secret'), $this->_api_context);
                $this->getUserInfo($accessToken->refresh_token);
            } catch (PayPalConnectionException $ex) {
                // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY

                exit(1);
            }

        }
    }

    function getUserInfo($refreshToken){
        try {
            $tokenInfo = new OpenIdTokeninfo();
            $tokenInfo = $tokenInfo->createFromRefreshToken(array('refresh_token' => $refreshToken), $this->_api_context);

            $params = array('access_token' => $tokenInfo->getAccessToken());
            $userInfo = OpenIdUserinfo::getUserinfo($params, $this->_api_context);
            $email = $userInfo->getEmail();
            $username = $this->session->userdata['logged_in']['username'];
            $this->login_database->add_paypal($username,$email);
            $this->session->userdata['logged_in']['paypal_account']=$email;
            redirect('/Auth/accountPage');
        } catch (Exception $ex) {
            ResultPrinter::printError("User Information", "User Info", null, null, $ex);
            exit(1);
        }
    }

    function create_payment_with_paypal()
    {
        $this->_api_context->setConfig($this->config->item('settings'));
        $receiver = $this->input->post('receiver');
// ### Payer
// A resource representing a Payer that funds a payment
// For direct credit card payments, set payment method
// to 'credit_card' and add an array of funding instruments.
        $donate_amount=$this->input->post('donate_amount');
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");

// ### Itemized information
// (Optional) Lets you specify item wise
// information
        $item1 = new Item();
        $item1->setName($this->input->post('item_name'))
            ->setCurrency('AUD')
            ->setQuantity(1)
            ->setSku($this->input->post('item_number')) // Similar to `item_number` in Classic API
            ->setPrice($this->input->post('donate_amount'))
            ->setDescription($this->input->post('item_description'));
        $itemList = new ItemList();
        $itemList->setItems(array($item1));

// ### Additional payment details
// Use this optional field to set additional
// payment information such as tax, shipping
// charges etc.
        $details = new Details();
        $details->setTax($this->input->post('details_tax'))
            ->setSubtotal($this->input->post('donate_amount'));
// ### Amount
// Lets you specify a payment amount.
// You can also specify additional details
// such as shipping, tax.
        $amount = new Amount();
        $amount->setCurrency("AUD")
            ->setTotal($this->input->post('donate_amount'))
            ->setDetails($details);
// ### Transaction
// A transaction defines the contract of a
// payment - what is the payment for and who
// is fulfilling it.
        $payee = new Payee();
        $payee->setEmail($this->login_database->get_paypal_account($receiver));
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription("Payment description")
            ->setPayee($payee)
            ->setInvoiceNumber(uniqid());


        // ### Redirect urls
// Set the urls that the buyer must be redirected to after
// payment approval/ cancellation.
        $baseUrl = base_url();
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($baseUrl."paypal/getPaymentStatus")
            ->setCancelUrl($baseUrl."paypal/getPaymentStatus");

// ### Payment
// A Payment Resource; create one using
// the above types and intent set to sale 'sale'
        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));

        try {
            $payment->create($this->_api_context);
        } catch (Exception $ex) {
            echo "payment error";
            exit(1);
        }
        $approvalUrl = $payment->getApprovalLink();
        echo $approvalUrl;
        if(isset($approvalUrl)) {
            /** redirect to paypal **/
            redirect($approvalUrl);
        }
        $this->session->set_flashdata('success_msg','Unknown error occurred');
        redirect('paypal/index');


    }


    public function getPaymentStatus()
    {

        // paypal credentials

        /** Get the payment ID before session clear **/
        $payment_id = $this->input->get("paymentId") ;
        $PayerID = $this->input->get("PayerID") ;
        $token = $this->input->get("token") ;
        /** clear the session payment ID **/

        if (empty($PayerID) || empty($token)) {
            $this->session->set_flashdata('success_msg','Payment failed');
            redirect('paypal/index');
        }

        $payment = Payment::get($payment_id,$this->_api_context);


        /** PaymentExecution object includes information necessary **/
        /** to execute a PayPal account payment. **/
        /** The payer_id is added to the request query parameters **/
        /** when the user is redirected from paypal back to your site **/
        $execution = new PaymentExecution();
        $execution->setPayerId($this->input->get('PayerID'));

        /**Execute the payment **/
        $result = $payment->execute($execution,$this->_api_context);



        //  DEBUG RESULT, remove it later **/
        if ($result->getState() == 'approved') {
            $trans = $result->getTransactions();

            // item info
            $Subtotal = $trans[0]->getAmount()->getDetails()->getSubtotal();
            $Tax = $trans[0]->getAmount()->getDetails()->getTax();

            $payer = $result->getPayer();
            // payer info //
            $PaymentMethod =$payer->getPaymentMethod();
            $PayerStatus =$payer->getStatus();
            $PayerMail =$payer->getPayerInfo()->getEmail();

            $relatedResources = $trans[0]->getRelatedResources();
            $sale = $relatedResources[0]->getSale();
            // sale info //
            $saleId = $sale->getId();
            $CreateTime = $sale->getCreateTime();
            $UpdateTime = $sale->getUpdateTime();
            $State = $sale->getState();
            $Total = $sale->getAmount()->getTotal();
            /** it's all right **/
            /** Here Write your database logic like that insert record or value in database if you want **/
            $data['txn_id']=$saleId;
            $data['PaymentMethod']=$PaymentMethod;
            $data['PayerStatus']=$PayerStatus;
            $data['PayerMail']=$PayerMail;
            $data['Total']=$Total;
            $data['SubTotal']=$Subtotal;
            $data['Tax']=$Tax;
            $data['Payment_state']=$State;
            $data['CreateTime']=$CreateTime;
            $data['UpdateTime']=$UpdateTime;
            $this->paypal->save($data);
            $this->session->set_flashdata('success_msg','Payment success');
            redirect('paypal/success');
        }
        $this->session->set_flashdata('success_msg','Payment failed');
        redirect('paypal/cancel');
    }
    function success(){
        $this->load->view("paypal/success");
    }
    function cancel(){
        $this->paypal->create_payment();
        $this->load->view("paypel/cancel");
    }
}
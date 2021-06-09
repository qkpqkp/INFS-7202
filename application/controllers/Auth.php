<?php

require_once (APPPATH.'models/sendEmails.php');
Class Auth extends CI_Controller {
	public function __construct() {
	parent::__construct();
	    $this->load->library('form_validation');
	    $this->load->library('session');
	    $this->load->model('login_database');
        $this->load->helper('captcha');
	}

    public function register(){
        $username = $this->input->post("username");
        $password = $this->input->post("password");
        $email = $this->input->post("email");
        $data['username'] = $username;
        $data['password'] = password_hash($password,PASSWORD_DEFAULT);
        $data['Email'] = $email;
        $data['verified'] = false;
        $data['token'] = bin2hex(random_bytes(50));
        $result = $this->login_database->register($data);
        if($result=="username"){
            $data['failure'] = "username";
            $this->load->view('register_form',$data);
        }elseif($result=="email"){
            $data['failure'] = "email";
            $this->load->view('register_form',$data);
        }elseif($result=="both"){
            $data['failure'] = "both";
            $this->load->view('register_form',$data);
        } else{
            redirect('/Auth/loginform','refresh');
        }

    }

    public function verifyform(){
	    $Email = $_GET['email'];
	    $token = $_GET['token'];
        setcookie("Email", $Email, time() + 3600, "/");
        setcookie("token", $token, time() + 3600, "/");
	    redirect('/Auth/load_verifyform','refresh');
    }

    public function load_verifyform(){
        $this->load->view('verify_form');
    }
    public function verify(){
        $username = $this->input->post("username");
        $password = $this->input->post("password");
        $token = $this->input->post("token");
        $data['username'] = $username;
        $data['password'] = $password;
        $data['token'] = $token;
        if($this->login_database->verify($data)==True){
            $this->session->userdata['logged_in']['verified']=1;
        }
        redirect('/Auth/accountPage','refresh');
    }

    public function forgotform(){
	    $this->load->view('forgot_form');
    }
    public function forgot(){
        $Email = $this->input->post("email");
        if($this->login_database->forgot($Email)){
            echo "An email has been sent";
        }
    }
    public function resetform(){
        $Email = $_GET['email'];
        $token = $_GET['token'];
        if(!$this->login_database->check_token($token,$Email)){
            echo "Please provide a correct token";
            return;
        }
        setcookie("Email", $Email, time() + 3600, "/");
        setcookie("token", $token, time() + 3600, "/");
        redirect('/Auth/load_resetform','refresh');
    }

    public function load_resetform(){
        $this->load->view('reset_form');
    }
    public function reset(){
        $password = $this->input->post("password");
        $token = $this->input->post("token");
        $data['password'] = password_hash($password,PASSWORD_DEFAULT);
        $data['token'] = $token;
        echo $this->login_database->reset($data);
        redirect('/Auth/login','refresh');
    }
	public function login()
	{
	    $captcha  = $this->input->post('captcha');
	    $sessionCaptcha = $this->session->userdata('sessCaptcha');
	    if(strtolower($captcha) !== strtolower($sessionCaptcha)){
            $this->session->set_userdata('incorrect_captcha',true);
            $data['captcha'] = $this->getCaptcha()['image'];
            $this->load->view('login_form',$data);
            return;
	    }

		$username = $this->input->post("username");
		$password = $this->input->post("password");
        $data['username'] = $username;
        $data['password'] = $password;
		$row = $this->login_database->login($data);
		if($row=="password")
		{
            $data['captcha'] = $this->getCaptcha()['image'];
            $data['failure'] = "password";
            $this->load->view('login_form',$data);
		}elseif($row=="username"){
            $data['captcha'] = $this->getCaptcha()['image'];
            $data['failure'] = "username";
            $this->load->view('login_form',$data);
        } else {
            $data['Email'] = $row['Email'];
            $data['verified'] = $row['verified'];
            $data['token']=$row['token'];
            $data['paypal_account']=$row['paypal_account'];
            $this->session->set_userdata('logged_in',$data);
            $this->session->set_userdata('timestamp',time());
            $remember = $this->input->post("remember");
            if($remember=="checked"){
                setcookie ("member_login",$username,time()+(86400*365*10));
            }else{
                if(isset($_COOKIE["member_login"])) {
                    setcookie ("member_login","");
                }
            }
            redirect('/','refresh');
		}
	}

	public function logout(){
        session_destroy();
        session_unset();
        redirect('/','refresh');
    }

    public function sendEmail(){
        $data['Email'] = $_GET['email'];
        $data['token'] = $_GET['token'];
        $data['username'] = $_GET['username'];
        sendVerificationEmail($data,$this);
        echo "An email has been sent!";
    }

	public function registerform(){
	    $this->load->view('register_form');
    }
    private function getCaptcha(){
        $config = array(
            'img_path'      => 'captcha_images/',
            'img_url'       => base_url().'captcha_images/',
            'font_path'     => '/var/www/htdocs/system/fonts/texb.ttf',
            'img_width'     => '160',
            'img_height'    => 50,
            'word_length'   => 8,
            'font_size'     => 18
        );
        $captcha = create_captcha($config);
        $this->session->unset_userdata('sessCaptcha');
        $this->session->set_userdata('sessCaptcha', $captcha['word']);
        return $captcha;
    }
	public function loginform() {
        $data['captcha'] = $this->getCaptcha()['image'];
		$this->load->view('login_form',$data);
	}

	public function accountPage(){
        $this->load->view('header');
	    $this->load->view('account_page');
    }

    public function userPage(){
	    $user = $_GET['user'];
        setcookie("page_user", $user, time() + 86400, "/");
	    $this->load->view('header');
	    $this->load->view('User');
    }

    public function uploadpicture(){
        $config['allowed_types'] = 'jpg|png';
        $config['upload_path'] = './pictures/';
        $config['encrypt_name'] = true;
        $this->load->library('upload', $config);
        $this->upload->initialize($config);
        if(!$this->upload->do_upload('picture')){
            $this->load->view('upload_failed');
        }else {
            $upload_data = $this->upload->data();
            $location = base_url('pictures/') . $upload_data['file_name'];
            $user = $this->input->post('user');
            $this->login_database->upload_picture($location,$user);
            $this->load->view('upload_succeed');
        }
    }

    public function unlink_paypal(){
        $user=$this->session->userdata['logged_in']['username'];
        $this->login_database->remove_paypal($user);
        $this->session->userdata['logged_in']['paypal_account']=null;
    }

    public function refreshCaptcha(){
        $captcha = $this->getCaptcha();
        echo $captcha['image'];
    }

    public function map(){
	    $this->load->view('map');
    }
}
	



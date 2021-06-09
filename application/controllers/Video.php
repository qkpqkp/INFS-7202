<?php

Class Video extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
        $this->load->library('session');
        $this->load->model('Video_database');
        $this->load->model('Login_database');
    }

    public function search(){

        if(isset($_GET['keyword'])){
            $keyword =  $_GET['keyword'];
            $page = $_GET['page'];
            try {
                $search_type = $_GET['search_type'];
            }catch (Exception $e){
                $search_type = 1;
            }
            if($search_type!=1&&$search_type!=2){
                $search_type=1;
            }
            $query_data = $this->Video_database->search_video($keyword,$page,$search_type);
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $this->session->set_userdata('searchresult',$query_data);
            $this->load->view('header');
            $this->load->view('Results');
        }
    }

    public function uploadform(){
        $this->load->view('upload_form');
    }

    public function uploadvideo(){

        $countfiles = count($_FILES['videos']['name']);
        for($i=0;$i<$countfiles;$i++) {
            if(!empty($_FILES['videos']['name'][$i])) {
                $_FILES['video']['name'] = $_FILES['videos']['name'][$i];
                $_FILES['video']['type'] = $_FILES['videos']['type'][$i];
                $_FILES['video']['tmp_name'] = $_FILES['videos']['tmp_name'][$i];
                $_FILES['video']['error'] = $_FILES['videos']['error'][$i];
                $_FILES['video']['size'] = $_FILES['videos']['size'][$i];
                $config['allowed_types'] = 'mp4|ogg|webm';
                $config['upload_path'] = './videos/';
                $config['file_name'] = $_FILES['videos']['name'][$i];
                $config['encrypt_name'] = true;
                $this->load->library('upload', $config);
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('video')) {
                    $error = $this->upload->display_errors();
                    echo $error;
                    //$this->load->view('upload_failed', $error);
                } else {

                    $upload_data = $this->upload->data();
                    $data['videoname'] = $this->input->post("videoname");
                    $data['location'] = base_url('videos/') . $upload_data['file_name'];
                    $data['user'] = $this->session->userdata["logged_in"]["username"];
                    $data['description'] = $this->input->post("description");
                    date_default_timezone_set('Australia/Brisbane');
                    $data['time'] = time();
                    $this->Video_database->upload_video($data);
                }
            }
        }
        $this->load->view('upload_succeed');
    }

    function endsWith($string, $endString)
    {
        $len = strlen($endString);
        if ($len == 0) {
            return true;
        }
        return (substr($string, -$len) === $endString);
    }

    public function display()
    {
        $id= $this->input->get("id");
        $videodata = $this->Video_database->videoInfo($id);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->session->set_userdata('videodata',$videodata);
        $this->load->view('header');
        $this->load->view('Video');
    }
    public function like()
    {
        $id = $this->input->post("id");
        $user = $this->input->post("user");
        $this->Video_database->like($id,$user);
    }

    public function dislike(){
        $id = $this->input->post("id");
        $user = $this->input->post("user");
        $this->Video_database->dislike($id,$user);
    }
    public function delete(){
        $location = $this->input->post("location");
        $directory = '/var/www/htdocs/videos/';
        unlink($directory.end(explode("/",$location)));
        $this->Video_database->delete($location);
    }

    public function autoComplete(){
        $keyword=$this->input->post("keyword");
        try {
            $search_type = $this->input->post("searchtype");
        }catch (Exception $e){
            $search_type = 1;
        }
        if($search_type!=1&&$search_type!=2){
            $search_type=1;
        }
        $query = $this->Video_database->autoComplete($keyword,$search_type);
        $arr = array();
        if($search_type==1) {
            foreach ($query as $row) {
                array_push($arr, $row['videoname']);
            }
        }else{
            foreach ($query as $row) {
                array_push($arr, $row['user']);
            }
        }
        $result = array('result' => $arr);
        echo json_encode($result);

    }
}
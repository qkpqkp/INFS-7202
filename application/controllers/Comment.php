<?php


Class Comment extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Comment_database');
    }

    public function addComment(){
        $user = $this->input->post("user");
        $video = $this->input->post("video");
        $content = $this->input->post("content");
        $this->Comment_database->addComment($user,$video,$content);
        redirect(base_url("Video/display?id=").$video);
    }

    public function getComments(){
        $page = $this->input->post("page");
        $id = $this->input->post("id");
        $query= $this->Comment_database->getComments($id,$page);

        $content = array();
        $id = array();
        $time = array();
        $user = array();
        foreach($query as $row){
            array_push($content,$row['content']);
            array_push($id,$row['id']);
            array_push($time,date("Y-m-d",$row['time']));
            array_push($user,$row['user']);
        }
        $result = array('user' => $user,'content' => $content,'id' => $id, 'time' => $time);
        echo json_encode($result);
    }
}
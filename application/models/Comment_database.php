<?php


Class Comment_database extends CI_Model
{

    function __construct()
    {
    }

    function addComment($user,$video,$content){
        $data['user'] = $user;
        $data['video'] = $video;
        $data['content'] = $content;
        $data['time'] = time();
        $this->db->insert('comments',$data);
        if ($this->db->affected_rows() > 0) {
            return true;
        }
    }

    function getComments($id,$page){
        $total_query = $this->db->query("
              SELECT COUNT(*) FROM comments WHERE video=" . "'" . $id . "'" . ";
             ")->row_array();
        $total = $total_query['COUNT(*)'];
        $limit = 10;
        $pages = ceil($total / $limit);
        if($pages==0){
            $pages=1;
        }
        if($page<1){
            $page=1;
        }
        if($page>$pages){
            $page=$pages;
        }
        $offset = ($page - 1)  * $limit;
        $start = $offset + 1;
        $end = min(($offset + $limit), $total);
        $query = $this->db->query("
                SELECT * FROM comments  WHERE video= " . "'" . $id . "'" . " LIMIT $offset, $limit;
            ")->result_array();
        return $query;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Bryan Potts <pottspotts@gmail.com>
 * Date: 6/4/16
 * Time: 10:15 PM
 */

if ( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    header('Content-type: application/json');
    $api = new MessageBoard($_POST);
    error_log("API output..."); error_log(print_r($api,1));
    echo $api->return;
}

class MessageBoard {
    private $db;
    public $return;
    public function __construct($post) {
        error_log(print_r($post,1));
        $method = $post['method'];
        $params = $post['params'];
        $this->db = mysqli_connect('localhost','root','pass','tsc',3307);
        $r = json_encode($this->{$method}($params));
        $this->return = $r;
        error_log("Message Board API returning...");
        error_log(print_r($r,1));
    }

    private function newComment($p){
        $sql = "INSERT INTO `msgboard_comments` (`topic_id`,`content`,`created_by`) VALUES (?,?,0)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is",$p['topic_id'],$p['content']); // TODO: createdBy
        $r = $stmt->execute();
        if ($r === false) {
            error_log(print_r($r,1));
            error_log(print_r($stmt,1));
            trigger_error('Statement execute failed! ' . htmlspecialchars(mysqli_stmt_error($stmt)), E_USER_ERROR);
        }

        return (object)["status"=>"success", "comment_id"=>mysqli_insert_id($this->db)];
    }

    private function getComments($params){
        $topic_id = $params['topic_id']; // set the search id as the topic_id if no comment
        $comment_id = isset($params['comment_id']) ? $params['comment_id'] : false;
        if($comment_id){
            $sql = "SELECT * FROM `msgboard_comments` WHERE `id` = ? LIMIT 1";
            $id = $comment_id;
        } else {
            $sql = "SELECT * FROM `msgboard_comments` WHERE `topic_id` = ?";
            $id = $topic_id;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $R = [];
        $r = $stmt->get_result();
        while($row = $r->fetch_array(MYSQLI_ASSOC)){ $R[] = $row; }
        error_log(print_r($R,1));
        error_log(json_encode($R));
        return $R;
    }

    private function getTopics($params){
        if(isset($params['topics'])){
            $t = array_values($params['topics'])[0];
            $sql = "SELECT * FROM `msgboard_topics` where `id` = ?";

        } else {
            $id = $params['locator_id'];
            $type = $params['locator_type'];
            $sql = "SELECT * FROM `msgboard_topics` WHERE `locator_type` = ? AND `locator_id` = ?";
        }
        error_log($sql);
        //$r = $this->db->query($sql) or trigger_error($this->db->error."[$sql]");

        if($stmt = $this->db->prepare($sql)) {
            if(isset($params['topics'])) {
                $stmt->bind_param('i', $t);
            } else {
                $stmt->bind_param('si', $type, $id);
            }
            $stmt->execute();
        }

        $R = [];
        $r = $stmt->get_result();
        while($row = $r->fetch_array(MYSQLI_ASSOC)){ $R[] = $row; }
        error_log(print_r($R,1));
        error_log(json_encode($R));
        return $R;
    }
}

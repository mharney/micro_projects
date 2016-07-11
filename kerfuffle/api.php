<?php
/**
 * Created by PhpStorm.
 * User: Bryan Potts <pottspotts@gmail.com>
 * Date: 6/4/16
 * Time: 10:15 PM
 */

if ( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    header('Content-type: application/json');
    error_log(print_r($_POST,1));
    $api = new MessageBoard($_POST);
    error_log("API output..."); error_log(print_r($api,1));
    echo $api->return;
}

class MessageBoard {
    private $db;
    public $return;
    private $failed = ["status"=>"failed"];
    private $success = ["status"=>"success"];
    public function __construct($post) {
        error_log(print_r($post,1));
        $method = $post['method'];
        $params = $post['params'];
        $this->db = mysqli_connect('localhost','root','pass','tsc',3307);
        $r = json_encode($this->{$method}($params));
        $this->return = $r;
        // error_log("\nMessage Board API returning..."); error_log(print_r($r,1));
    }

    /**
     * @param $sql string   - The SQL delete statement
     * @param $id int       - The id to be deleted, matching the target table in the SQL
     * @return object bool  - Standard success/failed message
     */
    private function deleteByID($sql,$id){
        if($stmt = $this->db->prepare($sql)){
            $stmt->bind_param("i",$id);
            $r = $stmt->execute();
            if ($r === false) {
                trigger_error('Statement execute failed! ' . htmlspecialchars(mysqli_stmt_error($stmt)), E_USER_ERROR);
            }
            return (object) $this->success;
        } else {
            return (object) $this->failed;
        }
    }

    /**
     * @param $p['comment_id'] int  - The target comment to be deleted
     * @return object bool          - Standard success/failed message
     */
    private function deleteComment($p){
        return $this->deleteByID("DELETE FROM `msgboard_comments` WHERE id = ? LIMIT 1", $p['comment_id']);
    }

    /**
     * @param $p['name'] string         - Topic name
     * @param $p['locator_id'] int      - Topic locator (e.g. 745)
     * @param $p['locator_type'] string - Topic locator ID (e.g. section_id)
     * @param $p['created_by'] string   - User ID of creator
     * @return array|object             - Return the newly inserted topic details or success/fail (TODO: get the row...)
     */
    private function newTopic($p){
        $sql = "INSERT INTO `msgboard_topics` (`name`,`locator_id`,`locator_type`,`created_by`) VALUES (?,?,?,0)";

        if($stmt = $this->db->prepare($sql)){
            $stmt->bind_param("sis",$p['name'],$p['locator_id'],$p['locator_type']); // TODO: createdBy
            $r = $stmt->execute();
            if ($r === false) {
                trigger_error('Statement execute failed! ' . htmlspecialchars(mysqli_stmt_error($stmt)), E_USER_ERROR);
            }
            $topic_id = mysqli_insert_id($this->db);
            $new_comment_res = $this->newComment(["topic_id"=>$topic_id, "content"=>$p['comment']]);
            $new_comment_id = $new_comment_res->comment_id;
            return ["status"=>"success", "topic"=>["id"=>$topic_id, "name"=> $p['name'], "comment_id"=>$new_comment_id, "date_create"=>date("Y-m-d H:i:s")]];

        } else {
            error_log(print_r($this->db->error,1));
            return (object) $this->failed;
        }
    }

    /**
     * @param $p['topic_id'] int    - Topic ID of new comment
     * @param $p['content'] string  - Content of new comment
     * @return object               - success/failed message
     */
    private function newComment($p){
        $sql = "INSERT INTO `msgboard_comments` (`topic_id`,`content`,`created_by`) VALUES (?,?,0)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is",$p['topic_id'],$p['content']); // TODO: createdBy
        $r = $stmt->execute();
        if($r === false){
            trigger_error('Statement execute failed! ' . htmlspecialchars(mysqli_stmt_error($stmt)), E_USER_ERROR);
        }


        return (object)["status"=>"success", "comment_id"=>mysqli_insert_id($this->db)];
    }

    /**
     * @param $p['topic_id'] int - The Topic ID of the comments to show
     * @param $p['topic_id'] int - The Comment ID of the single new comment to return
     * @return array             - Matching comment(s) by topic or comment ID
     */
    private function updateComments($p){
        $topic_id = $p['topic_id']; // set the search id as the topic_id if no comment
        $comment_id = isset($p['comment_id']) ? $p['comment_id'] : false;

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
        return $R;
    }

    /**
     * Conditionally find either the single topic for a given page, or all topics by location
     *
     * @param $params['topics'])[0] int - Topic ID (grabbed from array) TODO: fix this array nonsense
     * @param $p['locator_id'] int      - Topic locator (e.g. 745)
     * @param $p['locator_type'] string - Topic locator ID (e.g. section_id)
     * @return array                    - Topic(s) matching Topic ID or location params
     */
    private function getTopics($params){
        if(isset($params['topics'])){
            $t = array_values($params['topics'])[0];
            $sql = "SELECT * FROM `msgboard_topics` where `id` = ?";

        } else {
            $id = $params['locator_id'];
            $type = $params['locator_type'];
            $sql = "SELECT * FROM `msgboard_topics` WHERE `locator_type` = ? AND `locator_id` = ?";
        }

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
        return $R;
    }
}

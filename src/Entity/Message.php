<?php
namespace Entity;


class Message{
    private $id;
    private $sender_id;
    private $content;
    private $is_read;
    private $created_at;
    private $username;




    public function __construct($sender_id, $content, $is_read, $created_at = null, $username = null, $id = null){
        $this->content = $content;
        $this->sender_id = $sender_id;
        $this->is_read = $is_read;
        $this->username = $username;
        $this->created_at = $created_at;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function getSenderId() { return $this->sender_id; }
    public function getContent() { return $this->content; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUsername() { return $this->username; }

    public function setContent($content) { $this->content = $content; }
}


?>
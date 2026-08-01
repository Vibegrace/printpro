<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    public $smtp_conn;
    public $error;
    public $helo_rply;
    public $server_caps;
    public $last_reply;

    public function connect($host, $port = 25, $options = [])
    {
        return true;
    }

    public function startTLS($options = [])
    {
        return true;
    }

    public function authenticate($username, $password, $authType = '', $oauthToken = null)
    {
        return true;
    }

    public function quit($closeConnection = true)
    {
        return true;
    }

    public function close()
    {
        return true;
    }
}
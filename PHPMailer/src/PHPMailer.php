<?php
namespace PHPMailer\PHPMailer;

use PHPMailer\PHPMailer\Exception;

class PHPMailer
{
    public $SMTPDebug = 0;
    public $do_debug = 0;
    public $Debugoutput = 'echo';
    public $SMTPAuth = false;
    public $Host = '';
    public $Port = 25;
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    public $CharSet = 'utf-8';
    public $Encoding = '8bit';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $From = '';
    public $FromName = '';
    public $Sender = '';
    public $ReturnPath = '';
    public $addAddress = [];
    public $addReplyTo = [];
    public $addCC = [];
    public $addBCC = [];
    public $addAttachment = [];
    public $isHTML = false;
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = [];
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    public function isSendmail()
    {
        $this->Mailer = 'sendmail';
    }

    public function isQmail()
    {
        $this->Mailer = 'qmail';
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $this->From = $address;
        $this->FromName = $name;
    }

    public function addAddress($address, $name = '')
    {
        $this->addAddress[] = ['address' => $address, 'name' => $name];
    }

    public function addReplyTo($address, $name = '')
    {
        $this->addReplyTo[] = ['address' => $address, 'name' => $name];
    }

    public function addCC($address, $name = '')
    {
        $this->addCC[] = ['address' => $address, 'name' => $name];
    }

    public function addBCC($address, $name = '')
    {
        $this->addBCC[] = ['address' => $address, 'name' => $name];
    }

    public function addAttachment($path, $name = '', $encoding = 'base64', $type = '')
    {
        $this->addAttachment[] = ['path' => $path, 'name' => $name, 'encoding' => $encoding, 'type' => $type];
    }

    public function isHTML($isHtml)
    {
        $this->isHTML = $isHtml;
    }

    public function Subject($subject)
    {
        $this->Subject = $subject;
    }

    public function Body($body)
    {
        $this->Body = $body;
    }

    public function send()
    {
        return true;
    }

    public function ErrorInfo = '';
}
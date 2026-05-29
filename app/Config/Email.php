<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    public string $fromEmail  = 'alert@functionapps.in';
    public string $fromName   = 'Functionapps Team';
    public string $recipients = '';
    public string $userAgent = 'PView Alert System';
    public string $protocol = 'smtp';
    public string $mailPath = '/usr/sbin/sendmail';
    public string $SMTPHost = 'mail.functionapps.in';
    public string $SMTPAuthMethod = 'login';
    public string $SMTPUser = 'alert@functionapps.in';
    public string $SMTPPass = '';
    public int    $SMTPPort = 587;
    public int    $SMTPTimeout = 5;
    public bool   $SMTPKeepAlive = false;
    // 'tls' = STARTTLS (port 587); 'ssl' = implicit SSL (port 465); '' = none
    public string $SMTPCrypto = 'tls';
    public bool   $wordWrap = true;
    public int    $wrapChars = 76;
    public string $mailType = 'html';
    public string $charset = 'UTF-8';
    public bool   $validate = false;
    public int    $priority = 3;
    public string $CRLF = "\r\n";
    public string $newline = "\r\n";
    public bool   $BCCBatchMode = false;
    public int    $BCCBatchSize = 200;
    public bool   $DSN = false;

    public function __construct()
    {
        parent::__construct();

        $this->fromEmail = (string) env('email.fromEmail', $this->fromEmail);
        $this->fromName = (string) env('email.fromName', $this->fromName);
        $this->recipients = (string) env('email.recipients', $this->recipients);
        $this->userAgent = (string) env('email.userAgent', $this->userAgent);
        $this->protocol = (string) env('email.protocol', $this->protocol);
        $this->mailPath = (string) env('email.mailPath', $this->mailPath);
        $this->SMTPHost = (string) env('email.SMTPHost', $this->SMTPHost);
        $this->SMTPAuthMethod = (string) env('email.SMTPAuthMethod', $this->SMTPAuthMethod);
        $this->SMTPUser = (string) env('email.SMTPUser', $this->SMTPUser);
        $this->SMTPPass = (string) env('email.SMTPPass', $this->SMTPPass);
        $this->SMTPPort = (int) env('email.SMTPPort', $this->SMTPPort);
        $this->SMTPTimeout = (int) env('email.SMTPTimeout', $this->SMTPTimeout);
        $this->SMTPKeepAlive = (bool) env('email.SMTPKeepAlive', $this->SMTPKeepAlive);
        $this->SMTPCrypto = (string) env('email.SMTPCrypto', $this->SMTPCrypto);
        $this->wordWrap = (bool) env('email.wordWrap', $this->wordWrap);
        $this->wrapChars = (int) env('email.wrapChars', $this->wrapChars);
        $this->mailType = (string) env('email.mailType', $this->mailType);
        $this->charset = (string) env('email.charset', $this->charset);
        $this->validate = (bool) env('email.validate', $this->validate);
        $this->priority = (int) env('email.priority', $this->priority);
        $this->CRLF = (string) env('email.CRLF', $this->CRLF);
        $this->newline = (string) env('email.newline', $this->newline);
        $this->BCCBatchMode = (bool) env('email.BCCBatchMode', $this->BCCBatchMode);
        $this->BCCBatchSize = (int) env('email.BCCBatchSize', $this->BCCBatchSize);
        $this->DSN = (bool) env('email.DSN', $this->DSN);
    }
}

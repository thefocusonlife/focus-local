<?php
namespace PhpBook\Email;

class Email
{
    protected $phpmailer;

    public function __construct($email_config)
    {
        $this->phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $this->phpmailer->isSMTP();
        $this->phpmailer->SMTPAuth = true;

        $this->phpmailer->Host = $email_config['server'];
        $this->phpmailer->Port = (int)$email_config['port'];
        $this->phpmailer->Username = $email_config['username'];
        $this->phpmailer->Password = $email_config['password'];
        $this->phpmailer->SMTPDebug = (int)($email_config['debug'] ?? 0);
        $this->phpmailer->Debugoutput = function ($str, $level) {
            error_log("[SMTP][$level] $str");
        };

        $security = strtoupper($email_config['security'] ?? '');
        if ($security === 'SSL') {
            $this->phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($security === 'TLS') {
            $this->phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->phpmailer->SMTPSecure = '';
        }

        $this->phpmailer->CharSet = 'UTF-8';
        $this->phpmailer->isHTML(true);
    }

    public function sendEmail($from, $to, $subject, $message): bool
    {
        try {
            $this->phpmailer->clearAddresses();
            $this->phpmailer->clearReplyTos();

            $this->phpmailer->setFrom($from, 'The Focus On Life');
            $this->phpmailer->addReplyTo('contact@thefocusonlife.org', 'The Focus On Life');
            $this->phpmailer->addAddress($to);
            $this->phpmailer->Subject = $subject;
            $this->phpmailer->Body = '<!DOCTYPE html><html lang="en-us"><body>' . $message . '</body></html>';
            $this->phpmailer->AltBody = strip_tags($message);

            return $this->phpmailer->send();
        } catch (\Throwable $e) {
            error_log('[EMAIL] PHPMailer send failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
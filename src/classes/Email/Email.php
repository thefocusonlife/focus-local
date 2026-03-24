<?php
namespace PhpBook\Email; // Declare namespace

class Email
{
    protected $phpmailer; // PHPMailer object

    public function __construct($email_config)
    {
        $this->phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true); // Create PHPMailer
        $this->phpmailer->isSMTP(); // Use SMTP
        $this->phpmailer->SMTPAuth = true; // Authentication on
        $this->phpmailer->Host = $email_config['server']; // Server address
        // Set encryption based on config: 'SSL' (port 465) or 'TLS' (port 587)
        $security = strtolower($email_config['smtp_encryption'] ?? '');
        if ($security === 'ssl') {
        $this->phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($security === 'tls') {
            $this->phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
             $this->phpmailer->SMTPSecure = '';
        }
        $this->phpmailer->Host = $email_config['smtp_host'];
        $this->phpmailer->Port = (int)$email_config['smtp_port'];
        $this->phpmailer->Username = $email_config['smtp_username'];
        $this->phpmailer->Password = $email_config['smtp_password'];
        $this->phpmailer->SMTPDebug = $email_config['debug'] ?? 0;
    }

    public function sendEmail($from, $to, $subject, $message): bool
    {
        $this->phpmailer->setFrom(
        $this->phpmailer->Username,
        'The Focus On Life'
        );

        $this->phpmailer->addReplyTo(
        'contact@thefocusonlife.org',
        'The Focus On Life'
    );
        $this->phpmailer->addAddress($to); // To email address
        $this->phpmailer->Subject = $subject; // Subject of email
        $this->phpmailer->Body =
            '<!DOCTYPE html><html lang="en-us"><body>' . $message . '</body></html>'; // Body of email
        $this->phpmailer->AltBody = strip_tags($message); // Plain text body
        return $this->phpmailer->send();
    }
}

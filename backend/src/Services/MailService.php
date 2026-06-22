<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailService
{
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USER'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME'] ?? 'Health Platform');
        $mail->CharSet    = 'UTF-8';
        return $mail;
    }

    public function sendPasswordReset(string $to, string $name, string $token): void
    {
        $resetUrl = rtrim($_ENV['APP_URL'], '/') . '/reset-password?token=' . $token;

        $this->send(
            $to,
            $name,
            'Password Reset Request',
            $this->template('password_reset', ['name' => $name, 'reset_url' => $resetUrl, 'expires_in' => '1 hour'])
        );
    }

    public function sendWelcome(string $to, string $name, string $tempPassword): void
    {
        $this->send(
            $to,
            $name,
            'Welcome to Health Platform',
            $this->template('welcome', ['name' => $name, 'email' => $to, 'temp_password' => $tempPassword, 'app_url' => $_ENV['APP_URL']])
        );
    }

    public function sendSubmissionReminder(string $to, string $name, string $formName, string $deadline): void
    {
        $this->send(
            $to,
            $name,
            "Reminder: $formName submission due $deadline",
            $this->template('submission_reminder', ['name' => $name, 'form_name' => $formName, 'deadline' => $deadline])
        );
    }

    private function send(string $to, string $toName, string $subject, string $body): void
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to, $toName);
            $mail->Subject  = $subject;
            $mail->isHTML(true);
            $mail->Body     = $body;
            $mail->AltBody  = strip_tags($body);
            $mail->send();
        } catch (\Exception $e) {
            error_log('Mail send failed: ' . $e->getMessage());
            // Do not throw — mail failures should not break flows
        }
    }

    private function template(string $name, array $vars): string
    {
        $path = __DIR__ . '/../Views/emails/' . $name . '.html';
        $html = file_exists($path) ? file_get_contents($path) : $this->defaultTemplate($name, $vars);

        foreach ($vars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', htmlspecialchars((string) $value, ENT_QUOTES), $html);
        }

        return $html;
    }

    private function defaultTemplate(string $name, array $vars): string
    {
        $content = match ($name) {
            'password_reset'     => "Hello {{name}},<br><br>Click <a href=\"{{reset_url}}\">here</a> to reset your password. This link expires in {{expires_in}}.",
            'welcome'            => "Welcome {{name}},<br><br>Your account has been created. Login at <a href=\"{{app_url}}\">{{app_url}}</a><br>Temporary password: <strong>{{temp_password}}</strong>",
            'submission_reminder'=> "Hello {{name}},<br><br>Reminder: The form <strong>{{form_name}}</strong> is due by {{deadline}}.",
            default              => 'Hello {{name}},<br><br>You have a notification from Health Platform.',
        };

        return '<html><body style="font-family:Arial,sans-serif;padding:20px;">' .
               '<div style="max-width:600px;margin:auto;">' .
               '<h2 style="color:#1a5276;">Health Platform</h2><hr>' .
               "<p>$content</p>" .
               '<hr><p style="font-size:12px;color:#888;">This is an automated message. Please do not reply.</p>' .
               '</div></body></html>';
    }
}

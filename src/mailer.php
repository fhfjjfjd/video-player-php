<?php

declare(strict_types=1);

/*
 * mailer.php — outbound email for the PHP backend.
 *
 * Sends the registration verification code via SMTP using PHPMailer.
 * SMTP is configured through environment variables (MAIL_HOST, MAIL_PORT,
 * MAIL_USER, MAIL_PASS, MAIL_FROM, MAIL_ENCRYPTION). Registration requires
 * SMTP to be configured; there is no dev-mode fallback that leaks the code.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function send_verification_email(string $toEmail, string $code): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = smtp_host();
        $mail->Port       = smtp_port();
        $mail->SMTPAuth   = true;
        $mail->Username   = smtp_user();
        $mail->Password   = smtp_pass();
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = 0;
        $mail->Timeout    = 30;
        $mail->SMTPSecure = smtp_encryption() === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        $from = smtp_from() !== '' ? smtp_from() : smtp_user();
        $mail->setFrom($from, 'Video Player');
        $mail->addAddress($toEmail);
        $mail->isHTML(false);
        $mail->Subject = 'Mã xác thực đăng ký Video Player';

        $body  = "Xin chào,\n\n";
        $body .= "Mã xác thực đăng ký tài khoản Video Player của bạn là: $code\n\n";
        $body .= "Mã có hiệu lực trong 10 phút. Nếu bạn không đăng ký tài khoản, hãy bỏ qua email này.\n";
        $mail->Body = $body;

        return $mail->send();
    } catch (PHPMailerException $e) {
        return false;
    }
}

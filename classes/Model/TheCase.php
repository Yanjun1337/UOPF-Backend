<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\Exception;
use UOPF\ModelFieldType;
use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use UOPF\Exception\EmailSendingException;
use UOPF\Exception\EnvironmentVariableException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

final class TheCase extends Model {
    public function isExpired(int $timeout): bool {
        return ($this->data['modified'] + $timeout) < time();
    }

    public function sendValidationCode(): void {
        if (!isset($this->data['metadata']['email']))
            throw new Exception('No email to send validation code.');

        if (!isset($this->data['metadata']['code']))
            throw new Exception('No validation code to send.');

        static::sendValidationCodeEmail(
            $this->data['type'],
            $this->data['metadata']['email'],
            $this->data['metadata']['code']
        );
    }

    protected static function sendValidationCodeEmail(string $type, string $to, string $code): void {
        $subjectTemplate = SystemMetadataManager::get("email/{$type}/subject");
        $bodyTemplate = SystemMetadataManager::get("email/{$type}/body");

        if (
            !isset($subjectTemplate) ||
            strlen(trim($subjectTemplate)) <= 0 ||

            !isset($bodyTemplate) ||
            strlen(trim($bodyTemplate)) <= 0
        )
            throw new Exception("Missing email template for `{$type}`.");

        $variables = [
            '{code}' => $code
        ];

        $keywords = array_keys($variables);
        $replacements = array_values($variables);

        $subject = str_replace($keywords, $replacements, $subjectTemplate);
        $body = str_replace($keywords, $replacements, $bodyTemplate);

        static::sendEmail($to, $subject, $body);
    }

    protected static function sendEmail(string $to, string $subject, string $body): void {
        if (!isset($_ENV['UOPF_SMTP_HOSTNAME']))
            throw new EnvironmentVariableException('SMTP server is required.', 'UOPF_SMTP_HOSTNAME');

        if (!isset($_ENV['UOPF_SMTP_USERNAME']))
            throw new EnvironmentVariableException('SMTP username is required.', 'UOPF_SMTP_USERNAME');

        if (!isset($_ENV['UOPF_SMTP_PASSWORD']))
            throw new EnvironmentVariableException('SMTP password is required.', 'UOPF_SMTP_PASSWORD');

        if (!isset($_ENV['UOPF_SMTP_SECURE']))
            throw new EnvironmentVariableException('SMTP encryption mode is required.', 'UOPF_SMTP_SECURE');

        if (!isset($_ENV['UOPF_SMTP_PORT']))
            throw new EnvironmentVariableException('SMTP port is required.', 'UOPF_SMTP_PORT');

        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = $_ENV['UOPF_SMTP_HOSTNAME'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $_ENV['UOPF_SMTP_USERNAME'];
            $mailer->Password = $_ENV['UOPF_SMTP_PASSWORD'];
            $mailer->SMTPSecure = $_ENV['UOPF_SMTP_SECURE'];
            $mailer->Port = $_ENV['UOPF_SMTP_PORT'];

            $from = $_ENV['UOPF_SMTP_USERNAME'];
            $name = 'UOPF'; // @TODO

            $mailer->setFrom($from, $name);
            $mailer->addReplyTo($from, $name);
            $mailer->addAddress($to);

            $mailer->Subject = $subject;
            $mailer->Body = $body;

            $mailer->send();
        } catch (MailerException $exception) {
            throw new EmailSendingException('Failed to send email via SMTP.', previous: $exception);
        }
    }

    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'user' => ModelFieldType::integer,
            'tag' => ModelFieldType::string,
            'created' => ModelFieldType::time,
            'modified' => ModelFieldType::time,
            'type' => ModelFieldType::string,
            'status' => ModelFieldType::string,
            'metadata' => ModelFieldType::serialized
        ];
    }
}

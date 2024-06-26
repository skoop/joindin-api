<?php

namespace Joindin\Api\Service;

use Exception;
use Michelf\Markdown;
use Swift_Mailer;
use Swift_Message;
use Swift_SmtpTransport;

/**
 * Base Email Class
 *
 * This class provides a base for different email implementations
 *
 * @author Kim Rowan
 */
abstract class BaseEmailService
{
    /**
     * The SwiftMailer object
     */
    protected Swift_Mailer $mailer;

    /**
     * The SwiftMessage object
     */
    protected Swift_Message $message;

    protected array $recipients;

    /**
     * Template path, can be changed when testing
     */
    public string $templatePath = __DIR__ . '/../View/emails/';

    /**
     * Make a message to be sent later
     *
     * @param array $config     The system config
     * @param array $recipients An array of email addresses
     *
     * @throws Exception
     */
    public function __construct(array $config, array $recipients)
    {
        if (!isset($config['email']['smtp'])) {
            throw new Exception("SMTP Server not properly set up.");
        }

        $transport = new Swift_SmtpTransport(
            $config['email']['smtp']['host'],
            $config['email']['smtp']['port'],
            $config['email']['smtp']['security']
        );

        $transport
            ->setUsername($config['email']['smtp']['username'])
            ->setPassword($config['email']['smtp']['password']);

        $this->mailer  = new Swift_Mailer($transport);
        $this->message = new Swift_Message();

        if (
            isset($config['email']['forward_all_to'])
            && ! empty($config['email']['forward_all_to'])
        ) {
            $this->recipients = [$config['email']['forward_all_to']];
        } else {
            $this->recipients = $recipients;
        }

        $this->message->setFrom($config['email']['from']);
    }

    /**
     * Take the template and the replacements, return markdown
     * with the correct values in it
     *
     * @param string $templateName
     * @param array  $replacements
     *
     * @return string
     */
    public function parseEmail(string $templateName, array $replacements): string
    {
        $template = file_get_contents($this->templatePath . $templateName)
                    . file_get_contents($this->templatePath . 'signature.md');

        $message = $template;

        foreach ($replacements as $field => $value) {
            $message = str_replace('[' . $field . ']', $value, $message);
        }

        return $message;
    }

    /**
     * Set the body of the message
     *
     * @param string $body
     *
     * @return $this
     */
    protected function setBody(string $body): static
    {
        $this->message->setBody($body);

        return $this;
    }

    /**
     * Set the HTML body of the message
     *
     * Call setBody first
     *
     * @param string $body
     *
     * @return $this
     */
    protected function setHtmlBody(string $body): static
    {
        $this->message->addPart($body, 'text/html');

        return $this;
    }

    /**
     * Send the email that we created
     */
    protected function dispatchEmail(): void
    {
        foreach ($this->recipients as $to) {
            $this->message->setTo($to);
            $this->mailer->send($this->message);
        }
    }

    /**
     * Set the subject line of the email
     *
     * @param string $subject
     */
    protected function setSubject(string $subject): void
    {
        $this->message->setSubject($subject);
    }

    /**
     * Set the reply to header
     *
     * @param array $email
     */
    protected function setReplyTo(array $email): void
    {
        $this->message->setReplyTo($email);
    }

    /**
     * Get recipients list to check it
     *
     * @return array
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * Markdown to HTML
     *
     * @param string $markdown
     *
     * @return string
     */
    public function markdownToHtml(string $markdown): string
    {
        $messageHTML = Markdown::defaultTransform($markdown);

        return $messageHTML;
    }

    /**
     * @param string $html
     *
     * @return string
     */
    public function htmlToPlainText(string $html): string
    {
        return strip_tags($html);
    }
}

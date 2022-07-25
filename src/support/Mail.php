<?php

namespace Webman\support;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use support\Log;

class Mail
{
    /**
     * @var PHPMailer
     */
    protected static $instance;

    /**
     * @return void
     */
    public static function setInstance()
    {
        $config = config('mail');

        self::$instance = new PHPMailer(true);

        switch (env('MAIL_MAILER' , 'smtp')) {
            case 'smtp':
                self::$instance->isSMTP();
                if ($config['username'] != null && $config['password'] != null)
                    self::$instance->SMTPAuth = true;
                self::$instance->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                break;
            case 'mail':
                self::$instance->isMail();
                break;
            case 'sendmail':
                self::$instance->isSendmail();
                break;
            case 'qmail':
                self::$instance->isQmail();
                break;
        }

        self::$instance->Host = $config['host'];
        self::$instance->Username = $config['username'];
        self::$instance->Password = $config['password'];
        self::$instance->Port = $config['port'];

        try {
            self::$instance->setFrom(env('MAIL_FROM_ADDRESS', 'hello@example.com'), env('MAIL_FROM_NAME', 'Example'));
        } catch (Exception $e) {
            (new Log())->channel('mailer')->error($e->getMessage());
        }
    }

    /**
     * @param string|array $address
     * @return $this
     */
    public function to($address): self
    {
        if (is_string($address))
            try {
                self::$instance->addAddress($address);
            } catch (Exception $e) {
                (new Log())->channel('mailer')->error($e->getMessage());
            }
        else
            foreach ($address as $value){
                if (is_string($value))
                    try {
                        self::$instance->addAddress($value);
                    } catch (Exception $e) {
                        (new Log())->channel('mailer')->error($e->getMessage());
                    }

                if (is_array($value) && isset($value['address']) && isset($value['name']))
                    try {
                        self::$instance->addAddress($value['address'], $value['name']);
                    } catch (Exception $e) {
                        (new Log())->channel('mailer')->error($e->getMessage());
                    }
            }

        return $this;
    }

    /**
     * @param string|array $address
     * @return $this
     */
    public function replyTo($address): self
    {
        if (is_string($address))
            try {
                self::$instance->addReplyTo($address);
            } catch (Exception $e) {
                (new Log())->channel('mailer')->error($e->getMessage());
            }
        else
            foreach ($address as $value){
                if (is_string($value))
                    try {
                        self::$instance->addReplyTo($value);
                    } catch (Exception $e) {
                        (new Log())->channel('mailer')->error($e->getMessage());
                    }

                if (is_array($value) && isset($value['address']) && isset($value['name']))
                    try {
                        self::$instance->addReplyTo($value['address'], $value['name']);
                    } catch (Exception $e) {
                        (new Log())->channel('mailer')->error($e->getMessage());
                    }
            }

        return $this;
    }

    /**
     * @param string|array $address
     * @return $this
     */
    public function cc($address): self
    {
        if (is_string($address))
            try {
                self::$instance->addCC($address);
            } catch (Exception $e) {
                (new Log())->channel('mailer')->error($e->getMessage());
            }
        else
            foreach ($address as $value){
                if (is_string($value))
                    try {
                        self::$instance->addCC($value);
                    } catch (Exception $e) {
                        (new Log())->channel('mailer')->error($e->getMessage());
                    }

                if (is_array($value) && isset($value['address']) && isset($value['name']))
                    try {
                        self::$instance->addCC($value['address'], $value['name']);
                    } catch (Exception $e) {
                        (new Log())->channel('mailer')->error($e->getMessage());
                    }
            }

        return $this;
    }

    /**
     * @param string|array $address
     * @return $this
     */
    public function bcc($address): self
    {
        if (is_string($address))
            try {
                self::$instance->addBCC($address);
            } catch (Exception $e) {
                (new Log())->channel('mailer')->error($e->getMessage());
            }
        else
            foreach ($address as $value){
                if (is_string($value))
                    try {
                        self::$instance->addBCC($value);
                    } catch (Exception $e) {
                        (new Log())->channel('mailer')->error($e->getMessage());
                    }

                if (is_array($value) && isset($value['address']) && isset($value['name']))
                    try {
                        self::$instance->addBCC($value['address'], $value['name']);
                    } catch (Exception $e) {
                        (new Log())->channel('mailer')->error($e->getMessage());
                    }
            }

        return $this;
    }

    /**
     * @param string|array $file
     * @return $this
     */
    public function withFile($file): self
    {
        if (is_string($file))
            try {
                self::$instance->addAttachment($file);
            } catch (Exception $e) {
                (new Log())->channel('mailer')->error($e->getMessage());
            }
        else
            foreach ($file as $value)
                try {
                    self::$instance->addAttachment($value);
                } catch (Exception $e) {
                    (new Log())->channel('mailer')->error($e->getMessage());
                }

        return $this;
    }

    /**
     * @param string $subject
     * @return $this
     */
    public function subject(string $subject): self
    {
        self::$instance->Subject = $subject;

        return $this;
    }

    /**
     * @param string $body
     * @return $this
     */
    public function body(string $body): self
    {
        self::$instance->Body = $body;

        return $this;
    }

    /**
     * @param string $altBody
     * @return $this
     */
    public function altBody(string $altBody): self
    {
        self::$instance->AltBody = $altBody;

        return $this;
    }

    /**
     * @return void
     */
    public function send()
    {
        self::$instance->isHTML();

        try {
            self::$instance->send();
        } catch (Exception $e) {
            (new Log())->channel('mailer')->error($e->getMessage());
        }
    }
}
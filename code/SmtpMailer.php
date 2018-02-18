<?php
/**
 * SilverStripe-PhpMailer
 * @category smtp/code
 * @author gelysis <andreas@gelysis.net>
 * @copyright ©2017 Andreas Gerhards - All rights reserved
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please check LICENSE.md for more information
 */

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;


$phpMailerPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'phpmailer'.DIRECTORY_SEPARATOR;
require_once($phpMailerPath.'Exception.php');
require_once($phpMailerPath.'PHPMailer.php');
require_once($phpMailerPath.'SMTP.php');


class SmtpMailer extends Mailer
{

    /** @var int $this->sendDelaySeconds  Used for throttling (useful on some services like AWS SES). */
    private $sendDelaySeconds = 2;
    /** @var PHPMailer|null $this->mailer */
    public $mailer = null;


    /**
     * @param PHPMailer|null $mailer
     */
    public function __construct($mailer = null)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    /**
     * Instantiate SmtpMailer
     */
    protected function instantiate()
    {
        $this->sendDelaySeconds = defined('SMTP_SEND_DELAY_SECONDS')
            ? SMTP_SEND_DELAY_SECONDS
            : $this->sendDelaySeconds;

        if (is_null($this->mailer)) {
            $this->mailer = new PHPMailer(true);
            $this->mailer->IsSMTP();

            $this->mailer->Host = defined('SMTP_SERVER_ADDRESS')
                ? SMTP_SERVER_ADDRESS : "localhost";
            $this->mailer->SMTPAuth = defined('SMTP_DO_AUTHENTICATE')
                ? SMTP_DO_AUTHENTICATE : false;

            if ($this->mailer->SMTPAuth) {
                $this->mailer->Username = defined('SMTP_USERNAME')
                    ? SMTP_USERNAME : "username";
                $this->mailer->Password = defined('SMTP_PASSWORD')
                    ? SMTP_PASSWORD : "password";
            }

            $this->mailer->Port = defined('SMTP_SERVER_PORT')
                ? SMTP_SERVER_PORT : 25;
            $this->mailer->SMTPSecure = defined('SMTP_USE_SECURE_CONNECTION')
                ? strtolower(SMTP_USE_SECURE_CONNECTION) : '';
            $this->mailer->CharSet = defined('SMTP_CHARSET_ENCODING')
                ? SMTP_CHARSET_ENCODING : "utf-8";
            $this->mailer->SMTPDebug = defined('SMTP_DEBUG_MESSAGING_LEVEL')
                ? SMTP_DEBUG_MESSAGING_LEVEL : 0;
            $this->mailer->SetLanguage(defined('SMTP_LANGUAGE_OF_MESSAGES')
                ? SMTP_LANGUAGE_OF_MESSAGES : 'en');
            $this->mailer->ErrorLevel = defined('SMTP_ERROR_LEVEL')
                ? SMTP_ERROR_LEVEL : E_USER_ERROR;
        }
    }


    /**
     * {@inheritDoc}
     *  @param string $to
     *  @param string $from
     *  @param string $subject
     *  @param string $plainContent
     *  @param array $attachedFiles
     *  @param array $customHeaders
     *  @return string[]|false $sendResponse
     * @see Mailer::sendPlain()
     */
    public function sendPlain($to, $from, $subject, $plainContent, $attachedFiles = [], $customHeaders = [])
    {
        $this->instantiate();
        $this->mailer->IsHTML(false);
        $this->mailer->Body = $plainContent;

        return $this->sendMailViaSmtp($to, $from, $subject, $attachedFiles, $customHeaders);
    }


    /* Overwriting SilverStripe's Mailer's function */
    /**
     * {@inheritDoc}
     * @param string $to
     * @param string $from
     * @param string $subject
     * @param string $htmlContent
     * @param array $attachedFiles
     * @param array $customHeaders
     * @param string $plainContent
     * @todo Check usefulness of the current $inlineImages parameter functionality
     * @param array $inlineImages
     * @return string[]|false $sendResponse
     * @see Mailer::sendHtml()
     */
    public function sendHTML($to, $from, $subject, $htmlContent, $attachedFiles = [], $customHeaders = [],
        $plainContent = '', $inlineImages = [])
    {
        $this->instantiate();
        $this->mailer->IsHTML(true);
        if ($inlineImages) {
            // Inline images hav to be located in the base folder
            $this->mailer->MsgHTML($htmlContent, Director::baseFolder());
        }else {
            $this->mailer->Body = $htmlContent;
            if(empty($plainContent)){
                $plainContent = trim(Convert::html2raw($htmlContent));
            }
            $this->mailer->AltBody = $plainContent;
        }
        return $this->sendMailViaSmtp($to, $from, $subject, $attachedFiles, $customHeaders);
    }


    /**
     * @param string $to
     * @param string $from
     * @param string $subject
     * @param array $attachedFiles
     * @param array $customHeaders
     * @return string[]|false $sendResponse
     */
    protected function sendMailViaSmtp($to, $from, $subject, array $attachedFiles, array $customHeaders)
    {
        if ($this->mailer->SMTPDebug > 0) {
            /** @todo Check this logic and message */
            echo "<em><strong>*** Debug mode is on</strong>, printing debug messages and not redirecting to the website:</em><br/>";
            echo "To: $to, From: $from, Subject: $subject<br>";
        }
        $logMessage = "\n".'Smtp email send.'."\n".'Sender: $from'."\n".'Message: "'.($this->mailer->AltBody).'"';

        try {
            $this->buildBasicMail($to, $from, $subject);
            $this->attachFiles($attachedFiles);
            // Add the current domain for services like SendGrid
            $customHeader['X-SMTPAPI'] = '{"category": "'.$_SERVER['HTTP_HOST'].'"}';
            $this->addCustomHeader($customHeaders);

            if ($this->sendDelaySeconds > 0) {
                $delayInMicroseconds = $this->sendDelaySeconds * 1000000;
                usleep($delayInMicroseconds);
            }

            $this->mailer->Send();

            if ($this->mailer->SMTPDebug > 0) {
                /** @todo Check this logic and message */
                echo "<em><strong>*** E-mail to $to has been sent.</strong></em><br />";
                echo "<em><strong>*** The debug mode blocked the process</strong> to avoid the url redirection. So the CC e-mail is not sent.</em>";
                die();
            }

            $bounceAddress = $this->getBounceEmail();
            $bounceAddress = (is_string($bounceAddress) && strlen($bounceAddress) > 0 ? $bounceAddress : $from);
            $send = [$to, $subject, $this->mailer->Body, $customHeader, $bounceAddress];

        }catch (Exception $exception) {
            $this->handleError($exception->getMessage(), $logMessage);
            // @todo  Follow up with Silverstripe because standard functionaly does not have a consistent return type
            $send = false;
        }

        return $send;
    }


    /**
     * @param string $exceptionMessage
     * @param string $logMessage
     */
    function handleError($exceptionMessage, $logMessage)
    {
        $message = $exceptionMessage." \r\n".$logMessage;
        user_error($message, $this->mailer->ErrorLevel);
    }

    /**
     * @param string $to
     * @param string $from
     * @param string $subject
     */
    protected function buildBasicMail($to, $from, $subject)
    {
        $filterOutNamePattern = '#(\'|")(.*?)\1[ ]+<[ ]*(.*?)[ ]*>#';
        if (preg_match($filterOutNamePattern, $from, $nameAndEmail)) {
            $this->mailer->SetFrom($nameAndEmail[3], $nameAndEmail[2]);
        }else {
            $this->mailer->SetFrom($from);
        }

        $this->mailer->ClearAddresses();
        if (preg_match($filterOutNamePattern, $to, $nameAndEmail)) {
            $this->mailer->AddAddress($nameAndEmail[3], $nameAndEmail[2]);
        }else {
            $name = ucfirst(strstr($to, '@'));
            $this->mailer->AddAddress($to, $name);
        }

        $this->mailer->Subject = $subject;
    }

    /**
     * @param array $headers
     */
    protected function addCustomHeader(array $headers)
    {
        if (!is_array($headers)) {
            $headers = [];
        }
        if (!isset($headers["X-Mailer"])) {
            $headers["X-Mailer"] = X_MAILER;
        }
        if (!isset($headers["X-Priority"])) {
            $headers["X-Priority"] = 3;
        }
        $this->mailer->ClearCustomHeaders();

        foreach ($headers as $headerName=>$headerValue) {
            if(in_array(strtolower($headerName), ['cc', 'bcc', 'reply-to', 'replyto'])){
                $addresses = preg_split('/(,|;)/', $headerValue);
            }
            switch (strtolower($headerName)) {
                case 'cc':
                case 'bcc':
                    $addMethod = 'add'.strtoupper($headerName);
                    foreach($addresses as $address){
                        $this->mailer->$addMethod($address);
                    }
                    break;
                case 'reply-to':
                case 'replyto':
                    foreach ($addresses as $address) {
                        $this->mailer->addReplyTo($address);
                    }
                    break;
                default:
                    $this->mailer->AddCustomHeader($headerName . ':' . $headerValue);
            }

        }
    }


    /**
     * @param array $attachedFiles
     */
    protected function attachFiles(array $attachedFiles)
    {
        foreach ($attachedFiles as $attachedFile) {
            if (isset($attachedFile['filename']) && is_string($attachedFile['filename'])) {
                $filePath = $attachedFile['filename'];
                if (substr($filePath, 0, strlen(Director::baseFolder())) !== Director::baseFolder()) {
                    $filePath = Director::baseFolder().DIRECTORY_SEPARATOR.$filePath;
                }
                $this->mailer->AddAttachment($filePath);
            }
        }
    }

}

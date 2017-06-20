<?php
/**
 * SilverStripe-PhpMailer
 * @category smtp/code
 * @author Andreas Gerhards <ag.dialogue@yahoo.co.nz>
 * @copyright Copyright ©2017, Andreas Gerhards
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please check LICENSE.md for more information
 */

require_once(dirname(__DIR__).DIRECTORY_SEPARATOR.'phpmailer'.DIRECTORY_SEPARATOR.'class.phpmailer.php');
require_once(dirname(__DIR__).DIRECTORY_SEPARATOR.'phpmailer'.DIRECTORY_SEPARATOR.'class.smtp.php');


class SmtpMailer extends Mailer
{

    /** @var int $this->sendDelay  Throttling on some services (i.e. AWS SES) */
    private $sendDelay = 0;
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
     * Instanciate SmtpMailer
     */
    protected function instanciate()
    {
        $this->sendDelay = defined('SMTP_SEND_DELAY') ? SMTP_SEND_DELAY : 0;

        if (is_null($this->mailer)) {
            $this->mailer = new PHPMailer(true);
            $this->mailer->IsSMTP();
            $this->mailer->CharSet = defined('SMTP_CHARSET_ENCODING')
                ? SMTP_CHARSET_ENCODING : "utf-8";
            $this->mailer->Host = defined('SMTP_SMTP_SERVER_ADDRESS')
                ? SMTP_SMTP_SERVER_ADDRESS : "localhost";
            $this->mailer->Port = defined('SMTP_SMTP_SERVER_PORT')
                ? SMTP_SMTP_SERVER_PORT : 25;
            $this->mailer->SMTPSecure = defined('SMTP_USE_SECURE_CONNECTION')
                ? strtolower(SMTP_USE_SECURE_CONNECTION) : '';
            $this->mailer->SMTPAuth = defined('SMTP_DO_AUTHENTICATE')
                ? SMTP_DO_AUTHENTICATE : false;

            if ($this->mailer->SMTPAuth) {
                $this->mailer->Username = defined('SMTP_USERNAME')
                    ? SMTP_USERNAME : "username";
                $this->mailer->Password = defined('SMTP_PASSWORD')
                    ? SMTP_PASSWORD : "password";
            }

            $this->mailer->SMTPDebug = defined('SMTP_DEBUG_MESSAGING_LEVEL')
                ? SMTP_DEBUG_MESSAGING_LEVEL : 0;
            $this->mailer->SetLanguage(defined('SMTP_LANGUAGE_OF_MESSAGES')
                ? SMTP_LANGUAGE_OF_MESSAGES : 'en');
            $this->mailer->ErrorLevel = defined('SMTP_SMTP_ERROR_LEVEL')
                ? SMTP_SMTP_ERROR_LEVEL : E_USER_ERROR;
        }
    }


    /**
     * {@inheritDoc}
     *  @param string $to
     *  @param string $from
     *  @param string $subject
     *  @param string $plainContent
     *  @param string|false $attachedFiles
     *  @param string|false $customheaders
     *  @return string[]|false $sendResponse
     * @see Mailer::sendPlain()
     */
    function sendPlain($to, $from, $subject, $plainContent, $attachedFiles = false, $customheaders = false)
    {
        $this->instanciate();
        $this->mailer->IsHTML(false);
        $this->mailer->Body = $plainContent;

        return $this->sendMailViaSmtp($to, $from, $subject, $attachedFiles, $customheaders, false);
    }


    /* Overwriting SilverStripe's Mailer's function */
    /**
     * {@inheritDoc}
     *  @param unknown $to
     *  @param unknown $from
     *  @param unknown $subject
     *  @param unknown $htmlContent
     *  @param string $attachedFiles
     *  @param string $customheaders
     *  @param string $plainContent
     * @param string $inlineImages
     *  @return string[]|false $sendResponse
     * @see Mailer::sendHtml()
     */
    function sendHTML($to, $from, $subject, $htmlContent, $attachedFiles = false, $customheaders = false, $plainContent = false, $inlineImages = false){
        $this->instanciate();
        $this->mailer->IsHTML(true);
        if($inlineImages){
            $this->mailer->MsgHTML($htmlContent, Director::baseFolder());
        } else {
            $this->mailer->Body = $htmlContent;
            if(empty($plainContent)){
                $plainContent = trim(Convert::html2raw($htmlContent));
            }
            $this->mailer->AltBody = $plainContent;
        }
        return $this->sendMailViaSmtp($to, $from, $subject, $attachedFiles, $customheaders, $inlineImages);
    }


    /**
     * @param string $to
     * @param string $from
     * @param string $subject
     * @param string $attachedFiles
     * @param string $customheaders
     * @param string $inlineImages
     * @return string[]|false $sendResponse
     */
    protected function sendMailViaSmtp($to, $from, $subject, $attachedFiles = false, $customheaders = false, $inlineImages = false)
    {
        if ($this->mailer->SMTPDebug > 0) {
            echo "<em><strong>*** Debug mode is on</strong>, printing debug messages and not redirecting to the website:</em><br/>";
            echo "To: $to, From: $from, Subject: $subject<br>";
        }
        $logMessage = "\n*** The sender was : $from\n*** The message was :\n{$this->mailer->AltBody}\n";

        try {
            $this->buildBasicMail($to, $from, $subject);
            $customheaders['X-SMTPAPI'] = '{"category": "' . $_SERVER['HTTP_HOST'] . '"}'; // Add the current domain for services like SendGrid
            $this->addCustomHeaders($customheaders);
            $this->attachFiles($attachedFiles);

            //Due to AWS SES, sometimes we need to throttle out e-mail delivery
            if($this->sendDelay > 0){
                usleep($this->sendDelay * 1000);//we want milliseconds, not microseconds
            }

            $this->mailer->Send();

            if($this->mailer->SMTPDebug > 0){
                echo "<em><strong>*** E-mail to $to has been sent.</strong></em><br />";
                echo "<em><strong>*** The debug mode blocked the process</strong> to avoid the url redirection. So the CC e-mail is not sent.</em>";
                die();
            }

            $bounceAddress = $this->getBounceEmail();
            $bounceAddress = is_string($bounceAddress) && strlen($bounceAddress) > 0 ? $bounceAddress : $from;
            $send = array($to, $subject, $this->mailer->Body, $customheaders, $bounceAddress);

        }catch(Exception $exception) {
            $this->handleError($exception->getMessage(), $logMessage);
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
     * @param mixed $headers
     */
    protected function addCustomHeaders($headers)
    {
        if (!is_array($headers)) {
            $headers = array();
        }
        if (!isset($headers["X-Mailer"])) {
            $headers["X-Mailer"] = X_MAILER;
        }
        if(!isset($headers["X-Priority"])){
            $headers["X-Priority"] = 3;
        }
        $this->mailer->ClearCustomHeaders();

        // Convert cc/bcc/ReplyTo from headers to properties
        foreach ($headers as $header_name=>$header_value) {
            if(in_array(strtolower($header_name), array('cc', 'bcc', 'reply-to', 'replyto'))){
                $addresses = preg_split('/(,|;)/', $header_value);
            }
            switch (strtolower($header_name)) {
                case 'cc':
                    foreach($addresses as $address){ $this->mailer->addCC($address); }
                    break;
                case 'bcc':
                    foreach($addresses as $address) { $this->mailer->addBCC($address); }
                    break;
                case 'reply-to':
                    foreach ($addresses as $address) {
                        $this->mailer->addReplyTo($address);
                    }
                    break;
                default:
                    $this->mailer->AddCustomHeader($header_name . ':' . $header_value);
            }
        }
    }


    /**
     * @param mixed $attachedFiles
     */
    protected function attachFiles($attachedFiles)
    {
        if (is_array($attachedFiles)) {
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

}

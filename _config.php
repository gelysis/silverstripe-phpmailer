<?php
/**
 * SilverStripe-Phpmailer
 * @package smtp
 * @author gelysis <andreas@gelysis.net>
 * @copyright ©2017 Andreas Gerhards - All rights reserved
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please check LICENSE.md for more information
 */

$localConfigFile = './local.php';
if (file_exists($localConfigFile)) {
    require_once $localConfigFile;
}

Email::set_mailer(new SmtpMailer());

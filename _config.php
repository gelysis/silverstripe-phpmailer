<?php
/**
 * SilverStripe-Phpmailer
 * @category smtp
 * @author Andreas Gerhards <ag.dialogue@yahoo.co.nz>
 * @copyright Copyright ©2017, Andreas Gerhards
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please check LICENSE.md for more information
 */

Email::set_mailer(new SmtpMailer());

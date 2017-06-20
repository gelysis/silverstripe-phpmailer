SILVERSTRIPE-PHPMAILER
======================

PHPMailer for SilverStripe
--------------------------
Copyright ©2017, Andreas Gerhards <ag.dialogue@yahoo.co.nz>.
All rights reserved. / Alle Rechte vorbehalten.

Based on the [SilverStripe SmtpMailer fork of Philipp Krenn](https://github.com/xeraa/silverstripe-smtp.git).

# LICENSE
BSD-3: Please consult [LICENSE.md] for further details.

# LIZENZ
BSD-3: Bitte lesen sie [LICENSE.md] für weitergehende Informationen.

# SYSTEM REQUIREMENTS
Requires SilverStripe ~3.6.0 and PHP 5.6 or later.

# COMPONENTS
This package is a optional module for SilverStripe 3.

# DESCRIPTION
SilverStripe-Smtp sends emails to your provider's or host's SMTP server instead of using PHP's built-in mail() function and therefore replaces the classic SilverStripe Mailer with [PHPMailer](https://github.com/PHPMailer/PHPMailer) to send emails via the SMTP protocol to a local or remote SMTP server.

Use cases:
* Disabled mail().
* Troubles with the DNS configuration and the way some mail servers discard emails if the domain names don't match.
* If you want to send encrypted emails (using SSL or TLS protocols).
* Sending emails without having to install a mail server using an external SMTP server instead.
* If you are using AWS and would like to utilize the SES (Simple Email Service).

# INSTALLATION
* Use packagist depndency (geolysis/silverstripe-phpmailer).
* Configure the module using _config.php.model. Without any configuration the fallback is localhost without authentication.

# QUESTIONS AND FEEDBACK
Please contact the author.

# RELEASE INFORMATION
SilverStripe-Phpmailer 0.9.0
2017-06-20

# UPDATES
Please see [CHANGELOG.md].

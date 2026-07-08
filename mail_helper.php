<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mailerBase = __DIR__ . '/vendor/phpmailer/phpmailer/src';
require_once $mailerBase . '/Exception.php';
require_once $mailerBase . '/PHPMailer.php';
require_once $mailerBase . '/SMTP.php';

 define('MAIL_USERNAME', 'admin@powercabs.ie');
 define('MAIL_HOST', 'mail.powercabs.ie');
 define('MAIL_PASSWORD', 'Pwcabs@_1234');
 define('MAIL_FROM_ADDRESS', 'admin@powercabs.ie');
 define('MAIL_FROM_NAME', 'PowerCabs Admin');

/**
 * Send ride-assigned notification to passenger.
 * Returns true on success, or error string on failure.
 *
 * @param string $passengerEmail
 * @param string $passengerName
 * @param string $pickupAddr
 * @param string $destAddr
 * @param string $rideType
 * @param string $fareEur
 * @param string|null $templateDir Optional directory containing email_ride_assigned.html
 * @return true|string
 */
function sendRideAssignedEmail($passengerEmail, $passengerName, $pickupAddr, $destAddr, $rideType, $fareEur, $orderDate = null, $pickupTime = null, $templateDir = null) {
    if (empty($passengerEmail) || !filter_var($passengerEmail, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid or missing passenger email';
    }
    // Skip temp placeholder emails (e.g. phone@temp.passenger)
    if (stripos($passengerEmail, '@temp.passenger') !== false) {
        return 'Passenger has no real email (temp placeholder)';
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
        $mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer debug (level {$level}): {$str}");
        };
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 20;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($passengerEmail);
        $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->isHTML(true);
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->Subject  = 'Your ride has been assigned - PowerCabs';

        $body = buildRideAssignedBody($passengerName, $pickupAddr, $destAddr, $rideType, $fareEur, $orderDate, $pickupTime, $templateDir);
        $mail->Body    = $body;
        $mail->AltBody = buildRideAssignedAltBody($passengerName, $pickupAddr, $destAddr, $rideType, $fareEur, $orderDate, $pickupTime);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Ride-assigned email error: ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}

/**
 * Plain-text alternative body. Required for good deliverability — mailers
 * without AltBody get higher spam scores and are frequently dropped.
 */
function buildRideAssignedAltBody($passengerName, $pickupAddr, $destAddr, $rideType, $fareEur, $orderDate = null, $pickupTime = null) {
    $name   = $passengerName ?: 'Passenger';
    $pickup = $pickupAddr    ?: '-';
    $dest   = $destAddr      ?: '-';
    $type   = $rideType      ?: '-';
    $fare   = ($fareEur !== '' && $fareEur !== null) ? ('EUR ' . $fareEur) : '-';
    $date   = $orderDate  ?: '-';
    $time   = $pickupTime ?: '-';

    return "Hi {$name},\n\n"
        . "Your PowerCabs ride has been confirmed.\n\n"
        . "TRIP DETAILS\n"
        . "------------\n"
        . "Pick-up:     {$pickup}\n"
        . "Drop-off:    {$dest}\n"
        . "Service:     {$type}\n"
        . "Fare:        {$fare}\n"
        . "Order Date:  {$date}\n"
        . "Pickup Time: {$time}\n\n"
        . "Please be ready at your pickup location 5 minutes before the scheduled time.\n"
        . "The fare shown is an estimate; tolls, waiting time and route changes may affect the final amount.\n\n"
        . "Questions? Call +353 1 203 0727 or email info@powercabs.ie.\n\n"
        . "Thank you for choosing PowerCabs.\n"
        . "-- PowerCabs Ireland";
}

/**
 * Build HTML body for ride-assigned email.
 */
function buildRideAssignedBody($passengerName, $pickupAddr, $destAddr, $rideType, $fareEur, $orderDate = null, $pickupTime = null, $templateDir = null) {
    $dir = $templateDir !== null ? $templateDir : (__DIR__ . '/templates');
    $path = rtrim($dir, '/\\') . '/email_ride_assigned.html';
    if (is_file($path)) {
        $html = file_get_contents($path);
        $html = str_replace('[PASSENGER_NAME]', htmlspecialchars($passengerName ?: 'Passenger'), $html);
        $html = str_replace('[PICKUP_ADDRESS]', htmlspecialchars($pickupAddr ?: '—'), $html);
        $html = str_replace('[DEST_ADDRESS]',   htmlspecialchars($destAddr   ?: '—'), $html);
        $html = str_replace('[RIDE_TYPE]',      htmlspecialchars($rideType   ?: '—'), $html);
        $html = str_replace('[FARE_EUR]',       htmlspecialchars($fareEur !== '' && $fareEur !== null ? '€' . $fareEur : '—'), $html);
        $html = str_replace('[ORDER_DATE]',     htmlspecialchars($orderDate  ?: '—'), $html);
        $html = str_replace('[PICKUP_TIME]',    htmlspecialchars($pickupTime ?: '—'), $html);
        return $html;
    }
    // Fallback inline HTML
    $name = htmlspecialchars($passengerName ?: 'Passenger');
    $pickup = htmlspecialchars($pickupAddr ?: '—');
    $dest = htmlspecialchars($destAddr ?: '—');
    $type = htmlspecialchars($rideType ?: '—');
    $fare = ($fareEur !== '' && $fareEur !== null) ? '€' . htmlspecialchars($fareEur) : '—';
    return "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"></head><body>"
        . "<p>Hello {$name},</p>"
        . "<p>Your PowerCabs ride has been assigned.</p>"
        . "<p><strong>Pick-up:</strong> {$pickup}<br><strong>Drop-off:</strong> {$dest}<br>"
        . "<strong>Service:</strong> {$type}<br><strong>Fare:</strong> {$fare}</p>"
        . "<p>Thank you for choosing PowerCabs.</p></body></html>";
}

/**
 * Send the welcome email to a newly registered corporate account.
 * Returns true on success, or error string on failure.
 *
 * @param string $corporateEmail
 * @param string $companyName
 * @param string $appointedPerson
 * @param string|null $templateDir Optional dir containing email_corporate_welcome.html
 * @return true|string
 */
function sendCorporateWelcomeEmail($corporateEmail, $companyName, $appointedPerson = '', $templateDir = null) {
    if (empty($corporateEmail) || !filter_var($corporateEmail, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid or missing corporate email';
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
        $mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer debug (level {$level}): {$str}");
        };
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 20;

        $mail->setFrom(MAIL_FROM_ADDRESS, 'PowerCabs Business');
        $mail->addAddress($corporateEmail, $companyName ?: '');
        $mail->addReplyTo('support@powercabs.ie', 'PowerCabs Support');
        $mail->isHTML(true);
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->Subject  = 'Welcome onboard – let\'s get your team moving';

        $mail->Body    = buildCorporateWelcomeBody($companyName, $appointedPerson, $templateDir);
        $mail->AltBody = buildCorporateWelcomeAltBody($companyName, $appointedPerson);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Corporate welcome email error: ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}

function buildCorporateWelcomeAltBody($companyName, $appointedPerson) {
    $person  = $appointedPerson ?: ($companyName ?: 'there');
    $company = $companyName ?: 'your company';
    return "Dear {$person},\n\n"
        . "Welcome to PowerCabs Business — we're delighted to have {$company} onboard.\n\n"
        . "Your account is now set up, giving you access to a reliable and flexible transport solution designed to support your team's daily travel needs.\n\n"
        . "WHAT YOU CAN DO\n"
        . "- Book rides instantly or schedule them in advance\n"
        . "- Arrange transport for employees, clients, or guests\n"
        . "- Monitor trips in real time\n"
        . "- Manage your team and control usage\n"
        . "- Access clear reports and centralized billing\n\n"
        . "GETTING STARTED\n"
        . "Booking a ride: log in, enter pickup and destination, choose Book Now or schedule for later, then assign to an employee or guest.\n"
        . "Managing your team: add employees, set roles, permissions and usage limits.\n"
        . "Tracking trips: view all active and completed rides from your dashboard.\n\n"
        . "ADDITIONAL SERVICES\n"
        . "Delivery on demand — quick, secure delivery for keys, laptops, or documents.\n"
        . "Meet & Greet (Airport Service) — premium airport pickup and transport for clients or team members.\n\n"
        . "SUPPORT\n"
        . "Email: support@powercabs.ie\n"
        . "Phone: +353 89 972 8089\n\n"
        . "Kind regards,\n"
        . "PowerCabs Business Team";
}

function buildCorporateWelcomeBody($companyName, $appointedPerson, $templateDir = null) {
    $dir  = $templateDir !== null ? $templateDir : (__DIR__ . '/templates');
    $path = rtrim($dir, '/\\') . '/email_corporate_welcome.html';

    $companyEsc = htmlspecialchars($companyName ?: 'your company', ENT_QUOTES, 'UTF-8');
    $personEsc  = htmlspecialchars($appointedPerson ?: ($companyName ?: 'there'), ENT_QUOTES, 'UTF-8');

    if (is_file($path)) {
        $html = file_get_contents($path);
        $html = str_replace('[COMPANY_NAME]',     $companyEsc, $html);
        $html = str_replace('[APPOINTED_PERSON]', $personEsc,  $html);
        return $html;
    }
    // Fallback inline HTML
    return "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"></head><body style=\"font-family:Arial,sans-serif;\">"
        . "<h2 style=\"color:#f37a20;\">Welcome onboard</h2>"
        . "<p>Dear {$personEsc},</p>"
        . "<p>Welcome to PowerCabs Business — we're delighted to have <strong>{$companyEsc}</strong> onboard.</p>"
        . "<p>Your account is now set up. Log in to start booking rides for your team.</p>"
        . "<p>Need help? Email support@powercabs.ie or call +353 89 972 8089.</p>"
        . "<p>— PowerCabs Business Team</p>"
        . "</body></html>";
}

/**
 * Send the driver account approval / welcome email.
 * Returns true on success, or error string on failure.
 *
 * @param string $driverEmail
 * @param string $driverName
 * @return true|string
 */
function sendDriverApprovedEmail($driverEmail, $driverName) {
    if (empty($driverEmail) || !filter_var($driverEmail, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid or missing driver email';
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
        $mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer debug (level {$level}): {$str}");
        };
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 20;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($driverEmail, $driverName ?: '');
        $mail->addReplyTo('support@powercabs.ie', 'PowerCabs Support');
        $mail->isHTML(true);
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->Subject  = 'Your PowerCabs Driver Account Has Been Approved';

        $mail->Body    = buildDriverApprovedBody($driverName);
        $mail->AltBody = buildDriverApprovedAltBody($driverName);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Driver approved email error: ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}

function buildDriverApprovedAltBody($driverName) {
    $name = $driverName ?: 'Driver';
    return "Dear {$name},\n\n"
        . "Great news — your documents have been reviewed and your PowerCabs driver account is now approved. "
        . "You can go online in the app and start accepting ride requests right away.\n\n"
        . "GO ONLINE ANYTIME\n"
        . "Open the PowerCabs Driver app and tap Go Online whenever you're ready to start earning.\n\n"
        . "START ACCEPTING RIDES\n"
        . "You'll receive nearby ride requests based on your vehicle type, seats, and service area.\n\n"
        . "GET PAID WEEKLY\n"
        . "Track your earnings in real time in the app and receive automatic payouts to your registered IBAN.\n\n"
        . "Welcome to the team,\n"
        . "PowerCabs Team";
}

function buildDriverApprovedBody($driverName) {
    $safeName = htmlspecialchars($driverName ?: 'Driver', ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Poppins',Helvetica,Arial,sans-serif;color:#333">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)">

  <!-- Header -->
  <tr>
    <td style="background:#1A1A2E;padding:28px 24px;text-align:center">
      <span style="font-size:28px;font-weight:800;color:#F37A20;letter-spacing:1px">PowerCabs</span>
    </td>
  </tr>

  <!-- Success icon + title -->
  <tr>
    <td style="padding:36px 40px 0;text-align:center">
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 16px"><tr><td style="width:64px;height:64px;border-radius:50%;background:#DCFCE7;text-align:center;vertical-align:middle;line-height:64px;font-family:Arial,Helvetica,sans-serif;font-size:30px;font-weight:800;color:#16a34a">&#10003;</td></tr></table>
      <h1 style="margin:0 0 4px;font-size:24px;font-weight:700;color:#1A1A2E">Account Approved!</h1>
      <p style="margin:0;font-size:15px;font-weight:600;color:#F37A20">You're ready to hit the road</p>
    </td>
  </tr>

  <!-- Divider -->
  <tr>
    <td style="padding:20px 40px 0">
      <hr style="border:none;border-top:1px solid #e8e8e8;margin:0">
    </td>
  </tr>

  <!-- Body text -->
  <tr>
    <td style="padding:24px 40px 0;font-size:14px;line-height:1.7;color:#444">
      <p style="margin:0 0 12px">Dear {$safeName},</p>
      <p style="margin:0">Great news — your documents have been reviewed and your PowerCabs driver account is now <strong style="color:#16a34a">approved</strong>. You can go online in the app and start accepting ride requests right away.</p>
    </td>
  </tr>

  <!-- Info card 1: Go Online -->
  <tr>
    <td style="padding:24px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:44px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:800;color:#F37A20">1</div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Go Online Anytime</strong><br>
            Open the PowerCabs Driver app and tap <strong style="color:#F37A20">Go Online</strong> whenever you're ready to start earning.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Info card 2: Accept Rides -->
  <tr>
    <td style="padding:12px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:44px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:800;color:#F37A20">2</div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Start Accepting Rides</strong><br>
            You'll receive nearby ride requests based on your vehicle type, seats, and service area.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Info card 3: Get Paid -->
  <tr>
    <td style="padding:12px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:44px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:800;color:#F37A20">3</div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Get Paid Weekly</strong><br>
            Track your earnings in real time in the app and receive automatic payouts to your registered IBAN.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="padding:28px 40px 36px;text-align:center;font-size:14px;color:#444">
      <p style="margin:0 0 2px"><strong>Welcome to the team,</strong></p>
      <p style="margin:0;color:#F37A20;font-weight:600">PowerCabs Team</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Send the driver account removal / document-incomplete email.
 * Returns true on success, or error string on failure.
 *
 * @param string $driverEmail
 * @param string $driverName
 * @return true|string
 */
function sendDriverRemovedEmail($driverEmail, $driverName) {
    if (empty($driverEmail) || !filter_var($driverEmail, FILTER_VALIDATE_EMAIL)) {
        return 'Invalid or missing driver email';
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
        $mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer debug (level {$level}): {$str}");
        };
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 20;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($driverEmail, $driverName ?: '');
        $mail->addReplyTo('support@powercabs.ie', 'PowerCabs Support');
        $mail->isHTML(true);
        $mail->CharSet  = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->Subject  = 'Your PowerCabs Driver Account Has Been Removed';

        $mail->Body    = buildDriverRemovedBody($driverName);
        $mail->AltBody = buildDriverRemovedAltBody($driverName);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Driver removed email error: ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}

function buildDriverRemovedAltBody($driverName) {
    $name = $driverName ?: 'Driver';
    return "Dear {$name},\n\n"
        . "Your account has been removed due to incomplete or invalid documentation following previous notifications. "
        . "We welcome you to reapply after 30 days with all required documents ready for review.\n\n"
        . "RESTRICTIONS FOR 30 DAYS\n"
        . "Any application submitted within 30 days of account removal may be placed on hold and will be reviewed once 30 days have passed.\n\n"
        . "ADDITIONAL VERIFICATION\n"
        . "Future applications may also be subject to additional verification checks.\n\n"
        . "REPEATED INCOMPLETE APPLICATIONS\n"
        . "Repeated applications submitted without the required documentation or a complete profile may be considered misuse of the onboarding process and may result in a restriction on submitting further applications for up to 6 months.\n\n"
        . "PowerCabs reserves the right to accept or reject any future application based on the completeness and accuracy of the information provided.\n\n"
        . "Thank you,\n"
        . "PowerCabs Team";
}

function buildDriverRemovedBody($driverName) {
    $safeName = htmlspecialchars($driverName ?: 'Driver', ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:'Poppins',Helvetica,Arial,sans-serif;color:#333">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)">

  <!-- Header -->
  <tr>
    <td style="background:#1A1A2E;padding:28px 24px;text-align:center">
      <span style="font-size:28px;font-weight:800;color:#F37A20;letter-spacing:1px">PowerCabs</span>
    </td>
  </tr>

  <!-- Warning icon + title -->
  <tr>
    <td style="padding:36px 40px 0;text-align:center">
      <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 16px"><tr><td style="width:64px;height:64px;border-radius:50%;background:#FFF0E0;text-align:center;vertical-align:middle;line-height:64px;font-family:Arial,Helvetica,sans-serif;font-size:34px;font-weight:800;color:#F37A20">!</td></tr></table>
      <h1 style="margin:0 0 4px;font-size:24px;font-weight:700;color:#1A1A2E">Account Removed</h1>
      <p style="margin:0;font-size:15px;font-weight:600;color:#F37A20">Restrictions for 30 Days</p>
    </td>
  </tr>

  <!-- Divider -->
  <tr>
    <td style="padding:20px 40px 0">
      <hr style="border:none;border-top:1px solid #e8e8e8;margin:0">
    </td>
  </tr>

  <!-- Body text -->
  <tr>
    <td style="padding:24px 40px 0;font-size:14px;line-height:1.7;color:#444">
      <p style="margin:0 0 12px">Dear {$safeName},</p>
      <p style="margin:0">Your account has been removed due to incomplete or invalid documentation following previous notifications. We welcome you to reapply after <strong style="color:#F37A20">30 days</strong> with all required documents ready for review.</p>
    </td>
  </tr>

  <!-- Info card 1: Restrictions -->
  <tr>
    <td style="padding:24px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:44px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:800;color:#F37A20">1</div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Restrictions for 30 Days</strong><br>
            Please note that any application submitted within 30 days of account removal may be placed on hold and will be reviewed when 30 days have passed.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Info card 2: Additional Verification -->
  <tr>
    <td style="padding:12px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:44px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:800;color:#F37A20">2</div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Additional Verification</strong><br>
            Future applications may also be subject to additional verification checks.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Info card 3: Repeated Incomplete Applications -->
  <tr>
    <td style="padding:12px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F2;border-radius:10px;padding:20px">
        <tr>
          <td width="54" valign="top" style="padding:0 14px 0 0">
            <div style="width:44px;height:44px;background:#FFF0E0;border-radius:10px;text-align:center;line-height:44px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:800;color:#F37A20">3</div>
          </td>
          <td valign="top" style="font-size:13px;line-height:1.6;color:#555">
            <strong style="font-size:14px;color:#1A1A2E">Repeated Incomplete Applications</strong><br>
            Repeated applications submitted without the required documentation or a complete profile may be considered misuse of the onboarding process and may result in a restriction on submitting further applications for up to <strong style="color:#F37A20">6 months</strong>.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Disclaimer box -->
  <tr>
    <td style="padding:20px 40px 0">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F5F5;border-radius:10px;padding:16px 20px">
        <tr>
          <td width="32" valign="top" style="padding:0 10px 0 0">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width:28px;height:28px;border-radius:50%;background:#EFEFEF;text-align:center;vertical-align:middle;line-height:28px;font-family:Georgia,'Times New Roman',serif;font-size:14px;font-weight:800;font-style:italic;color:#999999">i</td></tr></table>
          </td>
          <td style="font-size:12px;line-height:1.6;color:#777">
            PowerCabs reserves the right to accept or reject any future application based on the completeness and accuracy of the information provided.
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="padding:28px 40px 36px;text-align:center;font-size:14px;color:#444">
      <p style="margin:0 0 2px"><strong>Thank you,</strong></p>
      <p style="margin:0;color:#F37A20;font-weight:600">PowerCabs Team</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

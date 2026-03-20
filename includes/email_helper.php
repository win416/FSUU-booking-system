<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/SimpleMailer.php';

/**
 * Send a 6-digit verification code to the given email address.
 *
 * @return bool  true on success, false on failure (error stored in $emailError)
 */
function sendVerificationEmail(
    string $toEmail,
    string $firstName,
    string $code,
    string &$emailError = ''
): bool {
    try {
        $mailer = new SimpleMailer(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS);
        $mailer->send(
            $toEmail,
            $firstName,
            'Verify your FSUU Dental Clinic account',
            buildVerificationEmailHtml($firstName, $code),
            defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'FSUU Dental Clinic'
        );
        return true;
    } catch (RuntimeException $e) {
        $emailError = $e->getMessage();
        error_log('[FSUU Email] ' . $e->getMessage());
        return false;
    }
}

/** Build the HTML body for the verification email. */
function buildVerificationEmailHtml(string $firstName, string $code): string
{
    $expiry = defined('OTP_EXPIRY_MINUTES') ? OTP_EXPIRY_MINUTES : 15;
    $digits = str_split($code);
    $digitBoxes = '';
    foreach ($digits as $d) {
        $digitBoxes .= '
            <td style="padding:4px;">
                <div style="
                    width:48px; height:56px; line-height:56px;
                    background:#f0f9ff; border:2px solid #00aeef;
                    border-radius:10px; font-size:28px; font-weight:800;
                    color:#0d6efd; text-align:center; font-family:monospace;
                ">' . htmlspecialchars($d) . '</div>
            </td>';
    }

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1faff;font-family:\'Segoe UI\',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1faff;padding:40px 0;">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:20px;overflow:hidden;
                    box-shadow:0 8px 30px rgba(0,174,239,0.12);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#00aeef,#0077b6);
                     padding:36px 40px;text-align:center;">
            <h1 style="margin:0;color:#fff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">
              FSUU Dental Clinic
            </h1>
            <p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:13px;">
              Email Verification
            </p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:40px 40px 32px;">
            <p style="margin:0 0 8px;font-size:15px;color:#344054;">
              Hi <strong>' . htmlspecialchars($firstName) . '</strong>,
            </p>
            <p style="margin:0 0 28px;font-size:15px;color:#344054;line-height:1.6;">
              Thank you for registering! Please use the verification code below
              to confirm your URIOS email address and activate your account.
            </p>

            <!-- OTP Boxes -->
            <table cellpadding="0" cellspacing="0" style="margin:0 auto 28px;">
              <tr>' . $digitBoxes . '</tr>
            </table>

            <p style="text-align:center;margin:0 0 24px;font-size:13px;color:#6c757d;">
              This code expires in <strong>' . $expiry . ' minutes</strong>.
              Do not share it with anyone.
            </p>

            <hr style="border:none;border-top:1px solid #e9eef5;margin:0 0 24px;">

            <p style="margin:0;font-size:13px;color:#6c757d;line-height:1.6;">
              If you did not create an account at FSUU Dental Clinic, please ignore this email.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8fbff;padding:20px 40px;text-align:center;
                     border-top:1px solid #e9eef5;">
            <p style="margin:0;font-size:12px;color:#adb5bd;">
              Father Saturnino Urios University &mdash; Dental Clinic
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>';
}

<?php
/**
 * Minimal SMTP mailer with STARTTLS support (Gmail-compatible).
 * No external dependencies required.
 */
class SimpleMailer
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    /** @var resource|null */
    private $socket = null;

    public function __construct(string $host, int $port, string $username, string $password)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Send an HTML email.
     *
     * @throws RuntimeException on any SMTP error
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $fromName = 'FSUU Dental Clinic'
    ): void {
        $this->openConnection();

        // Initial EHLO
        $this->cmd("EHLO mail.client");

        // Upgrade to TLS
        $r = $this->cmd("STARTTLS");
        if ((int) substr($r, 0, 3) !== 220) {
            throw new RuntimeException("STARTTLS rejected by server: $r");
        }
        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException("TLS handshake failed.");
        }

        // Re-identify after TLS
        $this->cmd("EHLO mail.client");

        // Authenticate
        $r = $this->cmd("AUTH LOGIN");
        if ((int) substr($r, 0, 3) !== 334) {
            throw new RuntimeException("AUTH LOGIN not accepted: $r");
        }
        $r = $this->cmd(base64_encode($this->username));
        if ((int) substr($r, 0, 3) !== 334) {
            throw new RuntimeException("Username rejected: $r");
        }
        $r = $this->cmd(base64_encode($this->password));
        if ((int) substr($r, 0, 3) !== 235) {
            throw new RuntimeException(
                "SMTP authentication failed. Make sure SMTP_USER and SMTP_PASS are set " .
                "correctly in includes/config.php (use a Gmail App Password)."
            );
        }

        // Envelope
        $this->cmd("MAIL FROM:<{$this->username}>");
        $r = $this->cmd("RCPT TO:<{$toEmail}>");
        if ((int) substr($r, 0, 3) !== 250) {
            throw new RuntimeException("Recipient address rejected: $r");
        }

        // DATA
        $r = $this->cmd("DATA");
        if ((int) substr($r, 0, 3) !== 354) {
            throw new RuntimeException("DATA command rejected: $r");
        }

        // Build RFC 2822 message
        $msg  = "Date: " . date('r') . "\r\n";
        $msg .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$this->username}>\r\n";
        $msg .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "\r\n";
        $msg .= chunk_split(base64_encode($htmlBody));
        $msg .= "\r\n.\r\n"; // end DATA

        fwrite($this->socket, $msg);
        $r = $this->readResponse();

        $this->cmd("QUIT");
        fclose($this->socket);
        $this->socket = null;

        if ((int) substr($r, 0, 3) !== 250) {
            throw new RuntimeException("Server rejected the message: $r");
        }
    }

    private function openConnection(): void
    {
        $this->socket = @fsockopen("tcp://{$this->host}", $this->port, $errno, $errstr, 15);
        if (!$this->socket) {
            throw new RuntimeException("Cannot connect to SMTP server {$this->host}:{$this->port} — $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 15);

        $resp = $this->readResponse();
        if ((int) substr($resp, 0, 3) !== 220) {
            throw new RuntimeException("Unexpected SMTP greeting: $resp");
        }
    }

    /** Send a command and return the server response. */
    private function cmd(string $command): string
    {
        fwrite($this->socket, $command . "\r\n");
        return $this->readResponse();
    }

    /** Read a (possibly multi-line) SMTP response. */
    private function readResponse(): string
    {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $response .= $line;
            // A line whose 4th character is a space marks the last line of the response
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}

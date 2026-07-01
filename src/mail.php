<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';   // e(), fmt_date(), fmt_dt(), slot_label()
require_once __DIR__ . '/repo.php';     // booking_by_id(), members lookups

/**
 * E-Mail-Layer (KAMC „Lounge oben").
 *
 * - Eine Layout-Hülle (HTML, email-sicher: Tabellen + Inline-Styles) + Body je Typ.
 * - Immer multipart: HTML + Plain-Text-Alternative.
 * - Steckbarer Versand (config['mail']['transport']):
 *     'log'  -> schreibt in logs/mail/ (Dev-Outbox, kein SMTP nötig)  [Default]
 *     'smtp' -> PHPMailer, falls installiert (composer require phpmailer/phpmailer)
 *     'mail' -> PHP mail() als Fallback
 * - Benachrichtigungen werden von den Endpoints aufgerufen (NICHT aus der Engine),
 *   in try/catch — ein Mail-Fehler darf den Buchungs-Flow nie brechen.
 */

function mail_cfg(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $all = require __DIR__ . '/../config.php';
        $cfg = ['mail' => $all['mail'] ?? [], 'app' => $all['app'] ?? []];
    }
    return $cfg;
}

function mail_base_url(): string
{
    return rtrim((string) (mail_cfg()['app']['base_url'] ?? ''), '/');
}

/* ------------------------------------------------------------------ *
 * Templates                                                          *
 * ------------------------------------------------------------------ */

/** Email-sichere Layout-Hülle (Navy-Header, Logo, Footer). */
function mail_layout(string $heading, string $innerHtml): string
{
    $logo = mail_base_url() . '/assets/img/kamc-logo.png';
    $navy = '#030053';
    return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:92%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
        // Header
        . '<tr><td style="background:' . $navy . ';padding:20px 28px;">'
        . '<img src="' . $logo . '" width="36" height="36" alt="KAMC" style="vertical-align:middle;border-radius:50%;background:#fff;">'
        . '<span style="color:#ffffff;font-size:18px;font-weight:bold;vertical-align:middle;margin-left:10px;">KAMC</span>'
        . '<span style="color:#9ec9ff;font-size:15px;vertical-align:middle;"> · Lounge oben</span>'
        . '</td></tr>'
        // Body
        . '<tr><td style="padding:28px;">'
        . '<h1 style="margin:0 0 14px;font-size:20px;color:' . $navy . ';">' . e($heading) . '</h1>'
        . $innerHtml
        . '</td></tr>'
        // Footer
        . '<tr><td style="background:#fafafa;border-top:1px solid #eee;padding:18px 28px;font-size:12px;color:#6b7280;">'
        . 'KAMC e.V. · Kölner Autbord- und Motoryachtclub · Rheinauhafen<br>'
        . '<a href="https://kamc.koeln" style="color:#6b7280;">kamc.koeln</a> · '
        . '<a href="https://kamc.kurabu.com" style="color:#6b7280;">Mitglieder-Portal</a>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Navy-Button für Mails. */
function mail_button(string $label, string $url): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0;"><tr>'
        . '<td style="background:#030053;border-radius:8px;">'
        . '<a href="' . e($url) . '" style="display:inline-block;padding:11px 22px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;">' . e($label) . '</a>'
        . '</td></tr></table>';
}

/** Eckdaten-Tabelle einer Buchung (HTML). */
function mail_facts(array $b): string
{
    $rows = [
        'Datum'       => fmt_date($b['booking_date']),
        'Slot'        => slot_label($b['slot']),
        'Personen'    => (string) (int) $b['party_size'],
        'Anlass'      => $b['purpose'] ?: '—',
    ];
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:6px 0 4px;">';
    foreach ($rows as $k => $v) {
        $html .= '<tr>'
            . '<td style="padding:6px 0;color:#6b7280;font-size:14px;width:120px;">' . e($k) . '</td>'
            . '<td style="padding:6px 0;color:#111827;font-size:14px;font-weight:bold;">' . e($v) . '</td>'
            . '</tr>';
    }
    return $html . '</table>';
}

/** Plain-Text-Eckdaten. */
function mail_facts_text(array $b): string
{
    return "Datum:    " . fmt_date($b['booking_date']) . "\n"
        . "Slot:     " . slot_label($b['slot']) . "\n"
        . "Personen: " . (int) $b['party_size'] . "\n"
        . "Anlass:   " . ($b['purpose'] ?: '-') . "\n";
}

/* ------------------------------------------------------------------ *
 * Versand                                                            *
 * ------------------------------------------------------------------ */

function send_mail(string $toEmail, string $toName, string $subject, string $html, string $text): bool
{
    $m = mail_cfg()['mail'];
    $transport = $m['transport'] ?? 'log';
    $fromAddr = $m['from_addr'] ?? 'lounge@kamc.koeln';
    $fromName = $m['from_name'] ?? 'KAMC · Lounge oben';

    try {
        if ($transport === 'smtp' && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $m['smtp_host'] ?? '';
            $mail->Port = (int) ($m['smtp_port'] ?? 587);
            $mail->SMTPAuth = true;
            $mail->Username = $m['smtp_user'] ?? '';
            $mail->Password = $m['smtp_pass'] ?? '';
            $mail->SMTPSecure = 'tls';
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            return $mail->send();
        }
        if ($transport === 'mail') {
            $boundary = 'b' . bin2hex(random_bytes(8));
            $headers = "From: {$fromName} <{$fromAddr}>\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n"
                . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n--{$boundary}--";
            return mail($toEmail, $subject, $body, $headers);
        }
        // Default: Dev-Outbox als Datei
        return mail_log_outbox($toEmail, $subject, $html, $text);
    } catch (\Throwable $e) {
        return false;
    }
}

/** Schreibt eine Mail als Datei in logs/mail/ (Dev-Outbox). */
function mail_log_outbox(string $toEmail, string $subject, string $html, string $text): bool
{
    $dir = __DIR__ . '/../logs/mail';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($subject));
    $base = $dir . '/' . bin2hex(random_bytes(4)) . '-' . substr($slug, 0, 40);
    $header = "To: {$toEmail}\nSubject: {$subject}\n\n";
    @file_put_contents($base . '.txt', $header . $text);
    @file_put_contents($base . '.html', $html);
    return true;
}

/** Interne Empfänger (Hafenmeisterei/Vorstand). */
function staff_recipients(PDO $pdo): array
{
    return $pdo->query("SELECT name, email FROM members WHERE role IN ('hafenmeister','admin') AND status='active'")->fetchAll();
}

/* ------------------------------------------------------------------ *
 * Benachrichtigungen (je Statuswechsel)                              *
 * ------------------------------------------------------------------ */

function notify_booking_created(PDO $pdo, int $bookingId): void
{
    $b = booking_by_id($pdo, $bookingId);
    if (!$b) { return; }
    $url = mail_base_url() . '/meine-buchungen.php';

    // an das Mitglied
    $inner = '<p style="font-size:15px;">Ahoi ' . e($b['member_name']) . ',</p>'
        . '<p style="font-size:15px;color:#374151;">deine Anfrage für die Lounge oben ist eingegangen. Die Hafenmeisterei prüft sie und meldet sich.</p>'
        . mail_facts($b) . mail_button('Meine Buchungen', $url);
    $text = "Ahoi {$b['member_name']},\n\ndeine Anfrage ist eingegangen. Die Hafenmeisterei prüft sie.\n\n" . mail_facts_text($b) . "\n{$url}\n";
    send_mail($b['member_email'], $b['member_name'], 'Deine Anfrage ist eingegangen', mail_layout('Anfrage eingegangen', $inner), $text);

    // intern an die Hafenmeisterei
    $adminUrl = mail_base_url() . '/hafenmeister/offene-buchungen.php';
    $innerHm = '<p style="font-size:15px;">Neue Buchungsanfrage von <strong>' . e($b['member_name']) . '</strong>:</p>'
        . mail_facts($b) . mail_button('Zur Freigabe', $adminUrl);
    $textHm = "Neue Buchungsanfrage von {$b['member_name']}:\n\n" . mail_facts_text($b) . "\n{$adminUrl}\n";
    foreach (staff_recipients($pdo) as $r) {
        send_mail($r['email'], $r['name'], 'Neue Buchungsanfrage · ' . fmt_date($b['booking_date']), mail_layout('Neue Buchungsanfrage', $innerHm), $textHm);
    }
}

function notify_booking_decided(PDO $pdo, int $bookingId): void
{
    $b = booking_by_id($pdo, $bookingId);
    if (!$b) { return; }
    $url = mail_base_url() . '/meine-buchungen.php';

    if ($b['status'] === 'confirmed') {
        $inner = '<p style="font-size:15px;">Ahoi ' . e($b['member_name']) . ',</p>'
            . '<p style="font-size:15px;color:#374151;">deine Buchung ist <strong>bestätigt</strong>. Schlüsselübergabe und Begehung laufen über die Hafenmeisterei. Denk an: keine Musik, Selbstversorgung, Grill reinigen.</p>'
            . mail_facts($b) . mail_button('Details ansehen', $url);
        $text = "Ahoi {$b['member_name']},\n\ndeine Buchung ist bestätigt.\n\n" . mail_facts_text($b) . "\n{$url}\n";
        send_mail($b['member_email'], $b['member_name'], 'Buchung bestätigt · ' . fmt_date($b['booking_date']), mail_layout('Buchung bestätigt', $inner), $text);
    } elseif ($b['status'] === 'rejected') {
        $grund = $b['decision_note'] ? '<p style="font-size:14px;color:#6b7280;">Grund: ' . e($b['decision_note']) . '</p>' : '';
        $inner = '<p style="font-size:15px;">Ahoi ' . e($b['member_name']) . ',</p>'
            . '<p style="font-size:15px;color:#374151;">deine Anfrage konnte leider nicht bestätigt werden. Wähl gern einen anderen Tag.</p>'
            . mail_facts($b) . $grund . mail_button('Neuen Termin wählen', mail_base_url() . '/lounge.php');
        $text = "Ahoi {$b['member_name']},\n\ndeine Anfrage wurde abgelehnt." . ($b['decision_note'] ? "\nGrund: {$b['decision_note']}" : '') . "\n\n" . mail_facts_text($b);
        send_mail($b['member_email'], $b['member_name'], 'Buchung abgelehnt · ' . fmt_date($b['booking_date']), mail_layout('Buchung abgelehnt', $inner), $text);
    }
}

function notify_inspection(PDO $pdo, int $bookingId): void
{
    $b = booking_by_id($pdo, $bookingId);
    if (!$b || !$b['inspection_result']) { return; }
    $url = mail_base_url() . '/meine-buchungen.php';

    if ($b['inspection_result'] === 'passed' && $b['case_status'] !== 'rework') {
        $inner = '<p style="font-size:15px;">Ahoi ' . e($b['member_name']) . ',</p>'
            . '<p style="font-size:15px;color:#374151;">die Abnahme ist erledigt — alles in Ordnung. Danke fürs saubere Hinterlassen!</p>' . mail_facts($b);
        $text = "Ahoi {$b['member_name']},\n\ndie Abnahme ist erledigt — alles in Ordnung. Danke!\n\n" . mail_facts_text($b);
        send_mail($b['member_email'], $b['member_name'], 'Abnahme erledigt · ' . fmt_date($b['booking_date']), mail_layout('Abnahme erledigt', $inner), $text);
    } elseif ($b['case_status'] === 'rework') {
        $frist = $b['rework_due'] ? '<p style="font-size:14px;color:#6b7280;">Frist: ' . e(fmt_date($b['rework_due'])) . '</p>' : '';
        $note = $b['inspection_notes'] ? '<p style="font-size:15px;color:#374151;">Beanstandung: ' . e($b['inspection_notes']) . '</p>' : '';
        $inner = '<p style="font-size:15px;">Ahoi ' . e($b['member_name']) . ',</p>'
            . '<p style="font-size:15px;color:#374151;">bei der Abnahme gab es etwas nachzuarbeiten. Bitte kümmere dich darum — die Hafenmeisterei nimmt danach erneut ab.</p>'
            . $note . $frist . mail_button('Details & Widerspruch', $url);
        $text = "Ahoi {$b['member_name']},\n\nbei der Abnahme gab es Nacharbeit." . ($b['inspection_notes'] ? "\nBeanstandung: {$b['inspection_notes']}" : '') . "\n\n{$url}\n";
        send_mail($b['member_email'], $b['member_name'], 'Nacharbeit nötig · ' . fmt_date($b['booking_date']), mail_layout('Nacharbeit nötig', $inner), $text);
    }
}

function notify_case_resolved(PDO $pdo, int $bookingId): void
{
    $b = booking_by_id($pdo, $bookingId);
    if (!$b) { return; }
    $inner = '<p style="font-size:15px;">Ahoi ' . e($b['member_name']) . ',</p>'
        . '<p style="font-size:15px;color:#374151;">der Vorgang zu deiner Buchung wurde vom Vorstand abgeschlossen'
        . ($b['resolution'] ? ' (' . e($b['resolution']) . ')' : '') . '.</p>' . mail_facts($b);
    $text = "Ahoi {$b['member_name']},\n\nder Vorgang wurde abgeschlossen" . ($b['resolution'] ? " ({$b['resolution']})" : '') . ".\n\n" . mail_facts_text($b);
    send_mail($b['member_email'], $b['member_name'], 'Vorgang abgeschlossen · ' . fmt_date($b['booking_date']), mail_layout('Vorgang abgeschlossen', $inner), $text);
}

/** Interner Hinweis an Vorstand/Hafenmeisterei (Widerspruch/Eskalation). */
function notify_staff_case(PDO $pdo, int $bookingId, string $headline): void
{
    $b = booking_by_id($pdo, $bookingId);
    if (!$b) { return; }
    $url = mail_base_url() . '/admin/eskalationen.php';
    $inner = '<p style="font-size:15px;">' . e($headline) . ' — <strong>' . e($b['member_name']) . '</strong></p>'
        . ($b['inspection_notes'] ? '<p style="font-size:14px;color:#6b7280;">Beanstandung: ' . e($b['inspection_notes']) . '</p>' : '')
        . mail_facts($b) . mail_button('Zu den Eskalationen', $url);
    $text = "{$headline} — {$b['member_name']}\n\n" . mail_facts_text($b) . "\n{$url}\n";
    foreach (staff_recipients($pdo) as $r) {
        send_mail($r['email'], $r['name'], $headline . ' · ' . fmt_date($b['booking_date']), mail_layout($headline, $inner), $text);
    }
}

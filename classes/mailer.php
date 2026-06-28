<?php
// Copyright (c) Vernon Apain / Educheckout.
// All rights reserved.
//
// This file is part of a proprietary plugin developed by Vernon Apain /
// Educheckout for use with Moodle. It is NOT free software and is NOT
// released under the GNU General Public License.
//
// Unauthorised copying, distribution, modification, or use of this file,
// in whole or in part, via any medium, is strictly prohibited without the
// prior written permission of Educheckout. The software is provided "as
// is", without warranty of any kind, express or implied.

/**
 * Helper for sending HTML emails with inline CID images.
 *
 * @package    local_imageblog
 * @copyright  © Vernon Apain / Educheckout
 * @license    Proprietary — Vernon Apain / Educheckout, all rights reserved
 */

namespace local_imageblog;

/**
 * Sends an HTML email through Moodle's configured PHPMailer with one or more
 * inline (Content-ID) image parts. email_to_user() in core only supports a
 * single file attachment by path and offers no hook for inline images, so we
 * drive the mailer directly here.
 */
class mailer {
    /**
     * Send an HTML email with embedded inline images.
     *
     * @param \stdClass            $to          Recipient user (needs email, firstname/lastname for fullname()).
     * @param \stdClass            $from        Sender user (e.g. core_user::get_noreply_user()).
     * @param string               $subject
     * @param string               $textbody    Plain-text alternative.
     * @param string               $htmlbody    HTML body referencing images as <img src="cid:KEY">.
     * @param array     $inlineimages CID => stored_file. Each file is embedded under that CID.
     * @return bool True on send success.
     */
    public static function send_html_with_inline_images(
        \stdClass $to,
        \stdClass $from,
        string $subject,
        string $textbody,
        string $htmlbody,
        array $inlineimages = []
    ): bool {
        global $CFG, $SITE;

        if (empty($to->email) || !validate_email($to->email)) {
            return false;
        }
        if (!empty($to->emailstop) || !empty($to->deleted) || !empty($to->suspended)) {
            return false;
        }

        $mail = get_mailer();
        $mail->Sender   = !empty($from->email) ? $from->email : (string)$CFG->noreplyaddress;
        $mail->From     = !empty($from->email) ? $from->email : (string)$CFG->noreplyaddress;
        $mail->FromName = !empty($from->firstname) || !empty($from->lastname)
            ? fullname($from)
            : format_string($SITE->fullname);
        $mail->Subject  = substr($subject, 0, 900);
        $mail->isHTML(true);
        $mail->CharSet  = 'UTF-8';
        $mail->Body     = $htmlbody;
        $mail->AltBody  = $textbody;

        $mail->addAddress($to->email, fullname($to));

        foreach ($inlineimages as $cid => $file) {
            if (!$file instanceof \stored_file) {
                continue;
            }
            $mimetype = $file->get_mimetype() ?: 'image/jpeg';
            $mail->addStringEmbeddedImage(
                $file->get_content(),
                (string)$cid,
                $file->get_filename(),
                \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                $mimetype,
                'inline'
            );
        }

        try {
            $ok = $mail->send();
            if (!$ok) {
                debugging('local_imageblog mailer error: ' . $mail->ErrorInfo, DEBUG_NORMAL);
            }
            return (bool)$ok;
        } catch (\Throwable $e) {
            debugging('local_imageblog mailer exception: ' . $e->getMessage(), DEBUG_NORMAL);
            return false;
        }
    }
}

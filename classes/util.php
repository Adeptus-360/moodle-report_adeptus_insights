<?php
namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

class util {
    private const SESSION_KEY = 'rai_redirect_subscription';

    /** Mark that we should redirect after admin saves settings. */
    public static function mark_post_settings_redirect(): void {
        // Use SESSION so it survives the admin_settings post/redirect cycle.
        $_SESSION[self::SESSION_KEY] = 1;
    }

    /** Consume and clear the redirect flag; returns true if set. */
    public static function consume_post_settings_redirect(): bool {
        if (!empty($_SESSION[self::SESSION_KEY])) {
            unset($_SESSION[self::SESSION_KEY]);
            return true;
        }
        return false;
    }
}

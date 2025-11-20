<?php
namespace Catlaq\Expo;

class Reminder_Service {
    public static function boot(): void {
        add_action( 'catlaq_send_agreement_reminders', [ __CLASS__, 'process' ] );
    }

    public static function process(): void {
        // Placeholder: scan agreements for pending statuses and log reminders.
        Logger::log( 'info', 'Agreement reminder cron ran', [ 'timestamp' => time() ] );
    }
}

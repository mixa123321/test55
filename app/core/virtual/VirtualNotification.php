<?php

namespace app\core\virtual;

use app\core\entities\WebhookHistory;
use app\core\Environment;
use app\core\helpers\HTTP;
use app\core\models\Messages;

class VirtualNotification
{
    const EMAILS = [
        's.ustenko@v-tersolutions.com',
    ];

    public static function sendWebhookToTelegram(WebhookHistory $webhook_history, $number, $group_name)
    {
        if (empty(WebhookHistory::WEBHOOK_OBJECT[$webhook_history->getName()]))
            return false;
        $name = $webhook_history->getName() . (Environment::isDev() ? ' (test)' : '');
        $message = "<b>$name</b> <a href='" . HTTP::getDomain() . "/reports/view_webhook/?id={$webhook_history->getId()}'>" . WebhookHistory::WEBHOOK_OBJECT[$webhook_history->getName()] . (empty($webhook_history->getNumber()) ? " (ref: $number)" : " #{$webhook_history->getNumber()}") . "</a>";
//        (new TelegramNotifier($group_name, $message, TelegramNotifier::PARSE_HTML))->send();
        Messages::addDebugInfo('', $message, $group_name, Messages::TEST_PARAMETERS);
    }

    public static function sendErrorToTelegram($subj, $message, $group_name)
    {
        $name = $subj . (Environment::isDev() ? ' (test)' : '');
        $message = "<b>$name</b> has an error:\n<i>$message</i>";
//        (new TelegramNotifier($group_name, $message, TelegramNotifier::PARSE_HTML))->send();
        Messages::addDebugInfo('', $message, $group_name, Messages::TEST_PARAMETERS);
    }

    public static function sendNotificationsToEmails($subj, $message, $emails = self::EMAILS)
    {
        mail(
            implode(',', $emails),
            $subj . (Environment::isDev() ? ' (test)' : ''),
            $message,
            "From: notifier@" . API_DOMAIN
        );
    }
}

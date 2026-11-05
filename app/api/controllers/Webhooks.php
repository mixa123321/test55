<?php

namespace app\api\controllers;

class Webhooks extends AuthApiController
{
    public function getSubscriptiontypes()
    {
        self::callAction('GET webhooks/subscriptiontypes', 'ActionWebhooksGet', 'subscriptiontypes');
    }

    public function postSubscription()
    {
        self::callAction('POST webhooks/subscription', 'ActionWebhooksPost', 'subscription');
    }

    public function get()
    {
        self::callAction('GET webhooks', 'ActionWebhooksGet');
    }

    public function put()
    {
        self::callAction('PUT webhooks', 'ActionWebhooksPut');
    }
}

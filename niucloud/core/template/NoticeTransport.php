<?php
declare(strict_types=1);

namespace core\template;

/** Sending a notice is not idempotent: never replay an ambiguous HTTP POST. */
final class NoticeTransport
{
    public static function client($application)
    {
        // A fresh client is necessary: getClient() may already cache an SDK
        // retry decorator, including for an Open Platform authorized account.
        // Clone configuration so unrelated reads retain their retry policy.
        $app = clone $application;
        $config = clone $app->getConfig();
        $config->set('http.retry', false);
        $config->set('http.max_retries', 0);
        $app->setConfig($config);
        return $app->createClient();
    }
}

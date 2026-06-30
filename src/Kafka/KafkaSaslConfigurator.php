<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Kafka;

use Junges\Kafka\Contracts\ConsumerBuilder;
use Junges\Kafka\Contracts\MessageProducer;

final class KafkaSaslConfigurator
{
    /**
     * @return array{
     *     username: string,
     *     password: string,
     *     mechanisms: string,
     *     security_protocol: string
     * }|null
     */
    public static function credentialsFromConfig(): ?array
    {
        $username = config('kafka.sasl.username');
        $password = config('kafka.sasl.password');

        if (! is_string($username) || trim($username) === '') {
            return null;
        }

        if (! is_string($password) || $password === '') {
            return null;
        }

        $mechanisms = strtoupper(trim((string) config('kafka.sasl.mechanisms', 'PLAIN')));
        if ($mechanisms === 'PLAINTEXT') {
            $mechanisms = 'PLAIN';
        }

        $securityProtocol = strtoupper(trim((string) config('kafka.securityProtocol', 'SASL_PLAINTEXT')));
        if ($securityProtocol === 'PLAINTEXT') {
            $securityProtocol = 'SASL_PLAINTEXT';
        }

        if (! in_array($securityProtocol, ['SASL_PLAINTEXT', 'SASL_SSL'], true)) {
            throw new \RuntimeException(sprintf(
                'Kafka SASL credentials require KAFKA_SECURITY_PROTOCOL=SASL_PLAINTEXT or SASL_SSL (got "%s").',
                $securityProtocol
            ));
        }

        return [
            'username' => $username,
            'password' => $password,
            'mechanisms' => $mechanisms,
            'security_protocol' => $securityProtocol,
        ];
    }

    public static function applyToProducer(MessageProducer $producer): MessageProducer
    {
        $credentials = self::credentialsFromConfig();
        if ($credentials === null) {
            return $producer;
        }

        return $producer->withSasl(
            $credentials['username'],
            $credentials['password'],
            $credentials['mechanisms'],
            $credentials['security_protocol']
        );
    }

    public static function applyToConsumer(ConsumerBuilder $consumer): ConsumerBuilder
    {
        $credentials = self::credentialsFromConfig();
        if ($credentials === null) {
            return $consumer;
        }

        return $consumer->withSasl(
            $credentials['username'],
            $credentials['password'],
            $credentials['mechanisms'],
            $credentials['security_protocol']
        );
    }
}

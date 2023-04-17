<?php

namespace Ofd\Api;

use JsonException;
use Ofd\Api\Adapter\Log\Logger;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class OfdClient - SDK Ofd API
 * @package Ofd\Api
 */
final class OfdClient
{
    private const STATIC_URL = "https://testapi.ofd-ya.ru/ofdapi/v1/";

    /**
     * SymfonyHttpClient constructor.
     * Токен в Ofd API бессрочный
     * @param HttpClientInterface|null $client - Symfony Http клиент
     */
    public function __construct(private ?HttpClientInterface $client = null, ?string $customToken = null)
    {
        # HttpClient - выбирает транспорт cURL если расширение PHP cURL включено,
        # и возвращается к потокам PHP в противном случае
        # Добавляем в header токен из cache
        $this->client = $client ?? HttpClient::create(
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Ofdapitoken' => $customToken ?? getenv('OFD_TOKEN')
                ],
                'http_version' => '2.0'
            ]
        );
    }

    /**
     * Отправить HTTP запрос - клиентом
     * @param string $method - Метод
     * @param string $model - Модель
     * @param array $options - Параметры
     * @return ResponseInterface
     */
    private function sendRequest(string $method, string $model, array $options = []): ResponseInterface
    {
        $method = strtoupper($method);
        $url = self::STATIC_URL . $model;

        return $this->client->request($method, $url, $options);
    }

    private function post(string $model, array $options): array
    {
        $options = [
            'body' => json_encode($options, JSON_UNESCAPED_UNICODE)
        ];
        $response = $this->sendRequest('POST', $model, $options);

        $this->throwStatusCode($response);

        return $response->toArray(false);
    }

    private function get(string $model, array $options): array
    {
        $options = [
            'query' => $options
        ];
        $response = $this->sendRequest('GET', $model, $options);

        $this->throwStatusCode($response);

        return $response->toArray(false);
    }

    private function throwStatusCode(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        switch ($statusCode) {
            case 200:
                return;
            case 500:
                $this->log('critical', "SDK. Ошибка OFD Api. 500 Internal Server Error", $response->toArray(false));
                throw new ServerException($response);
            default:
                $this->log('error', "SDK. Ошибка OFD Api: ", $response->toArray(false));
                throw new JsonException($response->getContent(false), $statusCode);
        }
    }

    /**
     * Метод возвращает данные по Кассовым чекам и БСО за сутки по номеру фискального накопителя (номер ФН).
     * @param array $params - Параметры запроса, которые передаются в Тело запроса (Request body)
     * @return array
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws JsonException
     */
    public function documents(array $params = []): array
    {
        return $this->post(
            "documents",
            $params
        );
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $logger = new Logger();
        $logger->$level($message, $context);
    }
}

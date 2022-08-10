<?php

namespace Ofd\Api;

use JsonException;
use Ofd\Api\Adapter\IlluminateOfdApi\Log\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class OfdClient - SDK Ofd API
 * @package Ofd\Api
 */
final class OfdClient
{
    private const STATIC_URL = "https://testapi.ofd-ya.ru/ofdapi/v1/";

    /**
     * Предоставляет гибкие методы для синхронного или асинхронного запроса ресурсов HTTP.
     * @var HttpClientInterface|null
     */
    private ?HttpClientInterface $client;

    /**
     * SymfonyHttpClient constructor.
     * Токен в Ofd API бессрочный
     * @param HttpClientInterface|null $client - Symfony Http клиент
     */
    public function __construct(HttpClientInterface $client = null)
    {
        # HttpClient - выбирает транспорт cURL если расширение PHP cURL включено,
        # и возвращается к потокам PHP в противном случае
        # Добавляем в header токен из cache
        $this->client = $client ?? HttpClient::create(
                [
                    'http_version' => '2.0',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Ofdapitoken' => getenv('OFD_TOKEN')
                    ]
                ]
            );
    }

    /**
     * Общий метод, для любой модели. Позволяет выполнить запрос к OFD API
     * @param string $method - Метод
     * @param string $model - Модель
     * @param array $params - Параметры
     * @return array
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws JsonException
     */
    public function request(string $method, string $model, array $params = []): array
    {
        #Создаем ссылку
        $url = self::STATIC_URL . $model;
        #Отправляем request запрос
        $response = $this->client->request(
            strtoupper($method),
            $url,
            [
                'body' => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ]
        );
        #Получаем статус запроса
        $statusCode = $response->getStatusCode();
        switch ($statusCode) {
            case 200:
            {
                return json_decode(
                    $response->getContent(false),
                    true,
                    512,
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                );
            }
            case 500:
            {
                $this->log('critical', "500 Internal Server Error", [$response->getContent(false)]);
                throw new JsonException("500 Internal Server Error", 500);
            }
            default:
            {
                $this->log('error', "Ошибка OFD: ", [$response->getContent(false)]);
                throw new JsonException("Ошибка OFD: ", $response->getStatusCode());
            }
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
        return $this->request(
            "POST",
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

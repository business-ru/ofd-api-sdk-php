<?php

namespace Codeception\Module;

use bru\api\Client;
use Codeception\Exception\BruApiException;
use Codeception\Util\HttpCode;

/**
 * Кастомный модуль Codeception
 * Адаптер между Codeception и business-ru/business-online-sdk-php
 * Class TusModule
 */
class TusModule extends BaseBruModule
{
	/**
	 * Инициализируем класс bru\api\Client
	 * @var Client|null
	 */
	private ?Client $client;

	/**
	 * Метод позволяет выполнить запрос к API Туса
	 * Для business-ru/business-online-sdk-php
	 * @param string $method - Наименование метода
	 * @param string $model - Наименование модели
	 * @param array $params - Параметры запроса
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 * @throws \bru\api\Exceptions\BruApiClientException
	 * @throws \bru\api\Exceptions\SimpleFileCacheException
	 */
	public function request(string $method, string $model, array $params = []): void
	{
		$this->client = new Client($this->account, $this->appID, $this->secretKey);
		$this->response = $this->client->request(strtoupper($method), $model, $params);
	}

	/**
	 * Запрос всех записей модели.
	 * Аналогично методу request, за исключением того, что данный метод
	 * выполняет get - запрос к переданной модели и возвращает все записи
	 * Для business-ru/business-online-sdk-php
	 * @param string $model - Наименование модели
	 * @param array $params - Параметры запроса
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 * @throws \bru\api\Exceptions\BruApiClientException
	 * @throws \bru\api\Exceptions\SimpleFileCacheException
	 */
	public function requestAll(string $model, array $params = []): void
	{
		$this->client = new Client($this->account, $this->appID, $this->secretKey);
		$this->response = $this->client->requestAll($model, $params);
	}

	/**
	 * Запрос к API в формате GraphQL.
	 * $data - Строка в формате GraphQL
	 * Для business-ru/business-online-sdk-php
	 * @param string $data - Строка в формате GraphQL
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 * @throws \bru\api\Exceptions\BruApiClientException
	 * @throws \bru\api\Exceptions\HttpClientException
	 * @throws \bru\api\Exceptions\SimpleFileCacheException
	 * @throws \bru\api\Exceptions\SimpleFileCacheInvalidArgumentException
	 */
	public function graphQL(string $data): void
	{
		$this->client = new Client($this->account, $this->appID, $this->secretKey);
		$this->response = $this->client->graphQL($data);
	}

	/**
	 * Отправить уведомление пользователям.
	 * Для business-ru/business-online-sdk-php
	 * @param $employees string|array ID пользователя или пользователей
	 * @param string $header Заголовок
	 * @param string $message Сообщение
	 * @param null $document_id ID документа
	 * @param null $model_name Название модели документа
	 * @param null $action Текст ссылки на документ
	 * @param int $seconds Задержка в секундах
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 * @throws \bru\api\Exceptions\BruApiClientException
	 * @throws \bru\api\Exceptions\SimpleFileCacheException
	 */
	public function sendNotificationSystem(
		array $employees,
		string $header,
		string $message,
		$document_id = null,
		$model_name = null,
		$action = null,
		int $seconds = 0
	): void {
		$this->client = new Client($this->account, $this->appID, $this->secretKey);
		$this->response = $this->client->sendNotificationSystem(
			$employees,
			$header,
			$message,
			$document_id,
			$model_name,
			$action,
			$seconds
		);
	}

	/**
	 * Проверка, совпадает ли статус в полученных данных
	 * Метод для business-ru/business-online-sdk-php
	 * @param $statusName - Наименование статуса
	 * @return \Codeception\Exception\BruApiException
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 * @throws \bru\api\Exceptions\BruApiClientException
	 * @throws \bru\api\Exceptions\SimpleFileCacheException
	 * @throws \bru\api\Exceptions\SimpleFileCacheInvalidArgumentException
	 */
	public function responseCheckStatus($statusName): BruApiException
	{
		$this->client = new Client($this->account, $this->appID, $this->secretKey);
		$response = json_encode($this->response, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
		$responseStatus = $this->response["status"];
		if ($responseStatus !== $statusName) {
			throw new BruApiException('Ошибка! Получен статус: ' . $responseStatus . PHP_EOL . $response);
		}
		return new BruApiException();
	}


	/**
	 * Проверка, совпадает ли статус в полученных данных
	 * @param $code - Наименование статуса
	 * @return \Codeception\Exception\BruApiException
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 * @throws \Psr\SimpleCache\InvalidArgumentException
	 * @throws \bru\api\Exceptions\BruApiClientException
	 * @throws \bru\api\Exceptions\SimpleFileCacheException
	 * @throws \bru\api\Exceptions\SimpleFileCacheInvalidArgumentException
	 */
	public function responseIsCode($code): BruApiException
	{
		$this->client = new Client($this->account, $this->appID, $this->secretKey);
		$response = json_encode($this->response, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
		if (HttpCode::OK !== $code) {
			throw new BruApiException($response);
		}
		return new BruApiException();
	}
}
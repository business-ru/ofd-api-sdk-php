<?php

namespace Codeception\Module;

use Exception;
use Ofd\Api\OfdClient;
use Utils\OpenApiConnector;

/**
 * Кастомный модуль Codeception
 * Адаптер между Codeception и онлайн чеки
 * Class OnlineChecksModule
 */
class OnlineChecksModule extends BaseBruModule
{
	/**
	 * Инициализируем класс
	 * @var OpenApiConnector|null
	 */
	private ?OpenApiConnector $openApiConnector;

	/**
	 * Инициализируем класс
	 * @var OfdClient|null
	 */
	private ?OfdClient $ofdClient;

	/**
	 * Метод отправляет запрос на открытие смены на ККТ.
	 * @return void Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws \Exception
	 */
	public function openShift(string $method): void
	{
		$this->openApiConnector = new OpenApiConnector($method, $this->appID, $this->secretKey);
		$this->response = $this->openApiConnector->openShift($method);
	}

	/**
	 * Метод отправляет запрос на закрытие смены на ККТ.
	 *
	 * @return void Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws \Exception
	 */
	public function closeShift(string $method): void
	{
		$this->openApiConnector = new OpenApiConnector($method, $this->appID, $this->secretKey);
		$this->response = $this->openApiConnector->closeShift($method);
	}

	/**
	 * Метод выполняет запрос на печать чека прихода на ККТ.
	 *
	 * @param string $method
	 * @param array $data Маасив параметров чека.
	 *
	 * @return void Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws \Exception
	 */
	public function printCheck(string $method, array $data): void
	{
		$this->openApiConnector = new OpenApiConnector($method, $this->appID, $this->secretKey);
		$this->response = $this->openApiConnector->printCheck($method, $data);
	}

	/**
	 * Метод выполняет запрос на печать чека возврата прихода на ККТ.
	 *
	 * @param array $data Маасив параметров чека.
	 *
	 * @return void Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws \Exception
	 */
	public function printPurchaseReturn(string $method, array $data): void
	{
		$this->openApiConnector = new OpenApiConnector($method, $this->appID, $this->secretKey);
		$this->response = $this->openApiConnector->printPurchaseReturn($method, $data);
	}

	/**
	 * Метод выполняет запрос на получение информации о состоянии системы.
	 * @return void Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws Exception
	 */
	public function getSystemStatus(string $method): void
	{
		$this->openApiConnector = new OpenApiConnector($method, $this->appID, $this->secretKey);
		$this->response = $this->openApiConnector->getSystemStatus($method);
	}

	/**
	 * Метод выполняет запрос на печать чека прихода на ККТ.
	 *
	 * @param string $method
	 * @param int|string $data Маасив параметров чека.
	 *
	 * @return void Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws \Exception
	 */
	public function dataCommandID(string $method, string $data): void
	{
		$this->openApiConnector = new OpenApiConnector($method, $this->appID, $this->secretKey);
		$this->response = $this->openApiConnector->dataCommandID($method, $data);
	}

	/**
	 * Метод позволяет выполнить запрос к API OFD
	 * Для ofd-api-sdk-php
	 * @param string $method - Наименование метода
	 * @param string $model - Наименование модели
	 * @param array $params - Параметры запроса
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 */
	public function request(string $method, string $model, array $params = []): void
	{
		$this->ofdClient = new OfdClient();
		$this->response = $this->ofdClient->request(strtoupper($method), $model, $params);
	}

}

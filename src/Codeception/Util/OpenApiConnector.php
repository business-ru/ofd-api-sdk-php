<?php

namespace Utils;

use Exception;

/**
 * @author Kirill Silianov <kirill.silianov@gmail.com>
 * Date: 28.08.2017
 * Time: 9:45
 *
 * @version 1.0.0
 */
class OpenApiConnector
{
	/**
	 * @var string BASE_URL Базовый адрес API сервиса.
	 *
	 * https://check-dev.business.ru/open-api/v1/ - Тестовый сервис.
	 * https://check.business.ru/open-api/v1/ - Stable сервис.
	 */
	private const BASE_URL = "https://check-dev.business.ru/open-api/v1/";

	/**
	 * @var mixed STATIC_APP_ID app_id сервиса (заменить своим из интеграции, если не используется внешнее хранение переменной).
	 */
	private const STATIC_APP_ID = "d78c7aa5-9edb-4d99-8159-a5655c3a4d1f";

	/**
	 * @var string STATIC_SECRET_KEY Secret key сервиса (заменить своим из интеграции, если не используется внешнее хранение переменной).
	 */
	private const STATIC_SECRET_KEY = "fMBRGdACY6LyqDKuk24wS7pv1cIgjE8n";

	/**
	 * @var string GET_TOKEN_URL Адрес для получения токена.
	 */
	private const GET_TOKEN_URL = "Token";

	/**
	 * @var string GET_SYSTEM_STATUS Адрес для получения состояния системы.
	 */
	private const GET_SYSTEM_STATUS = "StateSystem";

	/**
	 * @var string COMMAND_URL Адррес для передачи комманды на ККТ.
	 */
	private const COMMAND_URL = "Command";

	/**
	 * @var mixed $appID App_id интеграции.
	 */
	private $appID;

	/**
	 * @var false|string $token Токен интеграции.
	 */
	private $token = false;

	/**
	 * @var false|string $secret Secret key интеграции.
	 */
	private $secret;

	/**
	 * @var false|string $nonce "Соль" команды (Является уникальным идентификатором команды).
	 */
	private $nonce = false;

	/**
	 * OpenApiConnector constructor.
	 *
	 * Задает app_id и secret_key интеграции из параметров или из констант при отсутствии.
	 * Генерирует новый идентификатор команды.
	 * Получает новый токен.
	 *
	 * @param mixed $appID
	 * @param string $secret
	 * @throws \Exception
	 */
	public function __construct($method, $appID = self::STATIC_APP_ID, string $secret = self::STATIC_SECRET_KEY)
	{
		$this->appID = $appID;
		$this->secret = $secret;
		$this->getNonce();
		$this->getToken($method);
	}

	/**
	 * Метод возвращает app_id интеграции.
	 *
	 * @return string|bool app_id интеграции.
	 */
	public function getAppID(): string
	{
		return $this->appID;
	}

	/**
	 * Метод возвращает secret_key интеграции.
	 *
	 * @return string|bool secret_key интеграции.
	 */
	public function getSecretKey(): string
	{
		return $this->secret;
	}


	/**
	 * Метод отправляет запрос на генерацию нового токена и записывает его в переменную объекта.
	 * @throws \JsonException
	 */
	public function getToken(string $method): void //TODO: rename
	{
		$token = json_decode(
			$this->sendRequest(
				$method,
				self::GET_TOKEN_URL,
				[
					"app_id" => $this->appID,
					"nonce" => $this->nonce,
				]
			),
			true,
			512,
			JSON_THROW_ON_ERROR
		)["token"];
		$this->token = $token;
	}

	/**
	 * Метод отправляет запрос на открытие смены на ККТ.
	 *
	 * @return bool|string Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws \Exception
	 */
	public function openShift(string $method)
	{
		return $this->sendRequest(
			$method,
			self::COMMAND_URL,
			[
				"nonce" => $this->nonce,
				"app_id" => $this->appID,
				"token" => $this->token,
				"type" => "openShift",
				"command" => [
					"report_type" => false,
					"author" => "name" //TODO: make name
				]
			]
		);
	}

	/**
	 * Метод отправляет запрос на закрытие смены на ККТ.
	 *
	 * @return bool|string Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws Exception
	 */
	public function closeShift(string $method)
	{
		return $this->sendRequest(
			$method,
			self::COMMAND_URL,
			[
				"nonce" => $this->nonce,
				"app_id" => $this->appID,
				"token" => $this->token,
				"type" => "closeShift",
				"command" => [
					"report_type" => false,
					"author" => "name" //TODO: make name
				]
			]
		);
	}

	/**
	 * Метод выполняет запрос на печать чека прихода на ККТ.
	 *
	 * @param array $data Маасив параметров чека.
	 *
	 * @return bool|string Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws Exception
	 */
	public function printCheck(string $method, array $data)
	{
		$dataBody["app_id"] = $this->getAppID();
		$dataBody["command"] = $data;
		$dataBody["nonce"] = $this->nonce;
		$dataBody["token"] = $this->token;
		$dataBody["type"] = "printCheck";
		return $this->sendRequest(
			$method,
			self::COMMAND_URL,
			$dataBody
		);
	}

	/**
	 * Метод выполняет запрос на печать чека возврата прихода на ККТ.
	 *
	 * @param array $data Маасив параметров чека.
	 *
	 * @return bool|string Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws Exception
	 */
	public function printPurchaseReturn(string $method, array $data)
	{
		$data["app_id"] = $this->getAppID();
		$data["nonce"] = $this->nonce;
		$data["token"] = $this->token;
		$data["type"] = "printPurchaseReturn";

		return $this->sendRequest(
			$method,
			self::COMMAND_URL,
			$data
		);
	}

	/**
	 * Метод выполняет запрос на получение информации о состоянии системы.
	 *
	 * @return bool|string Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws Exception
	 */
	public function getSystemStatus(string $method)
	{
		return $this->sendRequest(
			$method,
			self::GET_SYSTEM_STATUS,
			[
				"app_id" => $this->getAppID(),
				"nonce" => $this->nonce,
				"token" => $this->token,
			]
		);
	}

	/**
	 * Метод выполняет запрос на печать чека возврата прихода на ККТ.
	 *
	 * @param int|string $data CommandID чека.
	 *
	 * @return bool|string Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws Exception
	 */
	public function dataCommandID(string $method, string $data)
	{
		$dataBody["nonce"] = $this->nonce;
		$dataBody["token"] = $this->token;
		$dataBody["app_id"] = $this->getAppID();
		return $this->sendRequest(
			$method,
			self::COMMAND_URL . "/" . $data,
			$dataBody
		);
	}

	/**
	 * Метод выполняет отпавку запроса на сервис.
	 *
	 * @param string $url Адрес отправки запрса.
	 * @param array $params Массив параметров запроса.
	 *
	 * @return bool|string Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws \JsonException
	 */
	private function sendRequest($method, string $url, array $params)
	{
		array_walk_recursive(
			$params,
			static function (&$val) {
				if (is_null($val)) {
					$val = '';
				}
			}
		);
		if ($method === "GET" || $url === self::GET_TOKEN_URL) {
			$isGet = true;
		} else {
			$isGet = false;
		}
		var_dump(self::BASE_URL . $url . "?" . http_build_query($params));
		$cURL = curl_init();

		if ($isGet) {
			curl_setopt_array(
				$cURL,
				[
					CURLOPT_URL => self::BASE_URL . $url . "?" . http_build_query($params),
					CURLOPT_CUSTOMREQUEST => "GET",
					CURLOPT_HTTPHEADER => [
						"sign: " . $this->getSign($params),
					]
				]
			);
		} else {
			curl_setopt_array(
				$cURL,
				[
					CURLOPT_URL => self::BASE_URL . $url,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
					CURLOPT_HTTPHEADER => [
						"Content-Type: application/json; charset=utf-8",
						"accept: application/json",
						"Content-Length: " . strlen(json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
						"sign: " . $this->getSign($params),
					]
				]
			);
		}

		curl_setopt_array(
			$cURL,
			[
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 5,
			]
		);
		return curl_exec($cURL);
	}

	/**
	 * Метод выполняет генерацию идентификатора запроса и сохраняет его в свойство объекта.
	 */
	private function getNonce(): void //TODO:rename
	{
		$this->nonce = "salt_" . str_replace(".", "", microtime(true));
	}

	/**
	 * Метод генерирует подпись запроса и возвращает подпись.
	 *
	 * @param array $params Параметры запроса для генерации на основе их подписи.
	 *
	 * @return string Подпись запроса.
	 * @throws \JsonException
	 */
	private function getSign(array $params): string
	{
		return md5(
			json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . $this->getSecretKey()
		);
	}
}

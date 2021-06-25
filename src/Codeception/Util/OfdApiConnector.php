<?php

namespace Utils;

use Exception;

/**
 * Date: 23.06.2021
 *
 * @version 1.0.0
 */
class OfdApiConnector
{
	/**
	 * @var string BASE_URL Базовый адрес API сервиса.
	 * dev - https://testapi.ofd-ya.ru/ofdapi/v1/
	 * stable - https://api.ofd-ya.ru/ofdapi/v1/
	 */
	private const BASE_URL = "https://testapi.ofd-ya.ru/ofdapi/v1/";

	/**
	 * @var string STATIC_TOKEN TOKEN сервиса (заменить своим из интеграции, если не используется внешнее хранение переменной).
	 */
	private const STATIC_TOKEN = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpbm4iOlsiNzcyMjgwMTUyNCJdfQ.BSXatJyIOr6kFRSRYi1Dcd2EvknOUtJVcQypo-_gZmo";


	/**
	 *  constructor.
	 *
	 * Задает app_id и secret_key интеграции из параметров или из констант при отсутствии.
	 * Генерирует новый идентификатор команды.
	 * Получает новый токен.
	 *
	 * @throws \Exception
	 */
	public function __construct()
	{
		$this->sendRequest(
			[
				"kktRegId" => "0000000000000000",
				"fiscalSign" => "0000000000"
			]
		);
	}


	/**
	 * Метод выполняет отпавку запроса на сервис.
	 *
	 * @param array $params Массив параметров запроса.
	 *
	 * @return string|bool Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws Exception
	 */
	private function sendRequest(array $params)
	{
		$curl = curl_init();

		curl_setopt_array(
			$curl,
			[
				CURLOPT_URL => self::BASE_URL . 'documents',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => '{"fiscalDriveNumber":"9999078902001864","date":"2021-06-03","find":{"field":"fiscalDocumentNumber","value":194350}}',
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json',
					'Ofdapitoken:' . self::STATIC_TOKEN
				],
			]
		);

		$response = curl_exec($curl);

		curl_close($curl);
		return $response;
	}

	/**
	 * Метод отправляет запрос на открытие смены на ККТ.
	 *
	 * @return bool|string Ошибка выполнения запроса.|Строка JSON ответа на запрос.
	 * @throws \Exception
	 */
	public function postDocuments()
	{
		return $this->sendRequest(
//			self::COMMAND_URL,
			[
				"fiscalDriveNumber" => 9999078902001864,
				"date" => "2021-06-03",
				"find" => [
					"field" => "fiscalDocumentNumber",
					"value" => 194350 //TODO: make name
				]
			]
		);
	}
}

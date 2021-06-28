<?php

namespace Ofd\Api;

use Ofd\Api\Exception\HttpClientException;
use Ofd\Api\Http\Response;
use Ofd\Api\Http\Stream;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class SimpleHttpClient implements ClientInterface
{
	/**
	 * @var string
	 * Токен
	 */
	private const STATIC_TOKEN = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpbm4iOlsiNzcyMjgwMTUyNCJdfQ.BSXatJyIOr6kFRSRYi1Dcd2EvknOUtJVcQypo-_gZmo";

	/**
	 * @param RequestInterface $request
	 * @return ResponseInterface
	 * @throws HttpClientException
	 */
	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		#Инициализирует сеанс cURL
		$curl = curl_init();
		#Получаем url с request запроса
		$url = (string)$request->getUri();
		#Получаем метод в верхнем регистре из request запроса
		$method = strtoupper($request->getMethod());
		#Получаем параметры request запроса
		$paramsString = $request->getUri()->getQuery();
		#Если метод не GET, Возвращаем оставшееся содержимое в строке
		if ($method !== 'GET') {
			$paramsString = $request->getBody()->getContents();
		}
		if ($method === 'POST') {
			curl_setopt_array(
				$curl,
				[
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $paramsString,
					CURLOPT_HTTPHEADER => [
						'Content-Type: application/json',
						'Ofdapitoken:' . self::STATIC_TOKEN
					],
				]
			);
		} elseif ($method === 'GET') {
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		} else {
			throw new HttpClientException('Метод ' . $method . ' не поддерживается.');
		}
		#Выполняем запрос cURL
		$result = curl_exec($curl);

		$stream = new Stream('php://temp/ofd/response', 'w+');
		$stream->write($result);
		#Возвращаем информацию об определённой операции
		$statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		#Создаем объект Response
		$response = new Response();
		$response = $response->withStatus($statusCode);
		$response = $response->withBody($stream);
		#Завершаем сеанс cURL
		curl_close($curl);

		return $response;
	}
}

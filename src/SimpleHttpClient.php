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
		$curl = curl_init();

		$url = (string)$request->getUri();
		$method = strtoupper($request->getMethod());
		$params_string = $request->getUri()->getQuery();
		if ($method !== 'GET') {
			$params_string = $request->getBody()->getContents();
		}
		if ($method === 'POST') {
			curl_setopt_array(
				$curl,
				[
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $params_string,
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
		$result = curl_exec($curl);

		$stream = new Stream('php://temp/ofd/response', 'w+');
		$stream->write($result);

		$status_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

		$response = new Response();
		$response = $response->withStatus($status_code);
		$response = $response->withBody($stream);

		curl_close($curl);

		return $response;
	}
}

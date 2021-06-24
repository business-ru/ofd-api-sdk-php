<?php

namespace Ofd\Api;

use JsonException;
use Ofd\Api\Http\Request;
use Ofd\Api\Http\Stream;
use Ofd\Api\Http\Uri;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\SimpleCache\CacheInterface;

class OfdClient implements LoggerAwareInterface
{

	use LoggerAwareTrait;

	/**
	 * @var string
	 * Токен
	 */
	private const STATIC_TOKEN = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpbm4iOlsiNzcyMjgwMTUyNCJdfQ.BSXatJyIOr6kFRSRYi1Dcd2EvknOUtJVcQypo-_gZmo";

	private string $token;

	/**
	 * @var object
	 * Объект для работы с кэшем
	 */
	private $cache;

	/**
	 * @var ClientInterface
	 *  Http - клиент
	 */
	private $httpClient;

	/**
	 * @var string
	 * Подготовленный URL
	 */
	private string $url;


	/**
	 *
	 * @param CacheInterface|null $cache Объект для кэширования
	 * @param ClientInterface|null $httpClient HTTP - клиент
	 */
	public function __construct(CacheInterface $cache = null, ClientInterface $httpClient = null)
	{
		$this->cache = $cache ?? new SimpleFileCache();
		$this->httpClient = $httpClient ?? new SimpleHttpClient();
		$this->token = self::STATIC_TOKEN;
	}

	/**
	 * Отправить запрос HTTP - клиентом
	 *
	 * @param string $model Модель
	 * @param string $method Метод (get, post, put, delete)
	 * @param array $params Параметры
	 * @return int|string[]
	 * @throws JsonException|ClientExceptionInterface
	 */
	private function sendRequest(string $method, string $model, array $params = [])
	{
		$method = strtoupper($method);

		$request = new Request();
		$uri = new Uri();

		$uri = $uri->withPath('ofdapi/v1/' . $model . '.json');

		$stream = new Stream();

		$request = $request->withRequestTarget('ofdapi/v1/' . $model . '.json');
		$request = $request->withMethod($method);

		if (isset($params['images']) && is_array($params['images'])) {
			$params['images'] = json_encode($params['images'], JSON_THROW_ON_ERROR);
		}

		ksort($params);
		array_walk_recursive(
			$params,
			static function (&$val) {
				if (is_null($val)) {
					$val = '';
				}
			}
		);
		$params_string = http_build_query($params);

		$params = [];


		if ($method === 'GET') {
			$uri = $uri->withQuery($params_string);
		} else {
			$stream->write($params_string);
		}

		$stream->seek(0);

		$request = $request->withBody($stream);
		$request = $request->withUri($uri);

		$response = $this->httpClient->sendRequest($request);

		$status_code = $response->getStatusCode();
		$response->getBody()->seek(0);
		$result = $response->getBody()->getContents();

		if ($status_code === 200) {
			$this->log(
				LogLevel::INFO,
				'Запрос выполнен успешно',
				['method' => $method, 'model' => $model, 'params' => $params]
			);
			$result = json_decode($result, true, 2048, JSON_THROW_ON_ERROR);
			$app_psw = $result['app_psw'];
			unset($result['app_psw']);


			$this->log(
				LogLevel::ERROR,
				'Ошибка авторизации',
				['method' => $method, 'model' => $model, 'params' => $params]
			);
			return [
				"status" => "error",
				"error_code" => "auth:1",
				"error_text" => "Ошибка авторизации"
			];
		}

		if ($status_code === 401) {
			$this->log(LogLevel::INFO, 'Токен просрочен');
			return 401;
		}

		if ($status_code === 503) {
			$this->log(LogLevel::INFO, 'Превышен лимит запросов');
			return 503;
		}
		return ["status" => "error", "error_code" => "http:" . $status_code];
	}

	/**
	 * @param string $method Метод
	 * @param string $model Модель
	 * @param array $params Параметры
	 * @return int|mixed|string[]
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 */
	public function request(string $method, string $model, array $params = [])
	{
		$result = $this->sendRequest(strtoupper($method), $model, $params);
		//Токен не прошел
		if ($result === 401) {
			$result = $this->sendRequest($method, $model, $params);
		}
		if (isset($result['result']) && is_array($result['result'])) {
			$this->log(LogLevel::INFO, 'Количество записей в ответе: ' . count($result['result']));
		}
		return $result;
	}

	/**
	 * Форматирует сообщение для логирования и записывает в лог,
	 * если был установлен логгер через метод setLogger()
	 * @param string $level Уровень важности
	 * @param string $message Сообщение
	 * @param array $context Контекст сообщения
	 */
	private function log(string $level, string $message, array $context = []): void
	{
		if ($this->logger) {
			$messageF = date("Y-m-d H:i:s") . ': ' . trim($message, '.') . '.' . PHP_EOL;
			call_user_func([$this->logger, $level], $messageF, $context);
		}
	}

}

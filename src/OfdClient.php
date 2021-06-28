<?php

namespace Ofd\Api;

use JsonException;
use Ofd\Api\Http\Request;
use Ofd\Api\Http\Stream;
use Ofd\Api\Http\Uri;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

final class OfdClient implements LoggerAwareInterface
{
	use LoggerAwareTrait;

	private const STATIC_URL = "https://testapi.ofd-ya.ru/ofdapi/v1/";

	/**
	 * @var object
	 * Объект для работы с кэшем
	 */
	protected $cache;

	/**
	 * @var ClientInterface
	 *  Http - клиент
	 */
	private $httpClient;

	/**
	 *
	 * @param CacheInterface|null $cache Объект для кэширования
	 * @param ClientInterface|null $httpClient HTTP - клиент
	 */
	public function __construct(CacheInterface $cache = null, ClientInterface $httpClient = null)
	{
		$this->cache = $cache ?? new SimpleFileCache();
		$this->httpClient = $httpClient ?? new SimpleHttpClient();
	}

	/**
	 * Отправить запрос HTTP - клиентом
	 *
	 * @param string $model Модель
	 * @param string $method Метод (get, post, put, delete)
	 * @param array $params Параметры
	 * @return mixed
	 * @throws JsonException|ClientExceptionInterface
	 */
	private function sendRequest(string $method, string $model, array $params = [])
	{
		#Верхний регистр для метода
		$method = strtoupper($method);
		#Создаем объект
		$request = new Request();
		#Создаем объект
		$uri = new Uri();
		#Создает объект потока, куда будет попадать request body
		$stream = new Stream('php://temp/ofd/request', 'w+');
		#Создаем ссылку
		$uri = $uri->withPath(self::STATIC_URL . $model);

		$request = $request->withRequestTarget(self::STATIC_URL . $model);
		$request = $request->withMethod($method);

		$paramsString = json_encode($params, JSON_THROW_ON_ERROR);

		if ($method === 'GET') {
			$uri = $uri->withQuery($paramsString);
		} else {
			$stream->write($paramsString);
		}

		$stream->seek(0);

		$request = $request->withBody($stream);
		$request = $request->withUri($uri);

		$response = $this->httpClient->sendRequest($request);

		$status_code = $response->getStatusCode();

		$response->getBody()->seek(0);

		$result = $response->getBody()->getContents();

		if ($status_code === 200) {
			$this->log(LogLevel::INFO, 'Запрос выполнен успешно', ['method' => $method, 'model' => $model, 'params' => $params]);
			return json_decode($result, true, 2048, JSON_THROW_ON_ERROR);
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

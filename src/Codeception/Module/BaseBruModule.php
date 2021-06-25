<?php

namespace Codeception\Module;

use Codeception\Exception\BruApiException;
use Codeception\Exception\ModuleException;
use Codeception\Lib\ModuleContainer;
use Codeception\Module;
use Codeception\PHPUnit\Constraint\JsonContains;
use Codeception\PHPUnit\Constraint\JsonType as JsonTypeConstraint;
use Codeception\TestInterface;
use Exception;
use Flow\JSONPath\JSONPath;
use JsonSchema\Constraints\Constraint as JsonContraint;
use JsonSchema\Validator as JsonSchemaValidator;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Utils\JsonArray;
use Utils\JsonType;

/**
 * Абстрактный класс с общими методами для тестирования SDK и API
 * Class BaseBruModule
 * @package Codeception\Module
 */
abstract class BaseBruModule extends Module
{
	/**
	 * Список http заголовков
	 * @var string[]
	 */
	protected array $headers = [];

	/**
	 * Получаем данные в виде массива
	 * @var mixed
	 */
	protected $response;

	/**
	 * Наименование аккаунта
	 * @var string|null
	 */
	protected ?string $account = null;

	/**
	 * ID приложения (интеграции)
	 * @var mixed
	 */
	protected $appID;

	/**
	 * Секретный ключ приложения
	 * @var string|null
	 */
	protected ?string $secretKey = null;

	/**
	 * Установка параметров для работы с API
	 * @return void
	 * @throws \Exception
	 */
	protected function setConfig(): void
	{
		#Создаем пустой массив для файлов
		$filesArray = [];
		#Парсим список файлов в папке _data/json
		$pathJson = glob(getenv('PROJECT_DIR') . 'tests/_data/json/' . '*.json', GLOB_BRACE);
		foreach ($pathJson as $file) {
			#Добавляем в пустой массив - список всех json файлов
			$filesArray[] = basename($file, '.json');
		}
		#Получаем наименование порта из...
		$request = new Request($_SERVER);
		$requestArgv = $request->query->get('argv');
		$port = "";
		if (isset($requestArgv)) {
			foreach ($requestArgv as $key => $value) {
				#Ищем аргумент "--env", получаем номер ключа
				if ($value === "--env" && isset($_SERVER["argv"][$key + 1])) {
					#Получаем наименование порта
					$port = $_SERVER["argv"][$key + 1];
				}
			}
		}
		#Проверка, есть ли порт в массиве - список файлов
		if (in_array($port, $filesArray, true)) {
			$configFile = getenv('PROJECT_DIR') . 'tests/_data/json/' . $port . '.json';
			#Если файла нет, вызываем исключение
			if (!file_exists($configFile)) {
				throw new Exception('Измените путь к файлу');
			}
			$cfg = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
			#Наименование аккаунта
			$this->account = $cfg['account'];
			#ID Приложения
			$this->appID = $cfg['app_id'];
			#Секретный ключ
			$this->secretKey = $cfg['secret_key'];
		}
	}

	/**
	 * @param \Codeception\Lib\ModuleContainer $moduleContainer
	 * @param null $config
	 * @throws \Exception
	 */
	public function __construct(ModuleContainer $moduleContainer, $config = null)
	{
		parent::__construct($moduleContainer, $config);
		$this->setConfig();
	}

	/**
	 * Проверьте, есть ли в данных непечатаемые байты и не является ли это допустимой строкой Unicode
	 *
	 * @param string $data - текстовая или двоичная строка данных
	 * @return bool
	 */
	private function isBinaryData(string $data): bool
	{
		return !ctype_print($data) && false === mb_detect_encoding($data, mb_detect_order(), true);
	}

	/**
	 * Отформатируйте двоичную строку для отладочной печати
	 * @param string $data - строка двоичных данных
	 * @return string - строка отладки
	 */
	private function binaryToDebugString(string $data): string
	{
		return '[binary-data length:' . strlen($data) . ' md5:' . md5($data) . ']';
	}

	/**
	 * Проверяет, соответствует ли последний ответ предоставленной схеме json (https://json-schema.org/)
	 * Предоставьте схему как строку json.
	 *
	 * Примеры:
	 *
	 * ``` php
	 * // response: {"name": "john", "age": 20}
	 * $I->responseIsValidOnJsonSchemaString('{"type": "object"}');
	 *
	 * // response {"name": "john", "age": 20}
	 * $schema = [
	 *  "properties" => [
	 *      "age" => [
	 *          "type" => "integer",
	 *          "minimum" => 18
	 *      ]
	 *  ]
	 * ];
	 * $I->responseIsValidOnJsonSchemaString(json_encode($schema));
	 *
	 * ```
	 *
	 * @param string $schema
	 * @part json
	 * @throws \JsonException
	 */
	private function responseIsValidOnJsonSchemaString(string $schema): void
	{
		$responseContent = $this->grabResponse();
		Assert::assertNotEquals('', $responseContent, 'ответ пуст');
		$responseObject = $this->decodeAndValidateJson($responseContent);

		Assert::assertNotEquals('', $schema, 'схема пуста');
		$schemaObject = $this->decodeAndValidateJson(
			$schema,
			"Недействительная схема json: %s. Системное сообщение: %s."
		);

		$validator = new JsonSchemaValidator();
		$validator->validate($responseObject, $schemaObject, JsonContraint::CHECK_MODE_VALIDATE_SCHEMA);
		$outcome = $validator->isValid();
		$error = "";
		if (!$outcome) {
			$errors = $validator->getErrors();
			$error = array_shift($errors)["message"];
		}
		\PHPUnit\Framework\Assert::assertTrue(
			$outcome,
			$error
		);
	}

	/**
	 * @throws \Flow\JSONPath\JSONPathException
	 * @throws \Exception
	 */
	private function filterByJsonPath($jsonPath): void
	{
		if (!class_exists(JSONPath::class)) {
			throw new \Exception('JSONPath library not installed. Please add `softcreatr/jsonpath` to composer.json');
		}
		(new JSONPath($this->response))->find($jsonPath)->getData();
	}

	/**
	 * Преобразует строку в json и утверждает, что при декодировании не было ошибок.
	 *
	 * @param string $jsonString строка в кодировке json
	 * @param string $errorFormat необязательная строка для настраиваемого формата sprintf
	 * @throws \JsonException
	 */
	private function decodeAndValidateJson(
		string $jsonString,
		string $errorFormat = "Неверный json: %s. Системное сообщение: %s."
	) {
		$json = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
		$errorCode = json_last_error();
		$errorMessage = json_last_error_msg();
		\PHPUnit\Framework\Assert::assertEquals(
			JSON_ERROR_NONE,
			$errorCode,
			sprintf(
				$errorFormat,
				$jsonString,
				$errorMessage
			)
		);
		return $json;
	}

	/**
	 * Если тест не прошел, добавляем response в allure report
	 * @param \Codeception\TestInterface $test
	 * @param \Exception $fail
	 * @return void
	 * @throws \JsonException
	 */
	public function _failed(TestInterface $test, $fail): void
	{
		$response = $this->grabResponse();
		if (!$response) {
			return;
		}
		$printedResponse = $response;
		if ($this->isBinaryData($printedResponse)) {
			$printedResponse = $this->binaryToDebugString($printedResponse);
		}
		$test->getMetadata()->addReport('body', $printedResponse);
	}

	/**
	 * Проверяет, был ли последний ответ действительным JSON.
	 * Это делается с помощью функции json_last_error.
	 *
	 * @part json
	 * @throws \JsonException
	 */
	public function responseIsJson(): void
	{
		$response = $this->grabResponse();
		\PHPUnit\Framework\Assert::assertNotEquals('', $response, 'ответ пуст');
		$this->decodeAndValidateJson($response);
	}

	/**
	 * Проверяет, содержит ли последний ответ текст.
	 *
	 * @param $text
	 * @part json
	 * @throws \JsonException
	 */
	public function responseContains($text): void
	{
		$response = $this->grabResponse();
		$this->assertStringContainsString($text, $response, "Ответ не содержит текст: " . $text);
	}


	/**
	 * Проверяет, содержит ли последний ответ JSON предоставленный массив.
	 * Ответ преобразуется в массив с json_decode ($ response, true)
	 * Таким образом, JSON представлен ассоциативным массивом.
	 * Этот метод соответствует тому массиву ответов, который содержит предоставленный массив.
	 *
	 * Примеры:
	 *
	 * ``` php
	 * // response: {name: john, email: john@gmail.com}
	 * $I->responseContainsJson(array('name' => 'john'));
	 *
	 * // response {user: john, profile: { email: john@gmail.com }}
	 * $I->responseContainsJson(array('email' => 'john@gmail.com'));
	 *
	 * ```
	 *
	 * Этот метод рекурсивно проверяет, можно ли найти один массив внутри другого.
	 *
	 * @param array $json
	 * @part json
	 * @throws \JsonException
	 */
	public function responseContainsJson(array $json = []): void
	{
		Assert::assertThat(
			json_encode($this->response, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			new JsonContains($json)
		);
	}

	/**
	 * Проверяет, соответствует ли последний ответ предоставленной схеме json (https://json-schema.org/)
	 * Укажите схему как относительный путь к файлу в каталоге вашего проекта или как абсолютный путь
	 *
	 * @param string $schemaFilename
	 * @part json
	 * @throws \Codeception\Exception\ModuleException
	 * @throws \JsonException
	 * @see codecept_absolute_path()
	 *
	 */
	public function responseIsValidOnJsonSchema(string $schemaFilename): void
	{
		$file = codecept_absolute_path($schemaFilename);
		if (!file_exists($file)) {
			throw new ModuleException(__CLASS__, "Файл $file не существует");
		}
		$this->responseIsValidOnJsonSchemaString(file_get_contents($file));
	}

	/**
	 * Возвращает текущий ответ, чтобы его можно было использовать в следующих шагах сценария.
	 *
	 * Пример:
	 *
	 * ``` php
	 * $user_id = $I->grabResponse();
	 * ```
	 *
	 * @part json
	 * @throws \JsonException
	 */
	public function grabResponse()
	{
		if (!is_string($this->response)) {
			$responseString = json_encode(
				$this->response,
				JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
			);
		} else {
			return $this->response;
		}
		return $responseString;
	}

	/**
	 * См. [#Jsonpath] (# jsonpath) для получения общей информации о JSONPath.
	 * Даже для одного значения возвращается массив.
	 * Пример:
	 *
	 * ``` php
	 * // соответствует первому `user.id` в json
	 * $firstUserId = $I->grabDataFromResponseByJsonPath('$..users[0].id');
	 * ```
	 *
	 * @param string $jsonPath
	 * @return array Массив совпадающих элементов
	 * @throws \Exception
	 * @part json
	 */
	public function grabDataFromResponseByJsonPath(string $jsonPath): array
	{
		$response = $this->grabResponse();
		$grabDataFromResponse = (new JsonArray($response))->filterByJsonPath($jsonPath);
		if (empty($grabDataFromResponse)) {
			throw new BruApiException(
				'Ошибка! Неправильный синтаксис в пути.' . PHP_EOL . $jsonPath . PHP_EOL . $response
			);
		}
		return $grabDataFromResponse;
	}

	/**
	 * Проверяет, соответствует ли структура json в ответ предоставленному xpath.
	 * JSON не должен проверяться на XPath, но его можно преобразовать в xml и использовать с XPath.
	 * Это утверждение позволяет вам проверить структуру ответа json.
	 *     *
	 * ```json
	 *   { "store": {
	 *       "book": [
	 *         { "category": "reference",
	 *           "author": "Nigel Rees",
	 *           "title": "Sayings of the Century",
	 *           "price": 8.95
	 *         },
	 *         { "category": "fiction",
	 *           "author": "Evelyn Waugh",
	 *           "title": "Sword of Honour",
	 *           "price": 12.99
	 *         }
	 *    ],
	 *       "bicycle": {
	 *         "color": "red",
	 *         "price": 19.95
	 *       }
	 *     }
	 *   }
	 * ```
	 *
	 * ```php
	 * // хотя бы у одной книги в магазине есть автор
	 * $I->responseJsonMatchesXpath('//store/book/author');
	 * // у первой книги в магазине есть автор
	 * $I->responseJsonMatchesXpath('//store/book[1]/author');
	 * // хотя бы один товар в магазине имеет цену
	 * $I->responseJsonMatchesXpath('/store//price');
	 * ```
	 * @param string $xpath
	 * @part json
	 * @throws \JsonException
	 */
	public function responseJsonMatchesXpath(string $xpath): void
	{
		$response = $this->grabResponse();
		$this->assertGreaterThan(
			0,
			(new JsonArray($response))->filterByXPath($xpath)->length,
			"Received JSON did not match the XPath `$xpath`.\nJson Response: \n" . $response
		);
	}

	/**
	 * Проверяет соответствие JSON предоставленным типам.
	 * Если вы не знаете фактических значений возвращаемых данных JSON, вы можете сопоставить их по типу.
	 * Запускает проверку с корневого элемента. Если данные JSON представляют собой массив, он проверит все его элементы.
	 * Вы можете указать путь в json, который следует проверить с помощью JsonPath
	 *
	 * Basic example:
	 *
	 * ```php
	 * // {'user_id': 1, 'name': 'davert', 'is_active': false}
	 * $I->responseMatchesJsonType([
	 *      'user_id' => 'integer',
	 *      'name' => 'string|null',
	 *      'is_active' => 'boolean'
	 * ]);
	 *
	 * // сузить соответствие с JsonPath:
	 * // {"users": [{ "name": "davert"}, {"id": 1}]}
	 * $I->responseMatchesJsonType(['name' => 'string'], '$.users[0]');
	 * ```
	 *
	 * Вы можете проверить, содержит ли запись поля с ожидаемыми типами данных.
	 * Список возможных типов данных:
	 *
	 * * string
	 * * integer
	 * * float
	 * * array (объект json также является массивом)
	 * * boolean
	 * * null
	 *
	 * Вы также можете использовать структуры вложенных типов данных и определить несколько типов для одного и того же поля:
	 *
	 * ```php
	 * // {'user_id': 1, 'name': 'davert', 'company': {'name': 'Codegyre'}}
	 * $I->responseMatchesJsonType([
	 *      'user_id' => 'integer|string', // несколько типов
	 *      'company' => ['name' => 'string']
	 * ]);
	 * ```
	 *
	 * Вы также можете применять фильтры для проверки значений.
	 * Фильтр можно применить с помощью символа `:` после объявления типа,или после другого фильтра,
	 * если вам нужно больше одного.
	 *
	 * Вот список возможных фильтров:
	 *
	 * * `integer:>{val}` - проверяет, что целое число больше, чем {val} (работает также с типами float и string).
	 * * `integer:<{val}` - проверяет, что целое число меньше, чем {val} (работает также с типами float и string).
	 * * `string:url` - проверяет, что значение является действительным URL-адресом.
	 * * `string:date` - проверяет, является ли значение датой в формате JavaScript:
	 * https://weblog.west-wind.com/posts/2014/Jan/06/JavaScript-JSON-Date-Parsing-and-real-Dates
	 * * `string:email` - проверяет, является ли значение действительным адресом электронной почты
	 * в соответствии с http://emailregex.com/
	 * * `string:regex({val})` -проверяет, соответствует ли строка регулярному выражению, предоставленному с {val}
	 *
	 * Вот как можно использовать фильтры:
	 *
	 * ```php
	 * // {'user_id': 1, 'email' => 'davert@codeception.com'}
	 * $I->responseMatchesJsonType([
	 *      'user_id' => 'string:>0:<1000', // можно использовать несколько фильтров
	 *      'email' => 'string:regex(~\@~)' //мы просто проверяем, что @ char включен
	 * ]);
	 *
	 * // {'user_id': '1'}
	 * $I->responseMatchesJsonType([
	 *      'user_id' => 'string:>0', // работает и со строками
	 * ]);
	 * ```
	 *
	 * Вы также можете добавить собственные фильтры, используя `{@link JsonType::addCustomFilter()}`.
	 * See [JsonType reference](http://codeception.com/docs/reference/JsonType).
	 *
	 * @part json
	 * @param array $jsonType
	 * @param string|null $jsonPath
	 * @throws \Exception
	 * @see JsonType
	 */
	public function responseMatchesJsonType(array $jsonType, string $jsonPath = null): void
	{
		if ($jsonPath) {
			$this->filterByJsonPath($jsonPath);
		}
		Assert::assertThat($this->response, new JsonTypeConstraint($jsonType));
	}

	/**
	 * Проверяет, совпадает ли ответ с предоставленным.
	 * @part json
	 * @param $expected
	 * @throws \JsonException
	 */
	public function responseEquals($expected): void
	{
		$response = $this->grabResponse();
		$this->assertEquals($expected, $response);
	}

}

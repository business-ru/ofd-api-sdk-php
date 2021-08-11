# ofd-api-sdk-php

## О проекте

Данная библиотека предназначена для работы с
сервисом [ОФД-Я](https://ofd-ya.ru/).

## Требования

* PHP 7.4 и выше
* PHP extension cURL

## Установка

```
composer require business-ru/ofd-api-sdk-php
```

Документация: https://ofd-ya.ru/docs/API_OFD_YA.pdf

## Использование

Добавляем в .env

```ini
OFD_TOKEN = Токен
```

## Пример использования

```php
/**
* Инициализируем класс
* @var OfdClient|null
*/
private ?OfdClient $ofdClient = null;

/**
* Общий метод, для любой модели
* Метод позволяет выполнить запрос к API OFD
* Для ofd-api-sdk-php
* @param string $method - Наименование метода
* @param string $model - Наименование модели
* @param array<array> $params - Параметры запроса
* @return int|mixed|string[]
* @throws \JsonException
* @throws ClientExceptionInterface
* @throws DecodingExceptionInterface
* @throws RedirectionExceptionInterface
* @throws ServerExceptionInterface
* @throws TransportExceptionInterface
*/
public function ofdApiRequest(string $method, string $model, array $params = [])
{
	$this->ofdClient = new OfdClient();
	$this->response = $this->ofdClient->request(strtoupper($method), $model, $params);
	return $this->response;
}

```

## Основные термины

В таблице приведены термины в порядке удобном для понимания.
<table>
    <tr>
        <td><strong>ОФД</strong></td>
        <td><strong>О</strong>ператор <strong>Ф</strong>искальных <strong>Д</strong>анных</td>
        <td>Сервис принимающий с кассого аппарата данные о выбитых чеках и передающий их в налоговую службу.</td>
    </tr>
    <tr>
        <td><strong>ККТ</strong></td>
        <td><strong>К</strong>онтрольно <strong>К</strong>ассовая <strong>Т</strong>ехника</td>
        <td>Кассовый аппарат выбивающий чеки либо на бумаге либо в электронном виде.</td>
    </tr>
    <tr>
        <td><strong>ККМ</strong></td>
        <td><strong>К</strong>онтрольно <strong>К</strong>ассовая <strong>М</strong>ашина</td>
        <td>Устаревшее название ККТ.</td>
    </tr>
    <tr>
        <td><strong>ФД</strong></td>
        <td><strong>Ф</strong>искальный <strong>Д</strong>окумент</td>
        <td>Документ отправляемый в налоговую службу. Кассовый чек является частным случаем ФД.
        </td>
    </tr>
    <tr>
        <td><strong>ФФД</strong></td>
        <td><strong>Ф</strong>ормат <strong>Ф</strong>искальных <strong>Д</strong>анных</td>
        <td>По сути спецификация описывающая свойства (реквизиты) и их значения которые могут быть у ФД.</td>
    </tr>
    <tr>
        <td><strong>Тег ФД</strong></td>
        <td>-</td>
        <td>По сути имя свойства (реквизита) ФД которые передаются в ОФД. Например, в теге 1037 касса передает
        свой регистрационный номер.
        </td>
    </tr>
</table>

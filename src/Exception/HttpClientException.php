<?php
namespace Ofd\Api\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use Exception;

class HttpClientException extends Exception implements ClientExceptionInterface
{

}

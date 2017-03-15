<?php

namespace AmoCRM\Request;

use AmoCRM\Exception;
use AmoCRM\NetworkException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;

/**
 * Class Request
 *
 * Класс отправляющий запросы к API amoCRM используя cURL
 *
 * @package AmoCRM\Request
 * @author dotzero <mail@dotzero.ru>
 * @link http://www.dotzero.ru/
 * @link https://github.com/dotzero/amocrm-php
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Request implements LoggerAwareInterface
{
    /**
     * @var bool Использовать устаревшую схему авторизации
     */
    protected $v1 = false;

    /**
     * @var ParamsBagInterface Экземпляр класса имплементирующего ParamsBagInterface
     */
    protected $parameters;

    /**
     * @var LoggerInterface Экземпляр класса имплементирующего LoggerInterface
     */
    protected $logger;

    /**
     * Request constructor
     *
     * @throws NetworkException
     */
    public function __construct()
    {
        if (!function_exists('curl_init')) {
            throw new NetworkException('The cURL PHP extension was not loaded');
        }

        $this->setParameters(new ParamsBag());
        $this->setLogger(new NullLogger());
    }

    /**
     * Устанавливает экземпляр класса имплементирующего ParamsBagInterface
     *
     * @param ParamsBagInterface $parameters экземпляр класса имплементирующего ParamsBagInterface
     */
    public function setParameters(ParamsBagInterface $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Возвращает экземпляр класса имплементирующего ParamsBagInterface
     *
     * @return ParamsBagInterface экземпляр класса имплементирующего ParamsBagInterface
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Устанавливает экземпляр класса имплементирующего LoggerInterface
     *
     * @param LoggerInterface $logger экземпляр класса имплементирующего LoggerInterface
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Выполнить HTTP GET запрос и вернуть тело ответа
     *
     * @param string $url Запрашиваемый URL
     * @param array $parameters Список GET параметров
     * @param null|string $modified Значение заголовка IF-MODIFIED-SINCE
     * @return mixed
     * @throws Exception
     * @throws NetworkException
     */
    protected function getRequest($url, $parameters = [], $modified = null)
    {
        if (!empty($parameters)) {
            $this->parameters->addGet($parameters);
        }

        return $this->request($url, $modified);
    }

    /**
     * Выполнить HTTP POST запрос и вернуть тело ответа
     *
     * @param string $url Запрашиваемый URL
     * @param array $parameters Список POST параметров
     * @return mixed
     * @throws Exception
     * @throws NetworkException
     */
    protected function postRequest($url, $parameters = [])
    {
        if (!empty($parameters)) {
            $this->parameters->addPost($parameters);
        }

        return $this->request($url);
    }

    /**
     * Подготавливает список заголовков HTTP
     *
     * @param null|string $modified Значение заголовка IF-MODIFIED-SINCE
     * @return array
     */
    protected function prepareHeaders($modified = null)
    {
        $headers = ['Content-Type: application/json'];

        if ($modified !== null) {
            $headers[] = 'IF-MODIFIED-SINCE: ' . (new \DateTime($modified))->format(\DateTime::RFC1123);
        }

        return $headers;
    }

    /**
     * Подготавливает URL для HTTP запроса
     *
     * @param string $url Запрашиваемый URL
     * @return string
     */
    protected function prepareEndpoint($url)
    {
        if ($this->v1 === false) {
            $query = http_build_query(array_merge($this->parameters->getGet(), [
                'USER_LOGIN' => $this->parameters->getAuth('login'),
                'USER_HASH' => $this->parameters->getAuth('apikey'),
            ]));
        } else {
            $query = http_build_query(array_merge($this->parameters->getGet(), [
                'login' => $this->parameters->getAuth('login'),
                'api_key' => $this->parameters->getAuth('apikey'),
            ]));
        }

        return sprintf('https://%s.amocrm.ru%s?%s', $this->parameters->getAuth('domain'), $url, $query);
    }

    /**
     * Выполнить HTTP запрос и вернуть тело ответа
     *
     * @param string $url Запрашиваемый URL
     * @param null|string $modified Значение заголовка IF-MODIFIED-SINCE
     * @return mixed
     * @throws Exception
     * @throws NetworkException
     */
    protected function request($url, $modified = null)
    {
        $headers = $this->prepareHeaders($modified);
        $endpoint = $this->prepareEndpoint($url);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $this->logger->debug('Set CURLOPT_URL to {endpoint}', ['endpoint' => $endpoint]);
        $this->logger->debug('Set CURLOPT_HTTPHEADER to {headers}', ['headers' => $headers]);

        if (count($this->parameters->getPost())) {
            $fields = json_encode([
                'request' => $this->parameters->getPost(),
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            $this->logger->debug('Set CURLOPT_POSTFIELDS to {fields}', ['fields' => $fields]);
        }

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        $this->logger->debug('Result of curl_exec is {curl_exec}', ['curl_exec' => $result]);
        $this->logger->debug('Result of curl_getinfo is {curl_getinfo}', ['curl_getinfo' => $info]);
        $this->logger->debug('Result of curl_error is {curl_error}', ['curl_error' => $error]);
        $this->logger->debug('Result of curl_errno is {curl_errno}', ['curl_errno' => $errno]);

        if ($result === false && !empty($error)) {
            throw new NetworkException($error, $errno);
        }

        return $this->parseResponse($result, $info);
    }

    /**
     * Парсит HTTP ответ, проверяет на наличие ошибок и возвращает тело ответа
     *
     * @param string $response HTTP ответ
     * @param array $info Результат функции curl_getinfo
     * @return mixed
     * @throws Exception
     */
    protected function parseResponse($response, $info)
    {
        $result = json_decode($response, true);

        if (!isset($result['response'])) {
            return false;
        } elseif (floor($info['http_code'] / 100) >= 3) {
            $code = 0;
            if (isset($result['response']['error_code']) && $result['response']['error_code'] > 0) {
                $code = $result['response']['error_code'];
            }
            if ($this->v1 === false) {
                throw new Exception($result['response']['error'], $code);
            } else {
                throw new Exception(json_encode($result['response']));
            }
        }

        return $result['response'];
    }
}

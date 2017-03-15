<?php

namespace AmoCRM;

use AmoCRM\Models\ModelInterface;
use AmoCRM\Request\ParamsBag;
use AmoCRM\Helpers\Fields;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;

/**
 * Class Client
 *
 * Основной класс для получения доступа к моделям amoCRM API
 *
 * @package AmoCRM
 * @author dotzero <mail@dotzero.ru>
 * @link http://www.dotzero.ru/
 * @link https://github.com/dotzero/amocrm-php
 * @property \AmoCRM\Models\Account $account
 * @property \AmoCRM\Models\Call $call
 * @property \AmoCRM\Models\Catalog $catalog
 * @property \AmoCRM\Models\CatalogElement $catalog_element
 * @property \AmoCRM\Models\Company $company
 * @property \AmoCRM\Models\Contact $contact
 * @property \AmoCRM\Models\Customer $customer
 * @property \AmoCRM\Models\CustomersPeriods $customers_periods
 * @property \AmoCRM\Models\CustomField $custom_field
 * @property \AmoCRM\Models\Lead $lead
 * @property \AmoCRM\Models\Links $links
 * @property \AmoCRM\Models\Note $note
 * @property \AmoCRM\Models\Pipelines $pipelines
 * @property \AmoCRM\Models\Task $task
 * @property \AmoCRM\Models\Transaction $transaction
 * @property \AmoCRM\Models\Unsorted $unsorted
 * @property \AmoCRM\Models\Webhooks $webhooks
 * @property \AmoCRM\Models\Widgets $widgets
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Client implements LoggerAwareInterface
{
    /**
     * @var Fields|null Экземпляр Fields для хранения номеров полей
     */
    public $fields = null;

    /**
     * @var ParamsBag|null Экземпляр ParamsBag для хранения аргументов
     */
    public $parameters = null;

    /**
     * @var LoggerInterface|null Экземпляр класса имплементирующего LoggerInterface
     */
    protected $logger = null;

    /**
     * Client constructor
     *
     * @param string $domain Поддомен amoCRM
     * @param string $login Логин amoCRM
     * @param string $apikey Ключ пользователя amoCRM
     */
    public function __construct($domain, $login, $apikey)
    {
        $this->parameters = new ParamsBag();
        $this->parameters->addAuth('domain', $domain);
        $this->parameters->addAuth('login', $login);
        $this->parameters->addAuth('apikey', $apikey);

        $this->fields = new Fields();
        $this->logger = new NullLogger();
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
     * Возвращает экземпляр модели для работы с amoCRM API
     *
     * @param string $name Название модели
     * @return ModelInterface
     * @throws ModelException
     */
    public function __get($name)
    {
        $classname = '\\AmoCRM\\Models\\' . $this->toCamelCase($name);

        if (!class_exists($classname)) {
            throw new ModelException('Model not exists: ' . $name);
        }

        $model = new $classname();
        $model->setParameters(clone $this->parameters);
        $model->setLogger(clone $this->logger);

        return $model;
    }

    /**
     * Приведение under_score к CamelCase
     *
     * @param string $string Строка
     * @return string Строка
     */
    private function toCamelCase($string)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }
}

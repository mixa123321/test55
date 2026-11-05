<?php

namespace app\core\helpers;

use app\core\db\DBException;
use app\core\dbal\SafeMysql;
use app\core\dbal\exceptions\DbQueryException;

class Defends
{
    const TYPE_JOBS = 'jobs';
    const TYPE_ORDERS = 'orders';
    const TYPE_ORDER_REQUEST = 'order_request';

    const TYPES = [
        self::TYPE_JOBS => [
            'time_waiting' => 90 // Блокируем на 90 секунд
        ],
        self::TYPE_ORDERS => [
            'time_waiting' => 900 // Блокируем на 15 мин
        ],
        self::TYPE_ORDER_REQUEST => [
            'time_waiting' => 900 // Блокируем на 15 мин
        ]
    ];

    const LOCK_ERROR = -1;     // Ошибка, в нормальной ситуации никогда не должно происходить
    const LOCK_NOT_UPDATE = 0; // Запись не обновилась/установилась
    const LOCK_NEW = 1;        // Добавилась новая запись. Значит сработал INSERT
    const LOCK_UPDATE = 2;     // Обновилась дата существующей записи. Значит прошло время и поле date обновилось

    const HASH_FIELD_SIZE = 32;
    const SALT_FOR_DIFFICULT_HASH = '|\^|^/|';

    const NUMBER_OF_ATTEMPTS_TO_CANCEL = 30;

    private $hash;
    private $type;

    public function setKey(string $data): Defends
    {
        $data = addslashes($data);
        if (mb_strlen($data) > self::HASH_FIELD_SIZE)
            throw new \Exception("Wrong key");
        $this->hash = $data;
        return $this;
    }

    public function setHash(string $data): Defends
    {
        $this->hash = md5($data);
        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setType(string $type): Defends
    {
        if (!isset(self::TYPES[$type]))
            throw new \Exception("Wrong type");
        $this->type = $type;
        return $this;
    }

    public function lockDate(): int
    {
        if (!$this->hash || !$this->type)
            throw new \Exception("Unknown exception");
        $time_waiting = self::TYPES[$this->type]['time_waiting'];
        $sql = "
        INSERT INTO defend (hash, type, date) VALUES (:hash, :type, NOW())
        ON DUPLICATE KEY UPDATE
            date = IF(TIMESTAMPDIFF(SECOND, date, NOW()) > :time_waiting, NOW(), date);";
        return $this->runQuery($sql, ['hash' => $this->hash, 'type' => $this->type, 'time_waiting' => $time_waiting]);
    }

    public function unLock(): Defends
    {
        if (!$this->hash || !$this->type)
            throw new \Exception("Unknown exception");
        $this->runQuery("DELETE FROM defend WHERE hash = :hash AND type = :type;", ['hash' => $this->hash, 'type' => $this->type]);
        return $this;
    }

    public function unLockAllOld(): Defends
    {
        if (!$this->type)
            throw new \Exception("Unknown exception");
        $time_waiting = self::TYPES[$this->type]['time_waiting'];
        $this->runQuery("DELETE FROM defend WHERE type = :type AND TIMESTAMPDIFF(SECOND, date, NOW()) > :time_waiting", ['type' => $this->type, 'time_waiting' => $time_waiting]);
        return $this;
    }

    private function runQuery(string $sql, array $params = []): int
    {
        $num = 0;
        while (true) {
            try {
                $result = (new SafeMysql())->execute($sql, $params);
            } catch (\Exception $e) {
                if (($e instanceof DBException || $e instanceof DbQueryException) && $e->isDeadlock($num++)) {
                    continue;
                }
                Debug::sendDebugInfo('Not fixed cron_jobs_defend', var_export([$e->getCode(), $e->getMessage()], 1));
                //Если не получилось обработать ошибку, выключаем механизм дедупликации для метода lock
                return self::LOCK_ERROR;
            }
            break;
        }
        return $result;
    }
}
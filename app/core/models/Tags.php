<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Tag;

class Tags
{
    public static function getById(int $id): ?Tag
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM tags WHERE id = :id", ['id' => $id]);
        if (empty($data))
            return null;
        return new Tag($data);
    }

    public static function getAll()
    {
        $res = [];
        foreach ((new SafeMysql())->fetchAll("SELECT * FROM tags") as $tag)
            $res[$tag['id']] = $tag['tag'];
        return $res;
    }
}

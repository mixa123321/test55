<?php

namespace app\core\entities;

use app\core\db\Mysql;

class TokenTag extends Entity
{
    private $token_id;
    private $tag_id;

    public function getTokenId()
    {
        return $this->token_id;
    }

    public function setTokenId($id)
    {
        $this->token_id = (int) $id;
    }

    public function getTagId()
    {
        return $this->tag_id;
    }

    public function setTagId($id)
    {
        $this->tag_id = (int) $id;
    }

    public function save()
    {
        $data = $this->toArray();
        if (empty($data['token_id']) || empty($data['tag_id']))
            return;
        Mysql::saveRow('tokens_tags', $data);
    }

    public function toArray()
    {
        return [
            'token_id' => $this->getTokenId(),
            'tag_id' => $this->getTagId(),
        ];
    }
}
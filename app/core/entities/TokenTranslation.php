<?php

namespace app\core\entities;

use app\core\db\Mysql;

class TokenTranslation extends Entity
{
    private $token_id;
    private $lang_id;
    private $translation;

    public function getTokenId()
    {
        return $this->token_id;
    }

    public function setTokenId($id)
    {
        $this->token_id = (int) $id;
    }

    public function getLangId()
    {
        return $this->lang_id;
    }

    public function setLangId($id)
    {
        $this->lang_id = (int) $id;
    }

    public function getTranslation()
    {
        return $this->translation;
    }

    public function setTranslation($name)
    {
        $this->translation = (string) $name;
    }

    public function save()
    {
        $data = $this->toArray();
        if (empty($data['token_id']) || empty($data['lang_id']) || empty($data['translation']))
            return;
        Mysql::saveRow('tokens_translation', $data);
    }

    public function toArray()
    {
        return [
            'token_id' => $this->getTokenId(),
            'lang_id' => $this->getLangId(),
            'translation' => $this->getTranslation(),
        ];
    }
}
<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Token;
use app\core\entities\TokenTag;
use app\core\entities\TokenTranslation;
use app\core\models\TokensTranslations;

class Advanced
{
    public static function addToken(string $token_name, int $tag_id, int $lang_id, string $translation)
    {
        $token = TokensTranslations::getToken($token_name);
        if (!empty($token))
            return 'Such a token already exists';
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $token = new Token(['token' => $token_name]);
        $token->save();
        $token_tag = new TokenTag([
            'token_id' => $token->getId(),
            'tag_id' => $tag_id,
        ]);
        $token_tag->save();
        $token_translation = new TokenTranslation([
            'token_id' => $token->getId(),
            'lang_id' => $lang_id,
            'translation' => addslashes($translation),
        ]);
        $token_translation->save();
        $mysql->commit();
        return true;
    }
}

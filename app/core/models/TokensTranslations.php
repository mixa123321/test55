<?php

namespace app\core\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;

class TokensTranslations
{
    const TAG_DEFAULT_TEMPLATE  = 'default_template';
    const TAG_MENU              = 'menu';
    const TAG_PASS              = 'pass';
    const TAG_MODELS            = 'models';
    const TAG_VIEW              = '_view';

    public static function getDefaultTemplateTokens($lang)
    {
        return self::getTokens(self::TAG_DEFAULT_TEMPLATE, $lang);
    }

    public static function getMenuTokens($lang)
    {
        return self::getTokens(self::TAG_MENU, $lang);
    }

    public static function getPassTokens($lang)
    {
        return self::getTokens(self::TAG_PASS, $lang);
    }

    public static function getModelsTokens($lang)
    {
        return self::getTokens(self::TAG_MODELS, $lang);
    }

    public static function getViewTokens($tag, $lang)
    {
        return self::getTokens($tag . self::TAG_VIEW, $lang);
    }

    public static function getTokens($tag, $lang)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom('
            SELECT t.token, IF(l.id IS NULL, t.token, ttr.translation) translation
            FROM tokens t');
        $query->addJoin('JOIN tokens_tags tt ON t.id = tt.token_id');
        $query->addJoin('JOIN tags ON tt.tag_id = tags.id AND tags.tag = :tag');
        $query->addJoin('LEFT JOIN tokens_translation ttr ON t.id = ttr.token_id');
        $query->addJoin('LEFT JOIN langs l ON ttr.lang_id = l.id AND l.code = :lang');
        $res = [];
        foreach ((new SafeMysql())->fetchAll($query->build(), ['tag' => $tag, 'lang' => $lang]) as $token)
            $res[strtolower($token['token'])] = $token['translation'];
        return $res;
    }

    public static function getAll($filters)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom('
            SELECT t.id, t.token, tags.tag, ttr.translation, l.code
            FROM tokens t');
        $query->addJoin('LEFT JOIN tokens_tags tt ON t.id = tt.token_id');
        $query->addJoin('LEFT JOIN tags ON tt.tag_id = tags.id');
        $query->addJoin('LEFT JOIN tokens_translation ttr ON t.id = ttr.token_id');
        $query->addJoin('LEFT JOIN langs l ON ttr.lang_id = l.id');
        $params = [];
        if (!empty($filters['token'])) {
            $query->addWhere("t.token LIKE :token_filter");
            $params['token_filter'] = '%' . $filters['token'] . '%';
        }
        $query->setOrderBy('t.token, tags.id');
        $res = [];
        foreach ((new SafeMysql())->fetchAll($query->build(), $params) as $token) {
            $token_id = $token['id'];
            if (empty($res[$token_id]))
                $res[$token_id] = [
                    'token' => $token['token'],
                    'tags' => [],
                    'translations' => [],
                ];
            if (!in_array($token['tag'], $res[$token_id]['tags']))
                $res[$token_id]['tags'][] = $token['tag'];
            if (empty($res[$token_id]['translations'][$token['code']]))
                $res[$token_id]['translations'][$token['code']] = $token['translation'];
        }
        return $res;
    }

    public static function getToken($token)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom('
            SELECT t.id, tt.tag_id, ttr.translation, ttr.lang_id
            FROM tokens t');
        $query->addJoin('LEFT JOIN tokens_tags tt ON t.id = tt.token_id');
        $query->addJoin('LEFT JOIN tokens_translation ttr ON t.id = ttr.token_id');
        $query->addWhere("t.token = :token");
        $data = (new SafeMysql())->fetchAll($query->build(), ['token' => $token]);
        $res = [];
        if (empty($data))
            return $res;
        foreach ($data as $token) {
            $token_id = $token['id'];
            if (empty($res[$token_id]))
                $res[$token_id] = [
                    'tags' => [],
                    'translations' => [],
                ];
            if (!in_array($token['tag_id'], $res[$token_id]['tags']))
                $res[$token_id]['tags'][] = $token['tag_id'];
            if (empty($res[$token_id]['translations'][$token['lang_id']]))
                $res[$token_id]['translations'][$token['lang_id']] = $token['translation'];
        }
        return $res;
    }

    public static function getTranslate(array $tokens, $token)
    {
        return $tokens[strtolower($token)] ?? $token;
    }
}

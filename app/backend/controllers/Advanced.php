<?php

namespace app\backend\controllers;

use app\backend\models\Advanced as AdvancedModel;
use app\core\exceptions\RedirectException;
use app\core\helpers\Request;
use app\core\models\Langs;
use app\core\models\Tags;
use app\core\models\TokensTranslations;

class Advanced extends SystemController
{
    public function languages()
    {
        $data = [
            'records' => TokensTranslations::getAll($_GET),
            'tags' => Tags::getAll(),
            'langs' => Langs::getAllForTranslate(),
        ];
        $this->render($data, 'system_settings/languages');
    }

    public function createToken()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSystem())
            return $this->response = json_encode(['error' => 'Access denied']);
        if (empty($_POST))
            return $this->response = json_encode(['error' => 'Error data']);
        $tag = Tags::getById($_POST['tag_id']);
        if (empty($tag))
            return $this->response = json_encode(['error' => 'Error data']);
        $lang = Langs::getCode($_POST['lang_id']);
        if (empty($lang))
            return $this->response = json_encode(['error' => 'Error data']);
        $errors = [];
        if (empty($_POST['token']))
            $errors[] = 'Token name cannot be empty';
        if (empty($_POST['translation']))
            $errors[] = 'Translation cannot be empty';
        if (!empty($errors))
            return $this->response = json_encode(['error' => '<li>' . implode('</li><li>', $errors) . '</li>']);
        $is_new = empty($_POST['id']);
        if ($is_new) {
            $res = AdvancedModel::addToken($_POST['token'], $tag->getId(), $_POST['lang_id'], $_POST['translation']);
            if ($res !== true)
                return $this->response = json_encode(['error' => $res]);
            return $this->response = json_encode(['success' => 'Done']);
        }

//        при редактировании тэг блокируется, а при изменении языка - меняется перевод (если есть переводы для нескольких языков)
        return $this->response = json_encode(['error' => 'Page is under construction']);
    }

    /** @throws */
    public function routes()
    {
//        $data = [];
//        $this->render($data, 'advanced/routes');
        throw new RedirectException('/system_settings');
    }

    /** @throws */
    public function roles()
    {
//        $this->render([], 'advanced/roles');
        throw new RedirectException('/system_settings');
    }

    /** @throws */
    public function userGroups()
    {
//        $this->render([], 'advanced/user_groups');
        throw new RedirectException('/system_settings');
    }
}

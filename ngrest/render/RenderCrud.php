<?php

namespace admin\ngrest\render;

use Yii;
use admin\components\Auth;
/**
 * @todo complet rewrite of this class - what is the best practive to acces data in the view? define all functiosn sindie here? re-create methods from config object?
 *  $this->config() $this....
 *
 * @author nadar
 */
class RenderCrud extends \admin\ngrest\base\Render implements \admin\ngrest\base\RenderInterface
{
    const TYPE_LIST = 'list';

    const TYPE_CREATE = 'create';

    const TYPE_UPDATE = 'update';

    public function __get($key)
    {
        return $this->config->getKey($key);
    }
    
    private $_permissions = [];
    
    public function can($type)
    {
        if (!array_key_exists($type, $this->_permissions)) {
            $this->_permissions[$type] = Yii::$app->auth->matchApi(Yii::$app->adminuser->getId(), $this->config->apiEndpoint, $type);
        }
        
        return $this->_permissions[$type];
    }

    public function render()
    {
        $view = new \yii\base\View();

        return $view->render('@admin/views/ngrest/render/crud.php', array(
            'canCreate' => $this->can(Auth::CAN_CREATE),
            'canUpdate' => $this->can(Auth::CAN_UPDATE),
            'canDelete' => $this->can(Auth::CAN_DELETE),
            'crud' => $this,
            'config' => $this->config,
            'activeWindowCallbackUrl' => 'admin/ngrest/callback',
        ));
    }

    private $_buttons = null;
    
    /**
     * collection all the buttons in the crud list.
     *
     * each items required the following keys (ngClick, icon, label):
     *
     * ```php
     * return [
     *     ['ngClick' => 'toggle(...)', 'icon' => 'fa fa-fw fa-edit', 'label' => 'Button Label']
     * ];
     * ```
     *
     * @return returns array with all buttons for this crud
     */
    public function getButtons()
    {
        if ($this->_buttons === null) {
            $buttons = [];
            // do we have an edit button
            if (count($this->getFields('update')) > 0 && $this->can(Auth::CAN_UPDATE)) {
                $buttons[] = [
                    'ngClick' => 'toggleUpdate(item.'.$this->config->getRestPrimaryKey().', $event)',
                    'icon' => 'mdi-editor-mode-edit',
                    'label' => '',
                ];
            }
            // get all activeWindows assign to the crud
            foreach ($this->getActiveWindows() as $activeWindow) {
                $buttons[] = [
                    'ngClick' => 'getActiveWindow(\''.$activeWindow['activeWindowHash'].'\', item.'.$this->config->getRestPrimaryKey().', $event)',
                    'icon' => '',
                    'label' => $activeWindow['alias'],
                ];
            }
    
            if ($this->config->isDeletable() && $this->can(Auth::CAN_DELETE)) {
                $buttons[] = [
                    'ngClick' => 'deleteItem(item.'.$this->config->getRestPrimaryKey().', $event)',
                    'icon' => '',
                    'label' => 'Löschen'  
                ];
            }
            $this->_buttons = $buttons;
        }

        return $this->_buttons;
    }

    public function apiQueryString($type)
    {
        // ngrestCall was previous ngrestExpandI18n
        // ($scope.config.apiEndpoint + '?ngrestExpandI18n=true&fields=' + $scope.config.list.join()
        return 'ngrestCall=true&ngrestCallType='.$type.'&fields='.implode(',', $this->getFields($type)).'&expand='.implode(',', $this->config->extraFields);
    }

    public function getFields($type)
    {
        $fields = [];
        foreach ($this->config->getKey($type) as $item) {
            $fields[] = $item['name'];
        }

        return $fields;
    }

    public function getActiveWindows()
    {
        return ($activeWindows = $this->config->getKey('aw')) ? $activeWindows : [];
    }

    /**
     * @todo do not return the specofic type content, but return an array contain more infos also about is multi linguage and foreach in view file! 
     * @param unknown_type $element
     * @param string       $configContext list,create,update
     */
    public function createElements($element, $configContext)
    {
        if ($element['i18n'] && $configContext !== self::TYPE_LIST) {
            $return = [];
            foreach (\admin\models\Lang::find()->all() as $l => $v) {
                $ngModel = $this->i18nNgModelString($configContext, $element['name'], $v->short_code);
                $id = 'id-'.md5($ngModel.$v->short_code);
                // anzahl cols durch anzahl sprachen
                $return[] = [
                    'id' => $id,
                    'label' => $element['alias'] . ' ' . $v->name,
                    'html' => $this->renderElementPlugins($configContext, $element['plugins'], $id, $element['name'], $ngModel, $element['alias'] . ' ' . $v->name, $element['gridCols'])
                ];
            }

            return $return;
        }

        if ($element['i18n'] && $configContext == self::TYPE_LIST) {
            $element['name'] = $element['name'].'.de'; // @todo get default language!
        }

        $ngModel = $this->ngModelString($configContext, $element['name']);
        $id = 'id-'.md5($ngModel);

        return [
            [
                'id' => $id,
                'label' => $element['alias'],
                'html' => $this->renderElementPlugins($configContext, $element['plugins'], $id, $element['name'], $ngModel, $element['alias'], $element['gridCols']),
            ]
        ];
    }

    private function renderElementPlugins($configContext, $plugins, $elmnId, $elmnName, $elmnModel, $elmnAlias, $elmnGridCols)
    {
        $doc = new \DOMDocument('1.0');

        foreach ($plugins as $key => $plugin) {
            $doc = $this->renderPlugin($doc, $configContext, $plugin['class'], $plugin['args'], $elmnId, $elmnName, $elmnModel, $elmnAlias, $elmnGridCols);
        }

        return $doc->saveHTML();
    }

    private function renderPlugin($DOMDocument, $configContext, $className, $classArgs, $elmnId, $elmnName, $elmnModel, $elmnAlias, $elmnGridCols)
    {
        $ref = new \ReflectionClass($className);
        $obj = $ref->newInstanceArgs($classArgs);
        $obj->setConfig($elmnId, $elmnName, $elmnModel, $elmnAlias, $elmnGridCols);
        $method = 'render'.ucfirst($configContext);

        return $obj->$method($DOMDocument);
    }

    private function ngModelString($configContext, $fieldId)
    {
        return 'data.'.$configContext.'.'.$fieldId;
    }

    private function i18nNgModelString($configContext, $fieldId, $lang)
    {
        return 'data.'.$configContext.'.'.$fieldId.'[\''.$lang.'\']';
    }
}

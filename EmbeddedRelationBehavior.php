<?php
/**
 * @link https://github.com/himiklab/yii2-common
 * @copyright Copyright (c) 2014-2018 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\yii2\common;

use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

/**
 * 'embeddedRelationNames' => ['<relation_name>']
 */
class EmbeddedRelationBehavior extends Behavior
{
    public $embeddedRelationNames = [];

    protected $changedRelationAttributes = [];
    protected $relationAttributes = [];
    protected $loadedRelations = [];

    public function events()
    {
        return [
            ActiveRecord::EVENT_INIT => 'behaviorInit'
        ];
    }

    public function behaviorInit()
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;

        foreach ($this->embeddedRelationNames as $currentRelationName) {
            /** @var \yii\db\ActiveQuery $relationQuery */
            $relationQuery = $model->{'get' . $currentRelationName}();
            /** @var ActiveRecord $relationModel */
            $relationModel = new $relationQuery->modelClass;

            foreach ($relationModel->safeAttributes() as $currentAttribute) {
                if (isset($this->relationAttributes[$currentAttribute])) {
                    throw new InvalidConfigException('Attribute name already exists in other relation.');
                }
                $this->relationAttributes[$currentAttribute] = [
                    'relation' => $currentRelationName,
                    'value' => null
                ];
            }
        }
    }

    public function __get($name)
    {
        if ($this->behaviorIsAttributeExists($name)) {
            if ($this->relationAttributes[$name]['value'] === null) {
                $this->behaviorLoadRelation($this->relationAttributes[$name]['relation']);
            }

            return $this->relationAttributes[$name]['value'];
        } else {
            return parent::__get($name);
        }
    }

    public function __set($name, $value)
    {
        if ($this->behaviorIsAttributeExists($name)) {
            if ($this->relationAttributes[$name]['value'] === null) {
                $this->behaviorLoadRelation($this->relationAttributes[$name]['relation']);
            }

            if (!isset($this->changedRelationAttributes[$name]) &&
                $this->relationAttributes[$name]['value'] != $value
            ) {
                $this->changedRelationAttributes[$name] = true;
            }

            $this->relationAttributes[$name]['value'] = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    public function canGetProperty($name, $checkVars = true)
    {
        if ($this->behaviorIsAttributeExists($name)) {
            return true;
        }

        return parent::canGetProperty($name, $checkVars);
    }

    public function canSetProperty($name, $checkVars = true)
    {
        if ($this->behaviorIsAttributeExists($name)) {
            return true;
        }

        return parent::canSetProperty($name, $checkVars);
    }

    public function getChangedRelationAttributes()
    {
        return $this->changedRelationAttributes;
    }

    protected function behaviorLoadRelation($relationName)
    {
        $model = $this->owner;
        if (isset($this->loadedRelations[$relationName]) || $model->{$relationName} === null) {
            return;
        }

        foreach ($this->relationAttributes as $key => &$value) {
            if ($value['relation'] === $relationName) {
                $value['value'] = $model->{$relationName}->{$key};
            }
        }
        unset($value);

        $this->loadedRelations[$relationName] = true;
    }

    protected function behaviorIsAttributeExists($name)
    {
        return \array_key_exists($name, $this->relationAttributes);
    }
}

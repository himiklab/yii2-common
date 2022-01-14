<?php
/**
 * @link https://github.com/himiklab/yii2-common
 * @copyright Copyright (c) 2014-2018 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\yii2\common;

use Yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

/**
 *  'relations' => [
 *      <relation_name> => [
 *          'type' => MultipleListBehavior::RELATION_TYPE_JUNCTION,
 *          'attribute' => <junction_table_class_name>
 *      ],
 *  ],
 *
 *  'relations' => [
 *      <relation_name> => [
 *          'type' => MultipleListBehavior::RELATION_TYPE_ATTRIBUTE,
 *          'attribute' => <attribute_from_relation>
 *      ],
 *  ],
 */
class MultipleListBehavior extends Behavior
{
    const RELATION_TYPE_JUNCTION = 1;
    const RELATION_TYPE_ATTRIBUTE = 2;

    /** @var array */
    public $relations = [];

    /** @var string */
    public $attributeSuffix = 'List';

    /** @var array */
    protected $attributesValues = [];

    public function events()
    {
        return [
            ActiveRecord::EVENT_INIT => 'behaviorInit',
            ActiveRecord::EVENT_AFTER_INSERT => 'behaviorAfterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'behaviorAfterSave',
        ];
    }

    public function behaviorInit()
    {
        foreach ($this->relations as $key => $value) {
            $this->attributesValues[$key] = null;
        }
    }

    public function __get($name)
    {
        $relationName = $this->behaviorGetRelationName($name);
        if ($this->behaviorIsAttributeExists($name)) {
            if ($this->attributesValues[$relationName] === null) {
                /** @var ActiveRecord $model */
                $model = $this->owner;
                /** @var ActiveRecord[] $relation */
                $relationModels = $model->{$relationName};
                if (!\is_array($relationModels)) {
                    throw new InvalidConfigException('Relation does not return an array.');
                }

                $values = [];
                foreach ($relationModels as $relationModel) {
                    if ($this->relations[$relationName]['type'] == self::RELATION_TYPE_JUNCTION) {
                        $relationPK = $relationModel->primaryKey;
                        if (empty($relationPK) || \is_array($relationPK)) {
                            throw new InvalidConfigException('Relation`s PK is missing or compound.');
                        }

                        $values[] = $relationModel->primaryKey;
                    } else {
                        $values[] = $relationModel->{$this->relations[$relationName]['attribute']};
                    }
                }

                $this->attributesValues[$relationName] = $values;
            }
            return $this->attributesValues[$relationName];
        } else {
            return parent::__get($name);
        }
    }

    public function __set($name, $value)
    {
        $relationName = $this->behaviorGetRelationName($name);
        if ($this->behaviorIsAttributeExists($name)) {
            $this->attributesValues[$relationName] = $value;
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

    public function behaviorAfterSave()
    {
        /** @var ActiveRecord $model */
        $model = $this->owner;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($this->relations as $relationName => $params) {
                $values = $this->attributesValues[$relationName] ?: [];
                /** @var \yii\db\ActiveQuery $relationQuery */
                $relationQuery = $model->{'get' . $relationName}();

                /** @var ActiveRecord $modelLink */
                if ($this->relations[$relationName]['type'] == self::RELATION_TYPE_JUNCTION) {
                    $modelLink = $params['attribute'];
                    $modelLinkAttribute = \array_shift($relationQuery->link);
                    $modelLinkAttributeVia = \array_keys($relationQuery->via->link);
                } else {
                    $modelLink = $relationQuery->modelClass;
                    $modelLinkAttribute = $this->relations[$relationName]['attribute'];
                    $modelLinkAttributeVia = \array_keys($relationQuery->link);
                }

                $queryData = [];
                if (\count($modelLinkAttributeVia) > 1) {
                    foreach ($modelLinkAttributeVia as $keyElement) {
                        $queryData[$keyElement] = $model->primaryKey[$keyElement];
                    }
                } else {
                    $queryData[$modelLinkAttributeVia[0]] = $model->primaryKey;
                }

                $oldValues = $modelLink::find()
                    ->andWhere($queryData)
                    ->select($modelLinkAttribute)
                    ->indexBy($modelLinkAttribute)
                    ->column();
                foreach ($values as $currentValue) {
                    /** @var ActiveRecord $link */
                    if (isset($oldValues[$currentValue])) {
                        unset($oldValues[$currentValue]);
                        continue;
                    }

                    $link = new $modelLink();
                    $link->setAttributes($queryData, false);
                    $link->{$modelLinkAttribute} = $currentValue;
                    if (!$link->save()) {
                        throw new Exception('Could not save.');
                    }
                    unset($oldValues[$currentValue]);
                }

                foreach ($oldValues as $currentOldValue) {
                    $oldLink = $modelLink::find()
                        ->andWhere($queryData)
                        ->andWhere([$modelLinkAttribute => $currentOldValue])
                        ->limit(1)
                        ->one();
                    if ($oldLink !== null && !$oldLink->delete()) {
                        throw new Exception('Could not delete.');
                    }
                }
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->commit();
    }

    protected function behaviorIsAttributeExists($name)
    {
        return \array_key_exists($this->behaviorGetRelationName($name), $this->relations);
    }

    protected function behaviorGetRelationName($attributeName)
    {
        return \str_replace($this->attributeSuffix, '', $attributeName);
    }
}

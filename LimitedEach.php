<?php
/**
 * @link https://github.com/himiklab/yii2-common
 * @copyright Copyright (c) 2014-2018 HimikLab
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace himiklab\yii2\common;

use yii\db\ActiveQuery;

class LimitedEach
{
    protected $query;

    public function __construct(ActiveQuery $query)
    {
        $this->query = $query;
    }

    /**
     * @param int $limit
     * @return \Generator
     * @throws \yii\db\Exception
     */
    public function each($limit = 100)
    {
        /** @var \yii\db\ActiveRecord $model */
        $model = $this->query->modelClass;
        $pk = $model::primaryKey();

        $transaction = $model::getDb()->beginTransaction();
        try {
            $size = $this->query->count();
            $iterations = \ceil($size / $limit);
            if (\count($pk) === 1 && empty($this->query->orderBy) && empty($this->query->limit)) {
                $currentPkValue = null;
                for ($iteration = 1; $iteration <= $iterations; ++$iteration) {
                    $currentQuery = clone $this->query;
                    $currentQuery->limit($limit);
                    $currentQuery->orderBy($pk[0]);

                    if ($currentPkValue) {
                        $currentQuery->andWhere(['>', $model::tableName() . '.' . $pk[0], $currentPkValue]);
                    }
                    /** @var \yii\db\ActiveRecord $currentResult */
                    foreach ($currentQuery->all() as $currentResult) {
                        yield $currentResult;
                        $currentPkValue = $currentResult->{$pk[0]};
                    }
                }
            } else {
                for ($iteration = 1; $iteration <= $iterations; ++$iteration) {
                    $currentQuery = clone $this->query;
                    $currentQuery->limit($limit);
                    if ($iteration !== 1) {
                        $currentQuery->offset($limit * ($iteration - 1));
                    }

                    /** @var \yii\db\ActiveRecord $currentResult */
                    foreach ($currentQuery->all() as $currentResult) {
                        yield $currentResult;
                    }
                }
            }
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $transaction->rollBack();
        }
    }
}

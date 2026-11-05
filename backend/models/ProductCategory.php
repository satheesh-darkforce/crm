<?php

namespace backend\models;

use backend\components\GuardedAttributes;
use Yii;
use yii\db\ActiveRecord;

/**
 * Product category (Artificial Grass, Vertical Gardens, …).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $description
 * @property int         $sort_order
 * @property int         $status       1 = active, 0 = removed (soft delete)
 * @property string|null $created_at
 * @property int|null    $created_by
 * @property string|null $updated_at
 * @property int|null    $updated_by
 */
class ProductCategory extends ActiveRecord
{
    use GuardedAttributes;

    public static function tableName(): string
    {
        return 'product_category';
    }

    protected static function guardedAttributes(): array
    {
        return ['slug', 'status', 'created_at', 'created_by', 'updated_at', 'updated_by'];
    }

    public function rules(): array
    {
        return [
            [['name', 'description'], 'trim'],
            [['name'], 'required'],
            [['name'], 'string', 'max' => 80],
            [['description'], 'string', 'max' => 255],
            [['sort_order'], 'integer', 'min' => 0, 'max' => 9999],
            [['sort_order'], 'default', 'value' => 0],
            [['name'], 'validateUniqueName'],
        ];
    }

    public function validateUniqueName(string $attr): void
    {
        $q = self::find()->where(['status' => 1])->andWhere('LOWER(name) = :n', [':n' => mb_strtolower((string)$this->$attr)]);
        if (!$this->isNewRecord) {
            $q->andWhere(['!=', 'id', $this->id]);
        }
        if ($q->exists()) {
            $this->addError($attr, 'A category with this name already exists.');
        }
    }

    public function attributeLabels(): array
    {
        return ['name' => 'Category name', 'description' => 'Description', 'sort_order' => 'Display order'];
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $uid = Yii::$app->has('user') && !Yii::$app->user->isGuest ? (int)Yii::$app->user->id : null;
        $this->slug = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($this->name)), '-') ?: 'category';
        if ($insert) {
            $this->status     = 1;
            $this->created_at = $now;
            $this->created_by = $uid;
        }
        $this->updated_at = $now;
        $this->updated_by = $uid;
        return true;
    }

    /** Active products in this category. */
    public function getProductCount(): int
    {
        return (int)Product::find()->where(['category_id' => $this->id, 'status' => 1])->count();
    }

    /** @return array<int,string> id => name, for dropdowns */
    public static function options(): array
    {
        return self::find()->select(['name', 'id'])->where(['status' => 1])
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])->indexBy('id')->column();
    }
}

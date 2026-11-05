<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $brand_id
 * @property int    $asset_cat_id
 * @property int    $asset_subcat_id
 * @property int    $vendor_id
 * @property string $product_name
 * @property string $model
 * @property string $date_of_purchase
 * @property string $date_of_expire
 * @property string $barcode
 * @property string $tags
 * @property float  $bill
 * @property int    $warranty    (0=No, 1=Yes)
 * @property string $warranty_start_date
 * @property string $warranty_end_date
 * @property int    $amc         (0=No, 1=Yes)
 * @property string $amc_start_date
 * @property string $amc_end_date
 * @property string $service_contact
 * @property int    $asset_loc
 * @property string $description
 * @property int    $status
 */
class Asset extends ActiveRecord
{
    use \backend\components\GuardedAttributes;

    /** Set by the controller, never by the form. */
    protected static function guardedAttributes(): array
    {
        return ['status'];
    }

    public static function tableName(): string { return 'assets'; }

    public function rules(): array
    {
        return [
            // Dates must be real YYYY-MM-DD values (checked when new or edited).
            [['date_of_purchase', 'date_of_expire', 'warranty_start_date', 'warranty_end_date', 'amc_start_date', 'amc_end_date'], 'date', 'format' => 'php:Y-m-d', 'message' => '{attribute} must be a date (YYYY-MM-DD).',
                'when' => fn($m, $a) => $m->isNewRecord || $m->isAttributeChanged($a, false)],
            [['product_name', 'brand_id', 'asset_cat_id', 'asset_subcat_id', 'vendor_id', 'asset_loc', 'date_of_purchase'], 'required'],
            [['brand_id', 'asset_cat_id', 'asset_subcat_id', 'vendor_id', 'warranty', 'amc', 'asset_loc', 'status'], 'integer'],
            [['bill'], 'number', 'min' => 0],
            [['product_name', 'model', 'barcode', 'service_contact'], 'string', 'max' => 250],
            [['description', 'tags'], 'string'],
            [['warranty', 'amc'], 'in', 'range' => [0, 1]],
            [['date_of_purchase', 'date_of_expire', 'warranty_start_date', 'warranty_end_date', 'amc_start_date', 'amc_end_date'], 'safe'],
            // Conditional: warranty dates required when warranty=1
            [['warranty_start_date', 'warranty_end_date'], 'required',
                'when' => fn($m) => (int)$m->warranty === 1,
                'whenClient' => "function(attr,val){ return parseInt($('#warranty-select').val()) === 1; }",
            ],
            // Conditional: AMC dates required when amc=1
            [['amc_start_date', 'amc_end_date'], 'required',
                'when' => fn($m) => (int)$m->amc === 1,
                'whenClient' => "function(attr,val){ return parseInt($('#amc-select').val()) === 1; }",
            ],
            // Date comparisons
            ['warranty_end_date', 'compareDates', 'params' => ['start' => 'warranty_start_date', 'label' => 'Warranty End Date']],
            ['amc_end_date',      'compareDates', 'params' => ['start' => 'amc_start_date',      'label' => 'AMC End Date']],
            ['date_of_expire',    'compareDates', 'params' => ['start' => 'date_of_purchase',    'label' => 'Expiry Date']],
        ];
    }

    public function compareDates(string $attribute, array $params): void
    {
        $startAttr = $params['start'];
        $label     = $params['label'];
        if (!$this->$attribute || !$this->$startAttr) return;
        if (strtotime($this->$attribute) < strtotime($this->$startAttr)) {
            $this->addError($attribute, "$label must be on or after the start date.");
        }
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        // Nullify empty date fields so DB gets NULL instead of empty string
        foreach (['date_of_expire', 'warranty_start_date', 'warranty_end_date', 'amc_start_date', 'amc_end_date'] as $dateAttr) {
            if (empty($this->$dateAttr)) {
                $this->$dateAttr = null;
            }
        }
        // Clear warranty dates when warranty=0
        if ((int)$this->warranty !== 1) {
            $this->warranty_start_date = null;
            $this->warranty_end_date   = null;
        }
        // Clear AMC dates when amc=0
        if ((int)$this->amc !== 1) {
            $this->amc_start_date = null;
            $this->amc_end_date   = null;
        }
        return true;
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        // Generate barcode on first save
        if ($insert && empty($this->barcode)) {
            $this->generateBarcode();
            static::updateAll(['barcode' => $this->barcode], ['id' => $this->id]);
        }
    }

    public function attributeLabels(): array
    {
        return [
            'product_name'       => 'Product Name',
            'model'              => 'Model Number',
            'asset_cat_id'       => 'Category',
            'asset_subcat_id'    => 'Sub Category',
            'brand_id'           => 'Brand',
            'vendor_id'          => 'Vendor',
            'asset_loc'          => 'Location',
            'date_of_purchase'   => 'Purchase Date',
            'date_of_expire'     => 'Expiry Date',
            'bill'               => 'Purchase Amount (₹)',
            'warranty'           => 'Under Warranty',
            'warranty_start_date'=> 'Warranty Start',
            'warranty_end_date'  => 'Warranty End',
            'amc'                => 'Under AMC',
            'amc_start_date'     => 'AMC Start',
            'amc_end_date'       => 'AMC End',
            'service_contact'    => 'Service Contact',
            'tags'               => 'Tags',
            'description'        => 'Description',
            'barcode'            => 'Barcode',
        ];
    }

    public function generateBarcode(): void
    {
        $raw = 'MAE' . $this->id . uniqid('', true);
        $this->barcode = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $raw), 0, 12));
    }

    /** Resolve comma-separated tag IDs to names */
    public function getTagNames(): string
    {
        if (empty($this->tags)) return '';
        $ids = array_filter(array_map('intval', explode(',', $this->tags)));
        if (empty($ids)) return '';
        $tags = AssetTag::find()->where(['id' => $ids, 'status' => 1])->all();
        return implode(', ', array_map(fn($t) => $t->name, $tags));
    }

    public function getBrand(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AssetBrand::class, ['id' => 'brand_id']);
    }

    public function getCategory(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AssetCategory::class, ['id' => 'asset_cat_id']);
    }

    public function getSubcategory(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AssetSubcategory::class, ['id' => 'asset_subcat_id']);
    }

    public function getLocation(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AssetLocation::class, ['id' => 'asset_loc']);
    }

    public function getVendor(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Vendor::class, ['id' => 'vendor_id']);
    }

    public function getHandovers(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AssetHandover::class, ['asset_id' => 'id']);
    }

    public function getMaintenances(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AssetMaintenance::class, ['asset_id' => 'id']);
    }

    public function getUploads(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AssetUpload::class, ['asset_id' => 'id'])->andWhere(['assets_uploads.status' => 1]);
    }

    public function hasActiveHandover(): bool
    {
        return AssetHandover::find()
            ->where(['status' => 1, 'out_date' => null])
            ->andWhere(['or',
                ['like', 'asset_id', '[' . $this->id . ']'],
                ['like', 'asset_id', '[' . $this->id . ','],
                ['like', 'asset_id', ',' . $this->id . ','],
                ['like', 'asset_id', ',' . $this->id . ']'],
            ])
            ->exists();
    }
}

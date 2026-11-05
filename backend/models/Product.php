<?php

namespace backend\models;

use backend\components\GuardedAttributes;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Sales catalogue item — a product (goods) or a service (installation, maintenance).
 *
 * Stored in the existing item master `delivery_product_details`, which delivery
 * challans also use (via DeliveryProduct), so there is one list of items.
 *
 * @property int         $id
 * @property string|null $serial_no
 * @property string|null $product_code
 * @property int|null    $category_id
 * @property int         $item_type          1 = Product, 2 = Service
 * @property string      $item_name
 * @property string|null $hsn_code           HSN (goods) or SAC (services)
 * @property string|null $units
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $sale_price
 * @property string|null $mrp
 * @property int         $price_on_request
 * @property string|null $gst_rate
 * @property string|null $features          JSON list of strings
 * @property string|null $specs             JSON object name => value
 * @property string|null $image
 * @property int         $is_active
 * @property string|null $created_at
 * @property int|null    $created_by
 * @property string|null $updated_at
 * @property int|null    $updated_by
 * @property int         $status            1 = live, 0 = removed (soft delete)
 *
 * @property ProductCategory|null $category
 */
class Product extends ActiveRecord
{
    use GuardedAttributes;

    const TYPE_PRODUCT = 1;
    const TYPE_SERVICE = 2;

    const GST_RATES = ['0', '5', '12', '18', '28'];

    /** Form inputs for the repeater / list fields (stored as JSON). */
    public array $featureList = [];
    public array $specList = [];

    public static function tableName(): string
    {
        return 'delivery_product_details';
    }

    protected static function guardedAttributes(): array
    {
        return ['features', 'specs', 'image', 'status', 'serial_no', 'created_at', 'created_by', 'updated_at', 'updated_by'];
    }

    public static function typeOptions(): array
    {
        return [self::TYPE_PRODUCT => 'Product', self::TYPE_SERVICE => 'Service'];
    }

    /**
     * Selling units grouped by how the item is measured. The price is always
     * entered per one unit; quotations multiply it by the quantity in that unit.
     * "decimals" = quantities may be fractional (12.5 m, 2.75 kg); count units are whole numbers.
     */
    const UNIT_GROUPS = [
        'area'     => ['label' => 'Area',            'decimals' => true,  'units' => ['Sq.ft' => 'Square feet', 'Sq.m' => 'Square metre']],
        'length'   => ['label' => 'Length',          'decimals' => true,  'units' => ['Mtr' => 'Metre', 'Rft' => 'Running feet', 'Ft' => 'Feet', 'Cm' => 'Centimetre']],
        'weight'   => ['label' => 'Weight',          'decimals' => true,  'units' => ['Kg' => 'Kilogram', 'Gram' => 'Gram', 'Ton' => 'Tonne']],
        'volume'   => ['label' => 'Volume',          'decimals' => true,  'units' => ['Ltr' => 'Litre', 'Cu.ft' => 'Cubic feet']],
        'quantity' => ['label' => 'Quantity (count)', 'decimals' => false, 'units' => ['Nos' => 'Numbers', 'Piece' => 'Piece', 'Panel' => 'Panel', 'Roll' => 'Roll',
                                                                                     'Set' => 'Set', 'Pair' => 'Pair', 'Box' => 'Box', 'Bag' => 'Bag', 'Bunch' => 'Bunch']],
        'service'  => ['label' => 'Service / time',  'decimals' => true,  'units' => ['Job' => 'Job / package', 'Lot' => 'Lot', 'Visit' => 'Visit', 'Hour' => 'Hour',
                                                                                     'Day' => 'Day', 'Month' => 'Month', 'Year' => 'Year']],
    ];

    /** Flat unit => unit list (validation). */
    public static function unitOptions(): array
    {
        $out = [];
        foreach (self::UNIT_GROUPS as $g) {
            foreach (array_keys($g['units']) as $u) {
                $out[$u] = $u;
            }
        }
        return $out;
    }

    /** Grouped list for the dropdown: group label => [unit => "Sq.ft — Square feet"]. */
    public static function unitDropdown(): array
    {
        $out = [];
        foreach (self::UNIT_GROUPS as $g) {
            foreach ($g['units'] as $u => $name) {
                $out[$g['label']][$u] = $u . ' — ' . $name;
            }
        }
        return $out;
    }

    /** Group key (area, length, weight, …) of a unit, or null for an unknown legacy unit. */
    public static function unitGroupOf(?string $unit): ?string
    {
        foreach (self::UNIT_GROUPS as $key => $g) {
            if ($unit !== null && isset($g['units'][$unit])) {
                return $key;
            }
        }
        return null;
    }

    /** Whether quantities in this unit may have decimals (count units may not). */
    public static function unitAllowsDecimals(?string $unit): bool
    {
        $g = self::unitGroupOf($unit);
        return $g === null || self::UNIT_GROUPS[$g]['decimals'];
    }

    /** Canonical spelling for a unit typed differently in old data ("NOS" → "Nos", "sqft" → "Sq.ft"). */
    public static function normaliseUnit(?string $unit): ?string
    {
        if ($unit === null || $unit === '') {
            return $unit;
        }
        $key = preg_replace('/[^a-z]/', '', mb_strtolower($unit));
        $aliases = ['sqft' => 'Sq.ft', 'sqfeet' => 'Sq.ft', 'sqm' => 'Sq.m', 'm' => 'Mtr', 'meter' => 'Mtr', 'metre' => 'Mtr', 'mtrs' => 'Mtr',
                    'kgs' => 'Kg', 'kilogram' => 'Kg', 'no' => 'Nos', 'nos' => 'Nos', 'numbers' => 'Nos', 'pcs' => 'Piece', 'pc' => 'Piece', 'ltr' => 'Ltr', 'litre' => 'Ltr'];
        if (isset($aliases[$key])) {
            return $aliases[$key];
        }
        foreach (self::unitOptions() as $u) {
            if (preg_replace('/[^a-z]/', '', mb_strtolower($u)) === $key) {
                return $u;
            }
        }
        return $unit;
    }

    public static function gstOptions(): array
    {
        $out = [];
        foreach (self::GST_RATES as $r) {
            $out[$r] = $r . '%';
        }
        return $out;
    }

    public function rules(): array
    {
        $changed = fn(string $attr) => fn($m) => $m->isNewRecord || (string)$m->getOldAttribute($attr) !== (string)$m->$attr;
        return [
            [['item_name', 'product_code', 'hsn_code', 'units', 'short_description', 'description'], 'trim'],
            [['units'], 'filter', 'filter' => fn($v) => self::normaliseUnit($v)],
            [['product_code'], 'filter', 'filter' => fn($v) => $v === null || $v === '' ? null : strtoupper($v)],
            [['item_name', 'item_type', 'category_id', 'units'], 'required'],
            [['item_name'], 'string', 'max' => 255],
            [['product_code'], 'string', 'max' => 50],
            [['product_code'], 'match', 'pattern' => '/^[A-Z0-9][A-Z0-9\-\/_.]*$/', 'message' => 'Code may use letters, numbers and - / _ . only.'],
            [['short_description'], 'string', 'max' => 255],
            [['description'], 'string', 'max' => 10000],
            [['item_type'], 'in', 'range' => array_keys(self::typeOptions())],
            [['category_id'], 'integer'],
            [['category_id'], 'exist', 'targetClass' => ProductCategory::class, 'targetAttribute' => 'id', 'filter' => ['status' => 1],
                'message' => 'Select a valid category.', 'when' => $changed('category_id')],
            [['units'], 'in', 'range' => array_keys(self::unitOptions()), 'message' => 'Select a valid unit.', 'when' => $changed('units')],
            [['hsn_code'], 'string', 'max' => 50],
            [['hsn_code'], 'match', 'pattern' => '/^\d{4,8}$/', 'message' => 'HSN code is 4 to 8 digits (e.g. 57033090).',
                'when' => fn($m) => (int)$m->item_type === self::TYPE_PRODUCT && $changed('hsn_code')($m),
                'whenClient' => "function(){ return $('#product-item_type').val() !== '" . self::TYPE_SERVICE . "'; }"],
            [['hsn_code'], 'match', 'pattern' => '/^99\d{4}$/', 'message' => 'SAC code is 6 digits starting with 99 (e.g. 995465).',
                'when' => fn($m) => (int)$m->item_type === self::TYPE_SERVICE && $changed('hsn_code')($m),
                'whenClient' => "function(){ return $('#product-item_type').val() === '" . self::TYPE_SERVICE . "'; }"],
            [['price_on_request', 'is_active'], 'boolean'],
            [['price_on_request'], 'default', 'value' => 0],
            [['is_active'], 'default', 'value' => 1],
            [['sale_price', 'mrp'], 'number', 'min' => 0, 'max' => 99999999.99],
            [['sale_price', 'mrp'], 'filter', 'filter' => fn($v) => $v === '' ? null : $v],
            [['sale_price'], 'required', 'when' => fn($m) => !(int)$m->price_on_request,
                'whenClient' => "function(){ return !$('#product-price_on_request').is(':checked'); }", 'message' => 'Enter the price for one unit, or mark the item as "price on request".'],
            [['mrp'], 'compare', 'compareAttribute' => 'sale_price', 'operator' => '>=', 'type' => 'number', 'skipOnEmpty' => true,
                'when' => fn($m) => $m->sale_price !== null && $m->sale_price !== '', 'message' => 'MRP cannot be lower than the unit price.'],
            [['gst_rate'], 'required'],
            [['gst_rate'], 'in', 'range' => self::GST_RATES, 'message' => 'Select a valid GST rate.'],
            [['featureList', 'specList'], 'safe'],
            [['featureList'], 'validateFeatures'],
            [['specList'], 'validateSpecs'],
            [['product_code'], 'validateUnique', 'when' => $changed('product_code')],
            [['item_name'], 'validateUnique', 'when' => $changed('item_name')],
        ];
    }

    /** Name and code are unique among live items (removed items may be reused). */
    public function validateUnique(string $attr): void
    {
        if ($this->$attr === null || $this->$attr === '') {
            return;
        }
        $q = self::find()->where(['status' => 1])->andWhere("LOWER($attr) = :v", [':v' => mb_strtolower((string)$this->$attr)]);
        if (!$this->isNewRecord) {
            $q->andWhere(['!=', 'id', $this->id]);
        }
        if ($q->exists()) {
            $this->addError($attr, $attr === 'product_code' ? 'Another item already uses this code.' : 'An item with this name already exists.');
        }
    }

    public function validateFeatures(string $attr): void
    {
        $clean = [];
        foreach ((array)$this->featureList as $f) {
            $f = trim((string)(is_scalar($f) ? $f : ''));
            if ($f === '') {
                continue;
            }
            if (mb_strlen($f) > 150) {
                $this->addError($attr, 'Each feature must be 150 characters or fewer.');
                return;
            }
            $clean[] = $f;
        }
        if (count($clean) > 20) {
            $this->addError($attr, 'Add at most 20 features.');
            return;
        }
        $this->featureList = $clean;
    }

    public function validateSpecs(string $attr): void
    {
        $clean = [];
        foreach ((array)$this->specList as $row) {
            $k = trim((string)(is_array($row) && is_scalar($row['name'] ?? null) ? $row['name'] : ''));
            $v = trim((string)(is_array($row) && is_scalar($row['value'] ?? null) ? $row['value'] : ''));
            if ($k === '' && $v === '') {
                continue;
            }
            if ($k === '' || $v === '') {
                $this->addError($attr, 'Every specification needs both a name and a value.');
                return;
            }
            if (mb_strlen($k) > 60 || mb_strlen($v) > 200) {
                $this->addError($attr, 'Specification names are up to 60 characters and values up to 200.');
                return;
            }
            if (isset($clean[mb_strtolower($k)])) {
                $this->addError($attr, 'Specification "' . $k . '" is listed twice.');
                return;
            }
            $clean[mb_strtolower($k)] = ['name' => $k, 'value' => $v];
        }
        if (count($clean) > 30) {
            $this->addError($attr, 'Add at most 30 specifications.');
            return;
        }
        $this->specList = array_values($clean);
    }

    public function attributeLabels(): array
    {
        return [
            'item_name'         => 'Name',
            'product_code'      => 'Code / SKU',
            'category_id'       => 'Category',
            'item_type'         => 'Type',
            'hsn_code'          => 'HSN / SAC code',
            'units'             => 'Sold by (unit)',
            'short_description' => 'Short description',
            'description'       => 'Full description',
            'sale_price'        => 'Unit price',
            'mrp'               => 'MRP / list price',
            'price_on_request'  => 'Price on request',
            'gst_rate'          => 'GST rate',
            'is_active'         => 'Active',
            'featureList'       => 'Features',
            'specList'          => 'Specifications',
        ];
    }

    public function afterFind(): void
    {
        parent::afterFind();
        $this->featureList = array_values(array_filter((array)json_decode((string)$this->features, true), 'is_string'));
        // Old rows may spell units differently (NOS, sqft); show and save the canonical form.
        $canonical = self::normaliseUnit($this->units);
        if ($canonical !== $this->units) {
            $this->units = $canonical;
            $this->setOldAttribute('units', $canonical);
        }
        $specs = json_decode((string)$this->specs, true);
        $this->specList = is_array($specs) ? array_values(array_filter($specs, fn($r) => is_array($r) && isset($r['name'], $r['value']))) : [];
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $uid = Yii::$app->has('user') && !Yii::$app->user->isGuest ? (int)Yii::$app->user->id : null;
        $this->features = $this->featureList ? json_encode(array_values($this->featureList), JSON_UNESCAPED_UNICODE) : null;
        $this->specs    = $this->specList ? json_encode(array_values($this->specList), JSON_UNESCAPED_UNICODE) : null;
        if ((int)$this->price_on_request && ($this->sale_price === '' || $this->sale_price === null)) {
            $this->sale_price = null;
        }
        // Legacy column is NOT NULL (delivery challans always had an HSN); "not set yet" is stored as ''.
        if ($this->hsn_code === null) {
            $this->hsn_code = '';
        }
        if ($insert) {
            $this->status     = 1;
            $this->created_at = $now;
            $this->created_by = $uid;
        }
        $this->updated_at = $now;
        $this->updated_by = $uid;
        return true;
    }

    public function getCategory(): ActiveQuery
    {
        return $this->hasOne(ProductCategory::class, ['id' => 'category_id']);
    }

    public function isService(): bool
    {
        return (int)$this->item_type === self::TYPE_SERVICE;
    }

    public function getTypeName(): string
    {
        return self::typeOptions()[(int)$this->item_type] ?? 'Product';
    }

    /** Discount off the MRP, in whole percent (null when there is no MRP above the price). */
    public function getDiscountPercent(): ?int
    {
        $mrp = (float)$this->mrp;
        $price = (float)$this->sale_price;
        if ($mrp <= 0 || $this->sale_price === null || $price >= $mrp) {
            return null;
        }
        return (int)round(($mrp - $price) / $mrp * 100);
    }

    /** Selling price including GST, or null when the price or the GST rate is not set. */
    public function getPriceWithTax(): ?float
    {
        if ($this->sale_price === null || $this->sale_price === '' || $this->gst_rate === null || $this->gst_rate === '') {
            return null;
        }
        return round((float)$this->sale_price * (1 + (float)$this->gst_rate / 100), 2);
    }

    /** "₹2,625 / Sq.ft" style label for lists. */
    public function getPriceLabel(): string
    {
        if ($this->sale_price === null || $this->sale_price === '') {
            return 'On request';
        }
        return '₹' . Yii::$app->formatter->asDecimal((float)$this->sale_price, 2) . ($this->units ? ' / ' . $this->units : '');
    }

    /** Number of delivery challan lines that use this item (shown on the detail page). */
    public function getChallanUseCount(): int
    {
        return (int)DcProduct::find()->where(['product_id' => $this->id, 'status' => 1])->count();
    }

    /** Next free code like GD-0001 for a new item. */
    public static function suggestCode(): string
    {
        $max = 0;
        foreach (self::find()->select('product_code')->where(['like', 'product_code', 'GD-%', false])->column() as $code) {
            if (preg_match('/^GD-(\d+)$/', (string)$code, $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
        return sprintf('GD-%04d', $max + 1);
    }

    /** Compact row for pickers (quotation / invoice line items). */
    public function toPickerArray(): array
    {
        return [
            'id'       => (int)$this->id,
            'code'     => (string)$this->product_code,
            'name'     => (string)$this->item_name,
            'type'     => $this->getTypeName(),
            'unit'     => (string)$this->units,
            'hsn'      => (string)$this->hsn_code,
            'price'    => $this->sale_price === null ? null : (float)$this->sale_price,
            'mrp'      => $this->mrp === null ? null : (float)$this->mrp,
            'gst_rate' => $this->gst_rate === null || $this->gst_rate === '' ? null : (float)$this->gst_rate,
            'unit_group'   => self::unitGroupOf($this->units),
            'qty_decimals' => self::unitAllowsDecimals($this->units),
            'category' => $this->category->name ?? '',
        ];
    }
}

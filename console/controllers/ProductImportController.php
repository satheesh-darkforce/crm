<?php

namespace console\controllers;

use backend\models\Product;
use backend\models\ProductCategory;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Imports the Green Divine website catalogue (categories + products) into Product Management.
 *
 *   php yii product-import/website <site-root> [--dry-run]
 *
 * <site-root> is the website folder holding data/content/categories.php,
 * data/content/products.php and assets/images/products/. Safe to re-run:
 * categories and products whose names already exist are skipped.
 *
 * HSN/SAC and GST are not on the website, so they are left empty for the
 * team to confirm — those items show "—" for tax until edited.
 */
class ProductImportController extends Controller
{
    public bool $dryRun = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun']);
    }

    public function optionAliases(): array
    {
        return ['n' => 'dryRun'];
    }

    public function actionWebsite(string $siteRoot): int
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        $catFile  = $siteRoot . '/data/content/categories.php';
        $prodFile = $siteRoot . '/data/content/products.php';
        if (!is_file($catFile) || !is_file($prodFile)) {
            $this->stderr("Not a website root: expected data/content/categories.php and products.php under $siteRoot\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }
        $categories = require $catFile;
        $products   = require $prodFile;
        if (!is_array($categories) || !is_array($products)) {
            $this->stderr("The data files did not return arrays.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $catIds = $this->importCategories($categories);
            [$added, $skipped] = $this->importProducts($products, $catIds, $siteRoot);
            if ($this->dryRun) {
                $tx->rollBack();
                $this->stdout("Dry run — nothing saved.\n", Console::FG_YELLOW);
            } else {
                $tx->commit();
            }
        } catch (\Throwable $e) {
            $tx->rollBack();
            $this->stderr('Import failed, nothing saved: ' . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout("Products: $added added, $skipped already present.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /** @return array<string,int> website slug => category id */
    private function importCategories(array $rows): array
    {
        $map = [];
        foreach (array_values($rows) as $i => $c) {
            $name = trim((string)($c['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $existing = ProductCategory::find()->where(['status' => 1])->andWhere('LOWER(name) = :n', [':n' => mb_strtolower($name)])->one();
            if ($existing) {
                $map[(string)$c['slug']] = (int)$existing->id;
                $this->stdout("  = category $name\n");
                continue;
            }
            $cat = new ProductCategory();
            $cat->name        = $name;
            $cat->description = mb_substr(trim((string)($c['desc'] ?? '')), 0, 255) ?: null;
            $cat->sort_order  = ($i + 1) * 10;
            if (!$cat->save()) {
                throw new \RuntimeException("Category $name: " . implode(' ', $cat->getFirstErrors()));
            }
            $map[(string)$c['slug']] = (int)$cat->id;
            $this->stdout("  + category $name\n", Console::FG_GREEN);
        }
        return $map;
    }

    private function importProducts(array $rows, array $catIds, string $siteRoot): array
    {
        $added = $skipped = 0;
        foreach ($rows as $p) {
            $name = trim((string)($p['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (Product::find()->where(['status' => 1])->andWhere('LOWER(item_name) = :n', [':n' => mb_strtolower($name)])->exists()) {
                $skipped++;
                $this->stdout("  = $name\n");
                continue;
            }
            $price = $this->money($p['price_label'] ?? '');
            $mrp   = $this->money($p['price_was'] ?? '');

            $m = new Product();
            $m->item_name         = $name;
            $m->product_code      = Product::suggestCode();
            $m->category_id       = $catIds[(string)($p['category'] ?? '')] ?? null;
            $m->item_type         = Product::TYPE_PRODUCT;
            $m->units             = $this->unitFor($p);
            $m->short_description = mb_substr(trim((string)($p['short'] ?? '')), 0, 255) ?: null;
            $m->description       = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], "\n", (string)($p['description'] ?? ''))))) ?: null;
            $m->sale_price        = $price;
            $m->mrp               = $mrp !== null && $price !== null && $mrp >= $price ? $mrp : null;
            $m->price_on_request  = $price === null ? 1 : 0;
            $m->gst_rate          = null; // not on the website — to be confirmed
            $m->hsn_code          = null;
            $m->is_active         = 1;
            $m->featureList       = array_values(array_filter(array_map('trim', (array)($p['features'] ?? [])), 'strlen'));
            $m->specList          = [];
            foreach ((array)($p['specs'] ?? []) as $k => $v) {
                $m->specList[] = ['name' => (string)$k, 'value' => (string)$v];
            }
            $m->image = $this->copyImage($siteRoot, (string)(($p['images'] ?? [])[0] ?? ''));

            // gst_rate is required in the form; the import leaves it for the team to fill in.
            if (!$m->validate(['item_name', 'product_code', 'category_id', 'item_type', 'units', 'sale_price', 'mrp', 'featureList', 'specList'])) {
                throw new \RuntimeException("$name: " . implode(' ', $m->getFirstErrors()));
            }
            $m->save(false);
            $added++;
            $this->stdout("  + $name ({$m->product_code}, {$m->getPriceLabel()})\n", Console::FG_GREEN);
        }
        return [$added, $skipped];
    }

    /** "₹2,625" / "Starting ₹45 / kg" → 2625 / 45; anything without a number → null. */
    private function money(string $label): ?float
    {
        return preg_match('/₹\s*([\d,]+(?:\.\d+)?)/u', $label, $m) ? (float)str_replace(',', '', $m[1]) : null;
    }

    /** The website has no unit field; take it from the price label or the category. */
    private function unitFor(array $p): string
    {
        $label = mb_strtolower((string)($p['price_label'] ?? ''));
        $name  = mb_strtolower((string)($p['name'] ?? ''));
        if (str_contains($label, '/ kg') || str_contains($label, '/kg')) {
            return 'Kg';
        }
        if (str_contains($label, 'sqft') || str_contains($label, 'sq.ft')) {
            return 'Sq.ft';
        }
        if (str_contains($name, 'package')) {
            return 'Job';
        }
        return match ((string)($p['category'] ?? '')) {
            'artificial-grass', '__sports' => 'Sq.ft',
            'artificial-vertical-garden'   => 'Panel',
            'commercial-installations'     => str_contains($name, 'panel') || str_contains($name, 'wall') ? 'Panel' : 'Nos',
            default                        => 'Nos',
        };
    }

    /** Copies the first website image into /uploads/products (real images only). */
    private function copyImage(string $siteRoot, string $file): ?string
    {
        $file = basename($file);
        $src  = $siteRoot . '/assets/images/products/' . $file;
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($file === '' || !is_file($src) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || @getimagesize($src) === false) {
            return null;
        }
        if ($this->dryRun) {
            return '/uploads/products/' . $file;
        }
        $dir = Yii::getAlias('@backend/web/uploads/products');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        return copy($src, $dir . '/' . $name) ? '/uploads/products/' . $name : null;
    }
}

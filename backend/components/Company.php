<?php

namespace backend\components;

use Yii;

/**
 * The company printed on documents (invoices, quotations, payslips, challans).
 * Values come from params['company'] in backend/config/params.php.
 *
 *   Company::get('name'), Company::addressLine(), Company::logoPath(), Company::logoUrl()
 */
class Company
{
    private const DEFAULTS = [
        'name' => 'Green Divine', 'legalName' => 'Green Divine', 'tagline' => '', 'address' => '',
        'city' => '', 'state' => '', 'stateCode' => '', 'phone' => '', 'email' => '', 'website' => '',
        'gstin' => '', 'logo' => 'images/logo.png', 'letterhead' => 'images/letterhead.png',
    ];

    public static function get(string $key): string
    {
        $c = (Yii::$app->params['company'] ?? []) + self::DEFAULTS;
        return (string) ($c[$key] ?? '');
    }

    /** "phone | email | website" — only the parts that are filled in. */
    public static function contactLine(string $sep = '  |  '): string
    {
        return implode($sep, array_filter([self::get('phone'), self::get('email'), self::get('website')]));
    }

    /** File path of the logo (for PDF engines). */
    public static function logoPath(): string
    {
        return Yii::getAlias('@webroot/' . ltrim(self::get('logo'), '/'));
    }

    /** Web URL of the logo. */
    public static function logoUrl(): string
    {
        return Yii::getAlias('@web/' . ltrim(self::get('logo'), '/'));
    }

    /** Relative path of the invoice letterhead image (under backend/web). */
    public static function letterhead(): string
    {
        return ltrim(self::get('letterhead'), '/');
    }
}

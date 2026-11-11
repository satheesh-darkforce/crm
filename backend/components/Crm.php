<?php

namespace backend\components;

use Yii;

/**
 * Installation profile switches (backend/config/params.php).
 *
 * crm.simple — Green Divine mode: Leads, Quotations and Invoices show only the
 * fields Green Divine uses (see HIDDEN_MODULES.md "Simplified forms"). Hidden
 * fields get safe defaults on the server, so nothing that relies on them breaks.
 */
class Crm
{
    public static function simple(): bool
    {
        return (bool)(Yii::$app->params['crm.simple'] ?? false);
    }
}

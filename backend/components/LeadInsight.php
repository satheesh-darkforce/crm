<?php

namespace backend\components;

use backend\models\ClientModel;
use backend\models\Lead;
use backend\models\Product;
use backend\models\ProductCategory;
use common\components\UserAccess;
use Yii;
use yii\helpers\Url;

/**
 * The "smart" side of Leads, kept in one place:
 *
 *  - score()      0–100 lead score from temperature, stage, details, site visit, urgency and activity
 *  - actions()    next-best-action prompts ("follow-up overdue", "send the quotation" …)
 *  - duplicates() other leads / clients with the same phone number or email
 *  - estimate()   value estimate from the site area and the catalogue unit prices
 *
 * Everything is calculated on the fly from existing data — nothing extra is stored.
 */
class LeadInsight
{
    /** Unit → factor to the base unit of its dimension (area: Sq.ft, length: feet). */
    const AREA_FACTORS   = ['Sq.ft' => 1.0, 'Sq.m' => 10.7639];
    const LENGTH_FACTORS = ['Rft' => 1.0, 'Ft' => 1.0, 'Mtr' => 3.28084, 'Cm' => 0.0328084];

    // ── Score ────────────────────────────────────────────────────────────

    /**
     * 0–100. Open leads only (converted = 100, dropped = 0).
     *
     * Temperature  Hot +25, Warm +12
     * Stage        Got requirement +10, Gave quotation +20, Waiting for payment +30
     * Details      products of interest +5, area +5, requirement +5, value/budget +5
     * Site visit   done +10, booked +5
     * Urgency      installation wanted within 30 days +10, within 90 days +5
     * Activity     last activity ≤ 7 days +10; > 30 days −10; > 60 days −20
     * Follow-up    overdue −10, none scheduled −5
     */
    public static function score(Lead $lead): int
    {
        $ls = (int)$lead->lead_status;
        if ($ls === Lead::LEAD_STATUS_CONVERTED) {
            return 100;
        }
        if ($ls === Lead::LEAD_STATUS_DROPPED) {
            return 0;
        }
        $s = match ((int)$lead->priority) {
            Lead::PRIORITY_HIGH   => 25,
            Lead::PRIORITY_MEDIUM => 12,
            default               => 0,
        };
        $s += match ($ls) {
            Lead::LEAD_STATUS_GOT_REQUIREMENT => 10,
            Lead::LEAD_STATUS_GAVE_QUOTATION  => 20,
            Lead::LEAD_STATUS_WAITING_PAYMENT => 30,
            default                           => 0,
        };
        if ($lead->interests) {
            $s += 5;
        }
        if ((float)$lead->site_area > 0) {
            $s += 5;
        }
        if (trim(strip_tags((string)$lead->description)) !== '') {
            $s += 5;
        }
        if ((float)$lead->lead_value > 0 || (float)$lead->expected_budget > 0) {
            $s += 5;
        }
        $visit = self::ts($lead->site_visit_date);
        if ($visit !== null) {
            $s += $visit <= time() ? 10 : 5;
        }
        $install = self::ts($lead->expected_install_date);
        if ($install !== null && $install >= strtotime('today')) {
            $days = (int)floor(($install - strtotime('today')) / 86400);
            $s += $days <= 30 ? 10 : ($days <= 90 ? 5 : 0);
        }
        $idle = self::idleDays($lead);
        if ($idle !== null) {
            $s += $idle <= 7 ? 10 : ($idle > 60 ? -20 : ($idle > 30 ? -10 : 0));
        }
        $state = Lead::followUpState($lead->meeting_reported_date);
        if ($state === 'overdue') {
            $s -= 10;
        } elseif (self::ts($lead->meeting_reported_date) === null) {
            $s -= 5;
        }
        return max(0, min(100, $s));
    }

    /** [label, tone] for a score. */
    public static function band(int $score): array
    {
        return match (true) {
            $score >= 60 => ['High', 'success'],
            $score >= 35 => ['Medium', 'warning'],
            default      => ['Low', 'neutral'],
        };
    }

    /** Days since the last activity (lead created, meeting attended, latest follow-up logged). */
    public static function idleDays(Lead $lead): ?int
    {
        $last = max(
            self::ts($lead->created_date) ?? 0,
            self::ts($lead->meeting_attended_date) ?? 0,
            self::ts($lead->latestFollower->created_date ?? null) ?? 0
        );
        return $last > 0 ? (int)floor((time() - $last) / 86400) : null;
    }

    // ── Next best actions ────────────────────────────────────────────────

    /**
     * Prompts for an open lead, most urgent first.
     * @return array<int,array{tone:string,icon:string,text:string}>
     */
    public static function actions(Lead $lead): array
    {
        $ls = (int)$lead->lead_status;
        if (in_array($ls, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true)) {
            return [];
        }
        $out   = [];
        $add   = function (string $tone, string $icon, string $text) use (&$out) { $out[] = compact('tone', 'icon', 'text'); };
        $today = strtotime('today');

        // The owner has left: nobody will follow this lead up.
        if (!$lead->assigned_to || ($lead->assignedTo && (int)$lead->assignedTo->status !== 1)) {
            $add('danger', 'user-slash', $lead->assigned_to
                ? 'The sales person (' . Lead::userLabel($lead->assignedTo) . ') is no longer active — reassign this lead.'
                : 'No sales person is assigned — assign one.');
        }

        $next = self::ts($lead->meeting_reported_date);
        if ($next === null) {
            $add('warning', 'calendar-plus', 'No next follow-up is scheduled — add one so the lead is not forgotten.');
        } elseif ($next < $today) {
            $d = (int)floor(($today - strtotime(date('Y-m-d', $next))) / 86400);
            $add('danger', 'phone', 'Follow-up overdue by ' . $d . ' day' . ($d === 1 ? '' : 's') . ' — call the client now.');
        } elseif (date('Y-m-d', $next) === date('Y-m-d')) {
            $add('info', 'phone', 'Follow-up due today at ' . date('h:i A', $next) . '.');
        }

        $install = self::ts($lead->expected_install_date);
        if ($install !== null && $install >= $today && $ls < Lead::LEAD_STATUS_WAITING_PAYMENT) {
            $d = (int)floor(($install - $today) / 86400);
            if ($d <= 14) {
                $add('danger', 'hourglass-half', 'Client wants the installation by ' . date('d M', $install) . ' (' . $d . ' day' . ($d === 1 ? '' : 's') . ') — move fast.');
            }
        }

        $visit = self::ts($lead->site_visit_date);
        if ($visit !== null && $visit >= $today && date('Y-m-d', $visit) <= date('Y-m-d', strtotime('+1 day'))) {
            $when = date('Y-m-d', $visit) === date('Y-m-d') ? 'today' : 'tomorrow';
            $at   = date('H:i', $visit) !== '00:00' ? ' at ' . date('h:i A', $visit) : '';
            $by   = $lead->siteVisitBy ? ' (' . Lead::userLabel($lead->siteVisitBy) . ')' : '';
            $add('info', 'map-marker-alt', "Site visit $when$at$by.");
        } elseif ($visit === null && (int)$lead->priority >= Lead::PRIORITY_MEDIUM && $ls < Lead::LEAD_STATUS_GAVE_QUOTATION) {
            $add('warning', 'map-marker-alt', 'This is a ' . strtolower($lead->getPriorityLabel()) . ' lead with no site visit — book one.');
        } elseif ($visit !== null && $visit < $today && $ls < Lead::LEAD_STATUS_GAVE_QUOTATION) {
            $d = (int)floor(($today - strtotime(date('Y-m-d', $visit))) / 86400);
            $add('warning', 'file-signature', 'Site visit done ' . ($d === 0 ? 'today' : $d . ' day' . ($d === 1 ? '' : 's') . ' ago') . ' — send the quotation'
                . ($lead->quotation_by ? '.' : ' and assign who prepares it.'));
        }

        $idle = self::idleDays($lead);
        if ($ls === Lead::LEAD_STATUS_GAVE_QUOTATION && $idle !== null && $idle >= 3) {
            $add('info', 'comments', 'Quotation shared ' . $idle . ' days ago with no update — check if the client has questions.');
        }
        if ($idle !== null && $idle >= 30) {
            $add('warning', 'bed', 'No activity for ' . $idle . ' days — re-engage the client or mark the lead dropped.');
        }
        if (!$lead->interests || (float)$lead->site_area <= 0) {
            $add('neutral', 'ruler-combined', 'Add ' . (!$lead->interests ? 'the products of interest' : '') . (!$lead->interests && (float)$lead->site_area <= 0 ? ' and ' : '')
                . ((float)$lead->site_area <= 0 ? 'the site area' : '') . ' to get a value estimate.');
        }
        return $out;
    }

    // ── Duplicates ───────────────────────────────────────────────────────

    /**
     * Keys of every number in a field — old leads hold several numbers in one
     * field ("98657 04301; 0422-2235258"), so split on ; , / | first.
     * @return string[]
     */
    public static function phoneKeys(?string $value): array
    {
        return array_values(array_unique(array_filter(array_map([self::class, 'phoneKey'], preg_split('#[;,/|]+#', (string)$value)))));
    }

    /** Last 10 digits of a phone number ('' if it has fewer than 6 digits). */
    public static function phoneKey(?string $phone): string
    {
        $d = preg_replace('/\D/', '', (string)$phone);
        return strlen($d) >= 6 ? substr($d, -10) : '';
    }

    /**
     * Other leads and clients with the same phone (any of the lead's numbers) or email.
     * Lead names are only shown for leads the current user may open.
     *
     * @return array<int,array{type:string,id:int,name:string,detail:string,match:string,url:?string}>
     */
    public static function duplicates(array $phones, ?string $email, ?int $excludeLeadId = null, int $limit = 8): array
    {
        $keys  = array_values(array_unique(array_merge([], ...array_map([self::class, 'phoneKeys'], $phones))));
        $email = mb_strtolower(trim((string)$email));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        if (!$keys && $email === '') {
            return [];
        }
        $out = [];

        // Leads: narrow in SQL by the last 7 digits, confirm in PHP on the last 10.
        $or = ['or'];
        foreach ($keys as $k) {
            $tail = substr($k, -7);
            foreach (['contact_no', 'contact_no2', 'contact_person_mobile'] as $col) {
                $or[] = ['like', self::digitsSql($col), $tail];
            }
        }
        if ($email !== '') {
            $or[] = ['email' => $email];
            $or[] = ['contact_person_email' => $email];
        }
        $q = Lead::find()->with('assignedTo')->where(['!=', 'status', Lead::STATUS_DELETED])->andWhere($or)->orderBy(['id' => SORT_DESC])->limit(50);
        if ($excludeLeadId) {
            $q->andWhere(['!=', 'id', $excludeLeadId]);
        }
        foreach ($q->all() as $l) {
            $match = '';
            foreach ([$l->contact_no, $l->contact_no2, $l->contact_person_mobile] as $p) {
                if (array_intersect(self::phoneKeys($p), $keys)) {
                    $match = 'phone';
                    break;
                }
            }
            if ($match === '' && $email !== '' && in_array($email, [mb_strtolower((string)$l->email), mb_strtolower((string)$l->contact_person_email)], true)) {
                $match = 'email';
            }
            if ($match === '') {
                continue;
            }
            $visible = LeadAccess::canAccess($l);
            $out[] = [
                'type'   => 'lead',
                'id'     => (int)$l->id,
                'name'   => $visible ? (string)$l->client_name : 'Lead #' . $l->id,
                'detail' => $l->getLeadStatusLabel() . ' · ' . Lead::userLabel($l->assignedTo),
                'match'  => $match,
                'url'    => $visible ? Url::to(['/lead/view', 'id' => $l->id]) : null,
            ];
            if (count($out) >= $limit) {
                return $out;
            }
        }

        // Clients.
        $or = ['or'];
        foreach ($keys as $k) {
            $tail = substr($k, -7);
            $or[] = ['like', self::digitsSql('phone'), $tail];
            $or[] = ['like', self::digitsSql('alt_phone'), $tail];
        }
        if ($email !== '') {
            $or[] = ['email_id' => $email];
        }
        $canClients = UserAccess::checkAccess('client/view') || UserAccess::checkAccess('client/index');
        foreach (ClientModel::find()->where(['status' => 1])->andWhere($or)->limit(20)->all() as $c) {
            $match = '';
            foreach ([$c->phone, $c->alt_phone] as $p) {
                if (array_intersect(self::phoneKeys($p), $keys)) {
                    $match = 'phone';
                    break;
                }
            }
            if ($match === '' && $email !== '' && mb_strtolower((string)$c->email_id) === $email) {
                $match = 'email';
            }
            if ($match === '') {
                continue;
            }
            $out[] = [
                'type'   => 'client',
                'id'     => (int)$c->id,
                'name'   => (string)$c->client_name,
                'detail' => 'Existing client' . ($c->company_name ? ' · ' . $c->company_name : ''),
                'match'  => $match,
                'url'    => $canClients ? Url::to(['/client/view', 'id' => $c->id]) : null,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    // ── Value estimate ───────────────────────────────────────────────────

    /**
     * Value range for the site area from the active catalogue prices of each
     * product category of interest (only products sold by a compatible unit).
     *
     * @return array{lines: array<int,array>, unit: string, area: float}|null  null when there is no area / interest
     */
    public static function estimate(array $categoryIds, $area, ?string $unit): ?array
    {
        $area = (float)$area;
        $unit = $unit ?: 'Sq.ft';
        $ids  = array_values(array_filter(array_map('intval', $categoryIds)));
        if ($area <= 0 || !$ids) {
            return null;
        }
        [$factors, $dimension] = isset(self::AREA_FACTORS[$unit]) ? [self::AREA_FACTORS, 'area'] : (isset(self::LENGTH_FACTORS[$unit]) ? [self::LENGTH_FACTORS, 'length'] : [null, null]);
        if ($factors === null) {
            return null;
        }
        $baseQty = $area * $factors[$unit];
        $names   = ProductCategory::find()->select(['name', 'id'])->where(['id' => $ids])->indexBy('id')->column();
        $lines   = [];
        foreach ($ids as $cid) {
            if (!isset($names[$cid])) {
                continue;
            }
            $rates = [];
            $products = Product::find()->select(['sale_price', 'units'])
                ->where(['category_id' => $cid, 'status' => 1, 'is_active' => 1])
                ->andWhere(['not', ['sale_price' => null]])->andWhere(['units' => array_keys($factors)])
                ->asArray()->all();
            foreach ($products as $p) {
                // price per product unit → price per base unit
                $rates[] = (float)$p['sale_price'] / $factors[$p['units']];
            }
            if (!$rates) {
                $lines[] = ['category' => $names[$cid], 'products' => 0, 'note' => 'No products priced by ' . ($dimension === 'area' ? 'area' : 'length') . ' in this category.'];
                continue;
            }
            $min = min($rates);
            $max = max($rates);
            $avg = array_sum($rates) / count($rates);
            $lines[] = [
                'category'  => $names[$cid],
                'products'  => count($rates),
                'rate_min'  => round($min * $factors[$unit], 2),   // shown per the lead's unit
                'rate_max'  => round($max * $factors[$unit], 2),
                'low'       => round($baseQty * $min),
                'high'      => round($baseQty * $max),
                'typical'   => round($baseQty * $avg),
            ];
        }
        return ['lines' => $lines, 'unit' => $unit, 'area' => $area];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** SQL expression: the column with spaces, dashes, dots, brackets and + removed (column names are fixed, never user input). */
    private static function digitsSql(string $column): \yii\db\Expression
    {
        $sql = $column;
        foreach ([' ', '-', '.', '(', ')', '+'] as $ch) {
            $sql = "REPLACE($sql, '$ch', '')";
        }
        return new \yii\db\Expression($sql);
    }

    /** Timestamp of a date/datetime string, or null for empty / legacy zero dates. */
    public static function ts($value): ?int
    {
        $ts = $value ? strtotime((string)$value) : false;
        return ($ts !== false && (int)date('Y', $ts) >= 1971) ? $ts : null;
    }
}

<?php

namespace backend\components;

use backend\models\ClientQuotation;
use backend\models\Invoice;
use backend\models\Lead;
use common\components\UserAccess;
use Yii;
use yii\db\ActiveQuery;

/**
 * Figures for the Green Divine sales dashboard (site/index in simple mode).
 * Lead figures follow the user's lead access (LeadAccess); quotation and invoice
 * blocks are only filled when the user may open those modules.
 */
class GdDashboard
{
    /** period key => [label, days back (null = all time, 0 = today)] */
    public const PERIODS = [
        'today' => ['Today', 0],
        'week'  => ['1 Week', 7],
        'month' => ['1 Month', 30],
        '3m'    => ['3 Months', 90],
        '6m'    => ['6 Months', 182],
        'all'   => ['All Time', null],
    ];

    public static function period(?string $key): string
    {
        return isset(self::PERIODS[$key]) ? $key : 'month';
    }

    /** First day of the period (Y-m-d) or null for all time. */
    public static function since(string $period): ?string
    {
        $days = self::PERIODS[$period][1];
        return $days === null ? null : date('Y-m-d', strtotime("-$days days"));
    }

    public static function data(string $period): array
    {
        $since = self::since($period);
        $today = date('Y-m-d');
        $out   = ['period' => $period, 'since' => $since, 'leads' => null, 'quotes' => null, 'invoices' => null];

        if (UserAccess::checkAccess('lead/index') || UserAccess::checkAccess('lead/my-leads')) {
            $open = fn(): ActiveQuery => self::leadScope()
                ->andWhere(['lead.status' => Lead::STATUS_ACTIVE])
                ->andWhere(['not in', 'lead.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]]);
            $inPeriod = fn(ActiveQuery $q, string $col): ActiveQuery => $since === null ? $q : $q->andWhere(['>=', $col, $since . ' 00:00:00']);

            $bySource = $inPeriod(self::leadScope()->andWhere(['lead.status' => [Lead::STATUS_ACTIVE, Lead::STATUS_INACTIVE]]), 'lead.created_date')
                ->select(['src' => 'lead.lead_source', 'n' => 'COUNT(*)'])->groupBy('lead.lead_source')->orderBy(['n' => SORT_DESC])
                ->asArray()->all();
            $sources = Lead::sourceOptions();

            $out['leads'] = [
                'new'       => (int) $inPeriod(self::leadScope()->andWhere(['lead.status' => [Lead::STATUS_ACTIVE, Lead::STATUS_INACTIVE]]), 'lead.created_date')->count(),
                'open'      => (int) $open()->count(),
                'hot'       => (int) $open()->andWhere(['lead.priority' => Lead::PRIORITY_HIGH])->count(),
                'dueToday'  => (int) $open()->andWhere(['between', 'lead.meeting_reported_date', "$today 00:00:00", "$today 23:59:59"])->count(),
                'overdue'   => (int) $open()->andWhere(['<', 'lead.meeting_reported_date', "$today 00:00:00"])
                                    ->andWhere(['>=', 'lead.meeting_reported_date', date('Y-m-d', strtotime('-60 days')) . ' 00:00:00'])->count(),
                'converted' => (int) ($since === null
                    ? self::leadScope()->andWhere(['lead.lead_status' => Lead::LEAD_STATUS_CONVERTED])->count()
                    : self::leadScope()->andWhere(['lead.lead_status' => Lead::LEAD_STATUS_CONVERTED])->andWhere(['>=', 'lead.converted_date_time', "$since 00:00:00"])->count()),
                'agenda'    => $open()->andWhere(['<=', 'lead.meeting_reported_date', "$today 23:59:59"])
                                    ->andWhere(['>=', 'lead.meeting_reported_date', date('Y-m-d', strtotime('-60 days')) . ' 00:00:00'])
                                    ->orderBy(['lead.meeting_reported_date' => SORT_ASC])->limit(7)->all(),
                'recent'    => self::leadScope()->andWhere(['lead.status' => [Lead::STATUS_ACTIVE, Lead::STATUS_INACTIVE]])
                                    ->orderBy(['lead.id' => SORT_DESC])->limit(6)->all(),
                'sources'   => array_map(fn($r) => ['label' => $sources[(int)$r['src']] ?? 'Not set', 'n' => (int)$r['n'], 'id' => (int)$r['src']], $bySource),
            ];
        }

        if (UserAccess::checkAccess('client-quotation/index')) {
            $q = fn(): ActiveQuery => ClientQuotation::find()->where(['status' => 1])
                ->andFilterWhere(['>=', 'quote_date', $since]);
            $count = fn(array $st) => (int) $q()->andWhere(['quote_status' => $st])->count();
            $value = fn(array $st) => (float) $q()->andWhere(['quote_status' => $st])->sum('COALESCE(grand_total, estimation_cost, 0)');
            $out['quotes'] = [
                'count'      => (int) $q()->count(),
                'value'      => (float) $q()->sum('COALESCE(grand_total, estimation_cost, 0)'),
                'open'       => $count([ClientQuotation::QS_DRAFT, ClientQuotation::QS_SENT]),
                'openValue'  => $value([ClientQuotation::QS_DRAFT, ClientQuotation::QS_SENT]),
                'won'        => $count([ClientQuotation::QS_ACCEPTED, ClientQuotation::QS_INVOICED]),
                'wonValue'   => $value([ClientQuotation::QS_ACCEPTED, ClientQuotation::QS_INVOICED]),
                'recent'     => ClientQuotation::find()->where(['status' => 1])->with('client')->orderBy(['id' => SORT_DESC])->limit(6)->all(),
            ];
        }

        if (UserAccess::checkAccess('invoice/index')) {
            $inv = fn(): ActiveQuery => Invoice::find()->where(['status' => 1, 'invoice_role' => Invoice::ROLE_TAX_INVOICE])
                ->andWhere(['<>', 'invoice_status', 0]);
            $out['invoices'] = [
                'count'   => (int) $inv()->andFilterWhere(['>=', 'invoice_date', $since])->count(),
                'billed'  => (float) $inv()->andFilterWhere(['>=', 'invoice_date', $since])->sum('net_total'),
                'received'=> (float) $inv()->andFilterWhere(['>=', 'invoice_date', $since])->sum('COALESCE(paid_amount, 0)'),
                // Pending is always all time: money still to collect.
                'pending' => (float) $inv()->sum('GREATEST(COALESCE(net_total,0) - COALESCE(paid_amount,0) - COALESCE(tds_amount,0), 0)'),
            ];
        }

        return $out;
    }

    /** Leads this user may see (everyone for managers). */
    private static function leadScope(): ActiveQuery
    {
        $q = Lead::find();
        if (!LeadAccess::canSeeAllLeads()) {
            $q->andWhere(LeadAccess::involvedCondition((int) Yii::$app->user->id));
        }
        return $q;
    }

    /** ₹ 12.4 L / ₹ 3.2 Cr style figures for the tiles. */
    public static function money(float $v): string
    {
        if ($v >= 10000000) {
            return '₹' . rtrim(rtrim(number_format($v / 10000000, 2), '0'), '.') . ' Cr';
        }
        if ($v >= 100000) {
            return '₹' . rtrim(rtrim(number_format($v / 100000, 2), '0'), '.') . ' L';
        }
        return '₹' . number_format($v, 0);
    }
}

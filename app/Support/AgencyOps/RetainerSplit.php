<?php

namespace App\Support\AgencyOps;

use App\Models\AgencyBillingProfile;
use App\Models\Client;
use App\Models\ClientRetainerTerm;
use App\Support\MetricsQuery;
use App\Support\TextClean;
use DOMDocument;
use Illuminate\Support\Carbon;

/**
 * E2-067: one month's split of pass-through ad spend vs the agency management fee for a client, and three exports:
 * CSV, a ZATCA-style TLV Base64 QR payload and a UBL 2.1 XML invoice structure for JoFotara. All three are drafts for the
 * agency's accounting software and carry the same neutral reminder; nothing is filed, signed, validated or sent.
 * Tax is applied only to the management fee and only at the rate the agency typed; pass-through spend carries no tax line.
 *
 * @phpstan-type Split array{month: string, currency: string, spend: float, fee_model: string, fixed: float, percent: float,
 *   percent_fee: float, min_fee: float, fee: float, min_applied: bool, tax_rate: ?float, tax: float, total: float}
 */
class RetainerSplit
{
    public const REMINDER_EN = 'Draft structure for your accounting software — confirm tax treatment with your accountant.';

    public const REMINDER_AR = 'هيكل مسودة لبرنامج المحاسبة لديك — تحقّق من المعاملة الضريبية مع محاسبك.';

    /** @return Split */
    public static function compute(Client $client, ClientRetainerTerm $terms, string $month): array
    {
        $from = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $spend = (float) (MetricsQuery::totals($client, $from->toDateString(), $from->copy()->endOfMonth()->toDateString())['spend'] ?? 0);
        $cur = strtoupper($terms->currency);
        $percentFee = in_array($terms->fee_model, ['percent_spend', 'hybrid'], true) ? $spend * $terms->spend_percent / 100 : 0.0;
        $fixed = in_array($terms->fee_model, ['fixed', 'hybrid'], true) ? $terms->fixed_amount : 0.0;
        $raw = $fixed + $percentFee;
        $r = fn (float $v) => round($v, self::decimals($cur));
        // Each line is rounded to the currency first, so the total always equals the sum of the printed lines.
        $spend = $r($spend);
        $fee = $r(max($raw, $terms->min_fee));
        $tax = $terms->tax_rate_percent !== null ? $r($fee * $terms->tax_rate_percent / 100) : 0.0;

        return [
            'month' => $month, 'currency' => $cur, 'spend' => $r($spend), 'fee_model' => $terms->fee_model,
            'fixed' => $r($fixed), 'percent' => $terms->spend_percent, 'percent_fee' => $r($percentFee), 'min_fee' => $r($terms->min_fee),
            'fee' => $r($fee), 'min_applied' => $fee > $raw, 'tax_rate' => $terms->tax_rate_percent, 'tax' => $r($tax),
            'total' => $r($spend + $fee + $tax),
        ];
    }

    /** DS-24: 3 decimals for KWD / BHD / OMR / JOD (and the other config ones), 2 for SAR / AED / USD and the rest. */
    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), config('adsranger.three_decimal_currencies', []), true) ? 3 : 2;
    }

    /** Plain machine amount (Western digits, no grouping) for the exports. */
    public static function amount(float $v, string $currency): string
    {
        return (string) TextClean::western(number_format($v, self::decimals($currency), '.', ''));
    }

    /** @param  Split  $s */
    public static function csv(Client $client, array $s, bool $ar): string
    {
        $c = $s['currency'];
        $rows = [
            $ar ? ['الشهر', 'العميل', 'إنفاق إعلاني يُمرَّر كما هو', 'نموذج الأتعاب', 'أتعاب الإدارة', 'نسبة الضريبة على الأتعاب', 'ضريبة الأتعاب', 'الإجمالي', 'العملة']
                : ['Month', 'Client', 'Pass-through ad spend', 'Fee model', 'Management fee', 'Tax rate on fee', 'Tax on fee', 'Total', 'Currency'],
            [$s['month'], $client->name, self::amount($s['spend'], $c), $s['fee_model'], self::amount($s['fee'], $c),
                $s['tax_rate'] !== null ? TextClean::western(number_format($s['tax_rate'], 2, '.', '')) : '', self::amount($s['tax'], $c), self::amount($s['total'], $c), $c],
            [],
            [$ar ? self::REMINDER_AR : self::REMINDER_EN],
        ];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($v) => self::cell((string) $v), $row), ',', '"', '');
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * ZATCA-style TLV (tags 1–5: seller, VAT number, time, total with VAT, VAT) as Base64, for a QR in the accounting tool.
     *
     * @param  Split  $s
     */
    public static function zatcaTlv(AgencyBillingProfile $profile, array $s, Carbon $issuedAt): string
    {
        $c = $s['currency'];
        $fields = [
            1 => $profile->legal_name,
            2 => (string) $profile->tax_identifier,
            3 => $issuedAt->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            4 => self::amount($s['total'], $c),
            5 => self::amount($s['tax'], $c),
        ];
        $bin = '';
        foreach ($fields as $tag => $value) {
            $value = mb_strcut($value, 0, 255, 'UTF-8');
            $bin .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($bin);
    }

    /** @return array<int, string> */
    public static function decodeTlv(string $base64): array
    {
        $bin = (string) base64_decode($base64, true);
        $out = [];
        for ($i = 0; $i + 2 <= strlen($bin);) {
            $tag = ord($bin[$i]);
            $len = ord($bin[$i + 1]);
            $out[$tag] = substr($bin, $i + 2, $len);
            $i += 2 + $len;
        }

        return $out;
    }

    /**
     * UBL 2.1 Invoice structure (as JoFotara reads it): supplier, customer, a management-fee line taxed at the agency's
     * rate and a pass-through ad-spend line with no tax category set. Unsigned, no UUID registration, no QR.
     *
     * @param  Split  $s
     */
    public static function ublXml(AgencyBillingProfile $profile, Client $client, array $s, string $invoiceId, Carbon $issuedAt): string
    {
        $c = $s['currency'];
        $ns = ['' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2'];
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $doc->appendChild($doc->createComment(' '.self::REMINDER_EN.' '));
        $root = $doc->createElementNS($ns[''], 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', $ns['cac']);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', $ns['cbc']);
        $doc->appendChild($root);
        $cbc = function (\DOMElement $parent, string $name, string $value, array $attrs = []) use ($doc, $ns): \DOMElement {
            $el = $doc->createElementNS($ns['cbc'], 'cbc:'.$name);
            $el->appendChild($doc->createTextNode($value));
            foreach ($attrs as $k => $v) {
                $el->setAttribute($k, $v);
            }
            $parent->appendChild($el);

            return $el;
        };
        $cac = function (\DOMElement $parent, string $name) use ($doc, $ns): \DOMElement {
            $el = $doc->createElementNS($ns['cac'], 'cac:'.$name);
            $parent->appendChild($el);

            return $el;
        };
        $money = fn (float $v) => self::amount($v, $c);

        $cbc($root, 'UBLVersionID', '2.1');
        $cbc($root, 'ProfileID', 'reporting:1.0');
        $cbc($root, 'ID', $invoiceId);
        $cbc($root, 'IssueDate', $issuedAt->toDateString());
        $cbc($root, 'InvoiceTypeCode', '388', ['name' => '012']);
        $cbc($root, 'Note', 'Period '.$s['month']);
        $cbc($root, 'DocumentCurrencyCode', $c);
        $cbc($root, 'TaxCurrencyCode', $c);

        $party = function (string $role, string $name, ?string $taxId, ?string $country) use ($root, $cac, $cbc): void {
            $p = $cac($cac($root, $role), 'Party');
            if ($country) {
                $cbc($cac($cac($p, 'PostalAddress'), 'Country'), 'IdentificationCode', $country);
            }
            if ($taxId) {
                $scheme = $cac($p, 'PartyTaxScheme');
                $cbc($scheme, 'CompanyID', $taxId);
                $cbc($cac($scheme, 'TaxScheme'), 'ID', 'VAT');
            }
            $cbc($cac($p, 'PartyLegalEntity'), 'RegistrationName', $name);
        };
        $party('AccountingSupplierParty', $profile->legal_name, $profile->tax_identifier, $profile->country_code ? strtoupper($profile->country_code) : null);
        $party('AccountingCustomerParty', $client->name, null, null);

        $taxTotal = $cac($root, 'TaxTotal');
        $cbc($taxTotal, 'TaxAmount', $money($s['tax']), ['currencyID' => $c]);

        $totals = $cac($root, 'LegalMonetaryTotal');
        $cbc($totals, 'LineExtensionAmount', $money($s['spend'] + $s['fee']), ['currencyID' => $c]);
        $cbc($totals, 'TaxExclusiveAmount', $money($s['spend'] + $s['fee']), ['currencyID' => $c]);
        $cbc($totals, 'TaxInclusiveAmount', $money($s['total']), ['currencyID' => $c]);
        $cbc($totals, 'PayableAmount', $money($s['total']), ['currencyID' => $c]);

        $line = function (string $id, string $name, float $amount, ?float $rate, float $tax) use ($root, $cac, $cbc, $money, $c): void {
            $l = $cac($root, 'InvoiceLine');
            $cbc($l, 'ID', $id);
            $cbc($l, 'InvoicedQuantity', '1', ['unitCode' => 'PCE']);
            $cbc($l, 'LineExtensionAmount', $money($amount), ['currencyID' => $c]);
            if ($rate !== null) {
                $t = $cac($l, 'TaxTotal');
                $cbc($t, 'TaxAmount', $money($tax), ['currencyID' => $c]);
                $cbc($t, 'RoundingAmount', $money($amount + $tax), ['currencyID' => $c]);
            }
            $item = $cac($l, 'Item');
            $cbc($item, 'Name', $name);
            if ($rate !== null) {
                $cat = $cac($item, 'ClassifiedTaxCategory');
                $cbc($cat, 'ID', 'S');
                $cbc($cat, 'Percent', number_format($rate, 2, '.', ''));
                $cbc($cac($cat, 'TaxScheme'), 'ID', 'VAT');
            }
            $cbc($cac($l, 'Price'), 'PriceAmount', $money($amount), ['currencyID' => $c]);
        };
        $line('1', 'Agency management fee '.$s['month'], $s['fee'], $s['tax_rate'], $s['tax']);
        $line('2', 'Pass-through advertising spend '.$s['month'].' (tax treatment to confirm)', $s['spend'], null, 0.0);

        return (string) $doc->saveXML();
    }

    /** CSV cells never start a formula. */
    private static function cell(string $v): string
    {
        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }
}

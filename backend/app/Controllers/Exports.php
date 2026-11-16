<?php

namespace App\Controllers;

/**
 * Accounting exports for an external accountant: Xero (manual journal & sales invoice CSV)
 * and Sage Business Cloud Accounting (journal CSV).
 */
class Exports extends BaseController
{
    public function index()
    {
        [$from, $to] = $this->range('month');
        return view('accounting/exports', ['from' => $from, 'to' => $to]);
    }

    private function csv(string $filename, array $header, array $rows)
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $header, ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($fh, $r, ',', '"', '\\');
        }
        rewind($fh);
        $body = stream_get_contents($fh);
        fclose($fh);
        return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')->setBody("\xEF\xBB\xBF" . $body);
    }

    private function journalLines(string $from, string $to): array
    {
        return $this->db->table('journal_lines l')->select('e.number, e.entry_date, e.description entry_desc, e.reference, l.description, l.debit, l.credit, a.code, a.name, a.sub_type')
            ->join('journal_entries e', 'e.id = l.journal_entry_id')->join('accounts a', 'a.id = l.account_id')
            ->where('e.entry_date >=', $from)->where('e.entry_date <=', $to)->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.id')->get()->getResultArray();
    }

    /** Xero "Manual Journal" import format. VAT is carried on explicit VAT accounts, so lines are "No VAT". */
    public function xeroJournals()
    {
        [$from, $to] = $this->range('month');
        $rows = [];
        foreach ($this->journalLines($from, $to) as $l) {
            $amount = round((float) $l['debit'] - (float) $l['credit'], 2);
            $rows[] = [$l['number'] . ' ' . $l['entry_desc'], date('d/m/Y', strtotime($l['entry_date'])), $l['description'] ?: $l['entry_desc'], $l['code'], 'No VAT', number_format($amount, 2, '.', ''), '', '', '', ''];
        }
        return $this->csv('xero-manual-journals-' . $from . '-' . $to . '.csv', ['*Narration', '*Date', 'Description', '*AccountCode', '*TaxRate', '*Amount', 'TrackingName1', 'TrackingOption1', 'TrackingName2', 'TrackingOption2'], $rows);
    }

    /** Xero "Sales Invoice" import format (one row per invoice line). */
    public function xeroInvoices()
    {
        [$from, $to] = $this->range('month');
        $map = ['labour' => '4000', 'service' => '4000', 'part' => '4100', 'fluid' => '4100', 'tyre' => '4100', 'sublet' => '4200', 'sundry' => '4200'];
        $lines = $this->db->table('invoices i')->select('i.number, i.issue_date, i.due_date, i.order_number, c.first_name, c.last_name, c.company_name, c.type, c.email, li.description, li.quantity, li.unit_price, li.discount_percent, li.item_type, li.vat_rate, v.registration')
            ->join('customers c', 'c.id = i.customer_id')->join('vehicles v', 'v.id = i.vehicle_id', 'left')
            ->join('line_items li', "li.document_type = 'invoice' AND li.document_id = i.id")
            ->where('i.deleted_at', null)->whereNotIn('i.status', ['draft', 'void'])->where('i.issue_date >=', $from)->where('i.issue_date <=', $to)
            ->orderBy('i.id')->orderBy('li.sort_order')->get()->getResultArray();
        $rows = [];
        foreach ($lines as $l) {
            $rows[] = [customer_name($l), $l['email'], $l['number'], $l['order_number'] ?: $l['registration'], date('d/m/Y', strtotime($l['issue_date'])), date('d/m/Y', strtotime($l['due_date'])),
                $l['description'], (float) $l['quantity'], number_format((float) $l['unit_price'], 2, '.', ''), (float) $l['discount_percent'] ?: '', $map[$l['item_type']] ?? '4200',
                (float) $l['vat_rate'] > 0 ? 'Standard Rate Sales' : 'Zero Rated Sales', 'ZAR'];
        }
        return $this->csv('xero-sales-invoices-' . $from . '-' . $to . '.csv', ['*ContactName', 'EmailAddress', '*InvoiceNumber', 'Reference', '*InvoiceDate', '*DueDate', 'Description', '*Quantity', '*UnitAmount', 'Discount', '*AccountCode', '*TaxType', 'Currency'], $rows);
    }

    /** Sage Business Cloud Accounting journal import (Date, Reference, Ledger account, Details, Debit, Credit, VAT). */
    public function sageJournals()
    {
        [$from, $to] = $this->range('month');
        $rows = [];
        foreach ($this->journalLines($from, $to) as $l) {
            $rows[] = [date('d/m/Y', strtotime($l['entry_date'])), $l['number'], $l['code'], mb_substr($l['description'] ?: $l['entry_desc'], 0, 60), (float) $l['debit'] ? number_format((float) $l['debit'], 2, '.', '') : '', (float) $l['credit'] ? number_format((float) $l['credit'], 2, '.', '') : '', 'No VAT'];
        }
        return $this->csv('sage-journals-' . $from . '-' . $to . '.csv', ['Date', 'Reference', 'Ledger Account', 'Details', 'Debit', 'Credit', 'VAT Rate'], $rows);
    }

    /** Chart of accounts (to set up matching codes in Xero / Sage first). */
    public function chart()
    {
        $rows = array_map(static fn ($a) => [$a['code'], $a['name'], ucfirst($a['type']), str_replace('_', ' ', $a['sub_type']), $a['description']],
            $this->db->table('accounts')->where('is_active', 1)->orderBy('code')->get()->getResultArray());
        return $this->csv('chart-of-accounts.csv', ['*Code', '*Name', '*Type', 'Detail type', 'Description'], $rows);
    }
}

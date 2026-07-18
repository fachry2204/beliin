<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Faktur Gabungan - {{ $customer['company_name'] ?: $customer['name'] }}</title>
<style>
@page { size: {{ \App\Support\PrintPaper::cssSize($company) }}; margin: {{ \App\Support\PrintPaper::cssMargin($company) }}; }
* { box-sizing: border-box; }
html, body { width: 100%; }
body { margin: 0; color: #000; background: #fff; font-family: "Courier New", Courier, monospace; font-size: 7.5pt; line-height: 1.3; }
.print-sheet { width: 100%; max-width: 100%; overflow: visible; }
.company { padding-bottom: 6px; border-bottom: 1px solid #000; text-align: center; }
.company h1 { margin: 0 0 3px; color: #000; font-size: 13pt; }
.company p { max-width: 100%; margin: 0; overflow-wrap: break-word; word-break: normal; }
.title { margin: 8px 0; text-align: center; }
.title h2 { margin: 0 0 3px; font-size: 12pt; }
.customer { margin-bottom: 8px; padding: 6px; border: 1px solid #000; line-height: 1.5; overflow-wrap: break-word; word-break: normal; }
table { width: 100%; max-width: 100%; border-collapse: collapse; table-layout: fixed; }
thead { display: table-header-group; }
tr { break-inside: avoid; page-break-inside: avoid; }
th { padding: 4px; border: 1px solid #000; background: #fff; color: #000; font-size: 7pt; line-height: 1.25; text-align: left; overflow-wrap: break-word; word-break: normal; }
td { padding: 4px; border: 1px solid #000; font-size: 7.5pt; line-height: 1.3; overflow-wrap: break-word; word-break: normal; }
.num { text-align: right; }
.after { display: table; width: 100%; margin-top: 8px; table-layout: fixed; }
.notes, .totals-cell { display: table-cell; vertical-align: top; }
.notes { width: 50%; padding: 6px; border: 1px solid #000; background: #fff; line-height: 1.35; }
.notes h3 { margin: 0 0 3px; color: #000; font-size: 7.5pt; }
.notes p { margin: 0 0 6px; }
.notes p:last-child { margin-bottom: 0; }
.totals-cell { width: 50%; padding-left: 8px; }
.totals td { font-weight: bold; }
.grand td { border-top-width: 2px; color: #000; font-size: 9pt; }
@if($company?->printer_paper_size === 'continuous_9_5x11')
body { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.35; }
.company { padding-bottom: 6px; }
.company h1 { margin-bottom: 3px; font-size: 15pt; line-height: 1.3; }
.company p { font-size: 8.5pt; line-height: 1.3; }
.title { margin: 7px 0; line-height: 1.35; }
.title h2 { font-size: 13pt; line-height: 1.3; }
.customer { margin-bottom: 7px; padding: 6px; font-size: 8.5pt; line-height: 1.35; overflow: visible; }
.invoice-table th { height: auto; padding: 3px 4px; font-size: 8.5pt; line-height: 1.2; vertical-align: middle; overflow: visible; }
.invoice-table td { height: auto; padding: 3.5px 4px; font-size: 9pt; line-height: 1.25; vertical-align: middle; overflow: visible; }
.after { margin-top: 7px; }
.notes { padding: 6px; font-size: 8.5pt; line-height: 1.35; overflow: visible; }
.notes h3 { font-size: 9pt; line-height: 1.3; }
.notes p { margin-bottom: 6px; }
.totals-cell { padding-left: 7px; }
.totals td { padding: 4px; font-size: 8.5pt; line-height: 1.3; overflow: visible; }
.grand td { font-size: 10.5pt; }
@endif
@media print {
    * { color: #000 !important; background: #fff !important; box-shadow: none !important; }
}
</style></head><body>
<div class="print-sheet">
<header class="company"><h1>{{ $company?->company_name ?? 'InvoFlow' }}</h1><p>{{ $company?->address ?: '-' }} | {{ $company?->phone ?: '-' }}</p></header>
<div class="title"><h2>FAKTUR GABUNGAN</h2><strong>Nomor Faktur: {{ $document->facture_number }}</strong><br><span>Tanggal faktur: {{ $document->opened_at->format('d/m/Y') }}</span>@if($document->due_date)<br><strong>Jatuh tempo: {{ $document->due_date->format('d/m/Y') }}</strong>@endif</div>
<section class="customer"><strong>Tagihan Kepada: {{ $customer['company_name'] ?: $customer['name'] }} ({{ $customer['name'] }}) ({{ $customer['phone'] ?: '-' }})</strong><br>Alamat: {{ $customer['address'] ?: '-' }}</section>
<table class="invoice-table">
<colgroup><col style="width:5%"><col style="width:20%"><col style="width:18%"><col style="width:12%"><col style="width:15%"><col style="width:15%"><col style="width:15%"></colgroup>
<thead><tr><th>No</th><th>Nomor Invoice</th><th>No. PO</th><th>Tanggal</th><th class="num">Tagihan</th><th class="num">Terbayar</th><th class="num">Sisa</th></tr></thead><tbody>
@foreach($invoices as $invoice)<tr><td>{{ $loop->iteration }}</td><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->purchase_order_number ?: '-' }}</td><td>{{ $invoice->invoice_date->format('d/m/Y') }}</td><td class="num">{{ number_format((float)$invoice->grand_total,0,',','.') }}</td><td class="num">{{ number_format((float)$invoice->paid_amount,0,',','.') }}</td><td class="num">{{ number_format((float)$invoice->remaining_amount,0,',','.') }}</td></tr>@endforeach
</tbody></table>
<div class="after">
<section class="notes"><h3>Catatan</h3><p>@forelse($invoices->filter(fn ($invoice) => filled($invoice->notes)) as $invoice)<strong>{{ $invoice->invoice_number }}:</strong> {{ $invoice->notes }}@unless($loop->last)<br>@endunless @empty-@endforelse</p><h3>Syarat pembayaran</h3><p>@if($document->due_date)Pembayaran jatuh tempo tanggal {{ $document->due_date->format('d/m/Y') }}.<br>@endif Pembayaran Melalui Transfer:<br><strong>Bank: {{ $company?->bank_name ?: '-' }} | No Rekening: {{ $company?->bank_account_number ?: '-' }}<br>Atas Nama Rekening: {{ $company?->bank_account_name ?: '-' }}</strong></p></section>
<div class="totals-cell"><table class="totals"><tr><td>Total Tagihan</td><td class="num">Rp {{ number_format((float)$totals['grand_total'],0,',','.') }}</td></tr><tr><td>Total Terbayar</td><td class="num">Rp {{ number_format((float)$totals['paid_total'],0,',','.') }}</td></tr><tr class="grand"><td>TOTAL SISA</td><td class="num">Rp {{ number_format((float)$totals['remaining_total'],0,',','.') }}</td></tr></table></div>
</div>
</div>
@if($autoPrint ?? false)<script>window.addEventListener('load',()=>window.setTimeout(()=>window.print(),150));window.addEventListener('afterprint',()=>window.opener?window.close():window.history.back());</script>@endif
</body></html>

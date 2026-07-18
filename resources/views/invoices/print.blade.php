<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { size: {{ \App\Support\PrintPaper::cssSize($company) }}; margin: {{ \App\Support\PrintPaper::cssMargin($company) }}; }
        * { box-sizing: border-box; }
        html, body { width: 100%; }
        body { margin: 0; color: #000; background: #fff; font-family: "Courier New", Courier, monospace; font-size: 7.5pt; line-height: 1.3; }
        .print-sheet { width: 100%; max-width: 100%; overflow: visible; }
        .top { width: 100%; border-bottom: 1px solid #000; padding-bottom: 8px; }
        .brand { text-align: center; }
        .brand h1 { margin: 0 0 4px; color: #000; font-size: 13pt; }
        .company-meta { max-width: 100%; font-size: 7pt; line-height: 1.4; overflow-wrap: break-word; word-break: normal; }
        .company-meta .separator { padding: 0 4px; color: #000; }
        .details { display: table; width: 100%; margin: 9px 0; table-layout: fixed; }
        .customer-col, .invoice-col { display: table-cell; vertical-align: top; }
        .customer-col { width: 42%; }
        .invoice-col { width: 58%; }
        .customer-col { padding-right: 5px; }
        .invoice-col { padding-left: 5px; }
        .invoice-title { text-align: right; }
        .invoice-heading { color: #000; font-size: 9pt; font-weight: bold; line-height: 1.25; overflow-wrap: break-word; word-break: normal; }
        .invoice-heading-label { font-size: 12pt; }
        .invoice-heading-meta { font-size: 8pt; }
        .invoice-po { margin-top: 3px; font-size: 7pt; font-weight: bold; line-height: 1.3; overflow-wrap: break-word; word-break: normal; }
        .box { min-height: 48px; padding: 6px; border: 1px solid #000; background: #fff; }
        .box h3 { margin: 0 0 4px; color: #000; font-size: 7.5pt; }
        .billing-line { line-height: 1.45; }
        .billing-recipient { font-size: 7pt; overflow-wrap: break-word; word-break: normal; }
        .billing-label { color: #000; font-weight: bold; }
        table { width: 100%; max-width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        th { padding: 4px; border: 1px solid #000; background: #fff; color: #000; font-size: 7pt; line-height: 1.25; text-align: left; overflow-wrap: break-word; word-break: normal; }
        td { padding: 4px; border: 1px solid #000; font-size: 7.5pt; line-height: 1.3; overflow-wrap: break-word; word-break: normal; }
        .num { text-align: right; }
        .after-items { display: table; width: 100%; margin-top: 6px; table-layout: fixed; }
        .signatures-cell, .summary-cell { display: table-cell; vertical-align: top; }
        .signatures-cell { width: 57%; padding-right: 8px; }
        .summary-cell { width: 43%; }
        .signatures-panel { display: table; width: 100%; min-height: 58px; border: 1px solid #000; background: #fff; table-layout: fixed; }
        .inline-signature { display: table-cell; width: 50%; padding: 5px 6px 4px; font-size: 6.8px; text-align: center; vertical-align: top; }
        .inline-signature + .inline-signature { border-left: 1px solid #000; }
        .summary { width: 100%; margin: 0; }
        .summary td { padding: 4px; border: 1px solid #000; font-size: 7pt; }
        .summary .grand td { padding-top: 5px; border: 1px solid #000; border-top-width: 2px; color: #000; font-size: 9pt; font-weight: bold; }
        .signature-space { height: 29px; }
        .signature-line { width: 82%; margin: 0 auto; padding-top: 3px; border-top: 1px solid #000; font-size: 6.5pt; font-weight: bold; }
        .created-by { margin-top: 5px; padding: 4px; border: 1px solid #000; font-size: 7pt; font-weight: bold; line-height: 1.35; overflow-wrap: break-word; word-break: normal; }
        @if($company?->printer_paper_size === 'continuous_9_5x11')
        .print-sheet { padding-top: 5mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.35; }
        .top { padding-bottom: 6px; }
        .brand h1 { margin-bottom: 3px; font-size: 15pt; }
        .company-meta { font-size: 8.5pt; line-height: 1.3; }
        .details { margin: 4mm 0 7px; }
        .invoice-heading { padding-bottom: 1px; font-size: 10pt; line-height: 1.3; }
        .invoice-heading-label { font-size: 13pt; }
        .invoice-heading-meta { font-size: 8.5pt; }
        .invoice-po { padding-bottom: 1px; font-size: 8.5pt; line-height: 1.3; }
        .box { min-height: 58px; padding: 6px; overflow: visible; }
        .box h3 { font-size: 9pt; }
        .billing-line { line-height: 1.35; }
        .billing-recipient { font-size: 8.5pt; }
        .items-table th { height: auto; padding: 3px 4px; font-size: 8.5pt; line-height: 1.2; vertical-align: middle; overflow: visible; }
        .items-table td { height: auto; padding: 3.5px 4px; font-size: 9pt; line-height: 1.25; vertical-align: middle; overflow: visible; }
        .signatures-panel { min-height: 72px; }
        .inline-signature { padding: 6px 6px 4px; font-size: 8.5pt; line-height: 1.3; overflow: visible; }
        .signature-space { height: 30px; }
        .signature-line { padding-bottom: 1px; font-size: 8.5pt; line-height: 1.3; }
        .summary td { padding: 4px; font-size: 8.5pt; line-height: 1.3; }
        .summary .grand td { font-size: 10.5pt; }
        .created-by { margin-top: 5px; padding: 4px; font-size: 8.5pt; line-height: 1.35; }
        @endif
        @media print {
            * { color: #000 !important; background: #fff !important; box-shadow: none !important; }
        }
    </style>
</head>
<body><div class="print-sheet">
    <div class="top">
        <div class="brand">
            <h1>{{ $company?->company_name ?? 'InvoFlow' }}</h1>
            <div class="company-meta">
                Alamat: {{ $company?->address ?: '-' }}
                <span class="separator">|</span>No. HP: {{ $company?->phone ?: '-' }}
            </div>
        </div>
    </div>

    <div class="details">
        <div class="customer-col">
            <div class="box">
                <div class="billing-line billing-recipient">
                    <span class="billing-label">Tagihan Kepada :</span>
                    <strong>{{ $invoice->billing_company ?: $invoice->billing_name }}</strong>
                    @if($invoice->billing_company && $invoice->billing_name && $invoice->billing_company !== $invoice->billing_name)
                        ({{ $invoice->billing_name }})
                    @endif
                    @if($invoice->billing_phone)
                        ({{ $invoice->billing_phone }})
                    @endif
                </div>
                <div class="billing-line">
                    <span class="billing-label">Alamat :</span>
                    {{ $invoice->billing_address ?: '-' }}
                </div>
            </div>
        </div>
        <div class="invoice-col">
            <div class="box invoice-title">
                <div class="invoice-heading">
                    <span class="invoice-heading-label">INVOICE</span>
                    <span class="invoice-heading-meta"> | Tanggal : {{ $invoice->invoice_date->format('d/m/Y') }} | No Invoice : {{ $invoice->invoice_number }}</span>
                </div>
                <div class="invoice-po">No. PO : {{ $invoice->purchase_order_number ?: '-' }}</div>
            </div>
        </div>
    </div>

    <table class="items-table">
        <colgroup>
            <col style="width: 7%"><col style="width: 37%"><col style="width: 10%">
            <col style="width: 12%"><col style="width: 17%"><col style="width: 17%">
        </colgroup>
        <thead>
            <tr><th>No</th><th>Nama Barang</th><th class="num">Qty</th><th>Satuan</th><th class="num">Harga</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product_name_snapshot }}</td>
                    <td class="num">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                    <td>{{ $item->unit_snapshot }}</td>
                    <td class="num">Rp {{ number_format((float) $item->selling_price, 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((float) $item->line_subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="after-items">
        <div class="signatures-cell">
            <div class="signatures-panel">
                <div class="inline-signature">
                    Penerima,
                    <div class="signature-space"></div>
                    <div class="signature-line">Nama &amp; Tanda Tangan</div>
                </div>
                <div class="inline-signature">
                    Pengantar (Kurir),
                    <div class="signature-space"></div>
                    <div class="signature-line">{{ $invoice->courier_name ?: 'Nama & Tanda Tangan' }}</div>
                </div>
            </div>
        </div>
        <div class="summary-cell">
            <table class="summary">
                <tr><td>Subtotal</td><td class="num">Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</td></tr>
                @if((float) $invoice->discount_amount > 0)
                    <tr><td>Diskon</td><td class="num">- Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}</td></tr>
                @endif
                @if((float) $invoice->tax_amount > 0)
                    <tr><td>Pajak ({{ min(100, max(0, round((float) $invoice->tax_percentage))) }}%)</td><td class="num">Rp {{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}</td></tr>
                @endif
                <tr class="grand"><td>GRAND TOTAL</td><td class="num">Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}</td></tr>
            </table>
            <div class="created-by">
                Di Buat Oleh : {{ $invoice->creator?->name ?: '-' }} &nbsp;&nbsp; Tanggal : {{ $invoice->created_at?->format('d/m/Y') ?: '-' }}
            </div>
        </div>
    </div>
    </div>
    @if($autoPrint ?? false)
        <script>
            window.addEventListener('load', () => window.setTimeout(() => window.print(), 150));
            window.addEventListener('afterprint', () => window.opener ? window.close() : window.history.back());
        </script>
    @endif
</body>
</html>

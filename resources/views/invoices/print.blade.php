<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { size: {{ \App\Support\PrintPaper::cssSize($company) }}; margin: {{ \App\Support\PrintPaper::cssMargin($company) }}; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f172a; font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.2px; line-height: 1.25; }
        .top { width: 100%; border-bottom: 2px solid #0ea5e9; padding-bottom: 8px; }
        .brand { text-align: center; }
        .brand h1 { margin: 0 0 4px; color: #0369a1; font-size: 15px; }
        .company-meta { font-size: 6.8px; line-height: 1.35; }
        .company-meta .separator { padding: 0 4px; color: #94a3b8; }
        .details { display: table; width: 100%; margin: 9px 0; table-layout: fixed; }
        .customer-col, .invoice-col { display: table-cell; width: 50%; vertical-align: top; }
        .customer-col { padding-right: 5px; }
        .invoice-col { padding-left: 5px; }
        .invoice-title { text-align: right; }
        .invoice-title h2 { margin: 0 0 4px; color: #0f172a; font-size: 12px; }
        .invoice-primary { font-size: 6.3px; font-weight: bold; white-space: nowrap; }
        .invoice-dates { margin-top: 3px; font-size: 6.1px; white-space: nowrap; }
        .box { min-height: 48px; padding: 6px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .box h3 { margin: 0 0 4px; color: #0369a1; font-size: 7.5px; }
        .billing-line { line-height: 1.45; }
        .billing-recipient { font-size: 5.8px; white-space: nowrap; }
        .billing-label { color: #0369a1; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 4px; background: #0369a1; color: #fff; font-size: 6.6px; text-align: left; }
        td { padding: 4px; border-bottom: 1px solid #e2e8f0; font-size: 7px; }
        .num { text-align: right; }
        .after-items { display: table; width: 100%; margin-top: 6px; table-layout: fixed; }
        .signatures-cell, .summary-cell { display: table-cell; vertical-align: top; }
        .signatures-cell { width: 57%; padding-right: 8px; }
        .summary-cell { width: 43%; }
        .signatures-panel { display: table; width: 100%; min-height: 58px; border: 1px solid #e2e8f0; background: #f8fafc; table-layout: fixed; }
        .inline-signature { display: table-cell; width: 50%; padding: 5px 6px 4px; font-size: 6.8px; text-align: center; vertical-align: top; }
        .inline-signature + .inline-signature { border-left: 1px solid #e2e8f0; }
        .summary { width: 100%; margin: 0; }
        .summary td { padding: 3px 2px; border: 0; font-size: 6.8px; }
        .summary .grand td { padding-top: 5px; border-top: 2px solid #0ea5e9; color: #0369a1; font-size: 9px; font-weight: bold; }
        .signature-space { height: 29px; }
        .signature-line { width: 82%; margin: 0 auto; padding-top: 3px; border-top: 1px solid #64748b; font-size: 6.5px; font-weight: bold; }
        .created-by { margin-top: 5px; padding-top: 4px; border-top: 1px solid #cbd5e1; font-size: 6.2px; font-weight: bold; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="top">
        <div class="brand">
            <h1>{{ $company?->company_name ?? 'InvoFlow' }}</h1>
            <div class="company-meta">
                Alamat: {{ $company?->address ?: '-' }}
                <span class="separator">|</span>No. HP: {{ $company?->phone ?: '-' }}
                <span class="separator">|</span>Email: {{ $company?->email ?: '-' }}
                <span class="separator">|</span>NPWP: {{ $company?->tax_number ?: '-' }}
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
                <h2>INVOICE</h2>
                <div class="invoice-primary">Nomor Invoice : {{ $invoice->invoice_number }} | No. PO : {{ $invoice->purchase_order_number ?: '-' }}</div>
                <div class="invoice-dates">Tanggal Invoice : {{ $invoice->invoice_date->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    <table>
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
    @if($autoPrint ?? false)
        <script>
            window.addEventListener('load', () => window.setTimeout(() => window.print(), 150));
            window.addEventListener('afterprint', () => window.opener ? window.close() : window.history.back());
        </script>
    @endif
</body>
</html>

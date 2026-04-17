<x-mitra-layout>
    <x-slot name="header">{{ __('Data Affiliate Mitra') }}</x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="surface-card p-6">
            <h3 class="text-base font-semibold text-slate-900">Performa Affiliate</h3>
            <p class="mt-1 text-sm text-slate-600">Pantau affiliate yang memasarkan produk toko Anda.</p>

            @if($affiliateRows->isEmpty())
                <p class="mt-4 text-sm text-slate-600">Belum ada transaksi dari affiliate.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4">Affiliate</th>
                                <th class="py-2 pr-4">Order</th>
                                <th class="py-2 pr-4">Qty Produk</th>
                                <th class="py-2">Total Komisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($affiliateRows as $row)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-3 pr-4">
                                        <p class="font-medium text-slate-900">{{ $row->affiliate_name }}</p>
                                        <p class="text-xs text-slate-600">{{ $row->affiliate_email }}</p>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $row->total_orders) }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $row->total_qty) }}</td>
                                    <td class="py-3 font-semibold text-indigo-700">Rp{{ number_format((float) $row->total_commission, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="surface-card p-6">
            <h3 class="text-base font-semibold text-slate-900">Performa Produk dari Channel Affiliate</h3>
            <p class="mt-1 text-sm text-slate-600">Produk aktif affiliate dengan kontribusi penjualan dan total komisi.</p>

            @if($productRows->isEmpty())
                <p class="mt-4 text-sm text-slate-600">Belum ada performa produk dari affiliate.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-slate-600">
                                <th class="py-2 pr-4">Produk</th>
                                <th class="py-2 pr-4">Qty Terjual</th>
                                <th class="py-2 pr-4">Jumlah Affiliate</th>
                                <th class="py-2">Total Komisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($productRows as $row)
                                <tr class="border-b border-slate-100 last:border-0">
                                    <td class="py-3 pr-4 font-medium text-slate-900">{{ $row->product_name }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $row->total_qty) }}</td>
                                    <td class="py-3 pr-4 text-slate-700">{{ number_format((int) $row->affiliate_count) }}</td>
                                    <td class="py-3 font-semibold text-indigo-700">Rp{{ number_format((float) $row->total_commission, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-mitra-layout>

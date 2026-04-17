<x-mitra-layout>
    <x-slot name="header">{{ __('Riwayat Mutasi Stok Produk') }}</x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-xl border bg-white p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Riwayat Mutasi Stok Produk</h3>
                        <h3 class="text-base font-semibold text-gray-900">{{ $product->name }}</h3>
                        @php
                            $unitLabel = strtolower(trim((string) ($product->unit ?? 'kg')));
                            if ($unitLabel === '') {
                                $unitLabel = 'kg';
                            }
                        @endphp
                        <p class="text-sm text-gray-600">Stok saat ini: {{ number_format((int) $product->stock_qty) }} {{ $unitLabel }}</p>
                    </div>
                    <a href="{{ route('mitra.products.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Kembali Inventori</a>
                </div>

                @if($mutations instanceof \Illuminate\Pagination\LengthAwarePaginator && $mutations->isEmpty())
                    <p class="mt-4 text-sm text-gray-600">Belum ada data mutasi stok.</p>
                @elseif($mutations instanceof \Illuminate\Support\Collection && $mutations->isEmpty())
                    <p class="mt-4 text-sm text-gray-600">Belum ada data mutasi stok.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-600">
                                    <th class="py-2 pr-4">Waktu</th>
                                    <th class="py-2 pr-4">Tipe</th>
                                    <th class="py-2 pr-4">Before</th>
                                    <th class="py-2 pr-4">Delta</th>
                                    <th class="py-2 pr-4">After</th>
                                    <th class="py-2">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mutations as $m)
                                    <tr class="border-b last:border-0">
                                        <td class="py-3 pr-4 text-gray-700">{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('d M Y H:i') }}</td>
                                        <td class="py-3 pr-4 uppercase text-gray-700">{{ $m->change_type }}</td>
                                        <td class="py-3 pr-4 text-gray-700">{{ number_format((int) $m->qty_before) }}</td>
                                        <td class="py-3 pr-4 text-gray-700">{{ (int) $m->qty_delta > 0 ? '+' : '' }}{{ number_format((int) $m->qty_delta) }}</td>
                                        <td class="py-3 pr-4 text-gray-700">{{ number_format((int) $m->qty_after) }}</td>
                                        <td class="py-3 text-gray-600">{{ $m->note ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($mutations instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4">{{ $mutations->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-mitra-layout>

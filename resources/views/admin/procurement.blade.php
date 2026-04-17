<x-admin-layout>
    <x-slot name="header">
        {{ __('Modul Pengadaan') }}
    </x-slot>

    <div data-testid="admin-procurement-page" class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @php
            $activeSection = request()->query('section', 'stock');
        @endphp

        <div
            class="space-y-6"
            x-data="{
                manageOpen: false,
                manageProduct: null,
                updateAction: '',
                deleteAction: '',
                openManage(product) {
                    this.manageProduct = product;
                    this.updateAction = product.update_url;
                    this.deleteAction = product.delete_url;
                    this.manageOpen = true;
                    this.$nextTick(() => {
                        this.$refs.name.value = product.name ?? '';
                        this.$refs.description.value = product.description ?? '';
                        this.$refs.price.value = product.price ?? 0;
                        this.$refs.unit.value = product.unit ?? 'kg';
                        this.$refs.min_order_qty.value = product.min_order_qty ?? 1;
                        this.$refs.stock_qty.value = product.stock_qty ?? 0;
                        this.$refs.warehouse_id.value = product.warehouse_id ?? '';
                        this.$refs.is_active.checked = !!product.is_active;
                    });
                },
                closeManage() {
                    this.manageOpen = false;
                    this.manageProduct = null;
                    this.updateAction = '';
                    this.deleteAction = '';
                }
            }"
            @keydown.escape.window="closeManage()"
        >
            <section data-testid="admin-procurement-tabs" class="rounded-xl border bg-white p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('admin.modules.procurement', ['section' => 'stock']) }}#pengadaan-stock-input"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $activeSection === 'stock' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}"
                    >
                        Input Produk Pengadaan
                    </a>
                    <a
                        href="{{ route('admin.modules.procurement', ['section' => 'catalog']) }}#katalog-master"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $activeSection === 'catalog' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}"
                    >
                        Daftar Produk Pengadaan
                    </a>
                    <a
                        href="{{ route('admin.modules.procurement', ['section' => 'orders']) }}#order-mitra"
                        class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $activeSection === 'orders' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50' }}"
                    >
                        Order Pengadaan Mitra
                    </a>
                </div>
            </section>

            @if(session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @if($activeSection === 'stock')
                @php
                    $warehouseRows = collect($warehouses ?? []);
                    $hasWarehouseOptions = $warehouseRows->isNotEmpty();
                @endphp
                <section id="pengadaan-stock-input" data-testid="admin-procurement-stock-input" class="rounded-xl border bg-white p-6 lg:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">Tambah Produk Pengadaan Admin</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                Produk dari form ini akan masuk ke katalog pengadaan Mitra dan bisa dibeli langsung.
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                            Form Produk Admin
                        </span>
                    </div>

                    <form method="POST" action="{{ route('admin.adminProducts.store') }}" class="mt-6 space-y-5">
                        @csrf
                        @php
                            $selectedUnit = old('unit', 'kg');
                            $selectedWarehouse = (string) old('warehouse_id');
                        @endphp

                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="text-sm font-semibold text-slate-800">Informasi Produk</p>
                            <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <label for="admin-product-name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Produk</label>
                                    <input id="admin-product-name" type="text" name="name" value="{{ old('name') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Contoh: Pupuk Urea" required>
                                </div>
                                <div>
                                    <label for="admin-product-description" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Deskripsi</label>
                                    <input id="admin-product-description" type="text" name="description" value="{{ old('description') }}" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Deskripsi singkat produk">
                                </div>
                                <div class="lg:col-span-2">
                                    <label for="admin-product-warehouse" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Gudang Pengadaan</label>
                                    <select
                                        id="admin-product-warehouse"
                                        name="warehouse_id"
                                        class="w-full rounded-lg border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-500"
                                        required
                                        {{ $hasWarehouseOptions ? '' : 'disabled' }}
                                    >
                                        <option value="">Pilih gudang</option>
                                        @foreach($warehouseRows as $warehouse)
                                            <option value="{{ $warehouse->id }}" @selected($selectedWarehouse === (string) $warehouse->id)>{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                    @if(! $hasWarehouseOptions)
                                        <p class="mt-1 text-xs font-semibold text-rose-700">Belum ada gudang aktif. Tambah gudang dulu di menu Gudang.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="text-sm font-semibold text-slate-800">Harga & Stok</p>
                            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label for="admin-product-price" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Harga (Rp)</label>
                                    <input id="admin-product-price" type="number" min="0" step="0.01" name="price" value="{{ old('price', 0) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                </div>
                                <div>
                                    <label for="admin-product-unit" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Satuan</label>
                                    <select id="admin-product-unit" name="unit" class="w-full rounded-lg border-slate-300 text-sm" required>
                                        <option value="kg" @selected($selectedUnit === 'kg')>kg</option>
                                        <option value="gram" @selected($selectedUnit === 'gram')>gram</option>
                                        <option value="lt" @selected($selectedUnit === 'lt')>lt</option>
                                        <option value="ml" @selected($selectedUnit === 'ml')>ml</option>
                                        <option value="pcs" @selected($selectedUnit === 'pcs')>pcs</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="admin-product-min-order" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Min Order</label>
                                    <input id="admin-product-min-order" type="number" min="1" name="min_order_qty" value="{{ old('min_order_qty', 1) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                </div>
                                <div>
                                    <label for="admin-product-stock" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Stok</label>
                                    <input id="admin-product-stock" type="number" min="0" name="stock_qty" value="{{ old('stock_qty', 0) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 md:flex-row md:items-center md:justify-between">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600" @checked((string) old('is_active', '1') === '1')>
                                <span>Aktifkan produk untuk pengadaan Mitra</span>
                            </label>

                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-400" {{ $hasWarehouseOptions ? '' : 'disabled' }}>
                                Simpan Produk Pengadaan
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:flex-row md:items-center md:justify-between">
                        <p class="text-sm text-slate-600">
                            Kelola produk yang sudah dibuat dari tab daftar produk.
                        </p>
                        <a
                            href="{{ route('admin.modules.procurement', ['section' => 'catalog']) }}#katalog-master"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                        >
                            Buka Daftar & Kelola Produk
                        </a>
                    </div>
                </section>

            @endif

            @if($activeSection === 'catalog')
                <section id="katalog-master" data-testid="admin-procurement-catalog" class="rounded-xl border bg-white p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-slate-900">Daftar Produk Pengadaan Admin</h3>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            {{ number_format((int) $summary['total_admin_products']) }} produk
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">Daftar produk pengadaan admin yang siap dibeli oleh Mitra.</p>

                    @if($adminProducts->isEmpty())
                        <p class="mt-3 text-sm text-slate-600">Belum ada produk admin.</p>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left text-slate-600">
                                        <th class="py-2 pr-4">Produk</th>
                                        <th class="py-2 pr-4">Gudang</th>
                                        <th class="py-2 pr-4">Harga</th>
                                        <th class="py-2 pr-4">Satuan</th>
                                        <th class="py-2 pr-4">Min Order</th>
                                        <th class="py-2 pr-4">Stok</th>
                                        <th class="py-2 pr-4">Status</th>
                                        <th class="sticky right-0 bg-white py-2 pl-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($adminProducts as $product)
                                        <tr class="border-b last:border-0">
                                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $product->name }}</td>
                                            <td class="py-3 pr-4 text-slate-700">{{ $product->warehouse_label }}</td>
                                            <td class="py-3 pr-4 text-slate-700">{{ $product->price_label }}</td>
                                            <td class="py-3 pr-4 text-slate-700">{{ $product->unit_label }}</td>
                                            <td class="py-3 pr-4 text-slate-700">{{ $product->min_order_qty_label }}</td>
                                            <td class="py-3 pr-4 text-slate-700">{{ $product->stock_qty_label }}</td>
                                            <td class="py-3 pr-4">
                                                <span class="{{ $product->status_badge_class }}">{{ $product->status_label }}</span>
                                            </td>
                                            <td class="sticky right-0 bg-white py-3 pl-4 text-right">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-lg border border-slate-300 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                                    @click="openManage(@js([
                                                        "id" => $product->id,
                                                        "name" => $product->name,
                                                        "description" => $product->description,
                                                        "price" => $product->price_value,
                                                        "unit" => $product->unit_value,
                                                        "min_order_qty" => $product->min_order_qty_value,
                                                        "stock_qty" => $product->stock_qty_value,
                                                        "warehouse_id" => $product->warehouse_id_value,
                                                        "is_active" => (bool) $product->is_active_value,
                                                        "update_url" => route("admin.adminProducts.update", ["adminProductId" => $product->id]),
                                                        "delete_url" => route("admin.adminProducts.destroy", ["adminProductId" => $product->id]),
                                                    ]))"
                                                >
                                                    Kelola
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            @if($activeSection === 'orders')
                <div id="order-mitra" data-testid="admin-procurement-orders" class="rounded-xl border bg-white p-6">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Order Pengadaan dari Mitra</h3>
                        <p class="mt-1 text-sm text-slate-600">Tampilan ringkas order. Verifikasi pembayaran dan update status dilakukan dari halaman detail order.</p>
                    </div>

                    @if(count($adminOrders) === 0)
                        <p class="mt-4 text-sm text-slate-600">Belum ada order pengadaan dari mitra.</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left text-slate-600">
                                        <th class="py-2 pr-4">Order</th>
                                        <th class="py-2 pr-4">Mitra</th>
                                        <th class="py-2 pr-4">Ringkasan</th>
                                        <th class="py-2 pr-4">Tanggal</th>
                                        <th class="py-2">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($adminOrders as $order)
                                        <tr class="border-b last:border-0">
                                            <td class="py-3 pr-4">
                                                <a href="{{ route('admin.procurement.orders.show', ['adminOrderId' => $order->id]) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">
                                                    #{{ $order->id }}
                                                </a>
                                                <span class="inline-flex rounded bg-slate-100 px-2 py-1 text-xs font-semibold uppercase text-slate-700">{{ $order->status_label }}</span>
                                                <p class="mt-1 text-xs text-slate-500">{{ $order->item_qty_total_label }} qty</p>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <p class="font-medium text-slate-900">{{ $order->mitra_name_label }}</p>
                                                <p class="text-xs text-slate-600">{{ $order->mitra_email_label }}</p>
                                            </td>
                                            <td class="py-3 pr-4 text-slate-700">
                                                <p class="font-medium text-slate-900">{{ $order->total_amount_label }}</p>
                                                <p class="mt-1 text-xs text-slate-600">{{ $order->item_qty_total_label }} qty</p>
                                                @if($hasPaymentColumns ?? false)
                                                    <p class="mt-1"><span class="{{ $order->payment_badge_class }}">{{ $order->payment_status_label }}</span></p>
                                                @endif
                                            </td>
                                            <td class="py-3 pr-4 text-slate-600">{{ $order->created_at_label }}</td>
                                            <td class="py-3">
                                                <a href="{{ route('admin.procurement.orders.show', ['adminOrderId' => $order->id]) }}" class="inline-flex items-center rounded border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                                    Detail
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if(method_exists($adminOrders, 'links'))
                            <div class="mt-4">{{ $adminOrders->links() }}</div>
                        @endif
                    @endif
                </div>
            @endif

            <div
                x-show="manageOpen"
                x-cloak
                class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/50 px-4 py-6"
                @click.self="closeManage()"
            >
                <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                        <div>
                            <h4 class="text-base font-semibold text-slate-900">
                                Kelola Produk Pengadaan
                                <span class="ml-1 text-slate-500" x-text="manageProduct ? ('#' + manageProduct.id) : ''"></span>
                            </h4>
                            <p class="mt-1 text-xs text-slate-600">Ubah detail produk admin untuk katalog pengadaan Mitra.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeManage()">
                            Tutup
                        </button>
                    </div>

                    <div class="max-h-[72vh] overflow-y-auto p-4">
                        <form method="POST" :action="updateAction" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Informasi Produk</p>
                                <div class="mt-2.5 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</label>
                                        <input x-ref="name" type="text" name="name" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Deskripsi</label>
                                        <input x-ref="description" type="text" name="description" class="w-full rounded-lg border-slate-300 text-sm">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Gudang Pengadaan</label>
                                        <select x-ref="warehouse_id" name="warehouse_id" class="w-full rounded-lg border-slate-300 text-sm" required>
                                            <option value="">Pilih gudang</option>
                                            @foreach(collect($warehouses ?? []) as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->code }} - {{ $warehouse->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Harga & Stok</p>
                                <div class="mt-2.5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Harga (Rp)</label>
                                        <input x-ref="price" type="number" min="0" step="0.01" name="price" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Satuan</label>
                                        <select x-ref="unit" name="unit" class="w-full rounded-lg border-slate-300 text-sm" required>
                                            <option value="kg">kg</option>
                                            <option value="gram">gram</option>
                                            <option value="lt">lt</option>
                                            <option value="ml">ml</option>
                                            <option value="pcs">pcs</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Min Order</label>
                                        <input x-ref="min_order_qty" type="number" min="1" name="min_order_qty" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Stok</label>
                                        <input x-ref="stock_qty" type="number" min="0" name="stock_qty" class="w-full rounded-lg border-slate-300 text-sm" required>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3.5 md:flex-row md:items-center md:justify-between">
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                    <input type="hidden" name="is_active" value="0">
                                    <input x-ref="is_active" type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600">
                                    <span>Aktifkan untuk pengadaan Mitra</span>
                                </label>

                                <div class="flex items-center gap-2 md:justify-end">
                                    <button type="submit" class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                        Simpan Perubahan
                                    </button>
                                    <button type="submit" form="admin-product-delete-form" class="rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </form>

                        <form id="admin-product-delete-form" method="POST" :action="deleteAction" class="hidden" onsubmit="return confirm('Hapus produk pengadaan ini? Jika sudah punya riwayat order, produk akan dinonaktifkan.');">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>

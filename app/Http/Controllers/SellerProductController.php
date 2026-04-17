<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerProductController extends Controller
{
    /**
     * Form tambah produk hasil tani milik penjual.
     */
    public function create(Request $request): View
    {
        return view('seller.products-create', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Halaman manajemen produk hasil tani milik penjual.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $keyword = trim($request->string('q')->toString());

        $summary = [
            'total' => 0,
            'total_stock' => 0,
        ];
        $products = collect();

        if (Schema::hasTable('farmer_harvests')) {
            $base = DB::table('farmer_harvests')
                ->where('farmer_id', $user->id);

            $summary['total'] = (int) (clone $base)->count();
            $summary['total_stock'] = (int) (clone $base)->sum('stock_qty');

            $query = clone $base;
            if ($keyword !== '') {
                $query->where(function ($builder) use ($keyword) {
                    $builder->where('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            }

            $products = $query
                ->orderByDesc('updated_at')
                ->simplePaginate(10)
                ->withQueryString();
        }

        return view('seller.products', [
            'user' => $user,
            'filters' => [
                'q' => $keyword,
            ],
            'summary' => $summary,
            'products' => $products,
        ]);
    }

    /**
     * Simpan produk hasil tani baru milik penjual.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->assertHarvestTableExists();

        $data = $this->validateProductPayload($request, requireAll: true);
        $imagePath = $request->file('image')
            ? $request->file('image')->store('seller-products', 'public')
            : null;

        DB::table('farmer_harvests')->insert([
            'farmer_id' => (int) $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'price' => $data['price'],
            'stock_qty' => $data['stock_qty'],
            'harvest_date' => $data['harvest_date'] ?: null,
            'image_url' => $imagePath,
            // Produk penjual langsung aktif agar alur operasional tidak bergantung review admin.
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('seller.products.index')
            ->with('status', 'produk berhasil ditambahkan');
    }

    /**
     * Update produk hasil tani milik penjual.
     */
    public function update(Request $request, int $harvestId): RedirectResponse
    {
        $this->assertHarvestTableExists();

        $harvest = DB::table('farmer_harvests')
            ->where('id', $harvestId)
            ->where('farmer_id', (int) $request->user()->id)
            ->first();

        if (! $harvest) {
            throw ValidationException::withMessages([
                'harvest' => 'Produk hasil tani tidak ditemukan atau bukan milik akun Anda.',
            ]);
        }

        $data = $this->validateProductPayload($request, requireAll: true);
        $imagePath = (string) ($harvest->image_url ?? '');

        if ($request->boolean('remove_image')) {
            $this->deleteStoredImage($imagePath);
            $imagePath = '';
        }

        if ($request->file('image')) {
            $newImagePath = $request->file('image')->store('seller-products', 'public');
            $this->deleteStoredImage($imagePath);
            $imagePath = $newImagePath;
        }

        DB::table('farmer_harvests')
            ->where('id', $harvestId)
            ->where('farmer_id', (int) $request->user()->id)
            ->update([
                'name' => $data['name'],
                'description' => $data['description'] ?: null,
                'price' => $data['price'],
                'stock_qty' => $data['stock_qty'],
                'harvest_date' => $data['harvest_date'] ?: null,
                'image_url' => $imagePath !== '' ? $imagePath : null,
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Produk hasil tani berhasil diperbarui.');
    }

    /**
     * Hapus produk hasil tani milik penjual.
     */
    public function destroy(Request $request, int $harvestId): RedirectResponse
    {
        $this->assertHarvestTableExists();

        $harvest = DB::table('farmer_harvests')
            ->where('id', $harvestId)
            ->where('farmer_id', (int) $request->user()->id)
            ->first();

        if (! $harvest) {
            throw ValidationException::withMessages([
                'harvest' => 'Produk hasil tani tidak ditemukan atau bukan milik akun Anda.',
            ]);
        }

        DB::table('farmer_harvests')
            ->where('id', $harvestId)
            ->where('farmer_id', (int) $request->user()->id)
            ->delete();

        $this->deleteStoredImage((string) ($harvest->image_url ?? ''));

        return back()->with('status', 'Produk hasil tani berhasil dihapus.');
    }

    /**
     * Validasi payload produk penjual.
     *
     * @return array{name:string,description:?string,price:numeric,stock_qty:int,harvest_date:?string}
     */
    private function validateProductPayload(Request $request, bool $requireAll = false): array
    {
        $rules = [
            'name' => [$requireAll ? 'required' : 'sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => [$requireAll ? 'required' : 'sometimes', 'numeric', 'min:100'],
            'stock_qty' => [$requireAll ? 'required' : 'sometimes', 'integer', 'min:1'],
            'harvest_date' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ];

        return $request->validate($rules, [
            'name.required' => 'Nama produk wajib diisi.',
            'price.required' => 'Harga produk wajib diisi.',
            'stock_qty.required' => 'Stok produk wajib diisi.',
            'price.min' => 'Harga minimal Rp100.',
            'stock_qty.min' => 'Stok minimal 1.',
            'image.image' => 'File gambar tidak valid.',
        ]);
    }

    /**
     * Pastikan tabel farmer_harvests tersedia.
     */
    private function assertHarvestTableExists(): void
    {
        if (! Schema::hasTable('farmer_harvests')) {
            throw ValidationException::withMessages([
                'table' => 'Tabel produk penjual belum tersedia. Hubungi admin sistem.',
            ]);
        }
    }

    /**
     * Hapus file gambar lokal jika path berasal dari storage aplikasi.
     */
    private function deleteStoredImage(string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            return;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        if (Str::startsWith($path, '/storage/')) {
            $path = ltrim(Str::after($path, '/storage/'), '/');
        } elseif (Str::startsWith($path, 'storage/')) {
            $path = ltrim(Str::after($path, 'storage/'), '/');
        } else {
            $path = ltrim($path, '/');
        }

        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}

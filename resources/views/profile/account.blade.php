@extends('layouts.marketplace')

@section('title', 'Rekening')
@section('pageTitle', 'Rekening')

@section('content')
    @php
        $bankProfile = $bankProfile ?? null;
        $bankNameValue = old('bank_name', (string) ($bankProfile->bank_name ?? ''));
        $accountNumberValue = old('account_number', (string) ($bankProfile->account_number ?? ''));
        $accountHolderValue = old('account_holder', (string) ($bankProfile->account_holder ?? ''));
        $isBankProfileComplete = (bool) ($isBankProfileComplete ?? false);
    @endphp

    <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->has('bank_profile'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ $errors->first('bank_profile') }}
            </div>
        @elseif($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Data Rekening</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Halaman ini khusus untuk mengelola rekening pencairan. Tidak ada fitur pengajuan mode di sini.
                    </p>
                </div>
                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $isBankProfileComplete ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                    {{ $isBankProfileComplete ? 'Rekening Lengkap' : 'Rekening Belum Lengkap' }}
                </span>
            </div>

            <form method="POST" action="{{ route('account.bank.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Nama Bank</span>
                        <input
                            type="text"
                            name="bank_name"
                            value="{{ $bankNameValue }}"
                            maxlength="120"
                            placeholder="Contoh: BCA"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                        >
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Nomor Rekening</span>
                        <input
                            type="text"
                            name="account_number"
                            value="{{ $accountNumberValue }}"
                            maxlength="120"
                            placeholder="Contoh: 1234567890"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                        >
                    </label>
                </div>

                <label class="block text-sm">
                    <span class="mb-1 block font-medium text-slate-700">Nama Pemilik Rekening</span>
                    <input
                        type="text"
                        name="account_holder"
                        value="{{ $accountHolderValue }}"
                        maxlength="255"
                        placeholder="Contoh: Petani Penjual"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                    >
                </label>

                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                    >
                        Simpan Rekening
                    </button>
                    <p class="text-xs text-slate-500">
                        Isi semua kolom untuk menyimpan rekening. Kosongkan semua kolom jika ingin menghapus data rekening.
                    </p>
                </div>
            </form>
        </section>
    </div>
@endsection

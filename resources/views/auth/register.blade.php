<x-guest-layout>
    @php
        $redirectTarget = trim((string) old('redirect_to', request()->query('redirect', '')));
    @endphp
    <form
        method="POST"
        action="{{ route('register') }}"
        x-data="regionPicker({
            provinceId: {{ (int) old('province_id', 0) }},
            cityId: {{ (int) old('city_id', 0) }},
            districtId: {{ (int) old('district_id', 0) }},
        })"
        x-init="init()"
    >
        @csrf
        @if($redirectTarget !== '')
            <input type="hidden" name="redirect_to" value="{{ $redirectTarget }}">
        @endif

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Phone Number -->
        <div class="mt-4">
            <x-input-label for="phone_number" :value="__('No Handphone')" />
            <x-text-input id="phone_number" class="block mt-1 w-full" type="text" name="phone_number" :value="old('phone_number')" required />
            <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="province_id" :value="__('Provinsi')" />
            <select
                id="province_id"
                name="province_id"
                x-model="provinceId"
                @change="onProvinceChange()"
                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
            >
                <option value="">Pilih Provinsi</option>
                <template x-for="province in provinces" :key="province.id">
                    <option :value="province.id" x-text="province.name"></option>
                </template>
            </select>
            <x-input-error :messages="$errors->get('province_id')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="city_id" :value="__('Kota/Kabupaten')" />
            <select
                id="city_id"
                name="city_id"
                x-model="cityId"
                @change="onCityChange()"
                :disabled="!provinceId"
                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
                required
            >
                <option value="">Pilih Kota/Kabupaten</option>
                <template x-for="city in cities" :key="city.id">
                    <option :value="city.id" x-text="(city.type ? (city.type + ' ') : '') + city.name"></option>
                </template>
            </select>
            <x-input-error :messages="$errors->get('city_id')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="district_id" :value="__('Kecamatan (Opsional)')" />
            <select
                id="district_id"
                name="district_id"
                x-model="districtId"
                :disabled="!cityId"
                class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
            >
                <option value="">Pilih Kecamatan</option>
                <template x-for="district in districts" :key="district.id">
                    <option :value="district.id" x-text="district.name"></option>
                </template>
            </select>
            <x-input-error :messages="$errors->get('district_id')" class="mt-2" />
            <p class="mt-2 text-xs text-gray-500" x-show="selectedCityLatLng">
                Koordinat kota: <span x-text="selectedCityLatLng"></span>
            </p>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ $redirectTarget !== '' ? route('login', ['redirect' => $redirectTarget]) : route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

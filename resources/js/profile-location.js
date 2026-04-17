window.regionPicker = function ({ provinceId = 0, cityId = 0, districtId = 0 } = {}) {
    return {
        provinces: [],
        cities: [],
        districts: [],

        provinceId: provinceId || '',
        cityId: cityId || '',
        districtId: districtId || '',

        selectedCityLatLng: '',

        async init() {
            await this.loadProvinces();

            if (this.provinceId) {
                await this.loadCities(this.provinceId, { preserveSelection: true });
            }
            if (this.cityId) {
                await this.loadDistricts(this.cityId, { preserveSelection: true });
                this.setCityLatLng();
            }
        },

        async loadProvinces() {
            const res = await fetch('/regions/provinces');
            this.provinces = await res.json();
        },

        async loadCities(provinceId, { preserveSelection = false } = {}) {
            const currentCityId = this.cityId;
            const currentDistrictId = this.districtId;
            this.cities = [];
            this.districts = [];
            this.selectedCityLatLng = '';

            const res = await fetch(`/regions/cities/${provinceId}`);
            this.cities = await res.json();

            if (preserveSelection && this.cities.some((entry) => String(entry.id) === String(currentCityId))) {
                this.cityId = String(currentCityId);
                this.districtId = String(currentDistrictId || '');
            } else {
                this.cityId = '';
                this.districtId = '';
            }
        },

        async loadDistricts(cityId, { preserveSelection = false } = {}) {
            const currentDistrictId = this.districtId;
            this.districts = [];

            const res = await fetch(`/regions/districts/${cityId}`);
            this.districts = await res.json();

            if (preserveSelection && this.districts.some((entry) => String(entry.id) === String(currentDistrictId))) {
                this.districtId = String(currentDistrictId);
            } else {
                this.districtId = '';
            }
        },

        async onProvinceChange() {
            if (!this.provinceId) {
                this.cities = [];
                this.cityId = '';
                this.districts = [];
                this.districtId = '';
                this.selectedCityLatLng = '';
                return;
            }
            await this.loadCities(this.provinceId);
        },

        async onCityChange() {
            if (!this.cityId) {
                this.districts = [];
                this.districtId = '';
                this.selectedCityLatLng = '';
                return;
            }
            await this.loadDistricts(this.cityId);
            this.setCityLatLng();
        },

        setCityLatLng() {
            const city = this.cities.find((entry) => String(entry.id) === String(this.cityId));
            if (!city) {
                this.selectedCityLatLng = '';
                return;
            }
            const lat = city.lat;
            const lng = city.lng;
            const hasLat = lat !== null && lat !== undefined && String(lat).trim() !== '';
            const hasLng = lng !== null && lng !== undefined && String(lng).trim() !== '';
            this.selectedCityLatLng = hasLat && hasLng ? `${lat}, ${lng}` : '(lat/lng kosong)';
        },
    };
};

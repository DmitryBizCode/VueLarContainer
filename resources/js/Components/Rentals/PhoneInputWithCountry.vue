<script setup>
import { parsePhoneNumber, AsYouType } from 'libphonenumber-js';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    countries: {
        type: Array,
        default: () => [],
    },
    userCountryId: {
        type: [Number, String, null],
        default: null,
    },
    placeholder: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const DEFAULT_PHONE_CODES = {
    UA: '+380',
    PL: '+48',
    DE: '+49',
    FR: '+33',
    ES: '+34',
    IT: '+39',
    GB: '+44',
    IE: '+353',
    NL: '+31',
    BE: '+32',
    SE: '+46',
    NO: '+47',
    DK: '+45',
    FI: '+358',
    CZ: '+420',
    SK: '+421',
    RO: '+40',
    HU: '+36',
    PT: '+351',
    AT: '+43',
    CH: '+41',
    TR: '+90',
    US: '+1',
    CA: '+1',
    AU: '+61',
    JP: '+81',
};

function isoToFlag(iso) {
    if (!iso || iso.length !== 2) return '';
    return iso
        .toUpperCase()
        .split('')
        .map((c) => String.fromCodePoint(127397 + c.charCodeAt(0)))
        .join('');
}

const selectedCountryId = ref(
    props.userCountryId != null ? Number(props.userCountryId) : (props.countries[0]?.id ?? null)
);

const selectedCountry = computed(() =>
    props.countries.find((c) => Number(c.id) === Number(selectedCountryId.value))
);

function resolvePhoneCode(country) {
    if (!country) return '';
    if (country.phone_code) return country.phone_code;
    const iso = String(country.iso_code || '').toUpperCase();
    return DEFAULT_PHONE_CODES[iso] ?? '';
}

const phoneCode = computed(() => resolvePhoneCode(selectedCountry.value));

const examplePlaceholder = computed(() => {
    if (!phoneCode.value) {
        return '+380 50 123 45 67';
    }
    return `${phoneCode.value} 50 123 45 67`;
});

const displayValue = ref('');

watch(
    () => props.modelValue,
    (v) => {
        if (!v) {
            displayValue.value = '';
            return;
        }
        if (v.startsWith('+')) {
            try {
                const p = parsePhoneNumber(v);
                displayValue.value = p ? p.formatNational() : v;
            } catch {
                displayValue.value = v;
            }
        } else {
            displayValue.value = v;
        }
    },
    { immediate: true }
);

watch(selectedCountryId, () => {
    updateE164();
});

function formatInput(value) {
    const country = selectedCountry.value;
    if (!country?.iso_code) return value;
    const digits = value.replace(/\D/g, '');
    if (!digits) return '';
    try {
        const formatter = new AsYouType(country.iso_code);
        formatter.input(digits);
        const num = formatter.getNumber();
        return num ? num.formatNational() : digits;
    } catch {
        return digits;
    }
}

function updateE164() {
    const digits = displayValue.value.replace(/\D/g, '');
    if (!digits || !selectedCountry.value?.iso_code) {
        if (!displayValue.value) emit('update:modelValue', '');
        return;
    }
    try {
        const parsed = parsePhoneNumber(digits, selectedCountry.value.iso_code);
        if (parsed?.isValid()) {
            emit('update:modelValue', parsed.format('E.164'));
        }
    } catch {
        const code = (phoneCode.value || '').replace(/\D/g, '');
        if (code && digits) emit('update:modelValue', '+' + code + digits);
    }
}

function onInput(e) {
    const v = e.target.value;
    displayValue.value = formatInput(v);
    updateE164();
}

function onBlur() {
    updateE164();
}
</script>

<template>
    <div class="flex rounded-xl border border-slate-200 bg-white shadow-sm transition focus-within:border-blue-700 focus-within:ring-2 focus-within:ring-blue-700/20">
        <select
            v-model="selectedCountryId"
            class="w-28 shrink-0 rounded-l-xl border-0 border-r border-slate-200 bg-slate-50/70 pl-2 pr-1 text-sm text-slate-700 focus:border-blue-700 focus:ring-0"
            aria-label="Country"
        >
            <option
                v-for="c in countries"
                :key="c.id"
                :value="c.id"
            >
                {{ isoToFlag(c.iso_code) }} {{ resolvePhoneCode(c) }}
            </option>
        </select>
        <input
            type="tel"
            :value="displayValue"
            :placeholder="placeholder || examplePlaceholder"
            class="min-w-0 flex-1 rounded-r-xl border-0 bg-transparent py-2.5 pl-3 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:ring-0"
            autocomplete="tel"
            @input="onInput"
            @blur="onBlur"
        >
    </div>
</template>

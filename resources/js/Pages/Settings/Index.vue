<template>
    <AppLayout>
        <template #header>الإعدادات</template>
        <div class="space-y-8">
            <div v-if="$page.props.flash?.success" class="p-4 rounded-xl border text-sm bg-green-50 border-green-200 text-green-700">✅ {{ $page.props.flash.success }}</div>

            <!-- سعر الصرف -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">💱 سعر الصرف</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <form @submit.prevent="submitRate" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="block text-sm font-medium text-gray-700 mb-1">التاريخ *</label><input v-model="rateForm.rate_date" type="date" required dir="ltr" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none"/></div>
                                <div><label class="block text-sm font-medium text-gray-700 mb-1">SAR → JOD *</label><input v-model="rateForm.sar_to_jod" type="number" step="0.000001" required dir="ltr" placeholder="0.078" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none"/></div>
                            </div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label><input v-model="rateForm.notes" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none"/></div>
                            <div class="flex items-center gap-4">
                                <button type="submit" :disabled="rateForm.processing" class="px-6 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 disabled:opacity-50">💾 حفظ سعر الصرف</button>
                                <p v-if="rateForm.sar_to_jod" class="text-xs text-gray-500">1 JOD = {{ (1/rateForm.sar_to_jod).toFixed(4) }} SAR</p>
                            </div>
                        </form>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-gray-600 mb-3">آخر الأسعار</h4>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <div v-for="r in recentRates" :key="r.id" class="flex items-center justify-between text-xs p-2 rounded-lg bg-gray-50">
                                <span class="font-mono text-gray-600">{{ r.rate_date }}</span>
                                <span class="font-bold font-mono text-gold-700">{{ Number(r.sar_to_jod).toFixed(6) }}</span>
                            </div>
                            <p v-if="!recentRates?.length" class="text-xs text-gray-400 text-center py-4">لا يوجد أسعار صرف</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الإعدادات العامة -->
            <div v-for="(items, group) in settings" :key="group" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    {{ {company:'🏢 بيانات الشركة',printing:'🖨️ الطباعة',notifications:'🔔 الإشعارات',financial:'💰 المالية'}[group]||('⚙️ '+group) }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="item in items" :key="item.key">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ item.description || item.key }}</label>
                        <template v-if="item.type==='boolean'">
                            <label class="flex items-center gap-2"><input v-model="settingsData[item.key]" type="checkbox" true-value="1" false-value="0" class="w-4 h-4 rounded text-gold-500"/><span class="text-sm text-gray-600">تفعيل</span></label>
                        </template>
                        <template v-else-if="item.type==='text'">
                            <textarea v-model="settingsData[item.key]" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none resize-none"></textarea>
                        </template>
                        <template v-else>
                            <input v-model="settingsData[item.key]" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none"/>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button @click="saveSettings" :disabled="saving" class="px-8 py-3 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md disabled:opacity-50">💾 حفظ جميع الإعدادات</button>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
import { ref, reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Components/Layout/AppLayout.vue';
const props = defineProps({ settings: Object, todayRate: Object, lastRate: Object, recentRates: Array });

const today = new Date().toISOString().split('T')[0];
const rateForm = useForm({
    rate_date: today,
    sar_to_jod: props.todayRate?.sar_to_jod || props.lastRate?.sar_to_jod || '',
    notes: '',
});

const settingsData = reactive({});
if (props.settings) {
    Object.values(props.settings).flat().forEach(s => { settingsData[s.key] = s.value || ''; });
}

const submitRate = () => { rateForm.post('/settings/exchange-rate', { preserveScroll: true }); };
const saving = ref(false);
const saveSettings = () => {
    saving.value = true;
    const payload = Object.entries(settingsData).map(([key, value]) => ({ key, value }));
    router.put('/settings', { settings: payload }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};
</script>

<template>
    <AppLayout>
        <template #header>الفواتير</template>
        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="p-4 rounded-xl border text-sm bg-green-50 border-green-200 text-green-700">✅ {{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="p-4 rounded-xl border text-sm bg-red-50 border-red-200 text-red-700">❌ {{ $page.props.flash.error }}</div>

            <!-- Controls -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <input v-model="search" type="text" placeholder="بحث بالرقم أو الوكيل أو العميل..." class="w-64 px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-gold-500" @input="debounceSearch"/>
                    <select v-model="statusFilter" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm" @change="applyFilter">
                        <option value="">كل الحالات</option><option value="pending">معلقة</option><option value="approved">معتمدة</option><option value="rejected">مرفوضة</option>
                    </select>
                </div>
                <button @click="openPOS()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md hover:shadow-gold-500/25">🧾 فاتورة جديدة</button>
            </div>

            <!-- Table -->
            <div class="rounded-xl border overflow-hidden shadow-sm bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right font-bold">الرقم</th>
                        <th class="px-4 py-3 text-right font-bold">الوكيل</th>
                        <th class="px-4 py-3 text-right font-bold">العميل</th>
                        <th class="px-4 py-3 text-right font-bold">الإجمالي SAR</th>
                        <th class="px-4 py-3 text-right font-bold">الإجمالي JOD</th>
                        <th class="px-4 py-3 text-right font-bold">الحالة</th>
                        <th class="px-4 py-3 text-right font-bold">بواسطة</th>
                        <th class="px-4 py-3 text-right font-bold">التاريخ</th>
                        <th class="px-4 py-3 text-center font-bold">إجراءات</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="inv in invoices.data" :key="inv.id" class="border-t border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/30">
                            <td class="px-4 py-3 text-right font-mono text-xs text-gold-700">{{ inv.invoice_number }}</td>
                            <td class="px-4 py-3 text-right text-xs">{{ inv.agent?.name||'—' }}</td>
                            <td class="px-4 py-3 text-right text-xs">{{ inv.client?.name||'—' }}</td>
                            <td class="px-4 py-3 text-right font-bold font-mono text-xs" dir="ltr">{{ Number(inv.total_sar).toLocaleString('en',{minimumFractionDigits:2}) }}</td>
                            <td class="px-4 py-3 text-right font-bold font-mono text-xs text-blue-600" dir="ltr">{{ Number(inv.total_jod).toLocaleString('en',{minimumFractionDigits:3}) }}</td>
                            <td class="px-4 py-3 text-right"><span class="px-2 py-0.5 rounded-full text-xs font-bold" :class="{'bg-yellow-100 text-yellow-700':inv.status==='pending','bg-green-100 text-green-700':inv.status==='approved','bg-red-100 text-red-700':inv.status==='rejected'}">{{ {pending:'معلقة',approved:'معتمدة',rejected:'مرفوضة'}[inv.status] }}</span></td>
                            <td class="px-4 py-3 text-right text-xs text-gray-500"><div>📝 {{ inv.creator?.name || '—' }}</div><div v-if="inv.status !== 'pending'" class="mt-0.5">{{ inv.status === 'approved' ? '✅' : '❌' }} {{ inv.approver?.name || '—' }}</div></td>
                            <td class="px-4 py-3 text-right font-mono text-xs text-gray-500" dir="ltr">{{ inv.invoice_date?.split('T')[0] }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <a :href="'/invoices/'+inv.id+'/print'" target="_blank" class="px-2 py-1 text-xs text-purple-600 hover:bg-purple-50 rounded-lg">🖨️</a>
                                <button @click="viewInv(inv)" class="px-2 py-1 text-xs text-blue-600 hover:bg-blue-50 rounded-lg">👁️</button>
                                <button v-if="inv.status==='pending'" @click="approveInv(inv)" class="px-2 py-1 text-xs text-green-600 hover:bg-green-50 rounded-lg">✅</button>
                                <button v-if="inv.status==='pending'" @click="rejectInv(inv)" class="px-2 py-1 text-xs text-orange-600 hover:bg-orange-50 rounded-lg">❌</button>
                                <button v-if="inv.status!=='approved'" @click="delInv(inv)" class="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded-lg">🗑️</button>
                            </td>
                        </tr>
                        <tr v-if="!invoices.data?.length"><td colspan="9" class="px-5 py-12 text-center text-gray-400">لا يوجد فواتير</td></tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- POS Modal -->
        <div v-if="showPOS" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPOS=false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-4xl mx-4 p-6 max-h-[95vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">🧾 فاتورة مبيعات جديدة</h3>
                    <button @click="showPOS=false" class="text-gray-400 dark:text-gray-500 hover:text-red-500 text-xl">&times;</button>
                </div>
                <div class="space-y-5">
                    <!-- Row 1: Agent + Client + Rate -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الوكيل *</label>
                            <SearchableSelect v-model="pos.agent_id" :options="agentOptions" placeholder="اختر الوكيل" search-placeholder="ابحث عن وكيل..." />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">العميل *</label>
                            <SearchableSelect v-model="pos.client_id" :options="clientOptions" placeholder="اختر العميل" search-placeholder="ابحث عن عميل..." @change="loadUnbilled" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">سعر الصرف (SAR→JOD)</label>
                            <input v-model="pos.exchange_rate" type="number" step="0.000001" dir="ltr" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-mono focus:ring-2 focus:ring-gold-500 focus:outline-none"/>
                        </div>
                    </div>

                    <!-- Add Items -->
                    <div class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-gray-700">بنود الفاتورة</h4>
                            <div class="flex gap-2">
                                <button @click="addServiceItem" type="button" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 hover:bg-blue-100">+ خدمة</button>
                                <button @click="addViolationItem" type="button" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-red-50 text-red-700 hover:bg-red-100">+ مخالفة</button>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <div v-if="pos.items.length" class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead><tr class="bg-gray-50 text-gray-500">
                                    <th class="px-3 py-2 text-right">النوع</th>
                                    <th class="px-3 py-2 text-right">الوصف</th>
                                    <th class="px-3 py-2 text-right w-16">الكمية</th>
                                    <th class="px-3 py-2 text-right w-24">السعر SAR</th>
                                    <th class="px-3 py-2 text-right w-24">البيع JOD</th>
                                    <th class="px-3 py-2 text-right w-24">الإجمالي SAR</th>
                                    <th class="px-3 py-2 text-right w-24">الإجمالي JOD</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr></thead>
                                <tbody>
                                    <tr v-for="(item, idx) in pos.items" :key="idx" class="border-t border-gray-100">
                                        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs" :class="item.item_type==='service'?'bg-blue-100 text-blue-700':'bg-red-100 text-red-700'">{{ item.item_type==='service'?'خدمة':'مخالفة' }}</span></td>
                                        <td class="px-3 py-2">
                                            <SearchableSelect v-if="item.item_type==='service'" v-model="item.service_id" :options="serviceOptions" placeholder="اختر خدمة" search-placeholder="ابحث..." @change="onServiceSelect(idx)" />
                                            <SearchableSelect v-else v-model="item.violation_id" :options="violationOptions" placeholder="اختر مخالفة" search-placeholder="ابحث..." @change="onViolationSelect(idx)" />
                                        </td>
                                        <td class="px-3 py-2"><input v-model.number="item.quantity" type="number" min="1" class="w-full px-2 py-1 rounded border border-gray-200 text-xs text-center" dir="ltr"/></td>
                                        <td class="px-3 py-2"><input v-model.number="item.unit_price_sar" type="number" step="0.01" class="w-full px-2 py-1 rounded border border-gray-200 text-xs font-mono" dir="ltr"/></td>
                                        <td class="px-3 py-2"><input v-model.number="item.sell_price_jod" type="number" step="0.001" class="w-full px-2 py-1 rounded border border-gray-200 text-xs font-mono" dir="ltr"/></td>
                                        <td class="px-3 py-2 font-mono font-bold text-gray-700" dir="ltr">{{ (item.quantity * item.unit_price_sar).toFixed(2) }}</td>
                                        <td class="px-3 py-2 font-mono font-bold text-blue-600" dir="ltr">{{ (item.quantity * item.sell_price_jod).toFixed(3) }}</td>
                                        <td class="px-3 py-2"><button @click="pos.items.splice(idx,1)" class="text-red-400 hover:text-red-600">✕</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-center text-gray-400 text-xs py-6">أضف بنوداً للفاتورة</p>
                    </div>

                    <!-- Totals -->
                    <div v-if="pos.items.length" class="bg-gray-50 rounded-xl p-4 space-y-3">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <div><span class="text-gray-500">إجمالي التكلفة (SAR):</span><p class="font-bold font-mono" dir="ltr">{{ subtotalSar.toFixed(2) }} SAR</p></div>
                            <div><label class="text-gray-500">الخصم SAR:</label><input v-model.number="pos.discount" type="number" step="0.01" min="0" dir="ltr" class="w-full px-2 py-1 rounded border border-gray-200 text-xs font-mono mt-1"/></div>
                            <div><span class="text-gray-500">الصافي (SAR):</span><p class="font-bold font-mono" dir="ltr">{{ netSar.toFixed(2) }} SAR</p></div>
                        </div>
                        <div class="border-t border-gray-200 pt-3 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <div><span class="text-gray-500">تكلفة الوكيل (JOD):</span><p class="font-bold font-mono text-orange-600" dir="ltr">{{ agentCostJod.toFixed(3) }} JOD</p></div>
                            <div><span class="text-gray-500">إجمالي العميل (JOD):</span><p class="font-bold font-mono text-lg text-blue-600" dir="ltr">{{ totalJod.toFixed(3) }} JOD</p></div>
                            <div><span class="text-gray-500">الربح:</span><p class="font-bold font-mono text-lg" :class="profitJod >= 0 ? 'text-green-600' : 'text-red-600'" dir="ltr">{{ profitJod.toFixed(3) }} JOD</p></div>
                        </div>
                    </div>

                    <!-- Notes + Submit -->
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label><textarea v-model="pos.notes" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none resize-none"></textarea></div>
                    <div class="flex gap-3">
                        <button @click="submitPOS" :disabled="submitting||!pos.agent_id||!pos.client_id||!pos.items.length" class="px-6 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md disabled:opacity-50">🧾 إرسال للاعتماد</button>
                        <button @click="showPOS=false" class="px-6 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Modal -->
        <div v-if="viewTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="viewTarget=null">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4"><h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">تفاصيل الفاتورة</h3><button @click="viewTarget=null" class="text-gray-400 dark:text-gray-500 hover:text-red-500 text-xl">&times;</button></div>
                <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                    <div><span class="text-gray-400">الرقم:</span><p class="font-mono text-gold-700">{{ viewTarget.invoice_number }}</p></div>
                    <div><span class="text-gray-400">التاريخ:</span><p>{{ viewTarget.invoice_date?.split('T')[0] }}</p></div>
                    <div><span class="text-gray-400">الوكيل:</span><p>{{ viewTarget.agent?.name }}</p></div>
                    <div><span class="text-gray-400">العميل:</span><p>{{ viewTarget.client?.name }}</p></div>
                    <div><span class="text-gray-400">الإجمالي SAR:</span><p class="font-bold font-mono" dir="ltr">{{ Number(viewTarget.total_sar).toFixed(2) }}</p></div>
                    <div><span class="text-gray-400">الإجمالي JOD:</span><p class="font-bold font-mono text-blue-600" dir="ltr">{{ Number(viewTarget.total_jod).toFixed(3) }}</p></div>
                    <div><span class="text-gray-400">سعر الصرف:</span><p class="font-mono" dir="ltr">{{ viewTarget.exchange_rate_snapshot }}</p></div>
                    <div><span class="text-gray-400">الخصم:</span><p class="font-mono" dir="ltr">{{ Number(viewTarget.discount_sar).toFixed(2) }} SAR</p></div>
                </div>
                <div v-if="viewTarget.notes" class="text-sm mb-4"><span class="text-gray-400">ملاحظات:</span><p>{{ viewTarget.notes }}</p></div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="deleteTarget=null">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 text-center">
                <div class="text-5xl mb-4">⚠️</div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">تأكيد الحذف</h3>
                <p class="text-sm text-gray-500 mb-6">حذف الفاتورة <strong>{{ deleteTarget.invoice_number }}</strong>؟</p>
                <div class="flex gap-3 justify-center">
                    <button @click="router.delete('/invoices/'+deleteTarget.id,{preserveScroll:true,onSuccess:()=>{deleteTarget=null}})" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-red-500 hover:bg-red-600">🗑️ حذف</button>
                    <button @click="deleteTarget=null" class="px-6 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({ invoices: Object, filters: Object, agents: Array, clients: Array, services: Array, exchangeRate: Number });
const search = ref(props.filters?.search||'');
const statusFilter = ref(props.filters?.status||'');
const showPOS = ref(false);
const viewTarget = ref(null);
const deleteTarget = ref(null);
const submitting = ref(false);
const unbilledViolations = ref([]);
let t = null;

const pos = reactive({
    agent_id: '',
    client_id: '',
    exchange_rate: props.exchangeRate || 0.078,
    discount: 0,
    notes: '',
    items: [],
});

// خيارات البحث في القوائم المنسدلة
const agentOptions = computed(() => props.agents.map(a => ({ value: a.id, label: `${a.name} (${a.code})` })));
const clientOptions = computed(() => props.clients.map(c => ({ value: c.id, label: `${c.name} (${c.code})` })));
const serviceOptions = computed(() => props.services.map(s => ({ value: s.id, label: s.name })));
const violationOptions = computed(() => unbilledViolations.value.map(v => ({ value: v.id, label: `${v.violation_number} - ${v.passport_name||'بدون اسم'} (${v.cost_sar} SAR)` })));

const subtotalSar = computed(() => pos.items.reduce((s, i) => s + i.quantity * i.unit_price_sar, 0));
const netSar = computed(() => Math.max(0, subtotalSar.value - (pos.discount || 0)));
// إجمالي العميل بالدينار (مجموع سعر البيع × الكمية)
const totalJod = computed(() => pos.items.reduce((s, i) => s + i.quantity * i.sell_price_jod, 0));
// تكلفة الوكيل بالدينار (الريال × سعر الصرف)
const agentCostJod = computed(() => netSar.value * (pos.exchange_rate || 0));
// الربح = ما يدفعه العميل - تكلفة الوكيل
const profitJod = computed(() => totalJod.value - agentCostJod.value);

const openPOS = () => {
    pos.agent_id = '';
    pos.client_id = '';
    pos.exchange_rate = props.exchangeRate || 0.078;
    pos.discount = 0;
    pos.notes = '';
    pos.items = [];
    unbilledViolations.value = [];
    showPOS.value = true;
};

const addServiceItem = () => {
    pos.items.push({ item_type: 'service', service_id: '', violation_id: null, description: '', quantity: 1, unit_price_sar: 0, sell_price_jod: 0 });
};

const addViolationItem = () => {
    if (!pos.client_id) { alert('اختر العميل أولاً'); return; }
    pos.items.push({ item_type: 'violation', service_id: null, violation_id: '', description: '', quantity: 1, unit_price_sar: 0, sell_price_jod: 0 });
};

const onServiceSelect = (idx) => {
    const s = props.services.find(x => x.id == pos.items[idx].service_id);
    if (s) {
        pos.items[idx].description = s.name;
        pos.items[idx].unit_price_sar = parseFloat(s.default_price_sar);
        pos.items[idx].sell_price_jod = parseFloat(s.default_price_jod);
    }
};

const onViolationSelect = (idx) => {
    const v = unbilledViolations.value.find(x => x.id == pos.items[idx].violation_id);
    if (v) {
        pos.items[idx].description = v.violation_number + ' - ' + (v.passport_name || '');
        pos.items[idx].unit_price_sar = parseFloat(v.cost_sar);
        pos.items[idx].sell_price_jod = parseFloat(v.cost_sar) * (pos.exchange_rate || 0);
    }
};

const loadUnbilled = async () => {
    if (!pos.client_id) { unbilledViolations.value = []; return; }
    try {
        const res = await fetch('/api/clients/' + pos.client_id + '/violations/unbilled');
        unbilledViolations.value = await res.json();
    } catch { unbilledViolations.value = []; }
};

const submitPOS = () => {
    submitting.value = true;
    const payload = {
        agent_id: pos.agent_id,
        client_id: pos.client_id,
        exchange_rate_snapshot: pos.exchange_rate,
        discount_sar: pos.discount || 0,
        notes: pos.notes,
        items: pos.items.map(i => ({
            item_type: i.item_type,
            description: i.description,
            quantity: i.quantity,
            unit_price_sar: i.unit_price_sar,
            sell_price_jod: i.sell_price_jod,
            service_id: i.service_id || null,
            violation_id: i.violation_id || null,
        })),
    };
    router.post('/invoices', payload, {
        preserveScroll: true,
        onSuccess: () => { showPOS.value = false; },
        onFinish: () => { submitting.value = false; },
    });
};

const viewInv = (inv) => { viewTarget.value = inv; };
const approveInv = (inv) => { if(confirm('اعتماد الفاتورة وتحديث الأرصدة؟')) router.post('/invoices/'+inv.id+'/approve',{},{preserveScroll:true}); };
const rejectInv = (inv) => { const r=prompt('سبب الرفض:'); if(r!==null) router.post('/invoices/'+inv.id+'/reject',{reason:r},{preserveScroll:true}); };
const delInv = (inv) => { deleteTarget.value = inv; };
const debounceSearch = () => { clearTimeout(t); t=setTimeout(()=>applyFilter(),400); };
const applyFilter = () => { router.get('/invoices',{search:search.value,status:statusFilter.value},{preserveState:true,replace:true}); };
</script>

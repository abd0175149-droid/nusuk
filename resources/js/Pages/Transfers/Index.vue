<template>
    <AppLayout>
        <template #header>الحوالات</template>
        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="p-4 rounded-xl border text-sm bg-green-50 border-green-200 text-green-700">✅ {{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="p-4 rounded-xl border text-sm bg-red-50 border-red-200 text-red-700">❌ {{ $page.props.flash.error }}</div>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <input v-model="search" type="text" placeholder="بحث بالرقم أو الوكيل..." class="w-64 px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-gold-500" @input="debounceSearch"/>
                    <select v-model="statusFilter" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm" @change="applyFilter">
                        <option value="">كل الحالات</option>
                        <option value="pending">معلقة</option>
                        <option value="approved">معتمدة</option>
                        <option value="rejected">مرفوضة</option>
                    </select>
                </div>
                <button @click="openForm()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md">+ حوالة جديدة</button>
            </div>

            <div class="rounded-xl border overflow-hidden shadow-sm bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50 text-gray-600">
                        <th class="px-5 py-3 text-right font-bold">الرقم</th>
                        <th class="px-5 py-3 text-right font-bold">الوكيل</th>
                        <th class="px-5 py-3 text-right font-bold">المبلغ (SAR)</th>
                        <th class="px-5 py-3 text-right font-bold">التكلفة (JOD)</th>
                        <th class="px-5 py-3 text-right font-bold">طريقة الدفع</th>
                        <th class="px-5 py-3 text-right font-bold">الحالة</th>
                        <th class="px-5 py-3 text-right font-bold">بواسطة</th>
                        <th class="px-5 py-3 text-center font-bold">إجراءات</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="t in transfers.data" :key="t.id" class="border-t border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/30">
                            <td class="px-5 py-3 font-mono text-xs text-gold-700">{{ t.transfer_number }}</td>
                            <td class="px-5 py-3 font-medium">{{ t.agent?.name }}</td>
                            <td class="px-5 py-3 font-bold font-mono text-green-600">{{ Number(t.amount_sar).toLocaleString('en',{minimumFractionDigits:2}) }}</td>
                            <td class="px-5 py-3 font-mono">{{ t.cost_jod ? Number(t.cost_jod).toLocaleString('en',{minimumFractionDigits:3}) : '—' }}</td>
                            <td class="px-5 py-3">{{ payMethods[t.payment_method] }}</td>
                            <td class="px-5 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="statusClasses[t.status]">{{ statusLabels[t.status] }}</span></td>
                            <td class="px-5 py-3 text-xs text-gray-500"><div>📝 {{ t.creator?.name || '—' }}</div><div v-if="t.status !== 'pending'" class="mt-0.5">{{ t.status === 'approved' ? '✅' : '❌' }} {{ t.approver?.name || '—' }}</div></td>
                            <td class="px-5 py-3 text-center space-x-1 space-x-reverse">
                                <template v-if="t.status==='pending'">
                                    <button @click="approveItem(t)" class="px-2 py-1 text-xs text-green-600 hover:bg-green-50 rounded-lg">✅ اعتماد</button>
                                    <button @click="rejectTarget=t" class="px-2 py-1 text-xs text-orange-600 hover:bg-orange-50 rounded-lg">❌ رفض</button>
                                    <button @click="deleteTarget=t" class="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded-lg">🗑️</button>
                                </template>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="!transfers.data?.length"><td colspan="8" class="px-5 py-12 text-center text-gray-400">لا يوجد حوالات</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Form Modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showForm=false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-xl mx-4 p-6">
                <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">حوالة جديدة</h3><button @click="showForm=false" class="text-gray-400 dark:text-gray-500 hover:text-red-500 text-xl">&times;</button></div>
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">الوكيل *</label><SearchableSelect v-model="form.agent_id" :options="agentOptions" placeholder="اختر الوكيل" search-placeholder="ابحث عن وكيل..." /><p v-if="form.errors.agent_id" class="mt-1 text-xs text-red-500">{{ form.errors.agent_id }}</p></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">المبلغ (SAR) *</label><input v-model="form.amount_sar" type="number" step="0.01" min="0.01" required dir="ltr" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none"/></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">التكلفة (JOD)</label><input v-model="form.cost_jod" type="number" step="0.001" min="0" dir="ltr" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none"/></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">سعر الصرف</label><input v-model="form.exchange_rate" type="number" step="0.000001" min="0" dir="ltr" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none"/></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">طريقة الدفع *</label><select v-model="form.payment_method" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500"><option value="cash">نقدي</option><option value="bank">تحويل بنكي</option><option value="check">شيك</option></select></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1">المرجع</label><input v-model="form.reference_number" dir="ltr" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none"/></div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label><textarea v-model="form.notes" rows="2" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none resize-none"></textarea></div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" :disabled="form.processing" class="px-6 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md disabled:opacity-50">✅ إنشاء</button>
                        <button type="button" @click="showForm=false" class="px-6 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="rejectTarget=null">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">سبب الرفض</h3>
                <textarea v-model="rejectReason" rows="3" placeholder="اذكر سبب الرفض..." class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-2 focus:ring-gold-500 focus:outline-none resize-none mb-4"></textarea>
                <div class="flex gap-3">
                    <button @click="submitReject" :disabled="!rejectReason" class="px-5 py-2.5 rounded-xl font-bold text-sm text-white bg-orange-500 hover:bg-orange-600 disabled:opacity-50">رفض</button>
                    <button @click="rejectTarget=null" class="px-5 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="deleteTarget=null">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 text-center">
                <div class="text-5xl mb-4">⚠️</div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">حذف الحوالة</h3>
                <p class="text-sm text-gray-500 mb-6">{{ deleteTarget.transfer_number }}</p>
                <div class="flex gap-3 justify-center">
                    <button @click="confirmDelete" class="px-6 py-2.5 rounded-xl font-bold text-sm text-white bg-red-500 hover:bg-red-600">🗑️ حذف</button>
                    <button @click="deleteTarget=null" class="px-6 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Components/Layout/AppLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
const props = defineProps({ transfers: Object, filters: Object, agents: Array });
const agentOptions = computed(() => props.agents.map(a => ({ value: a.id, label: `${a.name} (${a.code})` })));
const search = ref(props.filters?.search||'');
const statusFilter = ref(props.filters?.status||'');
const showForm = ref(false);
const rejectTarget = ref(null);
const rejectReason = ref('');
const deleteTarget = ref(null);
let t=null;
const statusLabels = { pending:'معلقة', approved:'معتمدة', rejected:'مرفوضة' };
const statusClasses = { pending:'bg-yellow-100 text-yellow-700', approved:'bg-green-100 text-green-700', rejected:'bg-red-100 text-red-700' };
const payMethods = { cash:'نقدي', bank:'تحويل بنكي', check:'شيك' };
const form = useForm({ agent_id:'', amount_sar:'', cost_jod:'', exchange_rate:'', payment_method:'cash', reference_number:'', notes:'' });

// حساب تلقائي: عند تغيير المبلغ بالريال أو سعر الصرف → يُحسب الدينار
let calcLock = false;
watch(() => form.amount_sar, (val) => {
    if (calcLock) return;
    const sar = parseFloat(val);
    const rate = parseFloat(form.exchange_rate);
    if (sar > 0 && rate > 0) {
        calcLock = true;
        form.cost_jod = (sar * rate).toFixed(3);
        calcLock = false;
    }
});
watch(() => form.exchange_rate, (val) => {
    if (calcLock) return;
    const rate = parseFloat(val);
    const sar = parseFloat(form.amount_sar);
    if (sar > 0 && rate > 0) {
        calcLock = true;
        form.cost_jod = (sar * rate).toFixed(3);
        calcLock = false;
    }
});
// عند تغيير الدينار → يُحسب الريال (إذا سعر الصرف موجود)
watch(() => form.cost_jod, (val) => {
    if (calcLock) return;
    const jod = parseFloat(val);
    const rate = parseFloat(form.exchange_rate);
    if (jod > 0 && rate > 0) {
        calcLock = true;
        form.amount_sar = (jod / rate).toFixed(2);
        calcLock = false;
    }
});

const openForm = () => { form.reset(); form.clearErrors(); showForm.value=true; };
const submitForm = () => { form.post('/transfers', { onSuccess:()=>{showForm.value=false}, preserveScroll:true }); };
const approveItem = (t) => { router.post('/transfers/'+t.id+'/approve', {}, {preserveScroll:true}); };
const submitReject = () => { router.post('/transfers/'+rejectTarget.value.id+'/reject', { rejection_reason: rejectReason.value }, { preserveScroll:true, onSuccess:()=>{rejectTarget.value=null;rejectReason.value='';} }); };
const confirmDelete = () => { router.delete('/transfers/'+deleteTarget.value.id, { preserveScroll:true, onSuccess:()=>{deleteTarget.value=null;} }); };
const debounceSearch = () => { clearTimeout(t); t=setTimeout(()=>applyFilter(),400); };
const applyFilter = () => { router.get('/transfers', { search:search.value, status:statusFilter.value }, { preserveState:true, replace:true }); };
</script>

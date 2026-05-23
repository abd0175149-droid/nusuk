<template>
    <AppLayout>
        <template #header>سندات القبض</template>
        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="p-4 rounded-xl border text-sm bg-green-50 border-green-200 text-green-700">✅ {{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="p-4 rounded-xl border text-sm bg-red-50 border-red-200 text-red-700">❌ {{ $page.props.flash.error }}</div>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <input v-model="search" type="text" placeholder="بحث..." class="w-64 px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-gold-500" @input="debounceSearch"/>
                <button v-if="can('receipts.create')" @click="openForm()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md">+ سند قبض</button>
            </div>
            <div class="rounded-xl border overflow-hidden shadow-sm bg-white"><table class="w-full text-sm">
                <thead><tr class="bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400"><th class="px-5 py-3 text-right font-bold">الرقم</th><th class="px-5 py-3 text-right font-bold">العميل</th><th class="px-5 py-3 text-right font-bold">المبلغ (JOD)</th><th class="px-5 py-3 text-right font-bold">الدفع</th><th class="px-5 py-3 text-right font-bold">الحالة</th><th class="px-5 py-3 text-right font-bold">بواسطة</th><th class="px-5 py-3 text-center font-bold">إجراءات</th></tr></thead>
                <tbody><tr v-for="r in receipts.data" :key="r.id" :data-row-id="r.id"
                            class="border-t border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/30"
                            :class="{ 'row-glow': isHighlighted(r.id) }">
                    <td class="px-5 py-3 text-right font-mono text-xs text-gold-700">{{ r.receipt_number }}</td>
                    <td class="px-5 py-3 text-right font-medium">{{ r.client?.name }}</td>
                    <td class="px-5 py-3 text-right font-bold font-mono text-xs text-green-600" dir="ltr">{{ Number(r.amount_jod).toLocaleString('en',{minimumFractionDigits:3}) }}</td>
                    <td class="px-5 py-3 text-right text-xs">{{ {cash:'نقدي',bank:'بنكي',check:'شيك'}[r.payment_method] }}</td>
                    <td class="px-5 py-3 text-right"><span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="{pending:'bg-yellow-100 text-yellow-700',approved:'bg-green-100 text-green-700',rejected:'bg-red-100 text-red-700',editing:'bg-blue-100 text-blue-700'}[r.status]">{{ {pending:'معلقة',approved:'معتمدة',rejected:'مرفوضة',editing:'تحت التعديل'}[r.status] }}</span></td>
                    <td class="px-5 py-3 text-right text-xs text-gray-500"><div>📝 {{ r.creator?.name || '—' }}</div><div v-if="r.status !== 'pending'" class="mt-0.5">{{ r.status === 'approved' ? '✅' : '❌' }} {{ r.approver?.name || '—' }}</div></td>
                    <td class="px-5 py-3 text-center whitespace-nowrap">
                        <a :href="'/receipts/'+r.id+'/print'" target="_blank" class="px-2 py-1 text-xs text-purple-600 hover:bg-purple-50 rounded-lg">🖨️</a>
                        <template v-if="r.status==='pending'"><button v-if="can('receipts.approve')" @click="router.post('/receipts/'+r.id+'/approve')" class="px-2 py-1 text-xs text-green-600 hover:bg-green-50 rounded-lg">✅</button><button v-if="can('receipts.delete')" @click="router.delete('/receipts/'+r.id)" class="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded-lg">🗑️</button></template>
                        <template v-else-if="r.status==='approved'"><button v-if="can('receipts.edit_approved')" @click="router.post('/receipts/'+r.id+'/start-edit')" class="px-2 py-1 text-xs text-blue-600 hover:bg-blue-50 rounded-lg">✏️ تعديل</button></template>
                    </td>
                </tr><tr v-if="!receipts.data?.length"><td colspan="7" class="px-5 py-12 text-center text-gray-400">لا يوجد سندات</td></tr></tbody>
            </table></div>
        </div>
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showForm=false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-xl mx-4 p-6">
                <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-bold">سند قبض جديد</h3><button @click="showForm=false" class="text-gray-400 dark:text-gray-500 hover:text-red-500 text-xl">&times;</button></div>
                <form @submit.prevent="form.post('/receipts',{onSuccess:()=>{showForm=false; form.reset(); form.clearErrors();},preserveState:false})" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium mb-1">العميل *</label><SearchableSelect v-model="form.client_id" :options="clientOptions" placeholder="اختر العميل" search-placeholder="ابحث عن عميل..." /></div>
                        <div><label class="block text-sm font-medium mb-1">المبلغ (JOD) *</label><input v-model="form.amount_jod" type="number" step="0.001" required dir="ltr" class="w-full px-4 py-2.5 rounded-xl border text-sm"/></div>
                        <div><label class="block text-sm font-medium mb-1">طريقة الدفع *</label><select v-model="form.payment_method" class="w-full px-4 py-2.5 rounded-xl border text-sm"><option value="cash">نقدي</option><option value="bank">بنكي</option><option value="check">شيك</option></select></div>
                    </div>
                    <div><label class="block text-sm font-medium mb-1">ملاحظات</label><textarea v-model="form.notes" rows="2" class="w-full px-4 py-2.5 rounded-xl border text-sm resize-none"></textarea></div>
                    <div class="flex gap-3"><button type="submit" :disabled="form.processing" class="px-6 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 disabled:opacity-50">✅ إنشاء</button><button type="button" @click="showForm=false" class="px-6 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-100">إلغاء</button></div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Components/Layout/SmartLayout.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useHighlight } from '@/composables/useHighlight';
const { can } = usePermissions();
const { isHighlighted } = useHighlight();
const props = defineProps({ receipts: Object, filters: Object, clients: Array });
const clientOptions = computed(() => props.clients.map(c => ({ value: c.id, label: c.name })));
const search = ref(''); const showForm = ref(false); let t=null;
const form = useForm({ client_id:'', amount_jod:'', payment_method:'cash', notes:'' });
const openForm=()=>{form.reset();showForm.value=true;};
const debounceSearch=()=>{clearTimeout(t);t=setTimeout(()=>router.get('/receipts',{search:search.value},{preserveState:true,replace:true}),400);};
</script>

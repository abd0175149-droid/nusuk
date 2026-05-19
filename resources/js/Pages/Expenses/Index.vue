<template>
    <AppLayout>
        <template #header>المصروفات</template>
        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="p-4 rounded-xl border text-sm bg-green-50 border-green-200 text-green-700">✅ {{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="p-4 rounded-xl border text-sm bg-red-50 border-red-200 text-red-700">❌ {{ $page.props.flash.error }}</div>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <input v-model="search" type="text" placeholder="بحث..." class="w-64 px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-gold-500" @input="debounceSearch"/>
                <button v-if="can('expenses.create')" @click="openForm()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md">+ مصروف جديد</button>
            </div>
            <div class="rounded-xl border overflow-hidden shadow-sm bg-white"><table class="w-full text-sm">
                <thead><tr class="bg-gray-50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-400"><th class="px-5 py-3 text-right font-bold">الرقم</th><th class="px-5 py-3 text-right font-bold">التصنيف</th><th class="px-5 py-3 text-right font-bold">الوصف</th><th class="px-5 py-3 text-right font-bold">المبلغ</th><th class="px-5 py-3 text-right font-bold">الحالة</th><th class="px-5 py-3 text-right font-bold">بواسطة</th><th class="px-5 py-3 text-center font-bold">إجراءات</th></tr></thead>
                <tbody><tr v-for="e in expenses.data" :key="e.id" :data-row-id="e.id"
                            class="border-t border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/30"
                            :class="{ 'row-glow': isHighlighted(e.id) }">
                    <td class="px-5 py-3 text-right font-mono text-xs text-gold-700">{{ e.expense_number }}</td>
                    <td class="px-5 py-3 text-right text-xs">{{ e.category?.name||'—' }}</td>
                    <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-400 text-xs max-w-xs truncate">{{ e.description }}</td>
                    <td class="px-5 py-3 text-right font-bold font-mono text-xs text-red-600" dir="ltr">{{ Number(e.amount).toLocaleString('en',{minimumFractionDigits:2}) }} {{ e.currency }}</td>
                    <td class="px-5 py-3 text-right"><span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="{pending:'bg-yellow-100 text-yellow-700',approved:'bg-green-100 text-green-700',rejected:'bg-red-100 text-red-700'}[e.status]">{{ {pending:'معلقة',approved:'معتمدة',rejected:'مرفوضة'}[e.status] }}</span></td>
                    <td class="px-5 py-3 text-right text-xs text-gray-500"><div>📝 {{ e.creator?.name || '—' }}</div><div v-if="e.status !== 'pending'" class="mt-0.5">{{ e.status === 'approved' ? '✅' : '❌' }} {{ e.approver?.name || '—' }}</div></td>
                    <td class="px-5 py-3 text-center whitespace-nowrap">
                        <a :href="'/expenses/'+e.id+'/print'" target="_blank" class="px-2 py-1 text-xs text-purple-600 hover:bg-purple-50 rounded-lg">🖨️</a>
                        <template v-if="e.status==='pending'"><button v-if="can('expenses.approve')" @click="router.post('/expenses/'+e.id+'/approve')" class="px-2 py-1 text-xs text-green-600 hover:bg-green-50 rounded-lg">✅</button><button v-if="can('expenses.delete')" @click="router.delete('/expenses/'+e.id)" class="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded-lg">🗑️</button></template>
                    </td>
                </tr><tr v-if="!expenses.data?.length"><td colspan="7" class="px-5 py-12 text-center text-gray-400">لا يوجد مصروفات</td></tr></tbody>
            </table></div>
        </div>
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showForm=false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-xl mx-4 p-6">
                <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-bold">مصروف جديد</h3><button @click="showForm=false" class="text-gray-400 dark:text-gray-500 hover:text-red-500 text-xl">&times;</button></div>
                <form @submit.prevent="form.post('/expenses',{onSuccess:()=>{showForm=false}})" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium mb-1">التصنيف *</label><SearchableSelect v-model="form.category_id" :options="categoryOptions" placeholder="اختر التصنيف" search-placeholder="ابحث..." /></div>
                        <div><label class="block text-sm font-medium mb-1">المبلغ *</label><input v-model="form.amount" type="number" step="0.01" required dir="ltr" class="w-full px-4 py-2.5 rounded-xl border text-sm"/></div>
                        <div><label class="block text-sm font-medium mb-1">العملة *</label><select v-model="form.currency" class="w-full px-4 py-2.5 rounded-xl border text-sm"><option value="JOD">JOD</option><option value="SAR">SAR</option></select></div>
                        <div><label class="block text-sm font-medium mb-1">طريقة الدفع *</label><select v-model="form.payment_method" class="w-full px-4 py-2.5 rounded-xl border text-sm"><option value="cash">نقدي</option><option value="bank">بنكي</option><option value="check">شيك</option></select></div>
                    </div>
                    <div><label class="block text-sm font-medium mb-1">الوصف *</label><textarea v-model="form.description" rows="2" required class="w-full px-4 py-2.5 rounded-xl border text-sm resize-none"></textarea></div>
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
import { usePermissions } from '@/composables/usePermissions';
import { useHighlight } from '@/composables/useHighlight';
const { can } = usePermissions();
const { isHighlighted } = useHighlight();
import SearchableSelect from '@/Components/SearchableSelect.vue';
const props = defineProps({ expenses: Object, filters: Object, categories: Array });
const categoryOptions = computed(() => props.categories.map(c => ({ value: c.id, label: c.name })));
const search = ref(''); const showForm = ref(false); let t=null;
const form = useForm({ category_id:'', description:'', amount:'', currency:'JOD', payment_method:'cash', notes:'' });
const openForm=()=>{form.reset();showForm.value=true;};
const debounceSearch=()=>{clearTimeout(t);t=setTimeout(()=>router.get('/expenses',{search:search.value},{preserveState:true,replace:true}),400);};
</script>

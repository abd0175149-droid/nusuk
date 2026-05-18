<template>
    <AppLayout>
        <template #header>شجرة الحسابات</template>
        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="p-4 rounded-xl border text-sm bg-green-50 border-green-200 text-green-700">✅ {{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="p-4 rounded-xl border text-sm bg-red-50 border-red-200 text-red-700">❌ {{ $page.props.flash.error }}</div>

            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ flatCount }} حساب</p>
                <div class="flex items-center gap-2">
                    <a href="/accounting/chart-of-accounts/print" target="_blank" class="px-4 py-2.5 rounded-xl text-sm text-purple-600 border border-purple-200 hover:bg-purple-50">🖨️ طباعة</a>
                    <button @click="openAdd()" class="px-5 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md">+ حساب جديد</button>
                </div>
            </div>

            <div class="rounded-xl border overflow-hidden shadow-sm bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 p-6">
                <div class="space-y-0.5">
                    <template v-for="account in accounts" :key="account.id">
                        <AccountRow :account="account" :depth="0" @edit="openEdit" @delete="deleteAccount" />
                    </template>
                </div>
                <div v-if="!accounts?.length" class="text-center text-gray-400 py-12">لا يوجد حسابات — قم بتشغيل Seeder</div>
            </div>
        </div>

        <!-- Modal إضافة/تعديل -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showModal=false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold">{{ editingId ? 'تعديل حساب' : 'حساب جديد' }}</h3>
                    <button @click="showModal=false" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
                </div>
                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- اختيار الحساب الأب أولاً (مطلوب عند الإضافة) -->
                    <div v-if="!editingId">
                        <label class="block text-sm font-medium mb-1">الحساب الأب *</label>
                        <select v-model="form.parent_id" required @change="fetchNextCode" class="w-full px-4 py-2.5 rounded-xl border text-sm">
                            <option :value="null" disabled>— اختر الحساب الأب —</option>
                            <option v-for="a in parentOptions" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                        </select>
                    </div>

                    <!-- رقم الحساب (يُولد تلقائياً — للقراءة فقط) -->
                    <div v-if="!editingId && nextCodePreview" class="p-3 rounded-xl border border-gold-200 bg-gold-50 dark:bg-gold-900/10 flex items-center gap-3">
                        <span class="text-sm text-gray-600">رقم الحساب:</span>
                        <span class="font-mono text-lg font-bold text-gold-700" dir="ltr">{{ nextCodePreview }}</span>
                        <span class="text-xs text-gray-400">(يُولد تلقائياً)</span>
                    </div>

                    <!-- عند التعديل: عرض الكود الحالي -->
                    <div v-if="editingId" class="p-3 rounded-xl border border-gray-200 bg-gray-50 flex items-center gap-3">
                        <span class="text-sm text-gray-600">رقم الحساب:</span>
                        <span class="font-mono text-lg font-bold text-gold-700" dir="ltr">{{ form.code }}</span>
                        <span v-if="editingSystem" class="text-xs text-orange-500">(حساب نظام — لا يمكن تغيير الرقم)</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium mb-1">اسم الحساب *</label>
                            <input v-model="form.name" type="text" required class="w-full px-4 py-2.5 rounded-xl border text-sm" placeholder="مثال: حساب بنك الأهلي" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">النوع *</label>
                            <select v-model="form.type" required class="w-full px-4 py-2.5 rounded-xl border text-sm">
                                <option value="asset">أصول</option>
                                <option value="liability">التزامات</option>
                                <option value="equity">حقوق ملكية</option>
                                <option value="revenue">إيرادات</option>
                                <option value="expense">مصروفات</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">العملة *</label>
                            <select v-model="form.currency" required class="w-full px-4 py-2.5 rounded-xl border text-sm">
                                <option value="JOD">JOD</option>
                                <option value="SAR">SAR</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">الوصف</label>
                        <textarea v-model="form.description" rows="2" class="w-full px-4 py-2.5 rounded-xl border text-sm resize-none"></textarea>
                    </div>
                    <div v-if="editingId" class="flex items-center gap-2">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="rounded" />
                            <span>نشط</span>
                        </label>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" :disabled="form.processing || (!editingId && !nextCodePreview)" class="px-6 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 disabled:opacity-50">{{ editingId ? '💾 حفظ' : '✅ إضافة' }}</button>
                        <button type="button" @click="showModal=false" class="px-6 py-2.5 rounded-xl text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, h, defineComponent } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Components/Layout/AppLayout.vue';

const props = defineProps({ accounts: Array });
const showModal = ref(false);
const editingId = ref(null);
const editingSystem = ref(false);
const nextCodePreview = ref('');

const form = useForm({
    code: '', name: '', type: 'asset', parent_id: null,
    currency: 'JOD', description: '', is_active: true,
});

// قائمة مسطحة لكل الحسابات (للحساب الأب)
const flattenAccounts = (accs, result = []) => {
    (accs || []).forEach(a => {
        result.push({ id: a.id, code: a.code, name: a.name });
        if (a.children_recursive?.length) flattenAccounts(a.children_recursive, result);
    });
    return result;
};

const parentOptions = computed(() => flattenAccounts(props.accounts));
const flatCount = computed(() => parentOptions.value.length);

// جلب الرقم التالي من السيرفر
const fetchNextCode = async () => {
    if (!form.parent_id) { nextCodePreview.value = ''; return; }
    try {
        const res = await fetch(`/accounting/accounts/next-code?parent_id=${form.parent_id}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        nextCodePreview.value = data.code || '';
    } catch (e) { nextCodePreview.value = ''; }
};

const openAdd = () => {
    editingId.value = null;
    editingSystem.value = false;
    nextCodePreview.value = '';
    form.reset();
    showModal.value = true;
};

const openEdit = (account) => {
    editingId.value = account.id;
    editingSystem.value = account.is_system;
    form.code = account.code;
    form.name = account.name;
    form.type = account.type;
    form.parent_id = account.parent_id;
    form.currency = account.currency;
    form.description = account.description || '';
    form.is_active = account.is_active;
    showModal.value = true;
};

const submitForm = () => {
    if (editingId.value) {
        form.put('/accounting/accounts/' + editingId.value, { onSuccess: () => showModal.value = false });
    } else {
        form.post('/accounting/accounts', { onSuccess: () => showModal.value = false });
    }
};

const deleteAccount = (account) => {
    if (confirm(`حذف الحساب "${account.name}"؟`)) {
        router.delete('/accounting/accounts/' + account.id);
    }
};

// Component: صف الحساب
const typeColors = {
    asset: 'bg-blue-100 text-blue-700', liability: 'bg-orange-100 text-orange-700',
    equity: 'bg-purple-100 text-purple-700', revenue: 'bg-green-100 text-green-700',
    expense: 'bg-red-100 text-red-700',
};
const typeLabels = { asset: 'أصول', liability: 'التزامات', equity: 'ملكية', revenue: 'إيرادات', expense: 'مصروفات' };
const typeIcons = { asset: '📂', liability: '📋', equity: '💰', revenue: '📈', expense: '📉' };

const AccountRow = defineComponent({
    name: 'AccountRow',
    props: { account: Object, depth: Number },
    emits: ['edit', 'delete'],
    setup(props, { emit }) {
        const a = props.account;
        const hasChildren = a.children_recursive?.length > 0;
        const isLeaf = !hasChildren;
        const indent = props.depth * 24;

        return () => h('div', {}, [
            h('div', {
                class: `group flex items-center gap-3 py-2.5 px-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors ${props.depth === 0 ? 'border-b border-gray-100 dark:border-gray-800' : ''}`,
                style: { paddingRight: `${12 + indent}px` },
            }, [
                h('span', { class: 'text-sm' }, isLeaf ? '📄' : (typeIcons[a.type] || '📂')),
                h('span', { class: 'font-mono text-xs text-gold-700 font-bold min-w-[50px]' }, a.code),
                h('span', { class: `text-sm font-medium flex-1 ${props.depth === 0 ? 'font-bold' : ''}` }, a.name),
                h('span', { class: `px-2 py-0.5 rounded text-xs font-bold ${typeColors[a.type] || ''}` }, typeLabels[a.type]),
                isLeaf ? h('span', {
                    class: `font-mono text-xs font-bold ${parseFloat(a.balance) >= 0 ? 'text-green-600' : 'text-red-600'}`,
                    dir: 'ltr',
                }, Number(a.balance || 0).toLocaleString('en', { minimumFractionDigits: 3 })) : null,
                h('span', { class: 'text-xs text-gray-400 font-mono' }, a.currency),
                // أزرار تعديل/حذف
                h('div', { class: 'hidden group-hover:flex items-center gap-1 mr-2' }, [
                    h('button', {
                        class: 'px-1.5 py-0.5 text-xs text-blue-600 hover:bg-blue-50 rounded',
                        onClick: (e) => { e.stopPropagation(); emit('edit', a); },
                    }, '✏️'),
                    !a.is_system ? h('button', {
                        class: 'px-1.5 py-0.5 text-xs text-red-600 hover:bg-red-50 rounded',
                        onClick: (e) => { e.stopPropagation(); emit('delete', a); },
                    }, '🗑️') : null,
                ]),
            ]),
            ...(hasChildren ? a.children_recursive.map(child =>
                h(AccountRow, { account: child, depth: props.depth + 1, key: child.id, onEdit: (ac) => emit('edit', ac), onDelete: (ac) => emit('delete', ac) })
            ) : []),
        ]);
    },
});
</script>

<template>
    <PrintLayout>
        <template #title>دليل الحسابات (شجرة الحسابات)</template>

        <!-- معلومات التقرير -->
        <div class="info-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="info-item"><label>تاريخ التقرير</label><span class="mono">{{ today }}</span></div>
            <div class="info-item"><label>عدد الحسابات</label><span>{{ totalAccounts }}</span></div>
            <div class="info-item"><label>العملة الأساسية</label><span>JOD / SAR</span></div>
        </div>

        <!-- جدول شجرة الحسابات -->
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:70px;">الكود</th>
                    <th>اسم الحساب</th>
                    <th style="width:70px;">النوع</th>
                    <th style="width:50px;">العملة</th>
                    <th style="width:100px;">الرصيد</th>
                    <th style="width:55px;">الحالة</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="row in flatRows" :key="row.id">
                    <tr :class="row.isParent ? 'parent-row' : ''">
                        <td class="mono" style="font-size:9pt;font-weight:bold;color:#b8860b;">{{ row.code }}</td>
                        <td :style="{ paddingRight: (12 + row.depth * 16) + 'px' }">
                            <span v-if="row.isParent" style="font-weight:bold;">{{ row.depth===0?'📂':row.depth===1?'├─':'│ ├─' }} {{ row.name }}</span>
                            <span v-else style="color:#444;">{{ row.depth>0 ? '│ ' : '' }}└─ {{ row.name }}</span>
                        </td>
                        <td style="font-size:8pt;text-align:center;">
                            <span :style="{color: typeColor(row.type), fontWeight:'bold'}">{{ typeLabel(row.type) }}</span>
                        </td>
                        <td class="mono" style="text-align:center;font-size:9pt;">{{ row.currency }}</td>
                        <td class="mono" :class="row.isParent ? '' : (parseFloat(row.balance)>=0?'green':'red')" style="text-align:left;font-weight:bold;">
                            {{ row.isParent ? '—' : fmt(row.balance) }}
                        </td>
                        <td style="text-align:center;font-size:8pt;">
                            <span v-if="row.is_active" style="color:#16a34a;">● نشط</span>
                            <span v-else style="color:#dc2626;">○ معطل</span>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <!-- ملخص الأرصدة حسب النوع -->
        <div style="margin-top:24px;">
            <h3 style="font-size:11pt;font-weight:bold;margin:0 0 10px;color:#333;border-bottom:2px solid #b8860b;padding-bottom:6px;">ملخص الأرصدة حسب التصنيف</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>التصنيف</th>
                        <th style="width:80px;">عدد الحسابات</th>
                        <th style="width:120px;">إجمالي الرصيد</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in summaryByType" :key="s.type">
                        <td style="font-weight:bold;" :style="{color: typeColor(s.type)}">{{ s.icon }} {{ typeLabel(s.type) }}</td>
                        <td class="mono" style="text-align:center;">{{ s.count }}</td>
                        <td class="mono bold" :class="s.total >= 0 ? 'green' : 'red'" style="text-align:left;">{{ fmt(s.total) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid #b8860b;">
                        <td style="font-weight:900;">المجموع</td>
                        <td class="mono bold" style="text-align:center;">{{ totalLeafAccounts }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- المعادلة المحاسبية -->
        <div style="margin-top:20px;background:#fffbeb;padding:14px 18px;border-radius:8px;border:1px solid #fde68a;">
            <h4 style="font-size:10pt;font-weight:bold;margin:0 0 8px;color:#92400e;">المعادلة المحاسبية (التحقق)</h4>
            <div style="display:grid;grid-template-columns:1fr auto 1fr auto 1fr;gap:8px;align-items:center;text-align:center;">
                <div>
                    <p style="font-size:8pt;color:#999;margin:0;">الأصول</p>
                    <p class="mono bold" style="margin:0;font-size:12pt;color:#2563eb;">{{ fmt(equation.assets) }}</p>
                </div>
                <span style="font-size:16pt;font-weight:bold;color:#b8860b;">=</span>
                <div>
                    <p style="font-size:8pt;color:#999;margin:0;">الالتزامات</p>
                    <p class="mono bold" style="margin:0;font-size:12pt;color:#ea580c;">{{ fmt(equation.liabilities) }}</p>
                </div>
                <span style="font-size:16pt;font-weight:bold;color:#b8860b;">+</span>
                <div>
                    <p style="font-size:8pt;color:#999;margin:0;">حقوق الملكية</p>
                    <p class="mono bold" style="margin:0;font-size:12pt;color:#7c3aed;">{{ fmt(equation.equity) }}</p>
                </div>
            </div>
            <div style="text-align:center;margin-top:10px;">
                <span v-if="equationBalanced" style="color:#16a34a;font-weight:bold;font-size:10pt;">✅ المعادلة المحاسبية متوازنة</span>
                <span v-else style="color:#dc2626;font-weight:bold;font-size:10pt;">⚠️ المعادلة غير متوازنة — فرق: {{ fmt(Math.abs(equation.assets - equation.liabilities - equation.equity)) }}</span>
            </div>
        </div>

        <!-- ملاحظات التدقيق -->
        <div style="margin-top:20px;background:#f9f9f9;padding:12px 16px;border-radius:8px;border:1px solid #eee;">
            <h4 style="font-size:10pt;font-weight:bold;margin:0 0 6px;color:#333;">ملاحظات التدقيق</h4>
            <ul style="font-size:9pt;color:#666;padding-right:18px;margin:0;line-height:1.8;">
                <li>هذا التقرير يعكس أرصدة الحسابات حتى تاريخ الطباعة</li>
                <li>الحسابات المعلّمة كـ "نظام" هي حسابات أساسية لا يمكن حذفها</li>
                <li>يجب التحقق من تطابق المعادلة المحاسبية قبل إغلاق الفترة</li>
                <li>أي حساب برصيد سالب يستدعي مراجعة القيود المسجلة عليه</li>
            </ul>
        </div>

        <!-- التوقيعات -->
        <div class="stamp-area">
            <div class="stamp-box"><p>المحاسب</p><div class="line">التوقيع والختم</div></div>
            <div class="stamp-box"><p>المدقق الداخلي</p><div class="line">التوقيع والختم</div></div>
            <div class="stamp-box"><p>المدير المالي</p><div class="line">التوقيع والختم</div></div>
        </div>
    </PrintLayout>
</template>

<script setup>
import { computed } from 'vue';
import PrintLayout from '@/Components/PrintLayout.vue';

const props = defineProps({ accounts: Array });
const today = new Date().toISOString().split('T')[0];
const fmt = (v) => Number(v || 0).toLocaleString('en', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

const typeLabel = (t) => ({ asset: 'أصول', liability: 'التزامات', equity: 'ملكية', revenue: 'إيرادات', expense: 'مصروفات' }[t] || t);
const typeColor = (t) => ({ asset: '#2563eb', liability: '#ea580c', equity: '#7c3aed', revenue: '#16a34a', expense: '#dc2626' }[t] || '#333');

// تسطيح الشجرة للطباعة
const flattenTree = (accounts, depth = 0, result = []) => {
    (accounts || []).forEach(a => {
        const hasChildren = a.children_recursive?.length > 0;
        result.push({
            id: a.id, code: a.code, name: a.name, type: a.type,
            currency: a.currency, balance: a.balance, is_active: a.is_active,
            is_system: a.is_system, depth, isParent: hasChildren,
        });
        if (hasChildren) flattenTree(a.children_recursive, depth + 1, result);
    });
    return result;
};

const flatRows = computed(() => flattenTree(props.accounts));
const totalAccounts = computed(() => flatRows.value.length);
const leafRows = computed(() => flatRows.value.filter(r => !r.isParent));
const totalLeafAccounts = computed(() => leafRows.value.length);

// ملخص حسب النوع
const summaryByType = computed(() => {
    const icons = { asset: '📂', liability: '📋', equity: '💰', revenue: '📈', expense: '📉' };
    const types = ['asset', 'liability', 'equity', 'revenue', 'expense'];
    return types.map(type => {
        const leaves = leafRows.value.filter(r => r.type === type);
        return {
            type, icon: icons[type],
            count: leaves.length,
            total: leaves.reduce((s, r) => s + parseFloat(r.balance || 0), 0),
        };
    }).filter(s => s.count > 0);
});

// المعادلة المحاسبية
const equation = computed(() => {
    const sum = (type) => leafRows.value.filter(r => r.type === type).reduce((s, r) => s + parseFloat(r.balance || 0), 0);
    const revenue = sum('revenue');
    const expenses = sum('expense');
    const netIncome = revenue - expenses;
    return {
        assets: sum('asset'),
        liabilities: sum('liability'),
        equity: sum('equity') + netIncome, // حقوق الملكية + صافي الدخل
    };
});
const equationBalanced = computed(() => Math.abs(equation.value.assets - equation.value.liabilities - equation.value.equity) < 0.01);
</script>

<style>
.parent-row td { background: #f9f9f9 !important; }
@media print {
    .parent-row td { background: #f5f5f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<template>
    <PrintLayout>
        <template #title>كشف حساب {{ type === 'agent' ? 'وكيل' : 'عميل' }}</template>

        <div class="info-grid">
            <div class="info-item"><label>الاسم</label><span>{{ entity.name }}</span></div>
            <div class="info-item"><label>الكود</label><span style="color:#b8860b">{{ entity.code }}</span></div>
            <div class="info-item"><label>الفترة</label><span>{{ filters.from }} → {{ filters.to }}</span></div>
            <div class="info-item"><label>العملة</label><span>{{ type==='agent'?'SAR':'JOD' }}</span></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;">
            <div style="background:#f9f9f9;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #eee;">
                <p style="font-size:8pt;color:#999;margin:0 0 2px;">رصيد افتتاحي</p>
                <p class="mono bold" style="margin:0">{{ fmt(summary.opening_balance) }}</p>
            </div>
            <div style="background:#fef2f2;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #fecaca;">
                <p style="font-size:8pt;color:#999;margin:0 0 2px;">مدين</p>
                <p class="mono bold red" style="margin:0">{{ fmt(summary.total_debit) }}</p>
            </div>
            <div style="background:#f0fdf4;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #bbf7d0;">
                <p style="font-size:8pt;color:#999;margin:0 0 2px;">دائن</p>
                <p class="mono bold green" style="margin:0">{{ fmt(summary.total_credit) }}</p>
            </div>
            <div style="background:#fffbeb;padding:8px 12px;border-radius:6px;text-align:center;border:1px solid #fde68a;">
                <p style="font-size:8pt;color:#999;margin:0 0 2px;">الرصيد الختامي</p>
                <p class="mono bold" style="margin:0;color:#b8860b">{{ fmt(type==='agent'?entity.balance_sar:entity.balance_jod) }}</p>
            </div>
        </div>

        <table class="print-table">
            <thead><tr>
                <th>#</th><th>التاريخ</th><th>الوصف</th><th>النوع</th><th>مدين</th><th>دائن</th><th>الرصيد</th>
            </tr></thead>
            <tbody>
                <tr v-for="(e, i) in entries" :key="e.id">
                    <td>{{ i+1 }}</td>
                    <td class="mono" style="font-size:9pt">{{ e.entry_date?.split('T')[0] }}</td>
                    <td>{{ e.description }}</td>
                    <td>{{ typeLabel(e.transaction_type) }}</td>
                    <td class="mono" :class="parseFloat(e.debit)>0?'red bold':''">{{ parseFloat(e.debit)>0?fmt(e.debit):'—' }}</td>
                    <td class="mono" :class="parseFloat(e.credit)>0?'green bold':''">{{ parseFloat(e.credit)>0?fmt(e.credit):'—' }}</td>
                    <td class="mono bold">{{ fmt(e.balance_after) }}</td>
                </tr>
                <tr v-if="!entries?.length"><td colspan="7" style="text-align:center;color:#999;padding:20px">لا يوجد حركات</td></tr>
            </tbody>
        </table>

        <div class="stamp-area">
            <div class="stamp-box"><p>المحاسب</p><div class="line">التوقيع والختم</div></div>
            <div class="stamp-box"><p>المدير المالي</p><div class="line">التوقيع والختم</div></div>
        </div>
    </PrintLayout>
</template>

<script setup>
import PrintLayout from '@/Components/PrintLayout.vue';
const props = defineProps({ entity: Object, entries: Array, summary: Object, filters: Object, type: String });
const decimals = props.type === 'agent' ? 2 : 3;
const fmt = (v) => Number(v||0).toLocaleString('en', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
const typeLabel = (t) => ({transfer:'حوالة',violation:'مخالفة',invoice:'فاتورة',receipt:'سند قبض',expense:'مصروف'}[t]||t);
</script>

<template>
    <PrintLayout>
        <template #title>فاتورة مبيعات</template>

        <div class="info-grid">
            <div class="info-item"><label>رقم الفاتورة</label><span style="color:#b8860b">{{ invoice.invoice_number }}</span></div>
            <div class="info-item"><label>التاريخ</label><span>{{ invoice.invoice_date }}</span></div>
            <div class="info-item"><label>سعر الصرف</label><span class="mono">{{ invoice.exchange_rate_snapshot }}</span></div>
            <div class="info-item"><label>الحالة</label><span>{{ {pending:'معلقة',approved:'معتمدة',rejected:'مرفوضة'}[invoice.status] }}</span></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div style="background:#f9f9f9;padding:10px 14px;border-radius:8px;border:1px solid #eee;">
                <p style="font-size:8pt;color:#999;margin:0 0 4px;">الوكيل</p>
                <p style="font-weight:700;margin:0;">{{ invoice.agent?.name }}</p>
                <p style="font-size:9pt;color:#666;margin:2px 0 0;">{{ invoice.agent?.code }}</p>
            </div>
            <div style="background:#f9f9f9;padding:10px 14px;border-radius:8px;border:1px solid #eee;">
                <p style="font-size:8pt;color:#999;margin:0 0 4px;">العميل</p>
                <p style="font-weight:700;margin:0;">{{ invoice.client?.name }}</p>
                <p style="font-size:9pt;color:#666;margin:2px 0 0;">{{ invoice.client?.code }}</p>
            </div>
        </div>

        <table class="print-table">
            <thead><tr>
                <th>#</th><th>النوع</th><th>الوصف</th><th>الكمية</th><th>السعر SAR</th><th>البيع JOD</th><th>الإجمالي SAR</th><th>الإجمالي JOD</th>
            </tr></thead>
            <tbody>
                <tr v-for="(item, i) in invoice.items" :key="i">
                    <td>{{ i+1 }}</td>
                    <td>{{ item.item_type==='service'?'خدمة':'مخالفة' }}</td>
                    <td>{{ item.description }}</td>
                    <td class="mono">{{ item.quantity }}</td>
                    <td class="mono">{{ Number(item.unit_price_sar).toFixed(2) }}</td>
                    <td class="mono">{{ Number(item.sell_price_jod).toFixed(3) }}</td>
                    <td class="mono bold">{{ Number(item.total_cost_sar).toFixed(2) }}</td>
                    <td class="mono bold">{{ Number(item.total_sell_jod).toFixed(3) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-row"><span>إجمالي التكلفة (SAR)</span><span class="mono">{{ Number(invoice.subtotal_sar).toFixed(2) }} SAR</span></div>
            <div class="summary-row" v-if="Number(invoice.discount_sar)>0"><span>الخصم</span><span class="mono red">-{{ Number(invoice.discount_sar).toFixed(2) }} SAR</span></div>
            <div class="summary-row"><span>صافي التكلفة (SAR)</span><span class="mono">{{ Number(invoice.total_sar).toFixed(2) }} SAR</span></div>
            <div class="summary-row" style="border-top:2px solid #e5e7eb;padding-top:8px;margin-top:4px;"><span>تكلفة الوكيل (JOD)</span><span class="mono" style="color:#ea580c">{{ (Number(invoice.total_sar) * Number(invoice.exchange_rate_snapshot)).toFixed(3) }} JOD</span></div>
            <div class="summary-row total"><span>إجمالي العميل (JOD)</span><span class="mono" style="color:#2563eb">{{ Number(invoice.total_jod).toFixed(3) }} JOD</span></div>
            <div class="summary-row total"><span>الربح</span><span class="mono" :style="{color: Number(invoice.profit_jod) >= 0 ? '#16a34a' : '#dc2626'}">{{ Number(invoice.profit_jod).toFixed(3) }} JOD</span></div>
        </div>

        <div v-if="invoice.notes" style="margin-top:12px;font-size:9pt;color:#666;"><strong>ملاحظات:</strong> {{ invoice.notes }}</div>

        <div class="stamp-area">
            <div class="stamp-box"><p>المحاسب</p><div class="line">التوقيع والختم</div></div>
            <div class="stamp-box"><p>المدير المالي</p><div class="line">التوقيع والختم</div></div>
            <div class="stamp-box"><p>العميل</p><div class="line">التوقيع والختم</div></div>
        </div>
    </PrintLayout>
</template>

<script setup>
import PrintLayout from '@/Components/PrintLayout.vue';
defineProps({ invoice: Object });
</script>

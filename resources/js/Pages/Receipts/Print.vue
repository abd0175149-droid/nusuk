<template>
    <PrintLayout>
        <template #title>سند قبض</template>

        <div class="info-grid">
            <div class="info-item"><label>رقم السند</label><span style="color:#b8860b;font-weight:bold">{{ receipt.receipt_number }}</span></div>
            <div class="info-item"><label>التاريخ</label><span class="mono">{{ receipt.receipt_date?.split('T')[0] }}</span></div>
            <div class="info-item"><label>العميل</label><span style="font-weight:bold">{{ receipt.client?.name }}</span></div>
            <div class="info-item"><label>كود العميل</label><span class="mono" style="color:#b8860b">{{ receipt.client?.code }}</span></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
            <div style="background:#f0fdf4;padding:12px;border-radius:8px;text-align:center;border:1px solid #bbf7d0;">
                <p style="font-size:8pt;color:#999;margin:0 0 4px;">المبلغ</p>
                <p class="mono bold green" style="margin:0;font-size:14pt;">{{ fmt(receipt.amount_jod) }} JOD</p>
            </div>
            <div style="background:#f9f9f9;padding:12px;border-radius:8px;text-align:center;border:1px solid #eee;">
                <p style="font-size:8pt;color:#999;margin:0 0 4px;">طريقة الدفع</p>
                <p style="margin:0;font-weight:bold;">{{ paymentLabel(receipt.payment_method) }}</p>
            </div>
            <div style="background:#f9f9f9;padding:12px;border-radius:8px;text-align:center;border:1px solid #eee;">
                <p style="font-size:8pt;color:#999;margin:0 0 4px;">الحالة</p>
                <p style="margin:0;font-weight:bold;" :style="{color: receipt.status==='approved'?'#16a34a':'#ca8a04'}">{{ statusLabel(receipt.status) }}</p>
            </div>
        </div>

        <div v-if="receipt.payment_method==='check'" style="background:#fffbeb;padding:12px;border-radius:8px;border:1px solid #fde68a;margin-bottom:16px;">
            <p style="font-size:9pt;color:#999;margin:0 0 6px;font-weight:bold;">بيانات الشيك</p>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                <div><span style="font-size:8pt;color:#999;">رقم الشيك:</span><br/><span class="mono">{{ receipt.check_number||'—' }}</span></div>
                <div><span style="font-size:8pt;color:#999;">البنك:</span><br/><span>{{ receipt.check_bank||'—' }}</span></div>
                <div><span style="font-size:8pt;color:#999;">تاريخ الاستحقاق:</span><br/><span class="mono">{{ receipt.check_date?.split('T')[0]||'—' }}</span></div>
            </div>
        </div>

        <div v-if="receipt.notes" style="background:#f9f9f9;padding:10px 14px;border-radius:8px;border:1px solid #eee;margin-bottom:16px;">
            <p style="font-size:8pt;color:#999;margin:0 0 4px;">ملاحظات</p>
            <p style="margin:0;font-size:10pt;">{{ receipt.notes }}</p>
        </div>

        <div style="text-align:center;margin:20px 0 10px;padding:12px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;">
            <p style="font-size:9pt;color:#666;margin:0 0 4px;">المبلغ كتابة</p>
            <p style="margin:0;font-weight:bold;font-size:12pt;">{{ amountInWords(receipt.amount_jod) }} دينار أردني فقط لا غير</p>
        </div>

        <div class="stamp-area">
            <div class="stamp-box"><p>المحاسب</p><div class="line">التوقيع والختم</div></div>
            <div class="stamp-box"><p>المستلم</p><div class="line">التوقيع والختم</div></div>
            <div class="stamp-box"><p>المدير المالي</p><div class="line">التوقيع والختم</div></div>
        </div>
    </PrintLayout>
</template>

<script setup>
import PrintLayout from '@/Components/PrintLayout.vue';
const props = defineProps({ receipt: Object });
const fmt = (v) => Number(v||0).toLocaleString('en', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const paymentLabel = (m) => ({cash:'نقداً',bank:'تحويل بنكي',check:'شيك'}[m]||m);
const statusLabel = (s) => ({pending:'معلق',approved:'معتمد',rejected:'مرفوض'}[s]||s);
const amountInWords = (v) => {
    const n = Math.floor(Number(v||0));
    if (n === 0) return 'صفر';
    const ones = ['','واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة','عشرة','أحد عشر','اثنا عشر','ثلاثة عشر','أربعة عشر','خمسة عشر','ستة عشر','سبعة عشر','ثمانية عشر','تسعة عشر'];
    const tens = ['','','عشرون','ثلاثون','أربعون','خمسون','ستون','سبعون','ثمانون','تسعون'];
    const hundreds = ['','مائة','مئتان','ثلاثمائة','أربعمائة','خمسمائة','ستمائة','سبعمائة','ثمانمائة','تسعمائة'];
    if (n < 20) return ones[n];
    if (n < 100) return (n%10?ones[n%10]+' و':'') + tens[Math.floor(n/10)];
    if (n < 1000) return hundreds[Math.floor(n/100)] + (n%100?' و'+amountInWords(n%100):'');
    if (n < 1000000) return amountInWords(Math.floor(n/1000)) + ' ألف' + (n%1000?' و'+amountInWords(n%1000):'');
    return String(n);
};
</script>

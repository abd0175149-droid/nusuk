<template>
    <div class="print-wrapper" dir="rtl">
        <!-- زر الطباعة (يختفي عند الطباعة) -->
        <div class="no-print toolbar">
            <button @click="doPrint" class="print-btn">🖨️ طباعة</button>
            <a href="/invoices" class="back-btn">← العودة</a>
        </div>

        <!-- صفحة A4 -->
        <div class="a4-page" ref="pageRef">
            <!-- خلفية القالب PDF (تُعرض كصورة عبر pdf.js) -->
            <canvas v-if="templateUrl" ref="pdfCanvas" class="pdf-bg"></canvas>
            <div v-else class="fallback-bg"></div>

            <!-- البيانات المطبوعة فوق القالب بإحداثيات ثابتة -->
            <div class="overlay">

                <!-- 1. عنوان الفاتورة — أعلى يسار -->
                <div class="field" :style="pos(45, 32)">
                    <span class="doc-title">فاتورة مبيعات</span>
                </div>

                <!-- 2. رقم الفاتورة + التاريخ + الحالة -->
                <div class="field" :style="pos(45, 45)">
                    <span class="label">رقم الفاتورة:</span>
                    <span class="value gold">{{ invoice.invoice_number }}</span>
                </div>
                <div class="field" :style="pos(45, 52)">
                    <span class="label">التاريخ:</span>
                    <span class="value">{{ formatDate(invoice.invoice_date) }}</span>
                </div>
                <div class="field" :style="pos(75, 45)">
                    <span class="label">الحالة:</span>
                    <span class="value" :class="'status-'+invoice.status">{{ statusLabels[invoice.status] }}</span>
                </div>

                <!-- 3. اسم العميل -->
                <div class="field" :style="pos(8, 65)">
                    <span class="label">العميل:</span>
                    <span class="value client-name">{{ invoice.client?.name }}</span>
                </div>

                <!-- 4. جدول التفاصيل -->
                <div class="table-area" :style="pos(5, 78)">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th class="col-num">#</th>
                                <th class="col-type">نوع الخدمة</th>
                                <th class="col-desc">وصف الخدمة</th>
                                <th class="col-qty">الكمية</th>
                                <th class="col-price">سعر البيع</th>
                                <th class="col-total">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, i) in invoice.items" :key="i">
                                <td class="center">{{ i + 1 }}</td>
                                <td>{{ item.item_type === 'service' ? 'خدمة' : 'مخالفة' }}</td>
                                <td>{{ item.description }}</td>
                                <td class="center mono">{{ item.quantity }}</td>
                                <td class="mono ltr">{{ fmt(item.sell_price_jod) }}</td>
                                <td class="mono ltr bold">{{ fmt(item.total_sell_jod) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 5. إجمالي الفاتورة -->
                <div class="totals-area" :style="totalPos">
                    <div class="total-row">
                        <span>الإجمالي:</span>
                        <span class="mono bold total-amount">{{ fmt(invoice.total_jod) }} JOD</span>
                    </div>
                </div>

                <!-- التوقيعات -->
                <div class="signatures" :style="pos(5, sigY)">
                    <div class="sig-box"><div class="sig-label">المحاسب</div><div class="sig-line"></div></div>
                    <div class="sig-box"><div class="sig-label">المدير المالي</div><div class="sig-line"></div></div>
                    <div class="sig-box"><div class="sig-label">العميل</div><div class="sig-line"></div></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';

const props = defineProps({ invoice: Object, templateUrl: String });

const pdfCanvas = ref(null);
const pageRef = ref(null);

const statusLabels = { pending: 'معلقة', approved: 'معتمدة', rejected: 'مرفوضة', editing: 'تحت التعديل' };

const fmt = (v) => Number(v || 0).toLocaleString('en', { minimumFractionDigits: 3 });

const formatDate = (d) => {
    if (!d) return '';
    const date = new Date(d);
    return date.toLocaleDateString('ar-SA', { year: 'numeric', month: 'long', day: 'numeric' });
};

// إحداثيات: x% من اليمين، y% من الأعلى (mm من A4)
const pos = (xPct, yMm) => ({
    position: 'absolute',
    right: xPct + '%',
    top: yMm + 'mm',
});

// موضع الإجمالي يعتمد على عدد البنود
const itemCount = computed(() => props.invoice?.items?.length || 0);
const totalPos = computed(() => pos(5, 78 + 8 + (itemCount.value * 7) + 4));
const sigY = computed(() => 78 + 8 + (itemCount.value * 7) + 25);

// تحميل PDF كخلفية عبر pdf.js (من CDN)
onMounted(async () => {
    if (!props.templateUrl || !pdfCanvas.value) return;

    try {
        // تحميل pdf.js من CDN
        const pdfjsLib = await loadPdfJs();

        const pdf = await pdfjsLib.getDocument(props.templateUrl).promise;
        const page = await pdf.getPage(1);

        // حجم A4 بالبكسل (210mm × 297mm عند 150 DPI ≈ 1240 × 1754)
        const scale = 2;
        const viewport = page.getViewport({ scale });

        const canvas = pdfCanvas.value;
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        const ctx = canvas.getContext('2d');
        await page.render({ canvasContext: ctx, viewport }).promise;
    } catch (e) {
        console.error('PDF load error:', e);
    }
});

const loadPdfJs = () => {
    return new Promise((resolve, reject) => {
        if (window.pdfjsLib) { resolve(window.pdfjsLib); return; }
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        script.onload = () => {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            resolve(window.pdfjsLib);
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });
};

const doPrint = () => window.print();
</script>

<style>
/* A4 dimensions */
.a4-page {
    width: 210mm;
    height: 297mm;
    position: relative;
    margin: 0 auto;
    overflow: hidden;
    background: white;
}

.pdf-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    pointer-events: none;
}

.fallback-bg {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: white;
    z-index: 0;
}

.overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 1;
    direction: rtl;
}

/* Typography */
.field { font-size: 11pt; }
.label { color: #555; font-size: 9pt; margin-left: 4px; }
.value { font-weight: 700; color: #111; }
.value.gold { color: #b8860b; font-family: monospace; font-size: 12pt; }
.value.client-name { font-size: 14pt; color: #1a1a1a; }
.value.status-approved { color: #16a34a; }
.value.status-pending { color: #ca8a04; }
.value.status-rejected { color: #dc2626; }

.doc-title {
    font-size: 18pt;
    font-weight: 900;
    color: #1a1a1a;
    border-bottom: 2px solid #b8860b;
    padding-bottom: 4px;
}

/* Table */
.table-area { width: 90%; }
.inv-table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
.inv-table th {
    background: #f0ebe0;
    padding: 5px 8px;
    text-align: right;
    font-weight: 700;
    border: 1px solid #d4c9a8;
    font-size: 9pt;
    color: #333;
}
.inv-table td {
    padding: 4px 8px;
    border: 1px solid #e5e1d5;
    font-size: 9pt;
}
.inv-table tr:nth-child(even) td { background: #faf9f6; }
.inv-table .center { text-align: center; }
.inv-table .mono { font-family: monospace; }
.inv-table .ltr { direction: ltr; text-align: left; }
.inv-table .bold { font-weight: 700; }

.col-num { width: 6%; }
.col-type { width: 14%; }
.col-desc { width: 36%; }
.col-qty { width: 10%; }
.col-price { width: 17%; }
.col-total { width: 17%; }

/* Totals */
.totals-area { width: 90%; }
.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f0ebe0;
    border: 2px solid #b8860b;
    border-radius: 6px;
    font-size: 13pt;
    font-weight: 900;
}
.total-amount { color: #b8860b; font-family: monospace; }

/* Signatures */
.signatures { display: flex; justify-content: space-between; width: 90%; }
.sig-box { text-align: center; width: 28%; }
.sig-label { font-size: 9pt; color: #555; margin-bottom: 30px; }
.sig-line { border-top: 1px solid #333; padding-top: 3px; font-size: 8pt; color: #999; }
.sig-line::after { content: 'التوقيع والختم'; }

/* Toolbar */
.toolbar {
    display: flex;
    gap: 12px;
    justify-content: center;
    padding: 16px;
    background: #f3f4f6;
}
.print-btn {
    padding: 10px 24px;
    background: linear-gradient(135deg, #b8860b, #d4a520);
    color: white;
    font-weight: 700;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
}
.back-btn {
    padding: 10px 24px;
    color: #666;
    text-decoration: none;
    border: 1px solid #ddd;
    border-radius: 10px;
    font-size: 14px;
}

/* Screen */
@media screen {
    body { background: #e5e7eb; margin: 0; }
    .print-wrapper { min-height: 100vh; }
    .a4-page { box-shadow: 0 4px 20px rgba(0,0,0,.15); margin: 20px auto; border-radius: 4px; }
}

/* Print */
@media print {
    .no-print { display: none !important; }
    body { margin: 0; padding: 0; }
    .print-wrapper { padding: 0; margin: 0; }
    .a4-page { margin: 0; box-shadow: none; border-radius: 0; }
    @page { size: A4; margin: 0; }
}
</style>

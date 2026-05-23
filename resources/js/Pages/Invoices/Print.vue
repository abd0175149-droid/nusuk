<template>
    <div class="print-wrapper" dir="rtl">
        <!-- شريط الأدوات (يختفي عند الطباعة) -->
        <div class="no-print toolbar">
            <button @click="doPrint" class="print-btn">🖨️ طباعة</button>
            <a href="/invoices" class="back-btn">← العودة</a>
        </div>

        <!-- صفحات A4 -->
        <div v-for="(page, pi) in pages" :key="pi" class="a4-page">
            <canvas v-if="templateUrl" :ref="el => setCanvas(el, pi)" class="pdf-bg"></canvas>
            <div v-else class="fallback-bg"></div>

            <div class="overlay">
                <!-- العناصر الثابتة (تظهر في كل الصفحات أو الأولى فقط) -->
                <template v-if="pi === 0">
                    <!-- عنوان -->
                    <div class="field" :style="elPos('title')">
                        <span :style="elFont('title')">فاتورة مبيعات</span>
                    </div>

                    <!-- رقم الفاتورة -->
                    <div class="field" :style="elPos('invoice_number')">
                        <span class="label">رقم الفاتورة:</span>
                        <span class="value gold" :style="elFont('invoice_number')">{{ invoice.invoice_number }}</span>
                    </div>

                    <!-- التاريخ -->
                    <div class="field" :style="elPos('invoice_date')">
                        <span class="label">التاريخ:</span>
                        <span class="value" :style="elFont('invoice_date')">{{ formatDate(invoice.invoice_date) }}</span>
                    </div>

                    <!-- الحالة -->
                    <div class="field" :style="elPos('status')">
                        <span class="label">الحالة:</span>
                        <span class="value" :class="'status-'+invoice.status" :style="elFont('status')">{{ statusLabels[invoice.status] }}</span>
                    </div>

                    <!-- اسم العميل -->
                    <div class="field" :style="elPos('client_name')">
                        <span class="label">العميل:</span>
                        <span class="value client-name" :style="elFont('client_name')">{{ invoice.client?.name }}</span>
                    </div>
                </template>

                <!-- جدول البنود (يتكرر حسب الصفحة) -->
                <div class="table-area" :style="elPos('items_table')">
                    <table class="inv-table" :style="tableWidth">
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
                            <tr v-for="(item, i) in page.items" :key="i">
                                <td class="center">{{ page.startIdx + i + 1 }}</td>
                                <td>{{ item.item_type === 'service' ? 'خدمة' : 'مخالفة' }}</td>
                                <td>{{ item.description }}</td>
                                <td class="center mono">{{ item.quantity }}</td>
                                <td class="mono ltr">{{ fmt(item.sell_price_jod) }}</td>
                                <td class="mono ltr bold">{{ fmt(item.total_sell_jod) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- الإجمالي (آخر صفحة فقط) -->
                <template v-if="page.isLast">
                    <div class="total-box" :style="totalPos(page)">
                        <div class="total-row">
                            <span>الإجمالي:</span>
                            <span class="mono bold total-amount" :style="elFont('total')">{{ fmt(invoice.total_jod) }} JOD</span>
                        </div>
                    </div>

                    <!-- التوقيعات -->
                    <div class="signatures" :style="sigPos(page)">
                        <div class="sig-box"><div class="sig-label">المحاسب</div><div class="sig-line"></div></div>
                        <div class="sig-box"><div class="sig-label">المدير المالي</div><div class="sig-line"></div></div>
                        <div class="sig-box"><div class="sig-label">العميل</div><div class="sig-line"></div></div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';

const props = defineProps({ invoice: Object, templateUrl: String, layout: Object });

const statusLabels = { pending: 'معلقة', approved: 'معتمدة', rejected: 'مرفوضة', editing: 'تحت التعديل' };
const fmt = (v) => Number(v || 0).toLocaleString('en', { minimumFractionDigits: 3 });
const formatDate = (d) => d ? new Date(d).toLocaleDateString('ar-SA', { year: 'numeric', month: 'long', day: 'numeric' }) : '';

// المواقع الافتراضية (mm)
const defaults = {
    title: { x: 10, y: 30, fontSize: 16 },
    invoice_number: { x: 10, y: 45, fontSize: 11 },
    invoice_date: { x: 80, y: 45, fontSize: 10 },
    status: { x: 150, y: 45, fontSize: 10 },
    client_name: { x: 10, y: 58, fontSize: 12 },
    items_table: { x: 10, y: 72, fontSize: 9, w: 190 },
    total: { x: 10, y: 200, fontSize: 13 },
    signatures: { x: 10, y: 250, fontSize: 9, w: 190 },
};

const el = (id) => props.layout?.elements?.[id] || defaults[id] || { x: 10, y: 10, fontSize: 10 };
const rowsPerPage = computed(() => props.layout?.rowsPerPage || 10);

const elPos = (id) => {
    const p = el(id);
    return { position: 'absolute', right: p.x + 'mm', top: p.y + 'mm' };
};

const elFont = (id) => {
    const p = el(id);
    return { fontSize: (p.fontSize || 10) + 'pt' };
};

const tableWidth = computed(() => {
    const p = el('items_table');
    return p.w ? { width: p.w + 'mm' } : {};
});

// تقسيم البنود على صفحات
const pages = computed(() => {
    const items = props.invoice?.items || [];
    const rpp = rowsPerPage.value;
    if (items.length <= rpp) {
        return [{ items, startIdx: 0, isLast: true }];
    }
    const result = [];
    for (let i = 0; i < items.length; i += rpp) {
        const chunk = items.slice(i, i + rpp);
        result.push({ items: chunk, startIdx: i, isLast: i + rpp >= items.length });
    }
    return result;
});

// موقع الإجمالي (أسفل الجدول في آخر صفحة)
const totalPos = (page) => {
    const p = el('items_table');
    const rowH = 7; // mm per row
    const headerH = 8;
    const y = p.y + headerH + (page.items.length * rowH) + 4;
    const tp = el('total');
    return { position: 'absolute', right: tp.x + 'mm', top: y + 'mm' };
};

const sigPos = (page) => {
    const p = el('items_table');
    const rowH = 7;
    const headerH = 8;
    const y = p.y + headerH + (page.items.length * rowH) + 25;
    const sp = el('signatures');
    return { position: 'absolute', right: sp.x + 'mm', top: y + 'mm', width: (sp.w || 190) + 'mm' };
};

// تحميل PDF
const canvases = {};
const setCanvas = (el, idx) => { if (el) canvases[idx] = el; };

const renderPdf = async () => {
    if (!props.templateUrl) return;
    try {
        const pdfjsLib = await loadPdfJs();
        const pdf = await pdfjsLib.getDocument(props.templateUrl).promise;
        const pdfPage = await pdf.getPage(1);
        const scale = 2;
        const viewport = pdfPage.getViewport({ scale });

        await nextTick();
        for (const [idx, canvas] of Object.entries(canvases)) {
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            await pdfPage.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        }
    } catch (e) { console.error('PDF error:', e); }
};

const loadPdfJs = () => new Promise((resolve, reject) => {
    if (window.pdfjsLib) { resolve(window.pdfjsLib); return; }
    const s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
    s.onload = () => {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        resolve(window.pdfjsLib);
    };
    s.onerror = reject;
    document.head.appendChild(s);
});

onMounted(() => { setTimeout(renderPdf, 300); });
const doPrint = () => window.print();
</script>

<style>
.a4-page { width: 210mm; height: 297mm; position: relative; margin: 0 auto; overflow: hidden; background: white; page-break-after: always; }
.a4-page:last-child { page-break-after: auto; }
.pdf-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
.fallback-bg { position: absolute; inset: 0; background: white; z-index: 0; }
.overlay { position: absolute; inset: 0; z-index: 1; direction: rtl; }

.field { font-size: 11pt; }
.label { color: #555; font-size: 9pt; margin-left: 4px; }
.value { font-weight: 700; color: #111; }
.value.gold { color: #b8860b; font-family: monospace; }
.value.client-name { color: #1a1a1a; }
.value.status-approved { color: #16a34a; }
.value.status-pending { color: #ca8a04; }
.value.status-rejected { color: #dc2626; }

.table-area { position: absolute; }
.inv-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
.inv-table th { background: #f0ebe0; padding: 4px 6px; text-align: right; font-weight: 700; border: 1px solid #d4c9a8; font-size: 8pt; }
.inv-table td { padding: 3px 6px; border: 1px solid #e5e1d5; font-size: 8pt; }
.inv-table tr:nth-child(even) td { background: #faf9f6; }
.inv-table .center { text-align: center; }
.inv-table .mono { font-family: monospace; }
.inv-table .ltr { direction: ltr; text-align: left; }
.inv-table .bold { font-weight: 700; }
.col-num { width: 6%; } .col-type { width: 14%; } .col-desc { width: 36%; }
.col-qty { width: 10%; } .col-price { width: 17%; } .col-total { width: 17%; }

.total-box { position: absolute; }
.total-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; background: #f0ebe0; border: 2px solid #b8860b; border-radius: 4px; font-size: 12pt; font-weight: 900; }
.total-amount { color: #b8860b; font-family: monospace; }

.signatures { display: flex; justify-content: space-between; position: absolute; }
.sig-box { text-align: center; width: 28%; }
.sig-label { font-size: 8pt; color: #555; margin-bottom: 25px; }
.sig-line { border-top: 1px solid #333; padding-top: 3px; font-size: 7pt; color: #999; }
.sig-line::after { content: 'التوقيع والختم'; }

.toolbar { display: flex; gap: 12px; justify-content: center; padding: 16px; background: #f3f4f6; }
.print-btn { padding: 10px 24px; background: linear-gradient(135deg, #b8860b, #d4a520); color: white; font-weight: 700; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; }
.back-btn { padding: 10px 24px; color: #666; text-decoration: none; border: 1px solid #ddd; border-radius: 10px; font-size: 14px; }

@media screen { body { background: #e5e7eb; margin: 0; } .a4-page { box-shadow: 0 4px 20px rgba(0,0,0,.15); margin: 20px auto; border-radius: 4px; } }
@media print { .no-print { display: none !important; } body { margin: 0; padding: 0; } .a4-page { margin: 0; box-shadow: none; border-radius: 0; } @page { size: A4; margin: 0; } }
</style>

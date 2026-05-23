<template>
    <div class="print-wrapper" dir="rtl">
        <div class="no-print toolbar">
            <button @click="doPrint" class="print-btn">🖨️ طباعة</button>
            <a href="/accounting/profit-loss" class="back-btn">← العودة</a>
        </div>
        <div class="a4-landscape">
            <canvas v-if="templateUrl" ref="pdfCanvas" class="pdf-bg"></canvas>
            <div v-else class="fallback-bg"></div>
            <div class="overlay">
                <div v-if="!isHidden('title')" class="field" :style="elPos('title')"><span :style="elFont('title')">قائمة الدخل</span></div>
                <div v-if="!isHidden('period')" class="field" :style="elPos('period')"><span class="label">الفترة:</span><span class="value" :style="elFont('period')">{{ filters.from }} → {{ filters.to }}</span></div>

                <!-- جدول الإيرادات -->
                <div v-if="!isHidden('revenue_table')" :style="elPos('revenue_table')">
                    <h4 class="section-title green-bg">📈 الإيرادات</h4>
                    <table class="print-tbl" :style="{ fontSize: (el('revenue_table').fontSize||9)+'pt', width: el('revenue_table').w?el('revenue_table').w+'mm':'100%' }">
                        <thead><tr><th>الكود</th><th>الحساب</th><th>المبلغ</th></tr></thead>
                        <tbody>
                            <tr v-for="r in revenues" :key="r.code"><td class="mono gold center bold">{{ r.code }}</td><td>{{ r.name }}</td><td class="mono right green bold">{{ fmt(r.amount) }}</td></tr>
                        </tbody>
                        <tfoot><tr class="total-row"><td colspan="2" class="right bold">إجمالي الإيرادات</td><td class="mono right green bold">{{ fmt(totalRevenue) }}</td></tr></tfoot>
                    </table>
                </div>

                <!-- جدول المصروفات -->
                <div v-if="!isHidden('expense_table')" :style="elPos('expense_table')">
                    <h4 class="section-title red-bg">📉 المصروفات</h4>
                    <table class="print-tbl" :style="{ fontSize: (el('expense_table').fontSize||9)+'pt', width: el('expense_table').w?el('expense_table').w+'mm':'100%' }">
                        <thead><tr><th>الكود</th><th>الحساب</th><th>المبلغ</th></tr></thead>
                        <tbody>
                            <tr v-for="e in expenses" :key="e.code"><td class="mono gold center bold">{{ e.code }}</td><td>{{ e.name }}</td><td class="mono right red bold">{{ fmt(e.amount) }}</td></tr>
                        </tbody>
                        <tfoot><tr class="total-row"><td colspan="2" class="right bold">إجمالي المصروفات</td><td class="mono right red bold">{{ fmt(totalExpenses) }}</td></tr></tfoot>
                    </table>
                </div>

                <!-- صافي الدخل -->
                <div v-if="!isHidden('net_income')" class="field" :style="elPos('net_income')">
                    <div class="net-box" :class="netIncome >= 0 ? 'profit' : 'loss'">
                        <span class="net-label">{{ netIncome >= 0 ? '✅ صافي الربح' : '⚠️ صافي الخسارة' }}</span>
                        <span class="net-val mono">{{ fmt(Math.abs(netIncome)) }} JOD</span>
                    </div>
                </div>

                <div v-if="!isHidden('signatures')" class="signatures" :style="elPos('signatures')">
                    <div class="sig-box"><div class="sig-label">المحاسب</div><div class="sig-line"></div></div>
                    <div class="sig-box"><div class="sig-label">المدير المالي</div><div class="sig-line"></div></div>
                </div>
                <template v-for="(pos, id) in customFields" :key="id"><div v-if="!pos.hidden" class="field" :style="elPos(id)"><span :style="elFont(id)" style="white-space:pre-wrap">{{ pos.text }}</span></div></template>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
const props = defineProps({ revenues: Array, expenses: Array, totalRevenue: Number, totalExpenses: Number, netIncome: Number, filters: Object, templateUrl: String, layout: Object });
const fmt = (v) => Number(v||0).toLocaleString('en',{minimumFractionDigits:3});
const defaults = { title:{x:10,y:15,fontSize:14}, period:{x:200,y:15,fontSize:10}, revenue_table:{x:10,y:32,fontSize:9,w:130}, expense_table:{x:150,y:32,fontSize:9,w:130}, net_income:{x:10,y:160,fontSize:13}, signatures:{x:10,y:180,fontSize:9,w:277} };
const el = (id) => props.layout?.elements?.[id] || defaults[id] || {x:10,y:10,fontSize:10};
const isHidden = (id) => !!(props.layout?.elements?.[id]?.hidden);
const elPos = (id) => { const p=el(id); return {position:'absolute',right:p.x+'mm',top:p.y+'mm',width:p.w?p.w+'mm':'auto'}; };
const elFont = (id) => { const p=el(id); return {fontSize:(p.fontSize||10)+'pt',color:p.color||undefined}; };
const customFields = computed(() => { if(!props.layout?.elements) return {}; const r={}; for(const[id,p] of Object.entries(props.layout.elements)){if(id.startsWith('custom_'))r[id]=p;} return r; });

const pdfCanvas = ref(null);
const renderPdf = async () => { if(!props.templateUrl||!pdfCanvas.value) return; try { const pdfjsLib=await loadPdfJs(); const pdf=await pdfjsLib.getDocument(props.templateUrl).promise; const page=await pdf.getPage(1); const vp=page.getViewport({scale:2}); pdfCanvas.value.width=vp.width; pdfCanvas.value.height=vp.height; await page.render({canvasContext:pdfCanvas.value.getContext('2d'),viewport:vp}).promise; }catch(e){console.error(e);} };
const loadPdfJs = () => new Promise((resolve,reject) => { if(window.pdfjsLib){resolve(window.pdfjsLib);return;} const s=document.createElement('script'); s.src='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js'; s.onload=()=>{window.pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';resolve(window.pdfjsLib);}; s.onerror=reject; document.head.appendChild(s); });
onMounted(()=>setTimeout(renderPdf,300));
const doPrint = () => window.print();
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=JetBrains+Mono:wght@400;700&display=swap');
.a4-landscape{width:297mm;height:210mm;position:relative;margin:0 auto;overflow:hidden;background:white;font-family:'Cairo',sans-serif}
.pdf-bg{position:absolute;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none}.fallback-bg{position:absolute;inset:0;background:white;z-index:0}
.overlay{position:absolute;inset:0;z-index:1;direction:rtl}.field{font-family:'Cairo',sans-serif}
.label{color:#8b8680;font-size:8pt;font-weight:600;margin-left:3px}.value{font-weight:700;color:#1a1715}
.gold{color:#96722a}.mono{font-family:'JetBrains Mono',monospace}.red{color:#dc2626}.green{color:#16a34a}.bold{font-weight:700}
.section-title{font-size:9pt;font-weight:700;margin:0 0 4px;padding:3px 8px;border-radius:4px;font-family:'Cairo'}
.green-bg{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}.red-bg{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.print-tbl{width:100%;border-collapse:collapse;font-family:'Cairo',sans-serif}
.print-tbl th{background:#2c2417;color:#dbb84d;padding:3px 5px;text-align:center;font-size:7pt;font-weight:700}
.print-tbl td{padding:2px 5px;border-bottom:.5px solid #e8e4de;font-size:7.5pt}.print-tbl .center{text-align:center}
.print-tbl .right{text-align:right}.print-tbl .total-row td{border-top:2px solid #2c2417;background:#f8f6f3}
.net-box{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-radius:8px;font-family:'Cairo'}
.net-box.profit{background:#f0fdf4;border:1.5px solid #86efac}.net-box.loss{background:#fef2f2;border:1.5px solid #fca5a5}
.net-label{font-size:12pt;font-weight:900}.net-val{font-size:14pt;font-weight:900}
.net-box.profit .net-val{color:#16a34a}.net-box.loss .net-val{color:#dc2626}
.signatures{display:flex;justify-content:space-around}.sig-box{text-align:center;width:25%}
.sig-label{font-size:7.5pt;color:#8b8680;font-weight:600;margin-bottom:24px}.sig-line{border-top:1.5px solid #2c2417;padding-top:3px;font-size:6pt;color:#b0a89e}.sig-line::after{content:'التوقيع والختم'}
.toolbar{display:flex;gap:12px;justify-content:center;padding:12px;background:#f8f6f3;border-bottom:1px solid #e0dbd3}
.print-btn{padding:8px 24px;background:linear-gradient(135deg,#2c2417,#4a3c2e);color:#dbb84d;font-weight:700;border:none;border-radius:10px;cursor:pointer;font-family:'Cairo'}
.back-btn{padding:8px 20px;color:#5a5046;text-decoration:none;border:1.5px solid #d4cec4;border-radius:10px;font-family:'Cairo'}
@media screen{body{background:#e8e4de;margin:0}.a4-landscape{box-shadow:0 8px 30px rgba(0,0,0,.12);margin:20px auto;border-radius:4px}}
@media print{.no-print{display:none!important}body{margin:0;padding:0}.a4-landscape{margin:0;box-shadow:none}@page{size:A4 landscape;margin:0}}
</style>

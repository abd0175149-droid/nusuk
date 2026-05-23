<template>
    <AppLayout>
        <template #header>محرر تخطيط الطباعة</template>
        <div class="space-y-4">
            <div v-if="$page.props.flash?.success" class="p-3 rounded-xl border text-sm bg-green-50 border-green-200 text-green-700">✅ {{ $page.props.flash.success }}</div>

            <!-- شريط التحكم -->
            <div class="bg-white rounded-2xl border p-4 shadow-sm flex flex-wrap items-center gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">نوع المطبوع</label>
                    <select v-model="docType" class="px-4 py-2 rounded-xl border text-sm font-bold" @change="loadLayout">
                        <option value="invoice">🧾 فاتورة</option>
                        <option value="transfer">💸 حوالة</option>
                        <option value="receipt">📄 سند قبض</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">صفوف الجدول/صفحة</label>
                    <input v-model.number="rowsPerPage" type="number" min="1" max="30" class="w-20 px-3 py-2 rounded-xl border text-sm text-center font-mono"/>
                </div>
                <div class="flex-1"></div>
                <button @click="resetLayout" class="px-4 py-2 rounded-xl text-xs text-red-600 hover:bg-red-50 border border-red-200">🔄 إعادة تعيين</button>
                <button @click="saveLayout" :disabled="saving" class="px-6 py-2.5 rounded-xl font-bold text-sm text-black bg-gradient-to-r from-gold-500 to-gold-400 shadow-md disabled:opacity-50">💾 حفظ التخطيط</button>
            </div>

            <!-- منطقة المحرر -->
            <div class="flex gap-4">
                <!-- قائمة العناصر -->
                <div class="w-56 bg-white rounded-2xl border shadow-sm p-4 space-y-2 shrink-0 self-start sticky top-4">
                    <h4 class="text-sm font-bold text-gray-700 mb-2">📦 العناصر</h4>
                    <div v-for="el in currentElements" :key="el.id"
                        class="flex items-center gap-2 p-2 rounded-lg text-xs cursor-pointer transition-all"
                        :class="selectedEl === el.id ? 'bg-gold-100 border border-gold-400 font-bold' : 'bg-gray-50 hover:bg-gray-100 border border-transparent'"
                        @click="selectedEl = el.id">
                        <span>{{ el.icon }}</span>
                        <span class="flex-1">{{ el.label }}</span>
                        <span class="text-[10px] font-mono text-gray-400" v-if="positions[el.id]">{{ Math.round(positions[el.id].x) }},{{ Math.round(positions[el.id].y) }}</span>
                    </div>
                    <div class="border-t pt-2 mt-2">
                        <p class="text-[10px] text-gray-400">اسحب العناصر على المعاينة</p>
                        <p class="text-[10px] text-gray-400">أو انقر + استخدم الأسهم</p>
                    </div>
                    <!-- تحكم دقيق -->
                    <div v-if="selectedEl && positions[selectedEl]" class="border-t pt-3 space-y-2">
                        <h5 class="text-xs font-bold text-gray-600">📐 الموقع</h5>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-[10px] text-gray-500">X (mm)</label><input v-model.number="positions[selectedEl].x" type="number" step="0.5" class="w-full px-2 py-1 rounded border text-xs font-mono text-center" dir="ltr"/></div>
                            <div><label class="text-[10px] text-gray-500">Y (mm)</label><input v-model.number="positions[selectedEl].y" type="number" step="0.5" class="w-full px-2 py-1 rounded border text-xs font-mono text-center" dir="ltr"/></div>
                        </div>

                        <h5 class="text-xs font-bold text-gray-600 mt-2">📏 الحجم</h5>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] text-gray-500">العرض (mm)</label>
                                <input v-model.number="positions[selectedEl].w" type="number" step="1" placeholder="تلقائي" class="w-full px-2 py-1 rounded border text-xs font-mono text-center" dir="ltr"/>
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500">حجم الخط (pt)</label>
                                <input v-model.number="positions[selectedEl].fontSize" type="number" step="0.5" min="6" max="36" class="w-full px-2 py-1 rounded border text-xs font-mono text-center" dir="ltr"/>
                            </div>
                        </div>

                        <h5 class="text-xs font-bold text-gray-600 mt-2">🎨 الألوان</h5>
                        <div class="space-y-2">
                            <div>
                                <label class="text-[10px] text-gray-500">لون النص</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" :value="positions[selectedEl].color || '#1a1715'" @input="positions[selectedEl].color = $event.target.value" class="w-7 h-7 rounded border cursor-pointer shrink-0"/>
                                    <input v-model="positions[selectedEl].color" placeholder="#1a1715" class="flex-1 px-2 py-1 rounded border text-[10px] font-mono" dir="ltr"/>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] text-gray-500">لون الخلفية</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" :value="positions[selectedEl].bgColor || '#ffffff'" @input="positions[selectedEl].bgColor = $event.target.value" class="w-7 h-7 rounded border cursor-pointer shrink-0"/>
                                    <input v-model="positions[selectedEl].bgColor" placeholder="شفاف" class="flex-1 px-2 py-1 rounded border text-[10px] font-mono" dir="ltr"/>
                                </div>
                            </div>
                        </div>
                        <button @click="positions[selectedEl].color = ''; positions[selectedEl].bgColor = ''" class="text-[10px] text-gray-400 hover:text-red-500">🗑️ مسح الألوان</button>

                        <!-- ألوان الجدول (تظهر فقط للجدول) -->
                        <template v-if="selectedEl === 'items_table'">
                            <h5 class="text-xs font-bold text-purple-600 mt-2">🎨 ألوان الجدول</h5>
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <input type="color" :value="positions[selectedEl].thBg || '#2c2417'" @input="positions[selectedEl].thBg = $event.target.value" class="w-6 h-6 rounded cursor-pointer"/>
                                    <span class="text-[10px] text-gray-500 flex-1">خلفية الرأس</span>
                                    <input v-model="positions[selectedEl].thBg" placeholder="#2c2417" class="w-20 px-1 py-0.5 rounded border text-[10px] font-mono" dir="ltr"/>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="color" :value="positions[selectedEl].thColor || '#e8dcc8'" @input="positions[selectedEl].thColor = $event.target.value" class="w-6 h-6 rounded cursor-pointer"/>
                                    <span class="text-[10px] text-gray-500 flex-1">نص الرأس</span>
                                    <input v-model="positions[selectedEl].thColor" placeholder="#e8dcc8" class="w-20 px-1 py-0.5 rounded border text-[10px] font-mono" dir="ltr"/>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="color" :value="positions[selectedEl].thBorder || '#b8960b'" @input="positions[selectedEl].thBorder = $event.target.value" class="w-6 h-6 rounded cursor-pointer"/>
                                    <span class="text-[10px] text-gray-500 flex-1">خط الرأس</span>
                                    <input v-model="positions[selectedEl].thBorder" placeholder="#b8960b" class="w-20 px-1 py-0.5 rounded border text-[10px] font-mono" dir="ltr"/>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="color" :value="positions[selectedEl].tdEven || '#faf8f5'" @input="positions[selectedEl].tdEven = $event.target.value" class="w-6 h-6 rounded cursor-pointer"/>
                                    <span class="text-[10px] text-gray-500 flex-1">صف زوجي</span>
                                    <input v-model="positions[selectedEl].tdEven" placeholder="#faf8f5" class="w-20 px-1 py-0.5 rounded border text-[10px] font-mono" dir="ltr"/>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- منطقة المعاينة A4 -->
                <div class="flex-1 flex justify-center">
                    <div class="relative bg-white shadow-xl border" ref="editorArea"
                        :style="{ width: pageW + 'px', height: pageH + 'px' }"
                        @mousedown="onBgClick">

                        <!-- خلفية PDF -->
                        <canvas ref="pdfCanvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>

                        <!-- شبكة مساعدة -->
                        <div class="absolute inset-0 pointer-events-none" style="background: repeating-linear-gradient(0deg, transparent, transparent 9px, rgba(0,0,0,0.03) 10px), repeating-linear-gradient(90deg, transparent, transparent 9px, rgba(0,0,0,0.03) 10px);"></div>

                        <!-- العناصر القابلة للسحب -->
                        <div v-for="el in currentElements" :key="el.id"
                            class="absolute cursor-move select-none transition-shadow"
                            :class="selectedEl === el.id ? 'ring-2 ring-gold-500 shadow-lg z-20' : 'hover:ring-1 hover:ring-blue-300 z-10'"
                            :style="getElStyle(el)"
                            @mousedown.stop="startDrag($event, el.id)">
                            <div class="px-2 py-1 rounded text-xs whitespace-nowrap"
                                :style="{ fontSize: (positions[el.id]?.fontSize || el.defaultFontSize || 10) + 'pt' }"
                                :class="selectedEl === el.id ? 'bg-gold-100/90 border border-gold-400' : 'bg-white/80 border border-dashed border-gray-400'">
                                <span class="opacity-60 mr-1">{{ el.icon }}</span>
                                {{ el.preview }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Components/Layout/AppLayout.vue';

const props = defineProps({ title: String, templateUrl: String, layouts: Object });

const docType = ref('invoice');
const rowsPerPage = ref(10);
const selectedEl = ref(null);
const saving = ref(false);
const editorArea = ref(null);
const pdfCanvas = ref(null);

// أبعاد A4 بالبكسل (عرض ثابت 595px ≈ 210mm)
const SCALE = 595 / 210; // px per mm
const pageW = 595;
const pageH = 842;

// تعريف العناصر لكل نوع مطبوع
const elementsByType = {
    invoice: [
        { id: 'title', label: 'عنوان الفاتورة', icon: '📌', preview: 'فاتورة مبيعات', defaultFontSize: 16 },
        { id: 'invoice_number', label: 'رقم الفاتورة', icon: '#️⃣', preview: 'INV-20260523-0001', defaultFontSize: 11 },
        { id: 'invoice_date', label: 'التاريخ', icon: '📅', preview: '2026/05/23', defaultFontSize: 10 },
        { id: 'status', label: 'الحالة', icon: '🏷️', preview: 'معتمدة', defaultFontSize: 10 },
        { id: 'client_name', label: 'اسم العميل', icon: '👤', preview: 'اسم العميل', defaultFontSize: 12 },
        { id: 'items_table', label: 'جدول البنود', icon: '📊', preview: '# | الخدمة | الوصف | الكمية | السعر | الإجمالي', defaultFontSize: 9, hasWidth: true },
        { id: 'total', label: 'الإجمالي', icon: '💰', preview: 'الإجمالي: 000.000 JOD', defaultFontSize: 13, hasWidth: true },
        { id: 'signatures', label: 'التوقيعات', icon: '✍️', preview: 'المحاسب | المدير | العميل', defaultFontSize: 9, hasWidth: true },
    ],
    transfer: [
        { id: 'title', label: 'عنوان الحوالة', icon: '📌', preview: 'حوالة مالية', defaultFontSize: 16 },
        { id: 'transfer_number', label: 'رقم الحوالة', icon: '#️⃣', preview: 'TRF-20260523-0001', defaultFontSize: 11 },
        { id: 'transfer_date', label: 'التاريخ', icon: '📅', preview: '2026/05/23', defaultFontSize: 10 },
        { id: 'status', label: 'الحالة', icon: '🏷️', preview: 'معتمدة', defaultFontSize: 10 },
        { id: 'client_name', label: 'اسم العميل', icon: '👤', preview: 'اسم العميل', defaultFontSize: 12 },
        { id: 'agent_name', label: 'اسم الوكيل', icon: '🏢', preview: 'اسم الوكيل', defaultFontSize: 12 },
        { id: 'amount', label: 'المبلغ (SAR)', icon: '💵', preview: 'المبلغ: 0,000 SAR', defaultFontSize: 12 },
        { id: 'cost', label: 'التكلفة (JOD)', icon: '💴', preview: 'التكلفة: 000.000 JOD', defaultFontSize: 12 },
        { id: 'difference', label: 'الفرق', icon: '📊', preview: 'الفرق: 000.000 JOD', defaultFontSize: 11 },
        { id: 'signatures', label: 'التوقيعات', icon: '✍️', preview: 'المحاسب | المدير | العميل', defaultFontSize: 9, hasWidth: true },
    ],
    receipt: [
        { id: 'title', label: 'عنوان السند', icon: '📌', preview: 'سند قبض', defaultFontSize: 16 },
        { id: 'receipt_number', label: 'رقم السند', icon: '#️⃣', preview: 'REC-20260523-0001', defaultFontSize: 11 },
        { id: 'receipt_date', label: 'التاريخ', icon: '📅', preview: '2026/05/23', defaultFontSize: 10 },
        { id: 'status', label: 'الحالة', icon: '🏷️', preview: 'معتمد', defaultFontSize: 10 },
        { id: 'client_name', label: 'اسم العميل', icon: '👤', preview: 'اسم العميل', defaultFontSize: 12 },
        { id: 'amount', label: 'المبلغ (JOD)', icon: '💰', preview: 'المبلغ: 000.000 JOD', defaultFontSize: 13 },
        { id: 'payment_method', label: 'طريقة الدفع', icon: '💳', preview: 'نقدي / بنكي / شيك', defaultFontSize: 10 },
        { id: 'commission', label: 'العمولة', icon: '🏦', preview: 'عمولة البنك: 0.000', defaultFontSize: 10 },
        { id: 'signatures', label: 'التوقيعات', icon: '✍️', preview: 'المحاسب | المدير | العميل', defaultFontSize: 9, hasWidth: true },
    ],
};

// المواقع الافتراضية (mm من أعلى يمين A4)
const defaultPositions = {
    invoice: {
        title: { x: 10, y: 30, fontSize: 16 },
        invoice_number: { x: 10, y: 45, fontSize: 11 },
        invoice_date: { x: 80, y: 45, fontSize: 10 },
        status: { x: 150, y: 45, fontSize: 10 },
        client_name: { x: 10, y: 58, fontSize: 12 },
        items_table: { x: 10, y: 72, fontSize: 9, w: 190 },
        total: { x: 10, y: 200, fontSize: 13, w: 190 },
        signatures: { x: 10, y: 250, fontSize: 9, w: 190 },
    },
    transfer: {
        title: { x: 10, y: 30, fontSize: 16 },
        transfer_number: { x: 10, y: 45, fontSize: 11 },
        transfer_date: { x: 80, y: 45, fontSize: 10 },
        status: { x: 150, y: 45, fontSize: 10 },
        client_name: { x: 10, y: 58, fontSize: 12 },
        agent_name: { x: 10, y: 68, fontSize: 12 },
        amount: { x: 10, y: 85, fontSize: 12 },
        cost: { x: 10, y: 95, fontSize: 12 },
        difference: { x: 10, y: 108, fontSize: 11 },
        signatures: { x: 10, y: 250, fontSize: 9, w: 190 },
    },
    receipt: {
        title: { x: 10, y: 30, fontSize: 16 },
        receipt_number: { x: 10, y: 45, fontSize: 11 },
        receipt_date: { x: 80, y: 45, fontSize: 10 },
        status: { x: 150, y: 45, fontSize: 10 },
        client_name: { x: 10, y: 58, fontSize: 12 },
        amount: { x: 10, y: 75, fontSize: 13 },
        payment_method: { x: 10, y: 88, fontSize: 10 },
        commission: { x: 80, y: 88, fontSize: 10 },
        signatures: { x: 10, y: 250, fontSize: 9, w: 190 },
    },
};

const positions = reactive({});
const currentElements = computed(() => elementsByType[docType.value] || []);

// تحميل التخطيط المحفوظ أو الافتراضي
const loadLayout = () => {
    const saved = props.layouts?.[docType.value];
    const defaults = defaultPositions[docType.value];
    const src = saved?.elements || defaults;
    Object.keys(positions).forEach(k => delete positions[k]);
    for (const [id, pos] of Object.entries(src)) {
        positions[id] = { ...pos };
    }
    rowsPerPage.value = saved?.rowsPerPage || 10;
    selectedEl.value = null;
};

const resetLayout = () => {
    const defaults = defaultPositions[docType.value];
    Object.keys(positions).forEach(k => delete positions[k]);
    for (const [id, pos] of Object.entries(defaults)) {
        positions[id] = { ...pos };
    }
    rowsPerPage.value = 10;
};

const saveLayout = () => {
    saving.value = true;
    router.post('/settings/print-layout', {
        type: docType.value,
        layout: { elements: { ...positions }, rowsPerPage: rowsPerPage.value },
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
};

// تحويل mm إلى بكسل على المعاينة
const mmToPx = (mm) => mm * SCALE;

const getElStyle = (el) => {
    const p = positions[el.id];
    if (!p) return { display: 'none' };
    return {
        right: mmToPx(p.x) + 'px',
        top: mmToPx(p.y) + 'px',
        width: p.w ? mmToPx(p.w) + 'px' : 'auto',
    };
};

// سحب العناصر
let dragging = null;
let dragStart = { mx: 0, my: 0, ox: 0, oy: 0 };

const startDrag = (e, id) => {
    selectedEl.value = id;
    dragging = id;
    const p = positions[id];
    if (!p) return;
    dragStart = { mx: e.clientX, my: e.clientY, ox: p.x, oy: p.y };
    e.preventDefault();
};

const onMouseMove = (e) => {
    if (!dragging) return;
    const dx = -(e.clientX - dragStart.mx) / SCALE; // عكس لأن RTL
    const dy = (e.clientY - dragStart.my) / SCALE;
    const p = positions[dragging];
    if (!p) return;
    p.x = Math.max(0, Math.min(200, dragStart.ox + dx));
    p.y = Math.max(0, Math.min(290, dragStart.oy + dy));
};

const onMouseUp = () => { dragging = null; };

const onBgClick = (e) => {
    if (e.target === editorArea.value || e.target.tagName === 'CANVAS') {
        selectedEl.value = null;
    }
};

// أسهم لتحريك العنصر المحدد
const onKeyDown = (e) => {
    if (!selectedEl.value || !positions[selectedEl.value]) return;
    const step = e.shiftKey ? 5 : 0.5;
    const p = positions[selectedEl.value];
    switch(e.key) {
        case 'ArrowRight': p.x = Math.max(0, p.x - step); e.preventDefault(); break;
        case 'ArrowLeft': p.x = Math.min(200, p.x + step); e.preventDefault(); break;
        case 'ArrowUp': p.y = Math.max(0, p.y - step); e.preventDefault(); break;
        case 'ArrowDown': p.y = Math.min(290, p.y + step); e.preventDefault(); break;
    }
};

// تحميل PDF كخلفية
const renderPdf = async () => {
    if (!props.templateUrl || !pdfCanvas.value) return;
    try {
        const pdfjsLib = await loadPdfJs();
        const pdf = await pdfjsLib.getDocument(props.templateUrl).promise;
        const page = await pdf.getPage(1);
        const viewport = page.getViewport({ scale: pageW / page.getViewport({ scale: 1 }).width });
        const canvas = pdfCanvas.value;
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
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

onMounted(() => {
    loadLayout();
    renderPdf();
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
    document.addEventListener('keydown', onKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
    document.removeEventListener('keydown', onKeyDown);
});
</script>

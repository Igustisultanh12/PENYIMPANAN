<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import {
    X,
    Save,
    Download,
    Lock,
    Unlock,
    FileText,
    Sheet,
    Presentation,
    Bold,
    Italic,
    Underline,
    AlignLeft,
    AlignCenter,
    AlignRight,
    AlignJustify,
    List,
    ListOrdered,
    Plus,
    Trash2,
    Copy,
    Play,
    Check,
    Loader2,
    Maximize2,
    Minimize2,
    Table as TableIcon
} from 'lucide-vue-next';
import http from '../utils/http';
import { useUiStore } from '../stores/ui';
import type { FileItem } from '../types';
import * as XLSX from '../vendor/xlsx.mjs';

const props = defineProps<{
    modelValue: boolean;
    file: FileItem | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'saved', file: FileItem): void;
}>();

const ui = useUiStore();

// Session & State
const isLoading = ref(true);
const isSaving = ref(false);
const saveStatus = ref<'saved' | 'saving' | 'unsaved'>('saved');
const sessionToken = ref<string | null>(null);
const mode = ref<'edit' | 'view'>('edit');
const docType = ref<'document' | 'spreadsheet' | 'presentation'>('document');
const currentVersion = ref(1);
const lockStatus = ref<{ locked: boolean; locked_by?: any } | null>(null);
const isFullscreen = ref(false);
const isSlideshow = ref(false);

// Document Content States
// 1. DOCX
const docHtml = ref('');
const docEditorRef = ref<HTMLDivElement | null>(null);
const wordCount = computed(() => {
    const text = docHtml.value.replace(/<[^>]*>/g, ' ').trim();
    return text ? text.split(/\s+/).length : 0;
});

// 2. XLSX
interface CellData {
    value: string;
    computed?: string;
    bold?: boolean;
    color?: string;
}
const columns = ref(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);
const rowCount = ref(20);
const sheetGrid = ref<Record<string, CellData>>({});
const selectedCell = ref('A1');
const formulaInput = ref('');
const availableSheets = ref<string[]>(['Sheet1']);
const currentSheetName = ref('Sheet1');
let currentWorkbook: XLSX.WorkBook | null = null;

// 3. PPTX
interface Slide {
    id: number;
    title: string;
    subtitle: string;
    notes: string;
    background: string;
}
const slides = ref<Slide[]>([
    { id: 1, title: 'Judul Presentasi', subtitle: 'Sub-judul presentasi MyStorage', notes: '', background: '#0f172a' }
]);
const activeSlideIndex = ref(0);
const currentSlide = computed(() => slides.value[activeSlideIndex.value] || slides.value[0]);

// Themes for slides
const slideThemes = [
    { name: 'Dark Slate', bg: '#0f172a', text: '#ffffff' },
    { name: 'Midnight Navy', bg: '#091e42', text: '#ffffff' },
    { name: 'Clean White', bg: '#ffffff', text: '#0f172a' },
    { name: 'Emerald', bg: '#064e3b', text: '#ecfdf5' },
    { name: 'Deep Indigo', bg: '#312e81', text: '#ffffff' },
];

let autosaveTimer: any = null;
let heartbeatTimer: any = null;

// Initialize Session when modal opens
watch(() => props.modelValue, (open) => {
    if (open && props.file) {
        initSession();
    } else {
        cleanupSession();
    }
});

async function initSession() {
    if (!props.file) return;
    isLoading.value = true;
    saveStatus.value = 'saved';

    try {
        const res = await http.get(`/office/session/${props.file.uuid}?mode=edit`);
        const data = res.data.data;

        sessionToken.value = data.session_token;
        mode.value = data.mode;
        docType.value = data.document_type;
        currentVersion.value = data.file.version;
        lockStatus.value = data.lock;

        // Parse Draft / File Content
        const draft = data.draft;
        if (docType.value === 'spreadsheet') {
            let loadedFromBinary = false;
            // 1. Coba baca langsung dari data.binary_base64 dari server
            if (data.binary_base64 && typeof data.binary_base64 === 'string' && data.binary_base64.length > 0) {
                try {
                    const workbook = XLSX.read(data.binary_base64, {
                        type: 'base64',
                        cellFormula: true,
                        cellText: true,
                    });

                    if (workbook && workbook.SheetNames && workbook.SheetNames.length > 0) {
                        currentWorkbook = workbook;
                        availableSheets.value = workbook.SheetNames;
                        currentSheetName.value = workbook.SheetNames[0];
                        const ws = workbook.Sheets[currentSheetName.value];
                        if (ws) {
                            loadSpreadsheetFromWorksheet(ws);
                            loadedFromBinary = true;
                        }
                    }
                } catch (b64Err) {
                    console.warn('Gagal membaca binary_base64 Excel, mencoba preview endpoint:', b64Err);
                }
            }

            // 2. Fallback baca preview endpoint
            if (!loadedFromBinary) {
                try {
                    const binaryRes = await http.get(`/files/${props.file.uuid}/preview`, {
                        responseType: 'arraybuffer',
                    });
                    if (binaryRes.data && binaryRes.data.byteLength > 0) {
                        const workbook = XLSX.read(new Uint8Array(binaryRes.data), {
                            type: 'array',
                            cellFormula: true,
                            cellText: true,
                        });

                        if (workbook && workbook.SheetNames && workbook.SheetNames.length > 0) {
                            currentWorkbook = workbook;
                            availableSheets.value = workbook.SheetNames;
                            currentSheetName.value = workbook.SheetNames[0];
                            const ws = workbook.Sheets[currentSheetName.value];
                            if (ws) {
                                loadSpreadsheetFromWorksheet(ws);
                                loadedFromBinary = true;
                            }
                        }
                    }
                } catch (readErr) {
                    console.warn('Gagal membaca biner preview Excel:', readErr);
                }
            }

            // 3. Fallback ke draft rows jika file kosong / baru
            if (!loadedFromBinary) {
                loadSpreadsheetDraft(draft);
            }
        } else if (docType.value === 'presentation') {
            loadPresentationDraft(draft);
        } else {
            loadDocumentDraft(draft, data.html_content);
        }

        // Start 45s heartbeat to keep document lock alive
        startHeartbeat();
    } catch (err: any) {
        const message = err.response?.data?.message || 'Gagal memuat sesi dokumen (' + (err.response?.status || 'Error') + ')';
        console.error('Office Session Load Error:', err);
        ui.addToast(message, 'error');
        emit('update:modelValue', false);
    } finally {
        isLoading.value = false;
    }
}

function loadSpreadsheetFromWorksheet(ws: any) {
    sheetGrid.value = {};
    if (!ws || !ws['!ref']) {
        return;
    }
    const range = XLSX.utils.decode_range(ws['!ref']);

    // Sesuaikan rowCount jika data baris banyak
    const totalRows = range.e.r + 1;
    rowCount.value = Math.max(25, Math.min(totalRows + 10, 1000));

    // Sesuaikan columns (A, B, C, ... sampai max col) menggunakan SheetJS helper encode_col
    const totalCols = Math.max(range.e.c + 1, 8);
    const newCols: string[] = [];
    for (let c = 0; c < Math.min(totalCols + 2, 50); c++) {
        newCols.push(XLSX.utils.encode_col(c));
    }
    columns.value = newCols;

    for (let R = range.s.r; R <= range.e.r; ++R) {
        for (let C = range.s.c; C <= range.e.c; ++C) {
            const cellAddress = XLSX.utils.encode_cell({ r: R, c: C });
            const cell = ws[cellAddress];
            if (cell && (cell.v !== undefined || cell.w !== undefined || cell.f !== undefined)) {
                const displayVal = cell.w !== undefined ? String(cell.w) : String(cell.v ?? '');
                sheetGrid.value[cellAddress] = {
                    value: cell.f ? '=' + cell.f : displayVal,
                    computed: displayVal,
                };
            }
        }
    }
    evaluateAllFormulas();
    selectCell('A1');
}

function switchSheet(sheetName: string) {
    if (!currentWorkbook || !currentWorkbook.Sheets[sheetName]) return;
    currentSheetName.value = sheetName;
    loadSpreadsheetFromWorksheet(currentWorkbook.Sheets[sheetName]);
}

function downloadOriginalFile() {
    if (!props.file) return;
    window.open(`/api/v1/files/${props.file.uuid}/preview`, '_blank');
}

function loadDocumentDraft(draft: any, htmlContent?: string) {
    if (htmlContent && htmlContent.trim() !== '') {
        docHtml.value = htmlContent;
    } else if (draft?.html && draft.html.trim() !== '') {
        docHtml.value = draft.html;
    } else if (typeof draft === 'string' && draft.trim() !== '') {
        docHtml.value = draft;
    } else if (draft?.paragraphs && Array.isArray(draft.paragraphs) && draft.paragraphs.length > 0) {
        docHtml.value = draft.paragraphs.map((p: any) => {
            const text = typeof p === 'string' ? p : p.text || '';
            const style = p.style === 'h1' ? 'font-size: 1.875rem; font-weight: bold; margin-bottom: 0.75rem;' : 'margin-bottom: 0.5rem;';
            return text ? `<p style="${style}">${text}</p>` : '<p><br/></p>';
        }).join('');
    } else {
        docHtml.value = '<p><br/></p>';
    }

    nextTick(() => {
        if (docEditorRef.value) {
            docEditorRef.value.innerHTML = docHtml.value;
        }
    });
}

function onDocInput(e: Event) {
    const target = e.target as HTMLElement;
    docHtml.value = target.innerHTML;
    triggerAutosave();
}

function loadSpreadsheetDraft(draft: any) {
    sheetGrid.value = {};
    if (draft?.rows && Array.isArray(draft.rows) && draft.rows.length > 0) {
        draft.rows.forEach((row: any, rIdx: number) => {
            const rowNum = rIdx + 1;
            Object.keys(row).forEach((colLetter) => {
                const cellRef = `${colLetter}${rowNum}`;
                const val = String(row[colLetter] ?? '');
                if (val !== '') {
                    sheetGrid.value[cellRef] = { value: val };
                }
            });
        });
        rowCount.value = Math.max(25, draft.rows.length + 10);
    } else {
        // Clean blank grid
        rowCount.value = 25;
        columns.value = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    }
    evaluateAllFormulas();
    selectCell('A1');
}

function loadPresentationDraft(draft: any) {
    if (draft?.slides && Array.isArray(draft.slides) && draft.slides.length > 0) {
        slides.value = draft.slides;
    } else {
        slides.value = [
            {
                id: 1,
                title: props.file?.original_name ? props.file.original_name.replace(/\.[^/.]+$/, '') : 'Judul Slide',
                subtitle: '',
                notes: '',
                background: '#0f172a'
            }
        ];
    }
    activeSlideIndex.value = 0;
}

// Autosave Debouncer
function triggerAutosave() {
    if (mode.value === 'view') return;
    saveStatus.value = 'unsaved';
    clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(async () => {
        if (!sessionToken.value) return;
        saveStatus.value = 'saving';
        try {
            const draftPayload = getDraftPayload();
            await http.post(`/office/draft/${sessionToken.value}`, { draft: draftPayload });
            saveStatus.value = 'saved';
        } catch {
            saveStatus.value = 'unsaved';
        }
    }, 2000);
}

function getDraftPayload() {
    if (docType.value === 'spreadsheet') {
        const rows: any[] = [];
        for (let r = 1; r <= rowCount.value; r++) {
            const rowObj: any = {};
            for (const col of columns.value) {
                const val = sheetGrid.value[`${col}${r}`]?.value || '';
                rowObj[col] = val;
            }
            rows.push(rowObj);
        }
        return { rows };
    } else if (docType.value === 'presentation') {
        return { slides: slides.value };
    } else {
        return docHtml.value;
    }
}

// Heartbeat for document lock
function startHeartbeat() {
    clearInterval(heartbeatTimer);
    heartbeatTimer = setInterval(async () => {
        if (!props.file || !sessionToken.value || mode.value !== 'edit') return;
        try {
            await http.post(`/office/lock/${props.file.uuid}`);
        } catch {
            // Lock lost or network error
        }
    }, 45000);
}

// Commit to Cloud as New Version
async function commitSave() {
    if (!sessionToken.value) return;
    isSaving.value = true;
    try {
        const draftPayload = getDraftPayload();
        let binaryBase64: string | undefined = undefined;

        if (docType.value === 'spreadsheet') {
            try {
                const wb = currentWorkbook || XLSX.utils.book_new();
                const wsData: any[][] = [];
                for (let r = 1; r <= rowCount.value; r++) {
                    const rowArr: any[] = [];
                    let hasRowData = false;
                    for (const col of columns.value) {
                        const val = sheetGrid.value[`${col}${r}`]?.value || '';
                        if (val) hasRowData = true;
                        rowArr.push(val);
                    }
                    if (hasRowData || r <= 15) {
                        wsData.push(rowArr);
                    }
                }
                const newWs = XLSX.utils.aoa_to_sheet(wsData);
                const targetSheet = currentSheetName.value || 'Sheet1';
                wb.Sheets[targetSheet] = newWs;
                if (!wb.SheetNames.includes(targetSheet)) {
                    wb.SheetNames.push(targetSheet);
                }
                const ext = (props.file?.extension || '').toLowerCase();
                const bookType = ext === 'csv' ? 'csv' : 'xlsx';
                binaryBase64 = XLSX.write(wb, { type: 'base64', bookType: bookType as any });
            } catch (exportErr) {
                console.warn('Gagal export XLSX base64:', exportErr);
            }
        }

        const res = await http.post(`/office/commit/${sessionToken.value}`, {
            draft: draftPayload,
            binary_base64: binaryBase64,
        });
        const updatedFile = res.data.data;

        currentVersion.value = updatedFile.version;
        saveStatus.value = 'saved';
        ui.addToast(`Dokumen tersimpan ke cloud (Versi ${updatedFile.version})`, 'success');
        emit('saved', updatedFile);
    } catch (err: any) {
        ui.addToast(err.response?.data?.message || 'Gagal menyimpan dokumen ke cloud.', 'error');
    } finally {
        isSaving.value = false;
    }
}

// Cleanup on Close
async function cleanupSession() {
    clearTimeout(autosaveTimer);
    clearInterval(heartbeatTimer);
    if (props.file && sessionToken.value && mode.value === 'edit') {
        try {
            await http.delete(`/office/lock/${props.file.uuid}`);
        } catch {
            // Ignore
        }
    }
    sessionToken.value = null;
}

function handleClose() {
    cleanupSession();
    emit('update:modelValue', false);
}

// ================= Spreadsheet Helpers =================
function selectCell(ref: string) {
    selectedCell.value = ref;
    formulaInput.value = sheetGrid.value[ref]?.value || '';
}

function onCellInput(ref: string, val: string) {
    if (!sheetGrid.value[ref]) {
        sheetGrid.value[ref] = { value: val };
    } else {
        sheetGrid.value[ref].value = val;
    }
    evaluateAllFormulas();
    triggerAutosave();
}

function onFormulaBarChange() {
    if (!sheetGrid.value[selectedCell.value]) {
        sheetGrid.value[selectedCell.value] = { value: formulaInput.value };
    } else {
        sheetGrid.value[selectedCell.value].value = formulaInput.value;
    }
    evaluateAllFormulas();
    triggerAutosave();
}

function evaluateAllFormulas() {
    for (const key of Object.keys(sheetGrid.value)) {
        const cell = sheetGrid.value[key];
        if (cell.value && cell.value.startsWith('=')) {
            cell.computed = calculateFormula(cell.value);
        } else {
            cell.computed = cell.value;
        }
    }
}

function parseRangeCells(rangeStr: string): string[] {
    const cells: string[] = [];
    const parts = rangeStr.split(',').map(s => s.trim());
    for (const part of parts) {
        if (part.includes(':')) {
            try {
                const range = XLSX.utils.decode_range(part);
                for (let r = range.s.r; r <= range.e.r; r++) {
                    for (let c = range.s.c; c <= range.e.c; c++) {
                        cells.push(XLSX.utils.encode_cell({ r, c }));
                    }
                }
            } catch {
                const m = part.match(/([A-Z]+)(\d+):([A-Z]+)(\d+)/i);
                if (m) {
                    const startCol = m[1].toUpperCase();
                    const startRow = parseInt(m[2]);
                    const endRow = parseInt(m[4]);
                    for (let r = startRow; r <= endRow; r++) {
                        cells.push(`${startCol}${r}`);
                    }
                }
            }
        } else if (/^[A-Z]+\d+$/i.test(part)) {
            cells.push(part.toUpperCase());
        }
    }
    return cells;
}

function calculateFormula(formula: string): string {
    const upper = formula.trim().toUpperCase();
    if (!upper.startsWith('=')) return formula;

    const funcMatch = upper.match(/^=(SUM|AVERAGE|AVG|COUNT|MAX|MIN)\((.+)\)$/);
    if (funcMatch) {
        const func = funcMatch[1];
        const arg = funcMatch[2];
        const cellRefs = parseRangeCells(arg);
        const vals: number[] = [];
        for (const ref of cellRefs) {
            const raw = sheetGrid.value[ref]?.computed ?? sheetGrid.value[ref]?.value;
            if (raw !== undefined && raw !== null && raw !== '') {
                const n = parseFloat(String(raw));
                if (!isNaN(n)) vals.push(n);
            }
        }

        if (func === 'SUM') {
            const sum = vals.reduce((acc, v) => acc + v, 0);
            return String(Math.round(sum * 10000) / 10000);
        }
        if (func === 'AVERAGE' || func === 'AVG') {
            if (vals.length === 0) return '0';
            const avg = vals.reduce((acc, v) => acc + v, 0) / vals.length;
            return String(Math.round(avg * 10000) / 10000);
        }
        if (func === 'COUNT') {
            return String(vals.length);
        }
        if (func === 'MAX') {
            return vals.length > 0 ? String(Math.max(...vals)) : '0';
        }
        if (func === 'MIN') {
            return vals.length > 0 ? String(Math.min(...vals)) : '0';
        }
    }

    // Arithmetic expression evaluation =A1+B1 or =(A1*2)+5
    try {
        let expr = formula.substring(1).trim();
        expr = expr.replace(/([A-Z]+\d+)/g, (match) => {
            const v = parseFloat(sheetGrid.value[match]?.computed || sheetGrid.value[match]?.value || '0');
            return isNaN(v) ? '0' : String(v);
        });
        // eslint-disable-next-line no-eval
        const result = Function(`'use strict'; return (${expr})`)();
        if (typeof result === 'number' && !isNaN(result)) {
            return String(Math.round(result * 10000) / 10000);
        }
        return String(result ?? '');
    } catch {
        return '#VALUE!';
    }
}

function onCellKeydown(e: KeyboardEvent, col: string, row: number) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const nextRow = e.shiftKey ? Math.max(1, row - 1) : Math.min(rowCount.value, row + 1);
        const nextRef = `${col}${nextRow}`;
        selectCell(nextRef);
        nextTick(() => {
            const el = document.getElementById(`cell-${nextRef}`) as HTMLInputElement | null;
            el?.focus();
            el?.select();
        });
    } else if (e.key === 'Tab') {
        e.preventDefault();
        const colIdx = columns.value.indexOf(col);
        if (colIdx !== -1) {
            const nextColIdx = e.shiftKey ? Math.max(0, colIdx - 1) : Math.min(columns.value.length - 1, colIdx + 1);
            const nextCol = columns.value[nextColIdx];
            const nextRef = `${nextCol}${row}`;
            selectCell(nextRef);
            nextTick(() => {
                const el = document.getElementById(`cell-${nextRef}`) as HTMLInputElement | null;
                el?.focus();
                el?.select();
            });
        }
    }
}

function addRow() {
    rowCount.value++;
    triggerAutosave();
}

function addColumn() {
    if (columns.value.length < 50) {
        columns.value.push(XLSX.utils.encode_col(columns.value.length));
        triggerAutosave();
    }
}

// ================= Presentation Helpers =================
function addSlide() {
    const newId = (slides.value.length > 0 ? Math.max(...slides.value.map(s => s.id)) : 0) + 1;
    slides.value.push({
        id: newId,
        title: 'Slide Baru',
        subtitle: 'Deskripsi singkat konten slide',
        notes: '',
        background: currentSlide.value?.background || '#0f172a'
    });
    activeSlideIndex.value = slides.value.length - 1;
    triggerAutosave();
}

function duplicateSlide(index: number) {
    const target = slides.value[index];
    const newId = Math.max(...slides.value.map(s => s.id)) + 1;
    slides.value.splice(index + 1, 0, {
        ...target,
        id: newId,
        title: `${target.title} (Salinan)`
    });
    activeSlideIndex.value = index + 1;
    triggerAutosave();
}

function deleteSlide(index: number) {
    if (slides.value.length <= 1) {
        ui.addToast('Presentasi harus memiliki minimal satu slide.', 'warning');
        return;
    }
    slides.value.splice(index, 1);
    if (activeSlideIndex.value >= slides.value.length) {
        activeSlideIndex.value = slides.value.length - 1;
    }
    triggerAutosave();
}

function setSlideTheme(themeBg: string) {
    if (currentSlide.value) {
        currentSlide.value.background = themeBg;
        triggerAutosave();
    }
}

// Keyboard shortcuts for slideshow
function handleKeyDown(e: KeyboardEvent) {
    if (!props.modelValue) return;
    if (isSlideshow.value) {
        if (e.key === 'Escape') {
            isSlideshow.value = false;
        } else if (e.key === 'ArrowRight' || e.key === 'Space') {
            if (activeSlideIndex.value < slides.value.length - 1) {
                activeSlideIndex.value++;
            }
        } else if (e.key === 'ArrowLeft') {
            if (activeSlideIndex.value > 0) {
                activeSlideIndex.value--;
            }
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    cleanupSession();
});

// Format action command for Rich Text Editor
function execDocCmd(command: string, value: string | undefined = undefined) {
    document.execCommand(command, false, value);
    triggerAutosave();
}
</script>

<template>
    <!-- Slideshow Fullscreen Mode -->
    <div
        v-if="isSlideshow"
        class="fixed inset-0 z-50 bg-black flex flex-col items-center justify-center p-8 select-none"
        tabindex="0"
    >
        <button
            @click="isSlideshow = false"
            class="absolute top-6 right-6 p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
            title="Keluar Slideshow (Esc)"
        >
            <X class="w-6 h-6" />
        </button>

        <div
            class="w-full max-w-5xl aspect-video rounded-2xl shadow-2xl p-12 flex flex-col justify-center items-center text-center transition-all duration-300"
            :style="{ backgroundColor: currentSlide?.background || '#0f172a' }"
        >
            <h1 class="text-5xl font-black text-white mb-6 tracking-tight drop-shadow-md">
                {{ currentSlide?.title }}
            </h1>
            <p class="text-2xl text-slate-200 font-light max-w-3xl drop-shadow-sm leading-relaxed">
                {{ currentSlide?.subtitle }}
            </p>
        </div>

        <div class="absolute bottom-6 flex items-center gap-4 text-white/70 text-sm">
            <span>Slide {{ activeSlideIndex + 1 }} dari {{ slides.length }}</span>
            <span class="text-xs text-white/40">(Gunakan Panah Kiri / Kanan atau Spasi)</span>
        </div>
    </div>

    <!-- Main Editor Modal -->
    <div
        v-if="modelValue && !isSlideshow"
        class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4 overflow-hidden"
    >
        <div
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden transition-all"
            :class="isFullscreen ? 'fixed inset-2 z-50 rounded-xl' : 'w-full max-w-6xl h-[92vh]'"
        >
            <!-- Top Header Bar -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/80 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600">
                        <FileText v-if="docType === 'document'" class="w-5 h-5" />
                        <Sheet v-else-if="docType === 'spreadsheet'" class="w-5 h-5" />
                        <Presentation v-else class="w-5 h-5" />
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-sm text-slate-800 dark:text-slate-100 max-w-xs sm:max-w-md truncate">
                                {{ file?.original_name }}
                            </span>
                            <span class="px-2 py-0.5 rounded-md bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 text-[10px] font-bold">
                                v{{ currentVersion }}
                            </span>
                            <span
                                v-if="mode === 'view'"
                                class="px-2 py-0.5 rounded-md bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 text-[10px] font-bold flex items-center gap-1"
                            >
                                <Lock class="w-2.5 h-2.5" /> Hanya Baca
                            </span>
                        </div>

                        <!-- Autosave / Lock Indicator -->
                        <div class="flex items-center gap-3 mt-0.5 text-xs text-slate-500">
                            <span v-if="saveStatus === 'saving'" class="flex items-center gap-1 text-blue-500">
                                <Loader2 class="w-3 h-3 animate-spin" /> Menyimpan draft...
                            </span>
                            <span v-else-if="saveStatus === 'saved'" class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                <Check class="w-3 h-3" /> Tersimpan otomatis di cloud
                            </span>
                            <span v-else class="text-amber-500">Perubahan belum disimpan</span>

                            <span v-if="mode === 'edit'" class="hidden sm:inline-flex items-center gap-1 text-slate-400 text-[11px]">
                                <Lock class="w-2.5 h-2.5 text-emerald-500" /> Terkunci untuk Anda
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Header Actions -->
                <div class="flex items-center gap-2">
                    <!-- Slideshow button for Presentation -->
                    <button
                        v-if="docType === 'presentation'"
                        @click="isSlideshow = true"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors"
                        title="Mulai Presentasi Layar Penuh"
                    >
                        <Play class="w-3.5 h-3.5 text-emerald-500 fill-emerald-500" />
                        <span class="hidden sm:inline">Slideshow</span>
                    </button>

                    <!-- Open in PC / Download Button -->
                    <button
                        v-if="props.file"
                        @click="downloadOriginalFile"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors border border-slate-200 dark:border-slate-700"
                        title="Unduh dan buka berkas dengan Microsoft Office di PC"
                    >
                        <Download class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                        <span class="hidden sm:inline">Buka di PC</span>
                    </button>

                    <!-- Save New Version to Cloud -->
                    <button
                        v-if="mode === 'edit'"
                        @click="commitSave"
                        :disabled="isSaving"
                        class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition-colors disabled:opacity-50"
                    >
                        <Loader2 v-if="isSaving" class="w-3.5 h-3.5 animate-spin" />
                        <Save v-else class="w-3.5 h-3.5" />
                        <span>Simpan ke Cloud</span>
                    </button>

                    <!-- Fullscreen toggle -->
                    <button
                        @click="isFullscreen = !isFullscreen"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                        :title="isFullscreen ? 'Kecilkan' : 'Perbesar Penuh'"
                    >
                        <Minimize2 v-if="isFullscreen" class="w-4 h-4" />
                        <Maximize2 v-else class="w-4 h-4" />
                    </button>

                    <!-- Close -->
                    <button
                        @click="handleClose"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div v-if="isLoading" class="flex-1 flex flex-col items-center justify-center p-12">
                <Loader2 class="w-10 h-10 text-blue-600 animate-spin mb-3" />
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Menyiapkan editor MyStorage...</p>
                <p class="text-xs text-slate-400 mt-1">Mengamankan kunci dokumen & memuat draft</p>
            </div>

            <!-- Editor Body -->
            <div v-else class="flex-1 flex flex-col overflow-hidden bg-slate-100 dark:bg-slate-950">
                <!-- ================= 1. WORD / DOCX EDITOR ================= -->
                <div v-if="docType === 'document'" class="flex-1 flex flex-col overflow-hidden">
                    <!-- Rich Text Toolbar -->
                    <div class="px-4 py-2 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center gap-1.5 flex-wrap shrink-0">
                        <button @click="execDocCmd('bold')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs" title="Tebal (Ctrl+B)">
                            <Bold class="w-4 h-4" />
                        </button>
                        <button @click="execDocCmd('italic')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs" title="Miring (Ctrl+I)">
                            <Italic class="w-4 h-4" />
                        </button>
                        <button @click="execDocCmd('underline')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs" title="Garis Bawah (Ctrl+U)">
                            <Underline class="w-4 h-4" />
                        </button>

                        <div class="w-px h-5 bg-slate-200 dark:bg-slate-800 mx-1"></div>

                        <button @click="execDocCmd('formatBlock', '<h1>')" class="px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold">
                            H1
                        </button>
                        <button @click="execDocCmd('formatBlock', '<h2>')" class="px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold">
                            H2
                        </button>
                        <button @click="execDocCmd('formatBlock', '<p>')" class="px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs">
                            Normal
                        </button>

                        <div class="w-px h-5 bg-slate-200 dark:bg-slate-800 mx-1"></div>

                        <button @click="execDocCmd('justifyLeft')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300">
                            <AlignLeft class="w-4 h-4" />
                        </button>
                        <button @click="execDocCmd('justifyCenter')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300">
                            <AlignCenter class="w-4 h-4" />
                        </button>
                        <button @click="execDocCmd('justifyRight')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300">
                            <AlignRight class="w-4 h-4" />
                        </button>
                        <button @click="execDocCmd('justifyFull')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300">
                            <AlignJustify class="w-4 h-4" />
                        </button>

                        <div class="w-px h-5 bg-slate-200 dark:bg-slate-800 mx-1"></div>

                        <button @click="execDocCmd('insertUnorderedList')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300">
                            <List class="w-4 h-4" />
                        </button>
                        <button @click="execDocCmd('insertOrderedList')" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300">
                            <ListOrdered class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Canvas Container -->
                    <div class="flex-1 overflow-y-auto p-4 sm:p-8 flex justify-center">
                        <div
                            ref="docEditorRef"
                            class="w-full max-w-3xl min-h-[700px] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-lg p-8 sm:p-12 text-slate-800 dark:text-slate-100 focus:outline-hidden leading-relaxed"
                            :contenteditable="mode === 'edit'"
                            @input="onDocInput"
                        ></div>
                    </div>

                    <!-- Status Bar -->
                    <div class="px-6 py-2 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-400 flex items-center justify-between shrink-0">
                        <span>{{ wordCount }} kata</span>
                        <span>MyStorage Document Editor</span>
                    </div>
                </div>

                <!-- ================= 2. EXCEL / SPREADSHEET EDITOR ================= -->
                <div v-else-if="docType === 'spreadsheet'" class="flex-1 flex flex-col overflow-hidden">
                    <!-- Formula & Grid Bar -->
                    <div class="px-4 py-2 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center gap-3 shrink-0">
                        <!-- Active Cell Name -->
                        <div class="px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-lg text-xs font-mono font-bold text-slate-700 dark:text-slate-200 min-w-[50px] text-center border border-slate-200 dark:border-slate-700">
                            {{ selectedCell }}
                        </div>

                        <span class="text-xs font-serif font-bold text-slate-400 italic">fx</span>

                        <!-- Formula Input Box -->
                        <input
                            v-model="formulaInput"
                            @input="onFormulaBarChange"
                            :disabled="mode === 'view'"
                            type="text"
                            placeholder="Ketik nilai atau rumus (misal: =SUM(B3:B4))"
                            class="flex-1 px-3 py-1 rounded-lg text-xs font-mono bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:outline-hidden focus:ring-1 focus:ring-blue-500 text-slate-800 dark:text-slate-100"
                        />

                        <!-- Add Row / Column Buttons -->
                        <div class="flex items-center gap-1">
                            <button
                                @click="addRow"
                                class="px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                            >
                                + Baris
                            </button>
                            <button
                                @click="addColumn"
                                class="px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                            >
                                + Kolom
                            </button>
                        </div>
                    </div>

                    <!-- Spreadsheet Table Grid -->
                    <div class="flex-1 overflow-auto bg-white dark:bg-slate-900">
                        <table class="w-full border-collapse text-xs font-sans select-none">
                            <thead>
                                <tr class="bg-slate-100 dark:bg-slate-800 sticky top-0 z-10">
                                    <th class="w-10 border border-slate-200 dark:border-slate-700 px-2 py-1.5 text-center text-slate-500 font-bold bg-slate-200/60 dark:bg-slate-800"></th>
                                    <th
                                        v-for="col in columns"
                                        :key="col"
                                        class="min-w-[110px] border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-center text-slate-700 dark:text-slate-300 font-bold"
                                    >
                                        {{ col }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in rowCount" :key="r" class="hover:bg-blue-50/20">
                                    <!-- Row Number Header -->
                                    <td class="border border-slate-200 dark:border-slate-700 px-2 py-1 text-center text-slate-400 font-bold bg-slate-100 dark:bg-slate-800 select-none">
                                        {{ r }}
                                    </td>
                                    <!-- Grid Cells -->
                                    <td
                                        v-for="col in columns"
                                        :key="`${col}${r}`"
                                        @click="selectCell(`${col}${r}`)"
                                        class="border border-slate-200 dark:border-slate-700 p-0 relative"
                                        :class="selectedCell === `${col}${r}` ? 'ring-2 ring-blue-500 z-10' : ''"
                                    >
                                        <input
                                            :id="`cell-${col}${r}`"
                                            :value="selectedCell === `${col}${r}` ? (sheetGrid[`${col}${r}`]?.value || '') : (sheetGrid[`${col}${r}`]?.computed || sheetGrid[`${col}${r}`]?.value || '')"
                                            @input="e => onCellInput(`${col}${r}`, (e.target as HTMLInputElement).value)"
                                            @keydown="onCellKeydown($event, col, r)"
                                            :disabled="mode === 'view'"
                                            type="text"
                                            class="w-full h-full px-2 py-1.5 text-xs bg-transparent focus:outline-hidden text-slate-800 dark:text-slate-100"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sheet Tabs Bar -->
                    <div v-if="availableSheets.length > 1" class="flex items-center gap-1.5 px-4 py-1.5 bg-slate-100 dark:bg-slate-800/90 border-t border-slate-200 dark:border-slate-700 overflow-x-auto text-xs shrink-0">
                        <span class="text-[10px] font-bold text-slate-400 mr-1 uppercase">Lembar Kerja:</span>
                        <button
                            v-for="sheet in availableSheets"
                            :key="sheet"
                            @click="switchSheet(sheet)"
                            class="px-3 py-1 rounded-md text-xs font-semibold transition-all"
                            :class="currentSheetName === sheet
                                ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs font-bold border border-slate-200 dark:border-slate-700'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 hover:bg-slate-200 dark:hover:bg-slate-700'"
                        >
                            {{ sheet }}
                        </button>
                    </div>

                    <!-- Grid Status Bar -->
                    <div class="px-6 py-2 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-400 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            <span class="font-mono">Sel: {{ selectedCell }}</span>
                            <span v-if="currentSheetName" class="font-medium text-slate-500">Lembar: {{ currentSheetName }}</span>
                        </div>
                        <span>MyStorage Spreadsheet Engine (SheetJS & OpenXML)</span>
                    </div>
                </div>

                <!-- ================= 3. POWERPOINT / PRESENTATION EDITOR ================= -->
                <div v-else class="flex-1 flex overflow-hidden">
                    <!-- Left Slide Thumbnails Panel -->
                    <div class="w-60 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col shrink-0">
                        <div class="p-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Slide ({{ slides.length }})</span>
                            <button
                                @click="addSlide"
                                :disabled="mode === 'view'"
                                class="flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 text-xs font-semibold hover:bg-blue-100"
                            >
                                <Plus class="w-3.5 h-3.5" /> Tambah
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-3 space-y-3">
                            <div
                                v-for="(slide, idx) in slides"
                                :key="slide.id"
                                @click="activeSlideIndex = idx"
                                class="group relative p-2.5 rounded-xl border transition-all cursor-pointer"
                                :class="activeSlideIndex === idx
                                    ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-950/30 shadow-xs'
                                    : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'"
                            >
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[11px] font-bold text-slate-400">#{{ idx + 1 }}</span>
                                    <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 transition-opacity">
                                        <button @click.stop="duplicateSlide(idx)" class="p-1 hover:text-blue-500 text-slate-400" title="Duplikat">
                                            <Copy class="w-3 h-3" />
                                        </button>
                                        <button @click.stop="deleteSlide(idx)" class="p-1 hover:text-red-500 text-slate-400" title="Hapus">
                                            <Trash2 class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Miniature Thumbnail -->
                                <div
                                    class="w-full aspect-video rounded-lg p-2 flex flex-col justify-center items-center text-center shadow-xs"
                                    :style="{ backgroundColor: slide.background }"
                                >
                                    <p class="text-[10px] font-bold text-white truncate max-w-full">{{ slide.title || 'Slide Baru' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Slide Workspace -->
                    <div class="flex-1 flex flex-col overflow-y-auto p-6 items-center justify-center">
                        <!-- Theme Palette Picker -->
                        <div class="mb-4 flex items-center gap-2 bg-white dark:bg-slate-900 p-1.5 rounded-xl border border-slate-200 dark:border-slate-800">
                            <span class="text-xs text-slate-400 px-2 font-medium">Tema Slide:</span>
                            <button
                                v-for="t in slideThemes"
                                :key="t.name"
                                @click="setSlideTheme(t.bg)"
                                class="w-5 h-5 rounded-full border border-slate-300 shadow-xs transition-transform hover:scale-110"
                                :style="{ backgroundColor: t.bg }"
                                :title="t.name"
                            ></button>
                        </div>

                        <!-- Active Slide Canvas (16:9 Widescreen) -->
                        <div
                            class="w-full max-w-3xl aspect-video rounded-2xl shadow-2xl p-10 flex flex-col justify-center items-center text-center transition-all border border-slate-200/20"
                            :style="{ backgroundColor: currentSlide?.background || '#0f172a' }"
                        >
                            <input
                                v-model="currentSlide.title"
                                @input="triggerAutosave"
                                :disabled="mode === 'view'"
                                type="text"
                                placeholder="Klik untuk menambah judul slide"
                                class="w-full text-center text-3xl sm:text-4xl font-black text-white bg-transparent border-none focus:outline-hidden placeholder-white/40 mb-4"
                            />
                            <textarea
                                v-model="currentSlide.subtitle"
                                @input="triggerAutosave"
                                :disabled="mode === 'view'"
                                rows="3"
                                placeholder="Klik untuk menambah sub-judul atau poin presentasi"
                                class="w-full max-w-xl text-center text-base sm:text-lg text-slate-200 bg-transparent border-none focus:outline-hidden placeholder-white/30 resize-none"
                            ></textarea>
                        </div>

                        <!-- Slide Speaker Notes -->
                        <div class="w-full max-w-3xl mt-4 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
                            <label class="text-[11px] font-bold text-slate-400 block mb-1 uppercase tracking-wider">Catatan Pembicara</label>
                            <input
                                v-model="currentSlide.notes"
                                @input="triggerAutosave"
                                :disabled="mode === 'view'"
                                type="text"
                                placeholder="Tambahkan catatan untuk Anda saat presentasi..."
                                class="w-full text-xs text-slate-700 dark:text-slate-200 bg-transparent focus:outline-hidden"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

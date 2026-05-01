/*
    MathLab - Visual Mathematical Expression Builder
    Copyright (C) 2026 - Licensed under GPL-3.0
*/
'use strict';

/* ═══════════════════════════════════════════════════════════════
   Config
   ═══════════════════════════════════════════════════════════════ */
const CONFIG = Object.freeze({
    PARSER: { add: '+', subtract: '-', multiply: '*', divide: '/', equals: '=', openParen: '(', closeParen: ')', radical: 'sqrt', power: '^', fraction: '/', pi: 'pi' },
    API_ENDPOINT: 'index.php',
    API_PARAM: 'equation',
    ZOOM_MIN: 0.25, ZOOM_MAX: 4, ZOOM_STEP: 0.1,
    UNDO_LIMIT: 100,
    DEBOUNCE: 180, THROTTLE: 16,
    LONG_PRESS: 180,
    SNAP_DIST: 90
});

/* ═══════════════════════════════════════════════════════════════
   Utils
   ═══════════════════════════════════════════════════════════════ */
const Utils = {
    uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 8); },

    escapeHtml(s) {
        if (typeof s !== 'string') return '';
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    },

    sanitize(s) {
        if (typeof s !== 'string') return '';
        // Allow minus prefix for numbers, but strip all HTML-dangerous chars
        return s.replace(/[<>'"&\\`]/g, '').trim().slice(0, 200);
    },

    sanitizeNumber(s) {
        if (typeof s !== 'string') return '';
        // Only digits, optional leading minus and single dot
        return s.replace(/[^\d.\-]/g, '').slice(0, 20);
    },

    sanitizeVariable(s) {
        if (typeof s !== 'string') return '';
        // Only word chars; max 64 to match RegexCache::isValidIdentifier in MathLibrary v1.0.0-stable.
        // Also strip any leading digits or underscores — identifiers must begin with [a-zA-Z].
        return s.replace(/[^\w]/g, '').replace(/^[0-9_]+/, '').slice(0, 64);
    },

    debounce(fn, ms) {
        let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
    },

    throttle(fn, ms) {
        let w = false; return (...a) => { if (!w) { fn(...a); w = true; setTimeout(() => { w = false; }, ms); } };
    },

    clone(o) { return JSON.parse(JSON.stringify(o)); },
    dist(x1, y1, x2, y2) { return Math.hypot(x2 - x1, y2 - y1); }
};

/* ═══════════════════════════════════════════════════════════════
   i18n
   ═══════════════════════════════════════════════════════════════ */
const I18n = {
    lang: 'fa',
    strings: {
        fa: {
            'app.title': 'MathLab', 'app.subtitle': 'میزکار ریاضی',
            'sidebar.title': 'عناصر', 'sidebar.tapHint': 'ضربه = افزودن',
            'groups.numbers': 'اعداد', 'groups.operations': 'عملیات', 'groups.symbols': 'نمادها', 'groups.variables': 'متغیرها',
            'elements.number': 'عدد', 'elements.decimal': 'اعشاری', 'elements.add': 'جمع', 'elements.subtract': 'تفریق',
            'elements.multiply': 'ضرب', 'elements.divide': 'تقسیم', 'elements.equals': 'مساوی',
            'elements.fraction': 'کسر', 'elements.radical': 'رادیکال', 'elements.power': 'توان', 'elements.variable': 'متغیر',
            'workspace.title': 'میزکار', 'workspace.hint': 'عناصر را اینجا بکشید',
            'preview.title': 'معادله', 'preview.copy': 'کپی', 'preview.copied': 'کپی شد!',
            'validation.title': 'بررسی',
            'validation.valid': 'معادله معتبر است',
            'validation.empty': 'معادله خالی',
            'validation.multiEquals': 'بیش از یک علامت مساوی مجاز نیست',
            'validation.emptyField': 'فیلد خالی',
            'validation.invalidNumber': 'فرمت عدد نامعتبر',
            'validation.invalidDecimal': 'فرمت اعشاری نامعتبر',
            'validation.invalidVariable': 'نام متغیر نامعتبر (باید با حرف شروع شود، حداکثر ۶۴ کاراکتر)',
            'validation.startsWithOp': 'معادله با عملگر شروع می‌شود',
            'validation.endsWithOp': 'معادله با عملگر تمام می‌شود',
            'validation.consecutiveOps': 'دو عملگر پشت سر هم',
            'validation.missingOp': 'عملگر بین دو مقدار وجود ندارد',
            'validation.unmatchedClose': 'پرانتز بسته بدون باز',
            'validation.unclosedParen': 'پرانتز باز نشده',
            'validation.divByZero': 'تقسیم بر صفر',
            'validation.emptyNumerator': 'صورت کسر خالی است',
            'validation.emptyDenominator': 'مخرج کسر خالی است',
            'validation.emptyBase': 'پایه توان خالی است',
            'validation.emptyExponent': 'توان خالی است',
            'validation.emptyRadical': 'محتوای رادیکال خالی است',
            'validation.negativeRoot': 'رادیکال عدد منفی (مقدار موهومی)',
            'validation.zeroToZero': '۰ به توان ۰ نامشخص است',
            'validation.emptyParen': 'پرانتز خالی ()',
            'validation.exprMode': 'محاسبه عبارت (بدون مساوی)',
            'validation.inFracNum': 'در صورت کسر', 'validation.inFracDen': 'در مخرج کسر',
            'validation.inBase': 'در پایه توان', 'validation.inExp': 'در توان', 'validation.inRadical': 'در رادیکال',
            'results.title': 'نتیجه محاسبه', 'results.answer': 'پاسخ نهایی',
            'results.prev': 'قبلی', 'results.next': 'بعدی', 'results.play': 'پخش', 'results.pause': 'مکث',
            'results.replay': 'بازپخش', 'results.close': 'بستن',
            'results.step': 'گام', 'results.of': 'از',
            'results.expression': 'عبارت', 'results.equation': 'معادله',
            'results.symbolic': 'نمادین',
            'actions.calculate': 'محاسبه',
            'context.duplicate': 'تکرار', 'context.moveLeft': 'چپ', 'context.moveRight': 'راست', 'context.delete': 'حذف',
            'status.sending': 'در حال ارسال...', 'status.success': '✓ موفق', 'status.error': '✗ خطا',
            'clear.confirm': 'میزکار پاک شود؟',
            'import.title': 'ورودی متنی',
            'import.placeholder': 'sqrt(x+1) = (a+b)/(c)',
            'import.syntax': 'sqrt(…) · frac(a,b) · pow(a,b) · x^2 · (a)/(b)',
            'import.confirm': 'تایید',
            'import.cancel': 'لغو',
            'import.invalid': 'ورودی نامعتبر است',
            'import.replaced': 'معادله جایگزین شد',
            'fileDrop.hint': 'فایل .txt را رها کنید',
            'fileDrop.loaded': 'فایل بارگذاری شد',
            'varPanel.title': 'متغیرها',
            'varPanel.hint': 'متغیری در معادله یافت نشد',
            'varPanel.placeholder': 'عدد (اختیاری)',
            'varPanel.symHint': 'نمادین',
            'context.details': 'جزئیات',
            'details.title': 'جزئیات',
            'details.type': 'نوع',
            'details.parent': 'والد',
            'details.children': 'فرزندان',
            'details.description': 'توضیحات',
            'details.rootToken': 'توکن ریشه',
            'details.none': 'ندارد',
            'details.empty': 'خالی',
            'details.type.number': 'عدد',
            'details.type.decimal': 'اعشاری',
            'details.type.variable': 'متغیر',
            'details.type.pi': 'عدد پی',
            'details.type.add': 'جمع',
            'details.type.subtract': 'تفریق',
            'details.type.multiply': 'ضرب',
            'details.type.divide': 'تقسیم',
            'details.type.equals': 'مساوی',
            'details.type.openParen': 'پرانتز باز',
            'details.type.closeParen': 'پرانتز بسته',
            'details.type.fraction': 'کسر',
            'details.type.power': 'توان',
            'details.type.radical': 'رادیکال',
            'details.desc.number': 'یک عدد صحیح.',
            'details.desc.decimal': 'یک عدد اعشاری.',
            'details.desc.variable': 'یک متغیر با نام دلخواه (با حرف شروع می‌شود).',
            'details.desc.pi': 'ثابت ریاضی π (عدد پی).',
            'details.desc.add': 'عملگر جمع.',
            'details.desc.subtract': 'عملگر تفریق.',
            'details.desc.multiply': 'عملگر ضرب.',
            'details.desc.divide': 'عملگر تقسیم.',
            'details.desc.equals': 'علامت تساوی.',
            'details.desc.openParen': 'پرانتز باز برای گروه‌بندی.',
            'details.desc.closeParen': 'پرانتز بسته برای گروه‌بندی.',
            'details.desc.fraction': 'کسر: شامل صورت و مخرج.',
            'details.desc.power': 'توان: شامل پایه و نما.',
            'details.desc.radical': 'رادیکال: محاسبهٔ ریشهٔ دوم عبارت درون آن.',
        },
        en: {
            'app.title': 'MathLab', 'app.subtitle': 'Math Workspace',
            'sidebar.title': 'Elements', 'sidebar.tapHint': 'Tap = add',
            'groups.numbers': 'Numbers', 'groups.operations': 'Operations', 'groups.symbols': 'Symbols', 'groups.variables': 'Variables',
            'elements.number': 'Number', 'elements.decimal': 'Decimal', 'elements.add': 'Add', 'elements.subtract': 'Subtract',
            'elements.multiply': 'Multiply', 'elements.divide': 'Divide', 'elements.equals': 'Equals',
            'elements.fraction': 'Fraction', 'elements.radical': 'Radical', 'elements.power': 'Power', 'elements.variable': 'Variable',
            'workspace.title': 'Workspace', 'workspace.hint': 'Drag elements here',
            'preview.title': 'Equation', 'preview.copy': 'Copy', 'preview.copied': 'Copied!',
            'validation.title': 'Check',
            'validation.valid': 'Valid equation',
            'validation.empty': 'Empty equation',
            'validation.multiEquals': 'Only one equals sign (=) is allowed',
            'validation.emptyField': 'Empty field',
            'validation.invalidNumber': 'Invalid number format',
            'validation.invalidDecimal': 'Invalid decimal format',
            'validation.invalidVariable': 'Invalid variable name (must start with a letter, max 64 chars)',
            'validation.startsWithOp': 'Expression starts with an operator',
            'validation.endsWithOp': 'Expression ends with an operator',
            'validation.consecutiveOps': 'Consecutive operators',
            'validation.missingOp': 'Missing operator between values',
            'validation.unmatchedClose': 'Unmatched closing parenthesis',
            'validation.unclosedParen': 'Unclosed parenthesis',
            'validation.divByZero': 'Division by zero',
            'validation.emptyNumerator': 'Empty fraction numerator',
            'validation.emptyDenominator': 'Empty fraction denominator',
            'validation.emptyBase': 'Empty power base',
            'validation.emptyExponent': 'Empty exponent',
            'validation.emptyRadical': 'Empty radical content',
            'validation.negativeRoot': 'Square root of negative number (imaginary)',
            'validation.zeroToZero': '0^0 is undefined',
            'validation.emptyParen': 'Empty parentheses ()',
            'validation.exprMode': 'Expression mode (no equals sign)',
            'validation.inFracNum': 'In numerator', 'validation.inFracDen': 'In denominator',
            'validation.inBase': 'In base', 'validation.inExp': 'In exponent', 'validation.inRadical': 'In radical',
            'results.title': 'Solution', 'results.answer': 'Final Answer',
            'results.prev': 'Prev', 'results.next': 'Next', 'results.play': 'Play', 'results.pause': 'Pause',
            'results.replay': 'Replay', 'results.close': 'Close',
            'results.step': 'Step', 'results.of': 'of',
            'results.expression': 'Expression', 'results.equation': 'Equation',
            'results.symbolic': 'Symbolic',
            'actions.calculate': 'Calculate',
            'context.duplicate': 'Duplicate', 'context.moveLeft': 'Move Left', 'context.moveRight': 'Move Right', 'context.delete': 'Delete',
            'status.sending': 'Sending...', 'status.success': '✓ Success', 'status.error': '✗ Error',
            'clear.confirm': 'Clear the workspace?',
            'import.title': 'Text Input',
            'import.placeholder': 'sqrt(x+1) = (a+b)/(c)',
            'import.syntax': 'sqrt(…) · frac(a,b) · pow(a,b) · x^2 · (a)/(b)',
            'import.confirm': 'Confirm',
            'import.cancel': 'Cancel',
            'import.invalid': 'Invalid input',
            'import.replaced': 'Equation replaced',
            'fileDrop.hint': 'Drop a .txt file here',
            'fileDrop.loaded': 'File loaded',
            'varPanel.title': 'Variables',
            'varPanel.hint': 'No variables in expression',
            'varPanel.placeholder': 'number (opt.)',
            'varPanel.symHint': 'symbolic',
            'context.details': 'Details',
            'details.title': 'Details',
            'details.type': 'Type',
            'details.parent': 'Parent',
            'details.children': 'Children',
            'details.description': 'Description',
            'details.rootToken': 'Root token',
            'details.none': 'None',
            'details.empty': 'Empty',
            'details.type.number': 'Number',
            'details.type.decimal': 'Decimal',
            'details.type.variable': 'Variable',
            'details.type.pi': 'Pi',
            'details.type.add': 'Addition',
            'details.type.subtract': 'Subtraction',
            'details.type.multiply': 'Multiplication',
            'details.type.divide': 'Division',
            'details.type.equals': 'Equals',
            'details.type.openParen': 'Opening Parenthesis',
            'details.type.closeParen': 'Closing Parenthesis',
            'details.type.fraction': 'Fraction',
            'details.type.power': 'Power',
            'details.type.radical': 'Radical',
            'details.desc.number': 'An integer number.',
            'details.desc.decimal': 'A decimal number.',
            'details.desc.variable': 'A variable (must start with a letter).',
            'details.desc.pi': 'The mathematical constant π (pi).',
            'details.desc.add': 'Addition operator.',
            'details.desc.subtract': 'Subtraction operator.',
            'details.desc.multiply': 'Multiplication operator.',
            'details.desc.divide': 'Division operator.',
            'details.desc.equals': 'Equals sign.',
            'details.desc.openParen': 'Opening parenthesis for grouping.',
            'details.desc.closeParen': 'Closing parenthesis for grouping.',
            'details.desc.fraction': 'Fraction: consists of numerator and denominator.',
            'details.desc.power': 'Power: consists of base and exponent.',
            'details.desc.radical': 'Square root of the contained expression.',
        }
    },

    t(k) { return (this.strings[this.lang] || {})[k] || k; },

    setLang(lang) {
        this.lang = lang;
        document.documentElement.lang = lang;
        document.documentElement.dir = lang === 'fa' ? 'rtl' : 'ltr';
        document.documentElement.dataset.lang = lang;
        this.updateDOM();
        if (typeof App !== 'undefined') { App.updateValidation(); App._updateVariablePanel?.(); }
    },

    updateDOM() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            el.textContent = this.t(el.dataset.i18n);
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            el.placeholder = this.t(el.dataset.i18nPlaceholder);
        });
    }
};

/* ═══════════════════════════════════════════════════════════════
   State
   ═══════════════════════════════════════════════════════════════ */
const State = {
    tokens: [], undoStack: [], redoStack: [],
    selectedId: null, zoom: 1, panX: 0, panY: 0,
    varValues: {}, // variable name → numeric value (for calculation)

    init() { this.tokens = []; this.undoStack = []; this.redoStack = []; this.selectedId = null; this._selectedEl = null; this.zoom = 1; this.panX = 0; this.panY = 0; this.varValues = {}; },

    save() {
        this.undoStack.push(Utils.clone(this.tokens));
        if (this.undoStack.length > CONFIG.UNDO_LIMIT) this.undoStack.shift();
        this.redoStack = [];
    },

    undo() { if (!this.undoStack.length) return false; this.redoStack.push(Utils.clone(this.tokens)); this.tokens = this.undoStack.pop(); this.selectedId = null; this._selectedEl = null; return true; },
    redo() { if (!this.redoStack.length) return false; this.undoStack.push(Utils.clone(this.tokens)); this.tokens = this.redoStack.pop(); this.selectedId = null; this._selectedEl = null; return true; },

    createToken(type) {
        const t = { id: Utils.uid(), type };
        switch (type) {
            case 'number': t.value = '1'; break;
            case 'decimal': t.value = '1.0'; break;
            case 'variable': t.value = 'x'; break;
            case 'fraction': t.numerator = []; t.denominator = []; break;
            case 'power': t.base = []; t.exponent = []; break;
            case 'radical': t.content = []; break;
        }
        return t;
    },

    findToken(id, list = this.tokens) {
        for (const t of list) {
            if (t.id === id) return t;
            const c = this._inChildren(id, t);
            if (c) return c;
        }
        return null;
    },

    _inChildren(id, t) {
        if (t.type === 'fraction') return this.findToken(id, t.numerator) || this.findToken(id, t.denominator);
        if (t.type === 'power') return this.findToken(id, t.base) || this.findToken(id, t.exponent);
        if (t.type === 'radical') return this.findToken(id, t.content);
        return null;
    },

    removeToken(id, list = this.tokens) {
        const i = list.findIndex(t => t.id === id);
        if (i !== -1) { list.splice(i, 1); return true; }
        for (const t of list) {
            const ok = t.type === 'fraction' ? (this.removeToken(id, t.numerator) || this.removeToken(id, t.denominator))
                : t.type === 'power' ? (this.removeToken(id, t.base) || this.removeToken(id, t.exponent))
                    : t.type === 'radical' ? this.removeToken(id, t.content)
                        : false;
            if (ok) return true;
        }
        return false;
    },

    findParentToken(id, list = this.tokens, parent = null) {
        for (const t of list) {
            if (t.id === id) return parent;
            let childList = null;
            if (t.type === 'fraction') childList = [t.numerator, t.denominator];
            else if (t.type === 'power') childList = [t.base, t.exponent];
            else if (t.type === 'radical') childList = [t.content];
            if (childList) {
                for (const sub of childList) {
                    const found = this.findParentToken(id, sub, t);
                    if (found !== undefined) return found;
                }
            }
        }
        return undefined; // not found
    },

    findParentList(id, list = this.tokens, parent = null) {
        for (let i = 0; i < list.length; i++) {
            if (list[i].id === id) return { list, index: i };
            const t = list[i];
            if (t.type === 'fraction') {
                const r = this.findParentList(id, t.numerator, t) || this.findParentList(id, t.denominator, t);
                if (r) return r;
            }
            if (t.type === 'power') {
                const r = this.findParentList(id, t.base, t) || this.findParentList(id, t.exponent, t);
                if (r) return r;
            }
            if (t.type === 'radical') {
                const r = this.findParentList(id, t.content, t);
                if (r) return r;
            }
        }
        return null;
    },

    countTokens(list = this.tokens) {
        return list.reduce((s, t) => {
            let n = 1;
            if (t.type === 'fraction') n += this.countTokens(t.numerator) + this.countTokens(t.denominator);
            if (t.type === 'power') n += this.countTokens(t.base) + this.countTokens(t.exponent);
            if (t.type === 'radical') n += this.countTokens(t.content);
            return s + n;
        }, 0);
    },

    countEquals(list = this.tokens) {
        // Only count at the level provided — do NOT recurse into sub-structures.
        // Equals inside a fraction/power/radical is a separate validation error.
        return list.reduce((s, t) => s + (t.type === 'equals' ? 1 : 0), 0);
    }
};

/* ═══════════════════════════════════════════════════════════════
   Desktop Drag (HTML5 DnD)
   ═══════════════════════════════════════════════════════════════ */
const Drag = {
    state: 'IDLE', payload: null, ghost: null,

    init() { this.ghost = document.getElementById('dragGhost'); },

    start(payload) {
        if (this.state !== 'IDLE') return false;
        this.state = 'DRAGGING'; this.payload = payload;
        if (this.ghost && payload.text) { this.ghost.textContent = payload.text; this.ghost.classList.add('visible'); }
        return true;
    },

    updateGhost: Utils.throttle(function (x, y) {
        if (this.ghost && this.state === 'DRAGGING') { this.ghost.style.left = x + 'px'; this.ghost.style.top = y + 'px'; }
    }, CONFIG.THROTTLE),

    end() { this.state = 'IDLE'; const r = this.payload; this.payload = null; this.ghost?.classList.remove('visible'); return r; },
    cancel() { this.state = 'IDLE'; this.payload = null; this.ghost?.classList.remove('visible'); }
};

/* ═══════════════════════════════════════════════════════════════
   Touch Drag (Mobile / Tablet)
   ═══════════════════════════════════════════════════════════════ */
const TouchDrag = {
    active: false, payload: null, ghost: null, lastX: 0, lastY: 0,

    init() {
        this.ghost = document.getElementById('dragGhost');
        document.addEventListener('touchmove', e => this._onMove(e), { passive: false });
        document.addEventListener('touchend', e => this._onEnd(e), { passive: false });
        document.addEventListener('touchcancel', () => this._cleanup(), { passive: true });
    },

    start(touch, payload) {
        if (this.active) return false;
        Drag.cancel();
        this.active = true; this.payload = payload;
        this.lastX = touch.clientX; this.lastY = touch.clientY;
        this.ghost.textContent = payload.text || '?';
        this.ghost.classList.add('visible');
        this._moveGhost(touch.clientX, touch.clientY);
        document.body.classList.add('is-dragging');
        return true;
    },

    _moveGhost(x, y) { this.ghost.style.left = x + 'px'; this.ghost.style.top = y + 'px'; },

    _onMove(e) {
        if (!this.active) return;
        e.preventDefault();
        const t = e.touches[0];
        this.lastX = t.clientX; this.lastY = t.clientY;
        this._moveGhost(t.clientX, t.clientY);
        this._highlightDrop(t.clientX, t.clientY);
    },

    _highlightDrop(x, y) {
        this.ghost.style.visibility = 'hidden';
        const el = document.elementFromPoint(x, y);
        this.ghost.style.visibility = '';

        document.querySelectorAll('.drop-zone.drag-over').forEach(z => z.classList.remove('drag-over'));
        Render.clearAllIndicators();
        const ws = document.getElementById('workspace');
        const wr = document.getElementById('workspaceWrapper');
        ws?.classList.remove('dragging-over');
        wr?.classList.remove('drop-ready');

        if (!el) return;
        const dz = el.closest('.drop-zone');
        if (dz && dz._list) {
            dz.classList.add('drag-over');
            Render.showIndicator(dz, Render.calcDropIndex(x, y, dz));
            return;
        }
        if (wr && (wr.contains(el) || el === wr)) {
            ws?.classList.add('dragging-over');
            wr.classList.add('drop-ready');
            Render.showIndicator(ws, State.tokens.length === 0 ? 0 : App.calcWorkspaceDropIndex(x, y));
        }
    },

    _onEnd(e) {
        if (!this.active) return;
        const t = e.changedTouches[0];
        this.ghost.style.visibility = 'hidden';
        const el = document.elementFromPoint(t.clientX, t.clientY);
        this.ghost.style.visibility = '';
        this._drop(el, t.clientX, t.clientY);
        this._cleanup();
    },

    _drop(el, x, y) {
        if (!el || !this.payload) return;
        const dz = el.closest?.('.drop-zone');
        if (dz && dz._list) { App.performDrop(this.payload, dz._list, Render.calcDropIndex(x, y, dz), x, y); return; }
        const wr = document.getElementById('workspaceWrapper');
        if (wr && (wr.contains(el) || el === wr)) {
            App.performDrop(this.payload, State.tokens, State.tokens.length === 0 ? 0 : App.calcWorkspaceDropIndex(x, y), x, y);
        }
    },

    _cleanup() {
        this.active = false; this.payload = null;
        this.ghost.classList.remove('visible');
        document.body.classList.remove('is-dragging');
        const ws = document.getElementById('workspace'), wr = document.getElementById('workspaceWrapper');
        ws?.classList.remove('dragging-over'); wr?.classList.remove('drop-ready');
        document.querySelectorAll('.drop-zone.drag-over').forEach(z => z.classList.remove('drag-over'));
        Render.clearAllIndicators();
    }
};

/* ═══════════════════════════════════════════════════════════════
   Math Text Parser  (text string → token array)
   Supports: numbers, decimals, variables, pi/π
             + - * × / ÷ =
             sqrt(…) √(…)
             frac(a,b)
             pow(a,b)
             (a)/(b)  → fraction
             expr^expr → power
             (expr)   → parenthesised group
   ═══════════════════════════════════════════════════════════════ */
const MathParser = (() => {
    let src = '', pos = 0;

    function parse(input) {
        src = (input || '').trim();
        pos = 0;
        try {
            skipWS();
            if (pos >= src.length) return { ok: false, error: 'Empty input' };
            const tokens = parseEquation();
            skipWS();
            if (pos < src.length) return { ok: false, error: `Unexpected '${src[pos]}' at position ${pos + 1}` };
            return { ok: true, tokens };
        } catch (e) {
            return { ok: false, error: e.message };
        }
    }

    function parseEquation() {
        const left = parseExpr();
        skipWS();
        if (pos < src.length && src[pos] === '=') {
            pos++;
            const eq = mk('equals');
            const right = parseExpr();
            return [...left, eq, ...right];
        }
        return left;
    }

    function parseExpr() {
        let res = parseTerm();
        skipWS();
        while (pos < src.length && (src[pos] === '+' || src[pos] === '-')) {
            const op = src[pos++];
            res.push(mk(op === '+' ? 'add' : 'subtract'));
            res.push(...parseTerm());
            skipWS();
        }
        return res;
    }

    function parseTerm() {
        let res = parsePowerOrAtom();
        skipWS();
        while (pos < src.length) {
            const ch = src[pos];
            if (ch === '*' || ch === '×') { pos++; res.push(mk('multiply')); res.push(...parsePowerOrAtom()); }
            else if (ch === '÷') { pos++; res.push(mk('divide')); res.push(...parsePowerOrAtom()); }
            else if (ch === '/' && !(pos + 1 < src.length && src[pos + 1] === '(')) {
                // plain divide (not fraction notation)
                pos++; res.push(mk('divide')); res.push(...parsePowerOrAtom());
            } else break;
            skipWS();
        }
        return res;
    }

    function parsePowerOrAtom() {
        // Check for paren-group which may become fraction or power
        if (pos < src.length && src[pos] === '(') return parseParenCompound();
        return parseAtom();
    }

    function parseParenCompound() {
        pos++; // consume '('
        const inner = parseExpr();
        skipWS();
        if (pos >= src.length || src[pos] !== ')') throw new Error('Expected closing )');
        pos++; // consume ')'
        skipWS();

        // Fraction: (inner)/(...)
        if (pos < src.length && src[pos] === '/') {
            pos++; skipWS();
            let den;
            if (pos < src.length && src[pos] === '(') {
                pos++; den = parseExpr(); skipWS();
                if (pos >= src.length || src[pos] !== ')') throw new Error('Expected ) in fraction denominator');
                pos++;
            } else {
                den = parseAtom();
            }
            const f = mk('fraction'); f.numerator = inner; f.denominator = den;
            return checkPower([f]);
        }

        // Power: (inner)^...
        if (pos < src.length && src[pos] === '^') {
            pos++; skipWS();
            const exp = pos < src.length && src[pos] === '(' ? parseParen() : parseAtom();
            const pw = mk('power'); pw.base = inner; pw.exponent = exp;
            return [pw];
        }

        // Just a paren group
        return [mk('openParen'), ...inner, mk('closeParen')];
    }

    function parseParen() {
        pos++; const inner = parseExpr(); skipWS();
        if (pos >= src.length || src[pos] !== ')') throw new Error('Expected )');
        pos++; return inner;
    }

    function parseAtom() {
        skipWS();
        if (pos >= src.length) throw new Error('Unexpected end of expression');
        const ch = src[pos];

        // Unary minus
        if (ch === '-') {
            pos++; skipWS();
            const next = parseAtom();
            if (next.length === 1 && (next[0].type === 'number' || next[0].type === 'decimal')) {
                next[0].value = '-' + next[0].value; return next;
            }
            return [mk('subtract'), ...next];
        }

        // sqrt / √
        if (src.startsWith('sqrt', pos)) { pos += 4; return parseSqrtBody(); }
        if (ch === '√') { pos++; return parseSqrtBody(); }

        // frac(a,b)
        if (src.startsWith('frac', pos) && !isAlNum(src[pos + 4])) {
            pos += 4; skipWS();
            if (src[pos] !== '(') throw new Error("Expected '(' after frac");
            pos++;
            const num = parseExpr(); skipWS();
            if (src[pos] !== ',') throw new Error("Expected ',' in frac(a,b)");
            pos++;
            const den = parseExpr(); skipWS();
            if (src[pos] !== ')') throw new Error("Expected ')' to close frac");
            pos++;
            const f = mk('fraction'); f.numerator = num; f.denominator = den;
            return [f];
        }

        // pow(a,b)
        if (src.startsWith('pow', pos) && !isAlNum(src[pos + 3])) {
            pos += 3; skipWS();
            if (src[pos] !== '(') throw new Error("Expected '(' after pow");
            pos++;
            const base = parseExpr(); skipWS();
            if (src[pos] !== ',') throw new Error("Expected ',' in pow(a,b)");
            pos++;
            const exp = parseExpr(); skipWS();
            if (src[pos] !== ')') throw new Error("Expected ')' to close pow");
            pos++;
            const pw = mk('power'); pw.base = base; pw.exponent = exp;
            return [pw];
        }

        // pi / π
        if (src.startsWith('pi', pos) && !isAlNum(src[pos + 2])) { pos += 2; return checkPower([mk('pi')]); }
        if (ch === 'π') { pos++; return checkPower([mk('pi')]); }

        // Number / decimal
        if ((ch >= '0' && ch <= '9') || (ch === '.' && pos + 1 < src.length && src[pos + 1] >= '0' && src[pos + 1] <= '9')) {
            return parseNumber();
        }

        // Variable — must start with a letter (NOT '_'), aligning with MathLibrary v1.0.0-stable
        // RegexCache::isValidIdentifier which rejects any identifier that does not begin with [a-zA-Z].
        // An underscore at atom-start position is therefore treated as unexpected, throwing a clear error.
        if ((ch >= 'a' && ch <= 'z') || (ch >= 'A' && ch <= 'Z')) return parseVariable();

        // Paren
        if (ch === '(') return parseParenCompound();

        if (ch === ')') throw new Error(`Unexpected ')' at position ${pos + 1}`);
        throw new Error(`Unexpected '${ch}' at position ${pos + 1}`);
    }

    function parseSqrtBody() {
        skipWS();
        if (pos >= src.length || src[pos] !== '(') throw new Error("Expected '(' after sqrt");
        pos++;
        const content = parseExpr(); skipWS();
        if (pos >= src.length || src[pos] !== ')') throw new Error("Expected ')' after sqrt content");
        pos++;
        const r = mk('radical'); r.content = content;
        return checkPower([r]);
    }

    function parseNumber() {
        let num = ''; let dot = false;
        while (pos < src.length) {
            const c = src[pos];
            if (c >= '0' && c <= '9') { num += c; pos++; }
            else if (c === '.' && !dot) { dot = true; num += c; pos++; }
            else break;
        }
        const t = mk(dot ? 'decimal' : 'number'); t.value = num;
        return checkPower([t]);
    }

    function parseVariable() {
        let name = '';
        while (pos < src.length && isAlNum(src[pos])) name += src[pos++];
        const t = mk('variable'); t.value = name;
        return checkPower([t]);
    }

    function checkPower(base) {
        skipWS();
        if (pos < src.length && src[pos] === '^') {
            pos++; skipWS();
            const exp = pos < src.length && src[pos] === '(' ? parseParen() : parseAtom();
            const pw = mk('power'); pw.base = base; pw.exponent = exp;
            return [pw];
        }
        return base;
    }

    function isAlNum(c) { return c && ((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c === '_'); }
    function skipWS() { while (pos < src.length && ' \t\n\r'.includes(src[pos])) pos++; }
    function mk(type) { return State.createToken(type); }

    return { parse };
})();

/* ═══════════════════════════════════════════════════════════════
   Validator
   ═══════════════════════════════════════════════════════════════ */
const Validator = {
    validate(tokens) {
        const issues = [];
        if (!tokens || !tokens.length) return issues;
        const eqCount = State.countEquals();
        if (eqCount > 1) issues.push({ level: 'error', message: I18n.t('validation.multiEquals') });
        if (eqCount === 0 && tokens.length > 0) issues.push({ level: 'info', message: I18n.t('validation.exprMode') });
        this._list(tokens, issues, null);
        return issues;
    },

    _list(tokens, issues, ctx) {
        if (!tokens || !tokens.length) return;
        const ops = new Set(['add', 'subtract', 'multiply', 'divide']);
        const allOps = new Set(['add', 'subtract', 'multiply', 'divide', 'equals']);
        const vals = new Set(['number', 'decimal', 'variable', 'pi', 'fraction', 'power', 'radical', 'openParen', 'closeParen']);
        const pfx = ctx ? `[${I18n.t(ctx)}] ` : '';

        const first = tokens[0], last = tokens[tokens.length - 1];
        // Catch operator/equals at start or end
        if (allOps.has(first.type)) issues.push({ level: 'error', message: pfx + I18n.t('validation.startsWithOp'), tokenId: first.id });
        if (allOps.has(last.type)) issues.push({ level: 'error', message: pfx + I18n.t('validation.endsWithOp'), tokenId: last.id });

        // Catch equals inside sub-structures (ctx !== null means we are inside compound)
        if (ctx !== null) {
            tokens.forEach(t => {
                if (t.type === 'equals') issues.push({ level: 'error', message: pfx + I18n.t('validation.multiEquals'), tokenId: t.id });
            });
        }

        tokens.forEach(t => {
            switch (t.type) {
                case 'number': {
                    const v = (t.value || '').trim();
                    if (!v) issues.push({ level: 'error', message: pfx + I18n.t('validation.emptyField'), tokenId: t.id });
                    else if (!/^-?\d+$/.test(v)) issues.push({ level: 'warning', message: pfx + I18n.t('validation.invalidNumber'), tokenId: t.id });
                    break;
                }
                case 'decimal': {
                    const v = (t.value || '').trim();
                    if (!v) issues.push({ level: 'error', message: pfx + I18n.t('validation.emptyField'), tokenId: t.id });
                    else if (!/^-?\d*\.?\d+$/.test(v) || (v.match(/\./g) || []).length > 1) issues.push({ level: 'warning', message: pfx + I18n.t('validation.invalidDecimal'), tokenId: t.id });
                    break;
                }
                case 'variable': {
                    const v = (t.value || '').trim();
                    if (!v) issues.push({ level: 'error', message: pfx + I18n.t('validation.emptyField'), tokenId: t.id });
                    // MathLibrary v1.0.0-stable RegexCache::isValidIdentifier:
                    // must START with a letter (not _), only letters/digits/_ thereafter, max 64 chars, lone '_' rejected
                    else if (!/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(v))
                        issues.push({ level: 'warning', message: pfx + I18n.t('validation.invalidVariable'), tokenId: t.id });
                    break;
                }
                case 'fraction': {
                    if (!t.numerator || !t.numerator.length) issues.push({ level: 'error', message: I18n.t('validation.emptyNumerator'), tokenId: t.id });
                    else this._list(t.numerator, issues, 'validation.inFracNum');

                    if (!t.denominator || !t.denominator.length) issues.push({ level: 'error', message: I18n.t('validation.emptyDenominator'), tokenId: t.id });
                    else {
                        if (t.denominator.length === 1 && t.denominator[0].type === 'number' && t.denominator[0].value === '0')
                            issues.push({ level: 'error', message: I18n.t('validation.divByZero'), tokenId: t.id });
                        this._list(t.denominator, issues, 'validation.inFracDen');
                    }
                    break;
                }
                case 'power': {
                    if (!t.base || !t.base.length) issues.push({ level: 'error', message: I18n.t('validation.emptyBase'), tokenId: t.id });
                    else this._list(t.base, issues, 'validation.inBase');
                    if (!t.exponent || !t.exponent.length) issues.push({ level: 'error', message: I18n.t('validation.emptyExponent'), tokenId: t.id });
                    else {
                        if (t.base && t.base.length === 1 && t.base[0].type === 'number' && t.base[0].value === '0'
                            && t.exponent.length === 1 && t.exponent[0].type === 'number' && t.exponent[0].value === '0')
                            issues.push({ level: 'warning', message: I18n.t('validation.zeroToZero'), tokenId: t.id });
                        this._list(t.exponent, issues, 'validation.inExp');
                    }
                    break;
                }
                case 'radical': {
                    if (!t.content || !t.content.length) issues.push({ level: 'error', message: I18n.t('validation.emptyRadical'), tokenId: t.id });
                    else {
                        if (t.content.length === 1 && (t.content[0].type === 'number' || t.content[0].type === 'decimal') && parseFloat(t.content[0].value) < 0)
                            issues.push({ level: 'warning', message: I18n.t('validation.negativeRoot'), tokenId: t.id });
                        this._list(t.content, issues, 'validation.inRadical');
                    }
                    break;
                }
            }
        });

        // Sequence checks
        for (let i = 0; i < tokens.length - 1; i++) {
            const c = tokens[i], n = tokens[i + 1];
            // Consecutive arithmetic ops
            if (ops.has(c.type) && ops.has(n.type)) issues.push({ level: 'error', message: pfx + I18n.t('validation.consecutiveOps'), tokenId: n.id });
            // Op followed by equals or equals followed by op
            if (ops.has(c.type) && n.type === 'equals') issues.push({ level: 'error', message: pfx + I18n.t('validation.consecutiveOps'), tokenId: n.id });
            if (c.type === 'equals' && ops.has(n.type)) issues.push({ level: 'error', message: pfx + I18n.t('validation.consecutiveOps'), tokenId: n.id });
            const cVal = ['number', 'decimal', 'variable', 'pi', 'fraction', 'power', 'radical', 'closeParen'].includes(c.type);
            const nVal = ['number', 'decimal', 'variable', 'pi', 'fraction', 'power', 'radical', 'openParen'].includes(n.type);
            if (cVal && nVal) issues.push({ level: 'warning', message: pfx + I18n.t('validation.missingOp'), tokenId: n.id });
        }

        // Paren balance + empty parentheses check
        let depth = 0;
        for (let pi = 0; pi < tokens.length; pi++) {
            const t = tokens[pi];
            if (t.type === 'openParen') {
                depth++;
                // Check for () – empty paren pair
                if (pi + 1 < tokens.length && tokens[pi + 1].type === 'closeParen') {
                    issues.push({ level: 'warning', message: pfx + I18n.t('validation.emptyParen'), tokenId: t.id });
                }
            } else if (t.type === 'closeParen') {
                depth--;
                if (depth < 0) { issues.push({ level: 'error', message: pfx + I18n.t('validation.unmatchedClose'), tokenId: t.id }); depth = 0; }
            }
        }
        if (depth > 0) issues.push({ level: 'error', message: pfx + I18n.t('validation.unclosedParen') });
    }
};

/* ═══════════════════════════════════════════════════════════════
   Render Engine
   ═══════════════════════════════════════════════════════════════ */
const Render = {
    workspace: null,
    _cln: [], // cleanups

    init(ws) { this.workspace = ws; },

    cleanup() { this._cln.forEach(fn => { try { fn(); } catch (_) { } }); this._cln = []; },

    render(tokens) {
        this.cleanup();
        // Clear stale selected-element reference after re-render
        State._selectedEl = null;
        const hint = this.workspace.querySelector('.workspace-hint');
        this.workspace.innerHTML = '';
        if (hint) this.workspace.appendChild(hint);

        if (!tokens || !tokens.length) { hint?.classList.remove('hidden'); return; }
        hint?.classList.add('hidden');

        tokens.forEach((t, i) => this.workspace.appendChild(this._mkToken(t, tokens, i, 0)));

        const counter = document.getElementById('tokenCount');
        if (counter) { const n = State.countTokens(); counter.textContent = n ? String(n) : ''; }
    },

    _mkToken(token, parentList, index, depth) {
        const el = document.createElement('div');
        el.className = 'token';
        el.dataset.type = token.type;
        el.dataset.id = token.id;
        el.dataset.depth = String(depth);

        const isCompound = ['fraction', 'power', 'radical'].includes(token.type);

        if (!isCompound) {
            el.draggable = true;
            this._deskDrag(el, token, parentList);
        }
        this._touchDrag(el, token, parentList);

        // Delete button
        const del = document.createElement('button');
        del.className = 'token-delete'; del.textContent = '×'; del.setAttribute('aria-label', 'Delete');
        const onDel = e => { e.stopPropagation(); e.preventDefault(); this._doDelete(token.id); };
        del.addEventListener('click', onDel);
        del.addEventListener('touchend', onDel, { passive: false });
        el.appendChild(del);

        this._renderContent(el, token, depth);

        // Click — STOP PROPAGATION so nested tokens don't double-select
        const onClick = e => { e.stopPropagation(); this._doClick(token.id, el); };
        el.addEventListener('click', onClick);

        // Right-click — STOP PROPAGATION so only innermost fires
        const onCtx = e => {
            e.preventDefault();
            e.stopPropagation();
            App.showContextMenu(e.clientX, e.clientY, token, parentList, index);
        };
        el.addEventListener('contextmenu', onCtx);

        this._cln.push(() => {
            del.removeEventListener('click', onDel);
            del.removeEventListener('touchend', onDel);
            el.removeEventListener('click', onClick);
            el.removeEventListener('contextmenu', onCtx);
        });

        return el;
    },

    _renderContent(el, token, depth) {
        switch (token.type) {
            case 'number': case 'decimal': case 'variable':
                el.appendChild(this._mkInput(token, 'value')); break;
            case 'fraction': this._renderFrac(el, token, depth); break;
            case 'power': this._renderPower(el, token, depth); break;
            case 'radical': this._renderRadical(el, token, depth); break;
            default: { const s = document.createElement('span'); s.textContent = this.getSymbol(token.type); el.appendChild(s); }
        }
    },

    _renderFrac(el, token, depth) {
        el.draggable = true; this._deskDrag(el, token, null); this._touchDrag(el, token, null);
        const numZ = this._mkZone(token.numerator, depth + 1, I18n.t('validation.inFracNum'));
        token.numerator.forEach((t, i) => numZ.appendChild(this._mkToken(t, token.numerator, i, depth + 1)));
        const line = document.createElement('div'); line.className = 'frac-line';
        const denZ = this._mkZone(token.denominator, depth + 1, I18n.t('validation.inFracDen'));
        token.denominator.forEach((t, i) => denZ.appendChild(this._mkToken(t, token.denominator, i, depth + 1)));
        el.append(numZ, line, denZ);
    },

    _renderPower(el, token, depth) {
        el.draggable = true; this._deskDrag(el, token, null); this._touchDrag(el, token, null);
        const expZ = this._mkZone(token.exponent, depth + 1, I18n.t('validation.inExp'));
        expZ.className += ' power-exp';
        token.exponent.forEach((t, i) => expZ.appendChild(this._mkToken(t, token.exponent, i, depth + 1)));
        const baseZ = this._mkZone(token.base, depth + 1, I18n.t('validation.inBase'));
        baseZ.className += ' power-base';
        token.base.forEach((t, i) => baseZ.appendChild(this._mkToken(t, token.base, i, depth + 1)));
        el.append(expZ, baseZ);
    },

    _renderRadical(el, token, depth) {
        el.draggable = true; this._deskDrag(el, token, null); this._touchDrag(el, token, null);
        const sym = document.createElement('span'); sym.className = 'radical-sym'; sym.textContent = '√';
        const bar = document.createElement('div'); bar.className = 'radical-bar';
        const zone = this._mkZone(token.content, depth + 1, I18n.t('validation.inRadical'));
        token.content.forEach((t, i) => zone.appendChild(this._mkToken(t, token.content, i, depth + 1)));
        bar.appendChild(zone); el.append(sym, bar);
    },

    _mkZone(list, depth, label) {
        const z = document.createElement('div');
        z.className = 'drop-zone'; z.dataset.depth = String(depth); z._list = list;
        const h = document.createElement('div'); h.className = 'drop-zone-hint'; h.textContent = label;
        z.appendChild(h);
        this._zoneDrop(z, list);
        return z;
    },

    _mkInput(token, field) {
        const inp = document.createElement('input');
        inp.type = 'text'; inp.className = 'token-input'; inp.value = token[field] || '';
        inp.setAttribute('autocomplete', 'off'); inp.setAttribute('autocorrect', 'off');
        inp.setAttribute('spellcheck', 'false'); inp.setAttribute('autocapitalize', 'off');
        inp.setAttribute('aria-label', token.type === 'variable' ? 'variable name' : 'number value');

        // Type-aware key filter: block invalid characters before they land
        const isNum = token.type === 'number' || token.type === 'decimal';
        const onKeydown = e => {
            if (e.ctrlKey || e.metaKey || e.altKey) return; // allow shortcuts
            const nav = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Tab', 'Enter'];
            if (nav.includes(e.key)) return;
            if (isNum) {
                // allow digits, minus (only at pos 0), dot (only once for decimal)
                const allowed = /^[\d\-.]$/.test(e.key);
                if (!allowed) { e.preventDefault(); return; }
                if (e.key === '-' && inp.selectionStart !== 0) { e.preventDefault(); return; }
                if (e.key === '.' && inp.value.includes('.')) { e.preventDefault(); return; }
            } else if (token.type === 'variable') {
                // MathLibrary v1.0.0-stable: variable names must START with a letter
                const isFirst = inp.selectionStart === 0 && inp.selectionEnd === inp.value.length ||
                    (inp.value.length === 0);
                if (!/^[a-zA-Z0-9_]$/.test(e.key)) { e.preventDefault(); return; }
                if (isFirst && /^[0-9_]$/.test(e.key)) { e.preventDefault(); return; }
            }
        };

        const resize = () => { inp.style.width = Math.min(Math.max(2, (inp.value || '').length), 16) + 'ch'; };
        resize();

        const sanitizeFn = isNum ? Utils.sanitizeNumber : Utils.sanitizeVariable;
        const update = Utils.debounce(() => {
            token[field] = sanitizeFn(inp.value);
            // sync displayed value if it changed after sanitize
            if (inp.value !== token[field]) { inp.value = token[field]; }
            resize(); App.onUpdate();
        }, CONFIG.DEBOUNCE);

        const onInput = () => { resize(); update(); };
        const onMousedown = e => e.stopPropagation();
        const onTouchstart = e => e.stopPropagation();
        const onDragstart = e => e.preventDefault();

        inp.addEventListener('keydown', onKeydown);
        inp.addEventListener('input', onInput);
        inp.addEventListener('mousedown', onMousedown);
        inp.addEventListener('touchstart', onTouchstart, { passive: true });
        inp.addEventListener('dragstart', onDragstart);

        this._cln.push(() => {
            inp.removeEventListener('keydown', onKeydown);
            inp.removeEventListener('input', onInput);
            inp.removeEventListener('mousedown', onMousedown);
            inp.removeEventListener('touchstart', onTouchstart);
            inp.removeEventListener('dragstart', onDragstart);
        });
        return inp;
    },

    _deskDrag(el, token, parentList) {
        const onStart = e => {
            e.stopPropagation();
            if (Drag.start({ type: 'move', token, source: parentList, text: this.getSymbol(token.type) })) {
                el.classList.add('dragging');
                try { const c = document.createElement('canvas'); c.width = 1; c.height = 1; e.dataTransfer.setDragImage(c, 0, 0); } catch (_) { }
            }
        };
        const onEnd = () => { el.classList.remove('dragging'); Drag.cancel(); };
        el.addEventListener('dragstart', onStart);
        el.addEventListener('dragend', onEnd);
        this._cln.push(() => { el.removeEventListener('dragstart', onStart); el.removeEventListener('dragend', onEnd); });
    },

    _touchDrag(el, token, parentList) {
        let timer = null, sX = 0, sY = 0, started = false;

        const onStart = e => {
            if (e.touches.length !== 1) return;
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;
            sX = e.touches[0].clientX; sY = e.touches[0].clientY; started = false;
            timer = setTimeout(() => {
                timer = null; started = true;
                TouchDrag.start(e.touches[0], { type: 'move', token, source: parentList, text: this.getSymbol(token.type) });
                el.classList.add('dragging');
            }, CONFIG.LONG_PRESS);
        };

        const onMove = e => {
            if (!timer && !started) return;
            const dx = Math.abs(e.touches[0].clientX - sX), dy = Math.abs(e.touches[0].clientY - sY);
            if (timer && (dx > 6 || dy > 6)) {
                clearTimeout(timer); timer = null; started = true;
                TouchDrag.start(e.touches[0], { type: 'move', token, source: parentList, text: this.getSymbol(token.type) });
                el.classList.add('dragging');
            }
        };

        const onEnd = () => { clearTimeout(timer); timer = null; if (started) { el.classList.remove('dragging'); started = false; } };

        el.addEventListener('touchstart', onStart, { passive: true });
        el.addEventListener('touchmove', onMove, { passive: true });
        el.addEventListener('touchend', onEnd, { passive: true });
        el.addEventListener('touchcancel', onEnd, { passive: true });
        this._cln.push(() => { clearTimeout(timer); el.removeEventListener('touchstart', onStart); el.removeEventListener('touchmove', onMove); el.removeEventListener('touchend', onEnd); el.removeEventListener('touchcancel', onEnd); });
    },

    _zoneDrop(zone, list) {
        let idx = null;
        const onOver = Utils.throttle(e => {
            e.preventDefault(); e.stopPropagation();
            if (Drag.state !== 'DRAGGING') return;
            zone.classList.add('drag-over');
            idx = this.calcDropIndex(e.clientX, e.clientY, zone);
            this.showIndicator(zone, idx);
        }, CONFIG.THROTTLE);
        const onLeave = e => { if (!zone.contains(e.relatedTarget)) { zone.classList.remove('drag-over'); this.clearIndicator(zone); } };
        const onDrop = e => { e.preventDefault(); e.stopPropagation(); zone.classList.remove('drag-over'); this.clearIndicator(zone); App.handleDrop(list, idx); };
        zone.addEventListener('dragover', onOver);
        zone.addEventListener('dragleave', onLeave);
        zone.addEventListener('drop', onDrop);
        this._cln.push(() => { zone.removeEventListener('dragover', onOver); zone.removeEventListener('dragleave', onLeave); zone.removeEventListener('drop', onDrop); });
    },

    calcDropIndex(x, y, container) {
        const kids = Array.from(container.querySelectorAll(':scope > .token'));
        if (!kids.length) return 0;
        let best = kids.length, minD = Infinity;
        kids.forEach((el, i) => {
            const r = el.getBoundingClientRect();
            const d = Utils.dist(x, y, r.left + r.width / 2, r.top + r.height / 2);
            if (d < minD) { minD = d; best = x < (r.left + r.width / 2) ? i : i + 1; }
        });
        return best;
    },

    showIndicator(container, index) {
        this.clearIndicator(container);
        const ind = document.createElement('div'); ind.className = 'drop-indicator'; ind.dataset.indicator = '1';
        const kids = Array.from(container.querySelectorAll(':scope > .token'));
        container.insertBefore(ind, kids[index] || null);
    },

    clearIndicator(c) { c.querySelectorAll(':scope>[data-indicator]').forEach(e => e.remove()); },
    clearAllIndicators() { document.querySelectorAll('[data-indicator]').forEach(e => e.remove()); },

    getSymbol(type) {
        return {
            add: '+', subtract: '−', multiply: '×', divide: '÷', equals: '=', openParen: '(', closeParen: ')',
            pi: 'π', fraction: '⁄', radical: '√', power: '^', number: '#', decimal: '#.#', variable: 'x'
        }[type] || type;
    },

    _doDelete(id) { State.save(); State.removeToken(id); App.render(); },

    _doClick(id, el) {
        // Deselect any previously selected token without a full DOM sweep
        if (State._selectedEl && State._selectedEl !== el) {
            State._selectedEl.classList.remove('selected');
        }
        const same = State.selectedId === id;
        State.selectedId = same ? null : id;
        State._selectedEl = same ? null : el;
        el.classList.toggle('selected', !same);
    }
};

/* ═══════════════════════════════════════════════════════════════
   App
   ═══════════════════════════════════════════════════════════════ */
const App = {
    init() {
        State.init(); Drag.init(); TouchDrag.init(); Results.init();
        this._setupDetailsPanel();
        Render.init(document.getElementById('workspace'));
        const lang = this._lsGet('mathlab_lang') || 'fa';
        I18n.setLang(lang);
        this._setupSidebar();
        this._setupWorkspace();
        this._setupControls();
        this._setupKeyboard();
        this._setupPan();
        this._setupValidationPanel();
        this._setupVariablePanel();
        this._setupImportPanel();
        this._setupFileDrop();
        // Auto-collapse sidebar on small screens
        if (window.innerWidth <= 600) {
            document.getElementById('sidebar')?.classList.add('collapsed');
        }
        this.render();
    },

    _lsGet(k) { try { return localStorage.getItem(k); } catch (_) { return null; } },
    _lsSet(k, v) { try { localStorage.setItem(k, v); } catch (_) { } },

    /* ── Sidebar ─────────────────────────────────────────────── */
    _setupSidebar() {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarToggle');
        const backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);

        toggle?.addEventListener('click', () => {
            const c = sidebar.classList.toggle('collapsed');
            if (window.innerWidth <= 480) backdrop.classList.toggle('visible', !c);
        });
        backdrop.addEventListener('click', () => { sidebar.classList.add('collapsed'); backdrop.classList.remove('visible'); });

        document.querySelectorAll('.group-header').forEach(btn => {
            btn.addEventListener('click', () => btn.setAttribute('aria-expanded', btn.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'));
        });

        document.querySelectorAll('.element').forEach(card => {
            const type = card.dataset.type;

            // Keyboard: enter/space = add
            card.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this._addToken(type); } });

            // Desktop drag
            card.draggable = true;
            card.addEventListener('dragstart', e => {
                if (type === 'equals' && State.countEquals() >= 1) { e.preventDefault(); this.showStatus(I18n.t('validation.multiEquals'), 'error'); return; }
                const token = State.createToken(type);
                Drag.start({ type: 'new', token, text: Render.getSymbol(type) });
            });
            card.addEventListener('dragend', () => Drag.cancel());

            // Touch drag / tap
            let tActive = false, tMoved = false, tX = 0, tY = 0;
            card.addEventListener('touchstart', e => {
                if (e.touches.length !== 1) return;
                tX = e.touches[0].clientX; tY = e.touches[0].clientY; tActive = true; tMoved = false;
                card.classList.add('touch-active');
            }, { passive: true });

            card.addEventListener('touchmove', e => {
                if (!tActive) return;
                const dx = Math.abs(e.touches[0].clientX - tX), dy = Math.abs(e.touches[0].clientY - tY);
                if (!tMoved && (dx > 5 || dy > 5)) {
                    tMoved = true;
                    e.preventDefault();
                    if (type === 'equals' && State.countEquals() >= 1) { this.showStatus(I18n.t('validation.multiEquals'), 'error'); return; }
                    const token = State.createToken(type);
                    TouchDrag.start(e.touches[0], { type: 'new', token, text: Render.getSymbol(type) });
                }
            }, { passive: false });

            card.addEventListener('touchend', () => {
                tActive = false; card.classList.remove('touch-active');
                if (!tMoved && !TouchDrag.active) this._addToken(type);
            }, { passive: true });

            card.addEventListener('touchcancel', () => { tActive = false; tMoved = false; card.classList.remove('touch-active'); }, { passive: true });
        });
    },

    _addToken(type) {
        if (type === 'equals' && State.countEquals() >= 1) { this.showStatus(I18n.t('validation.multiEquals'), 'error'); return; }
        State.save(); State.tokens.push(State.createToken(type)); this.render();
    },

    /* ── Variable Panel — persistent, draggable ─────────────── */
    _setupVariablePanel() {
        const panel = document.getElementById('varPanel');
        const header = document.getElementById('varPanelHeader');
        const toggleBtn = document.getElementById('varPanelToggle');
        if (!panel || !header) return;

        toggleBtn?.addEventListener('click', e => {
            e.stopPropagation();
            const col = panel.classList.toggle('collapsed');
            if (toggleBtn) toggleBtn.textContent = col ? '▸' : '▾';
        });

        // Draggable
        let drag = false, sx = 0, sy = 0, px = 0, py = 0;
        const start = (x, y) => {
            drag = true; sx = x; sy = y;
            const r = panel.getBoundingClientRect();
            px = r.left; py = r.top;
            // Explicitly clear right/bottom so left+top have full control
            panel.style.cssText = `position:fixed;left:${px}px;top:${py}px;right:auto;bottom:auto;transform:none;width:${panel.offsetWidth}px;max-height:calc(100vh - 2rem)`;
        };
        const move = (x, y) => {
            if (!drag) return;
            const vw = window.innerWidth, vh = window.innerHeight, pw = panel.offsetWidth, ph = panel.offsetHeight;
            panel.style.left = Math.min(Math.max(0, px + (x - sx)), vw - pw) + 'px';
            panel.style.top = Math.min(Math.max(0, py + (y - sy)), vh - ph) + 'px';
        };
        const stop = () => { drag = false; };
        header.addEventListener('mousedown', e => {
            if (e.target === toggleBtn || e.target.tagName === 'INPUT') return;
            e.preventDefault(); start(e.clientX, e.clientY);
        });
        document.addEventListener('mousemove', e => move(e.clientX, e.clientY));
        document.addEventListener('mouseup', stop);
        header.addEventListener('touchstart', e => {
            if (e.target === toggleBtn || e.target.tagName === 'INPUT') return;
            start(e.touches[0].clientX, e.touches[0].clientY);
        }, { passive: true });
        document.addEventListener('touchmove', e => { if (drag) { e.preventDefault(); move(e.touches[0].clientX, e.touches[0].clientY); } }, { passive: false });
        document.addEventListener('touchend', stop, { passive: true });
    },

    _collectVarNames(tokens) {
        const names = new Set();
        const walk = list => {
            if (!list) return;
            list.forEach(t => {
                if (t.type === 'variable' && t.value && /^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(t.value)) names.add(t.value);
                if (t.type === 'fraction') { walk(t.numerator); walk(t.denominator); }
                if (t.type === 'power') { walk(t.base); walk(t.exponent); }
                if (t.type === 'radical') { walk(t.content); }
            });
        };
        walk(tokens);
        return Array.from(names);
    },

    _updateVariablePanel() {
        const panel = document.getElementById('varPanel');
        const body = document.getElementById('varPanelBody');
        const cntEl = document.getElementById('varPanelCount');
        if (!panel || !body) return;

        const varNames = this._collectVarNames(State.tokens);

        // Prune stale varValues (removed variables)
        Object.keys(State.varValues).forEach(k => {
            if (!varNames.includes(k)) delete State.varValues[k];
        });

        if (!varNames.length) { panel.classList.add('hidden'); return; }
        panel.classList.remove('hidden');
        if (cntEl) cntEl.textContent = String(varNames.length);

        // Keep track of existing rows by variable name
        const existingRows = {};
        body.querySelectorAll('.var-panel-row[data-var-name]').forEach(r => {
            existingRows[r.dataset.varName] = r;
        });

        // Remove rows no longer needed
        Object.keys(existingRows).forEach(n => {
            if (!varNames.includes(n)) existingRows[n].remove();
        });

        // Add/update rows
        varNames.forEach(name => {
            let row = existingRows[name];
            if (!row) {
                row = this._buildVarRow(name);
                body.appendChild(row);
            } else {
                // Update value if input not focused
                const inp = row.querySelector('input');
                const status = row.querySelector('.var-panel-status');
                if (inp && document.activeElement !== inp) {
                    inp.value = State.varValues[name] != null ? String(State.varValues[name]) : '';
                }
                if (status) {
                    const hasV = State.varValues[name] != null;
                    status.textContent = hasV ? '✓' : I18n.t('varPanel.symHint');
                    status.className = 'var-panel-status' + (hasV ? ' ok' : '');
                }
            }
        });
    },

    _buildVarRow(name) {
        const row = document.createElement('div');
        row.className = 'var-panel-row';
        row.dataset.varName = name;

        const lbl = document.createElement('span');
        lbl.className = 'var-panel-name';
        lbl.textContent = name;

        const inp = document.createElement('input');
        inp.type = 'number'; inp.step = 'any';
        inp.className = 'var-panel-input';
        inp.dataset.var = name;
        inp.placeholder = I18n.t('varPanel.placeholder');
        inp.setAttribute('autocomplete', 'off');
        inp.setAttribute('spellcheck', 'false');
        inp.dir = 'ltr';
        if (State.varValues[name] != null) inp.value = String(State.varValues[name]);

        const status = document.createElement('span');
        status.className = 'var-panel-status' + (State.varValues[name] != null ? ' ok' : '');
        status.textContent = State.varValues[name] != null ? '✓' : I18n.t('varPanel.symHint');

        inp.addEventListener('input', Utils.debounce(() => {
            const v = inp.value.trim(), fv = parseFloat(v);
            if (v !== '' && !isNaN(fv) && isFinite(fv)) {
                State.varValues[name] = fv;
                status.textContent = '✓'; status.className = 'var-panel-status ok';
            } else {
                delete State.varValues[name];
                status.textContent = I18n.t('varPanel.symHint'); status.className = 'var-panel-status';
            }
        }, 150));
        inp.addEventListener('mousedown', e => e.stopPropagation());
        inp.addEventListener('touchstart', e => e.stopPropagation(), { passive: true });
        inp.addEventListener('keydown', e => { e.stopPropagation(); if (e.key === 'Enter') inp.blur(); });

        row.appendChild(lbl);
        row.appendChild(inp);
        row.appendChild(status);
        return row;
    },


    /* ── detail panel ───────────────────────────────────────────── */

    _setupDetailsPanel() {
        const panel = document.getElementById('detailsPanel');
        const header = document.getElementById('detailsHeader');
        const overlay = document.getElementById('detailsOverlay');
        const closeBtn = document.getElementById('detailsClose');
        if (!panel || !header || !overlay) return;

        // Close button
        closeBtn.addEventListener('click', () => this._hideDetails());

        // Click on overlay (outside panel) closes? For consistency with results, we might not. But I'll add if needed; here we only close on button.
        // Drag
        let dragging = false, sx = 0, sy = 0, px = 0, py = 0;
        const startDrag = (x, y) => {
            dragging = true;
            sx = x; sy = y;
            const r = panel.getBoundingClientRect();
            px = r.left; py = r.top;
            panel.style.cssText = `position:fixed;left:${px}px;top:${py}px;right:auto;bottom:auto;margin:0;transform:none;width:${panel.offsetWidth}px;max-height:80vh`;
            overlay.style.alignItems = 'unset';
            overlay.style.justifyContent = 'unset';
        };
        const moveDrag = (x, y) => {
            if (!dragging) return;
            const vw = window.innerWidth, vh = window.innerHeight;
            const pw = panel.offsetWidth, ph = panel.offsetHeight;
            panel.style.left = Math.min(Math.max(0, px + (x - sx)), vw - pw) + 'px';
            panel.style.top = Math.min(Math.max(0, py + (y - sy)), vh - ph) + 'px';
        };
        const stopDrag = () => { dragging = false; };

        header.addEventListener('mousedown', e => {
            if (e.target === closeBtn) return;
            e.preventDefault();
            startDrag(e.clientX, e.clientY);
        });
        document.addEventListener('mousemove', e => { if (dragging) moveDrag(e.clientX, e.clientY); });
        document.addEventListener('mouseup', stopDrag);
        header.addEventListener('touchstart', e => {
            if (e.target === closeBtn) return;
            startDrag(e.touches[0].clientX, e.touches[0].clientY);
        }, { passive: true });
        document.addEventListener('touchmove', e => {
            if (dragging) { e.preventDefault(); moveDrag(e.touches[0].clientX, e.touches[0].clientY); }
        }, { passive: false });
        document.addEventListener('touchend', stopDrag, { passive: true });
    },

    /* ── Workspace ───────────────────────────────────────────── */
    _setupWorkspace() {
        const ws = document.getElementById('workspace'), wr = document.getElementById('workspaceWrapper');

        const onOver = Utils.throttle(e => {
            e.preventDefault(); e.stopPropagation();
            if (Drag.state !== 'DRAGGING') return;
            ws.classList.add('dragging-over'); wr.classList.add('drop-ready');
            Render.showIndicator(ws, this.calcWorkspaceDropIndex(e.clientX, e.clientY));
        }, CONFIG.THROTTLE);

        ws.addEventListener('dragover', onOver);
        ws.addEventListener('dragleave', e => { if (!wr.contains(e.relatedTarget)) { ws.classList.remove('dragging-over'); wr.classList.remove('drop-ready'); Render.clearIndicator(ws); } });
        ws.addEventListener('drop', e => {
            e.preventDefault(); e.stopPropagation();
            ws.classList.remove('dragging-over'); wr.classList.remove('drop-ready'); Render.clearIndicator(ws);
            this.handleDrop(State.tokens, this.calcWorkspaceDropIndex(e.clientX, e.clientY), e.clientX, e.clientY);
        });

        wr.addEventListener('dragover', e => { e.preventDefault(); if (Drag.state === 'DRAGGING') wr.classList.add('drop-ready'); });
        wr.addEventListener('dragleave', e => { if (!wr.contains(e.relatedTarget)) wr.classList.remove('drop-ready'); });
        wr.addEventListener('drop', e => {
            e.preventDefault(); wr.classList.remove('drop-ready'); ws.classList.remove('dragging-over'); Render.clearIndicator(ws);
            this.handleDrop(State.tokens, this.calcWorkspaceDropIndex(e.clientX, e.clientY), e.clientX, e.clientY);
        });

        ws.addEventListener('click', e => {
            if (e.target === ws || e.target.classList.contains('workspace-hint') || e.target.classList.contains('hint-text') || e.target.classList.contains('hint-icon')) {
                if (State._selectedEl) State._selectedEl.classList.remove('selected');
                State.selectedId = null; State._selectedEl = null;
            }
        });

        document.addEventListener('dragover', e => Drag.updateGhost(e.clientX, e.clientY));
    },

    calcWorkspaceDropIndex(x, y) {
        const kids = Array.from(document.getElementById('workspace').querySelectorAll(':scope>.token'));
        if (!kids.length) return 0;
        let best = kids.length, minD = Infinity;
        kids.forEach((el, i) => {
            const r = el.getBoundingClientRect();
            const d = Utils.dist(x, y, r.left + r.width / 2, r.top + r.height / 2);
            if (d < minD) { minD = d; best = x < (r.left + r.width / 2) ? i : i + 1; }
        });
        return best; // no SNAP_DIST clamp – always insert at nearest position
    },

    /* ── Controls ────────────────────────────────────────────── */
    _setupControls() {
        document.getElementById('langBtn')?.addEventListener('click', () => {
            const nl = I18n.lang === 'fa' ? 'en' : 'fa'; I18n.setLang(nl); this._lsSet('mathlab_lang', nl);
        });

        document.getElementById('undoBtn')?.addEventListener('click', () => { if (State.undo()) this.render(); });
        document.getElementById('redoBtn')?.addEventListener('click', () => { if (State.redo()) this.render(); });

        document.getElementById('clearBtn')?.addEventListener('click', () => {
            if (!State.tokens.length) return;
            if (confirm(I18n.t('clear.confirm'))) { State.save(); State.tokens = []; this.render(); }
        });

        document.getElementById('zoomIn')?.addEventListener('click', () => {
            State.zoom = Math.min(CONFIG.ZOOM_MAX, parseFloat((State.zoom + CONFIG.ZOOM_STEP).toFixed(2))); this._updateZoom();
        });
        document.getElementById('zoomOut')?.addEventListener('click', () => {
            State.zoom = Math.max(CONFIG.ZOOM_MIN, parseFloat((State.zoom - CONFIG.ZOOM_STEP).toFixed(2))); this._updateZoom();
        });
        document.getElementById('zoomReset')?.addEventListener('click', () => {
            State.zoom = 1; State.panX = 0; State.panY = 0; this._updateZoom();
        });

        document.getElementById('workspaceWrapper')?.addEventListener('wheel', e => {
            e.preventDefault();
            const d = e.deltaY > 0 ? -CONFIG.ZOOM_STEP : CONFIG.ZOOM_STEP;
            State.zoom = Math.min(CONFIG.ZOOM_MAX, Math.max(CONFIG.ZOOM_MIN, parseFloat((State.zoom + d).toFixed(2))));
            this._updateZoom();
        }, { passive: false });

        document.getElementById('copyBtn')?.addEventListener('click', async () => {
            const eq = this._buildEq(); if (!eq || eq === '—') return;
            let ok = false;
            if (navigator.clipboard && window.isSecureContext) {
                try { await navigator.clipboard.writeText(eq); ok = true; } catch (_) { }
            }
            if (!ok) {
                // Legacy fallback
                const ta = document.createElement('textarea');
                ta.value = eq; ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
                document.body.appendChild(ta); ta.select();
                try { ok = document.execCommand('copy'); } catch (_) { }
                ta.remove();
            }
            this._toast(I18n.t(ok ? 'preview.copied' : 'status.error'), ok ? 'success' : 'error');
        });

        document.getElementById('calculateBtn')?.addEventListener('click', () => this._calculate());

        const menu = document.getElementById('contextMenu');
        menu?.addEventListener('click', e => {
            const btn = e.target.closest('button'); if (!btn) return;
            this._ctxAction(btn.dataset.action, menu); menu.classList.remove('visible');
        });

        document.addEventListener('click', e => {
            menu?.classList.remove('visible');
            // Click outside all tokens deselects
            if (!e.target.closest('.token')) {
                if (State._selectedEl) State._selectedEl.classList.remove('selected');
                State.selectedId = null; State._selectedEl = null;
            }
        });
        document.addEventListener('touchstart', () => menu?.classList.remove('visible'), { passive: true });
    },

    /* ── Keyboard ────────────────────────────────────────────── */
    _setupKeyboard() {
        document.addEventListener('keydown', e => {
            const mod = e.ctrlKey || e.metaKey;
            if (mod && e.key === 'z' && !e.shiftKey) { e.preventDefault(); if (State.undo()) this.render(); return; }
            if (mod && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); if (State.redo()) this.render(); return; }
            if ((e.key === 'Delete' || e.key === 'Backspace') && State.selectedId && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault(); State.save(); State.removeToken(State.selectedId); State.selectedId = null; State._selectedEl = null; this.render(); return;
            }
            if (e.key === 'Escape') {
                document.querySelectorAll('.context-menu.visible').forEach(el => el.classList.remove('visible'));
                if (State._selectedEl) State._selectedEl.classList.remove('selected');
                State.selectedId = null; State._selectedEl = null;
                document.getElementById('importPanel')?.classList.add('hidden');
            }
            if (mod && e.key === 'Enter') { e.preventDefault(); this._calculate(); }
        });
    },

    /* ── Pan + Pinch Zoom ───────────────────────────────────── */
    _setupPan() {
        const wr = document.getElementById('workspaceWrapper'), ws = document.getElementById('workspace');
        let panning = false, sx = 0, sy = 0, lpx = State.panX, lpy = State.panY;
        let pinchDist = null, pinchMidX = 0, pinchMidY = 0;
        const isBg = t => t === wr || t === ws || t?.classList.contains('workspace-hint') || t?.classList.contains('hint-text') || t?.classList.contains('hint-icon');

        wr.addEventListener('mousedown', e => { if (e.button !== 0 || !isBg(e.target) || Drag.state === 'DRAGGING') return; panning = true; sx = e.clientX; sy = e.clientY; wr.style.cursor = 'grabbing'; e.preventDefault(); });
        document.addEventListener('mousemove', e => { if (!panning) return; State.panX = lpx + (e.clientX - sx); State.panY = lpy + (e.clientY - sy); this._updateZoom(); });
        document.addEventListener('mouseup', () => { if (!panning) return; panning = false; lpx = State.panX; lpy = State.panY; wr.style.cursor = ''; });

        // Touch – single finger pan (guarded against drag)
        wr.addEventListener('touchstart', e => {
            if (e.touches.length === 2) {
                // Pinch zoom start
                panning = false;
                const t0 = e.touches[0], t1 = e.touches[1];
                pinchDist = Math.hypot(t1.clientX - t0.clientX, t1.clientY - t0.clientY);
                pinchMidX = (t0.clientX + t1.clientX) / 2;
                pinchMidY = (t0.clientY + t1.clientY) / 2;
                return;
            }
            pinchDist = null;
            if (TouchDrag.active || e.touches.length !== 1 || !isBg(e.target)) return;
            panning = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchmove', e => {
            // Pinch
            if (e.touches.length === 2 && pinchDist !== null && wr.contains(e.target)) {
                e.preventDefault();
                const t0 = e.touches[0], t1 = e.touches[1];
                const newDist = Math.hypot(t1.clientX - t0.clientX, t1.clientY - t0.clientY);
                const ratio = newDist / pinchDist;
                State.zoom = Math.min(CONFIG.ZOOM_MAX, Math.max(CONFIG.ZOOM_MIN, parseFloat((State.zoom * ratio).toFixed(2))));
                pinchDist = newDist;
                this._updateZoom();
                return;
            }
            if (!panning || TouchDrag.active) return;
            e.preventDefault();
            State.panX = lpx + (e.touches[0].clientX - sx);
            State.panY = lpy + (e.touches[0].clientY - sy);
            this._updateZoom();
        }, { passive: false });

        document.addEventListener('touchend', e => {
            if (e.touches.length < 2) pinchDist = null;
            if (!panning) return;
            panning = false; lpx = State.panX; lpy = State.panY;
        }, { passive: true });
    },

    _updateZoom() {
        const ws = document.getElementById('workspace'), val = document.getElementById('zoomValue');
        if (ws) ws.style.transform = `translate(${State.panX}px,${State.panY}px) scale(${State.zoom})`;
        if (val) val.textContent = `${Math.round(State.zoom * 100)}%`;
    },

    /* ── Validation Panel ────────────────────────────────────── */
    _setupValidationPanel() {
        const panel = document.getElementById('validationPanel');
        const header = document.getElementById('panelHeader');
        const closeBtn = document.getElementById('panelClose');
        if (!panel || !header) return;

        let drag = false, sx = 0, sy = 0, px = 0, py = 0;
        const start = (x, y) => { drag = true; sx = x; sy = y; const r = panel.getBoundingClientRect(); px = r.left; py = r.top; panel.style.cssText += `;transform:none;left:${px}px;top:${py}px;bottom:auto`; };
        const move = (x, y) => { if (!drag) return; const vw = window.innerWidth, vh = window.innerHeight, pw = panel.offsetWidth, ph = panel.offsetHeight; panel.style.left = Math.min(Math.max(0, px + (x - sx)), vw - pw) + 'px'; panel.style.top = Math.min(Math.max(0, py + (y - sy)), vh - ph) + 'px'; };
        const stop = () => { drag = false; };

        header.addEventListener('mousedown', e => { if (e.target === closeBtn) return; e.preventDefault(); start(e.clientX, e.clientY); });
        document.addEventListener('mousemove', e => move(e.clientX, e.clientY));
        document.addEventListener('mouseup', stop);
        header.addEventListener('touchstart', e => { if (e.target === closeBtn) return; start(e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });
        document.addEventListener('touchmove', e => { if (drag) { e.preventDefault(); move(e.touches[0].clientX, e.touches[0].clientY); } }, { passive: false });
        document.addEventListener('touchend', stop, { passive: true });
        closeBtn?.addEventListener('click', () => panel.classList.add('hidden'));
    },

    /* ── Import Panel (text → visual) ───────────────────────── */
    _setupImportPanel() {
        const panel = document.getElementById('importPanel');
        const input = document.getElementById('importInput');
        const feedback = document.getElementById('importFeedback');
        const confirm = document.getElementById('importConfirm');
        const cancel = document.getElementById('importCancel');
        const closeBtn = document.getElementById('importClose');
        const parseBtn = document.getElementById('parseBtn');

        if (!panel || !input) return;

        let lastResult = null;

        const setFeedback = (msg, type) => {
            feedback.textContent = msg;
            feedback.className = 'import-feedback ' + type;
            input.className = 'import-input has-' + (type === 'success' ? 'ok' : 'error');
        };

        const clearFeedback = () => {
            feedback.className = 'import-feedback hidden';
            input.className = 'import-input';
            confirm.className = 'import-confirm disabled';
            lastResult = null;
        };

        const validate = Utils.debounce(() => {
            const v = input.value.trim();
            if (!v) { clearFeedback(); return; }
            const res = MathParser.parse(v);
            lastResult = res;
            if (res.ok) {
                // Also run our structural validator
                const issues = Validator.validate(res.tokens);
                const errors = issues.filter(i => i.level === 'error');
                if (errors.length) {
                    setFeedback(I18n.t('import.invalid') + ': ' + Utils.escapeHtml(errors[0].message), 'error');
                    confirm.className = 'import-confirm disabled'; lastResult = null;
                } else {
                    const warns = issues.filter(i => i.level === 'warning');
                    const preview = '✓ ' + this._buildEqFromTokens(res.tokens) + (warns.length ? ` ⚠ ${warns[0].message}` : '');
                    setFeedback(preview, 'success');
                    confirm.className = 'import-confirm';
                }
            } else {
                setFeedback(I18n.t('import.invalid') + ': ' + Utils.escapeHtml(res.error), 'error');
                confirm.className = 'import-confirm disabled'; lastResult = null;
            }
        }, 300);

        const doImport = () => {
            if (!lastResult || !lastResult.ok) return;
            State.save();
            State.tokens = lastResult.tokens;
            this.render();
            panel.classList.add('hidden');
            clearFeedback(); input.value = '';
            this._toast(I18n.t('import.replaced'), 'success');
        };

        input.addEventListener('input', validate);
        input.addEventListener('keydown', e => { if (e.key === 'Enter') doImport(); if (e.key === 'Escape') panel.classList.add('hidden'); });
        confirm.addEventListener('click', doImport);
        cancel.addEventListener('click', () => panel.classList.add('hidden'));
        closeBtn.addEventListener('click', () => panel.classList.add('hidden'));

        parseBtn.addEventListener('click', () => {
            const wasHidden = panel.classList.contains('hidden');
            panel.classList.toggle('hidden');
            if (wasHidden) {
                // Reset to default position (remove any drag offsets)
                panel.style.cssText = '';
                input.value = ''; clearFeedback();
                setTimeout(() => input.focus(), 50);
            }
        });

        /* ── Make Import Panel draggable ─────────────────────────── */
        const importHeader = panel.querySelector('.import-header');
        if (importHeader) {
            importHeader.style.cursor = 'move';
            let drag = false, dragStarted = false;
            let sx2 = 0, sy2 = 0, px2 = 0, py2 = 0;

            const capturePanelPos = () => {
                const r = panel.getBoundingClientRect();
                px2 = r.left; py2 = r.top;
                // Freeze panel at its current visual position before any drag moves it
                panel.style.cssText = `position:fixed;left:${px2}px;top:${py2}px;bottom:auto;transform:none;width:${panel.offsetWidth}px;max-width:94vw;z-index:300`;
            };

            const startDrag = (x, y) => {
                drag = true; dragStarted = false; sx2 = x; sy2 = y;
                // Capture position lazily (only if real drag starts)
            };

            const moveDrag = (x, y) => {
                if (!drag) return;
                const dx = Math.abs(x - sx2), dy = Math.abs(y - sy2);
                if (!dragStarted) {
                    if (dx < 4 && dy < 4) return; // ignore tiny movements (click vs drag)
                    dragStarted = true;
                    capturePanelPos(); // now freeze & anchor the panel
                }
                const vw = window.innerWidth, vh = window.innerHeight;
                const pw = panel.offsetWidth, ph = panel.offsetHeight;
                panel.style.left = Math.min(Math.max(0, px2 + (x - sx2)), vw - pw) + 'px';
                panel.style.top = Math.min(Math.max(0, py2 + (y - sy2)), vh - ph) + 'px';
            };

            const stopDrag = () => { drag = false; dragStarted = false; };

            importHeader.addEventListener('mousedown', e => {
                if (e.target.tagName === 'BUTTON' || e.target.tagName === 'INPUT') return;
                e.preventDefault(); startDrag(e.clientX, e.clientY);
            });
            document.addEventListener('mousemove', e => { if (drag) moveDrag(e.clientX, e.clientY); });
            document.addEventListener('mouseup', stopDrag);
            importHeader.addEventListener('touchstart', e => {
                if (e.target.tagName === 'BUTTON' || e.target.tagName === 'INPUT') return;
                startDrag(e.touches[0].clientX, e.touches[0].clientY);
            }, { passive: true });
            document.addEventListener('touchmove', e => {
                if (drag) { e.preventDefault(); moveDrag(e.touches[0].clientX, e.touches[0].clientY); }
            }, { passive: false });
            document.addEventListener('touchend', stopDrag, { passive: true });
        }
    },

    /* ── File Drop ───────────────────────────────────────────── */
    _setupFileDrop() {
        const overlay = document.getElementById('fileDropOverlay');
        let dragCount = 0;

        const isFile = e => e.dataTransfer?.types?.includes('Files');
        const isTxt = f => f.name.toLowerCase().endsWith('.txt') || f.type === 'text/plain';

        document.addEventListener('dragenter', e => { if (!isFile(e)) return; dragCount++; if (dragCount === 1) overlay?.classList.remove('hidden'); });
        document.addEventListener('dragleave', e => { if (!isFile(e)) return; dragCount = Math.max(0, dragCount - 1); if (!dragCount) overlay?.classList.add('hidden'); });
        document.addEventListener('dragover', e => { if (isFile(e)) e.preventDefault(); });

        document.addEventListener('drop', e => {
            if (!isFile(e)) return;
            e.preventDefault(); dragCount = 0; overlay?.classList.add('hidden');
            const file = Array.from(e.dataTransfer.files).find(isTxt);
            if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => {
                const text = (ev.target.result || '').slice(0, 2000).trim();
                if (!text) return;
                // Try each line until one parses
                const lines = text.split('\n');
                for (const line of lines) {
                    const l = line.trim();
                    if (!l) continue;
                    const res = MathParser.parse(l);
                    if (res.ok) {
                        const issues = Validator.validate(res.tokens);
                        const errors = issues.filter(i => i.level === 'error');
                        if (!errors.length) {
                            State.save(); State.tokens = res.tokens; this.render();
                            this._toast(I18n.t('fileDrop.loaded'), 'success'); return;
                        }
                    }
                }
                // If no line fully parsed, try the whole text
                const res = MathParser.parse(text.replace(/\n/g, ' '));
                if (res.ok) { State.save(); State.tokens = res.tokens; this.render(); this._toast(I18n.t('fileDrop.loaded'), 'success'); }
                else this._toast(I18n.t('import.invalid'), 'error');
            };
            reader.readAsText(file);
        });
    },

    /* ── Drop handlers ───────────────────────────────────────── */
    handleDrop(list, index, clientX, clientY) {
        const payload = Drag.end(); if (!payload) return;
        this.performDrop(payload, list, index, clientX, clientY);
    },

    performDrop(payload, list, index) {
        if (!payload) return;
        State.save();
        if (payload.type === 'new') {
            list.splice(index != null ? index : list.length, 0, payload.token);
        } else {
            // Capture source index before removal (same-list move adjustment)
            const srcIdx = list.indexOf(payload.token);
            State.removeToken(payload.token.id);
            let ins = index != null ? index : list.length;
            if (srcIdx !== -1 && srcIdx < ins) ins--;
            list.splice(Math.max(0, Math.min(ins, list.length)), 0, payload.token);
        }
        this.render();
    },

    /* ── Context Menu ────────────────────────────────────────── */
    showContextMenu(x, y, token, list, index) {
        const menu = document.getElementById('contextMenu'); if (!menu) return;
        menu.style.left = Math.min(x, window.innerWidth - 145) + 'px';
        menu.style.top = Math.min(y, window.innerHeight - 155) + 'px';
        menu.classList.add('visible');
        menu._token = token; menu._list = list; menu._index = index;
    },

    _ctxAction(action, menu) {
        const { _token: token, _list: list, _index: index } = menu;
        if (!token || !list) return;
        State.save();
        switch (action) {
            case 'duplicate': { const c = Utils.clone(token); this._reId(c); list.splice(index + 1, 0, c); break; }
            case 'moveLeft': if (index > 0) [list[index - 1], list[index]] = [list[index], list[index - 1]]; break;
            case 'moveRight': if (index < list.length - 1) [list[index], list[index + 1]] = [list[index + 1], list[index]]; break;
            case 'delete': State.removeToken(token.id); break;
            case 'details':
                this._showDetails(token);
                break;
        }
        this.render();
    },

    _showDetails(token) {
        const parent = State.findParentToken(token.id);
        const typeNameKey = 'details.type.' + token.type;
        const descKey = 'details.desc.' + token.type;

        let html = `<dl>
      <dt>${I18n.t('details.type')}</dt>
      <dd>${I18n.t(typeNameKey)}</dd>`;

        if (['number', 'decimal', 'variable'].includes(token.type)) {
            html += `<dt>${I18n.t('details.children') /* but actually value */}</dt>
                 <dd>${Utils.escapeHtml(token.value || '?')}</dd>`;
        }
        html += `<dt>${I18n.t('details.parent')}</dt>`;
        if (parent) {
            const parentTypeKey = 'details.type.' + parent.type;
            html += `<dd>${I18n.t(parentTypeKey)}</dd>`;
        } else {
            html += `<dd>${I18n.t('details.rootToken')}</dd>`;
        }
        html += `<dt>${I18n.t('details.children')}</dt>`;
        const getChildrenSummary = (list, depth = 0) => {
            if (!list || !list.length) return I18n.t('details.empty');
            return list.map(child => {
                const childTypeKey = 'details.type.' + child.type;
                let text = I18n.t(childTypeKey);
                if (['number', 'decimal', 'variable', 'pi'].includes(child.type)) {
                    text += ` (${Utils.escapeHtml(child.value || '?')})`;
                }
                let nested = 0;
                if (child.type === 'fraction') nested = (child.numerator?.length || 0) + (child.denominator?.length || 0);
                else if (child.type === 'power') nested = (child.base?.length || 0) + (child.exponent?.length || 0);
                else if (child.type === 'radical') nested = child.content?.length || 0;
                if (nested) text += ` [${nested} child${nested > 1 ? 'ren' : ''}]`;
                return text;
            }).join(', ');
        };
        let childrenHtml = '';
        if (token.type === 'fraction') {
            childrenHtml += `<div>${I18n.t('validation.inFracNum')}: ${getChildrenSummary(token.numerator)}</div>`;
            childrenHtml += `<div>${I18n.t('validation.inFracDen')}: ${getChildrenSummary(token.denominator)}</div>`;
        } else if (token.type === 'power') {
            childrenHtml += `<div>${I18n.t('validation.inBase')}: ${getChildrenSummary(token.base)}</div>`;
            childrenHtml += `<div>${I18n.t('validation.inExp')}: ${getChildrenSummary(token.exponent)}</div>`;
        } else if (token.type === 'radical') {
            childrenHtml += `<div>${I18n.t('validation.inRadical')}: ${getChildrenSummary(token.content)}</div>`;
        } else {
            childrenHtml = I18n.t('details.none');
        }
        html += `<dd>${childrenHtml}</dd></dl>`;

        // Description
        html += `<div class="desc-text">${I18n.t(descKey)}</div>`;

        const body = document.getElementById('detailsBody');
        if (body) body.innerHTML = html;

        const overlay = document.getElementById('detailsOverlay');
        overlay?.classList.remove('hidden');
    },

    /* Close details panel (helper) */
    _hideDetails() {
        document.getElementById('detailsOverlay')?.classList.add('hidden');
    },

    _reId(t) {
        t.id = Utils.uid();
        if (t.type === 'fraction') { t.numerator.forEach(x => this._reId(x)); t.denominator.forEach(x => this._reId(x)); }
        if (t.type === 'power') { t.base.forEach(x => this._reId(x)); t.exponent.forEach(x => this._reId(x)); }
        if (t.type === 'radical') { t.content.forEach(x => this._reId(x)); }
    },

    /* ── Render / Update ─────────────────────────────────────── */
    render() {
        cancelAnimationFrame(this._raf);
        this._raf = requestAnimationFrame(() => {
            Render.render(State.tokens);
            this._updatePreview();
            this.updateValidation();
            this._updateVariablePanel();
            this._updateZoom();
        });
    },
    onUpdate() {
        cancelAnimationFrame(this._rafUpdate);
        this._rafUpdate = requestAnimationFrame(() => {
            this._updatePreview();
            this.updateValidation();
            this._updateVariablePanel();
        });
    },

    _updatePreview() {
        const pre = document.getElementById('previewContent'); if (!pre) return;
        pre.textContent = this._buildEq() || '—';
    },

    _buildEq(tokens = State.tokens) { return this._buildEqFromTokens(tokens); },

    _buildEqFromTokens(tokens) {
        return tokens.map(t => this._tok2str(t)).join(' ');
    },

    _tok2str(t) {
        const P = CONFIG.PARSER;
        switch (t.type) {
            case 'number': case 'decimal': case 'variable': return Utils.sanitize(t.value) || '0';
            case 'pi': return P.pi;
            case 'add': return P.add;
            case 'subtract': return P.subtract;
            case 'multiply': return P.multiply;
            case 'divide': return P.divide;
            case 'equals': return P.equals;
            case 'openParen': return P.openParen;
            case 'closeParen': return P.closeParen;
            case 'fraction': return `(${this._buildEqFromTokens(t.numerator)})/(${this._buildEqFromTokens(t.denominator)})`;
            case 'power': return `(${this._buildEqFromTokens(t.base)})^(${this._buildEqFromTokens(t.exponent)})`;
            case 'radical': return `sqrt(${this._buildEqFromTokens(t.content)})`;
            default: return t.type;
        }
    },

    /* ── Validation ──────────────────────────────────────────── */
    updateValidation() {
        const issues = Validator.validate(State.tokens);
        const panel = document.getElementById('validationPanel');
        const icon = document.getElementById('panelIcon');
        const body = document.getElementById('panelBody');
        if (!panel) return;

        document.querySelectorAll('.token.error,.token.warning').forEach(el => el.classList.remove('error', 'warning'));
        panel.classList.remove('hidden');

        if (!State.tokens.length) { panel.className = 'validation-panel'; if (icon) icon.textContent = '○'; if (body) body.innerHTML = ''; return; }

        issues.forEach(iss => { if (iss.tokenId) { const el = document.querySelector(`.token[data-id="${CSS.escape(iss.tokenId)}"]`); el?.classList.add(iss.level === 'info' ? '' : iss.level); } });

        if (!issues.length) {
            panel.className = 'validation-panel';
            if (icon) icon.textContent = '✓';
            if (body) body.innerHTML = `<div class="panel-item success">${Utils.escapeHtml(I18n.t('validation.valid'))}</div>`;
        } else {
            const hasErr = issues.some(i => i.level === 'error');
            const hasWarn = issues.some(i => i.level === 'warning');
            panel.className = 'validation-panel ' + (hasErr ? 'has-errors' : (hasWarn ? 'has-warnings' : ''));
            if (icon) icon.textContent = hasErr ? '✕' : (hasWarn ? '⚠' : 'ℹ');
            if (body) body.innerHTML = issues.map(iss => {
                const tokenId = iss.tokenId ? ` data-tid="${Utils.escapeHtml(iss.tokenId)}"` : '';
                return `<div class="panel-item ${Utils.escapeHtml(iss.level)}${iss.tokenId ? ' clickable' : ''}"${tokenId}>${Utils.escapeHtml(iss.message)}</div>`;
            }).join('');

            // Click on panel item → scroll/highlight token
            body.querySelectorAll('.panel-item.clickable').forEach(item => {
                item.addEventListener('click', () => {
                    const tid = item.dataset.tid; if (!tid) return;
                    const el = document.querySelector(`.token[data-id="${CSS.escape(tid)}"]`);
                    if (el) { el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); el.classList.add('selected'); State.selectedId = tid; setTimeout(() => { if (State.selectedId === tid) { el.classList.remove('selected'); State.selectedId = null; } }, 1500); }
                });
            });
        }
    },

    /* ══════════════════════════════════════════════════════════════
       Calculate — sends expression to PHP backend, shows animated
       step-by-step results panel.
       ══════════════════════════════════════════════════════════════ */
    // --- Place this cache object OUTSIDE the function (e.g., at module level) ---
    resultCache: {},

    _calculate() {
        if (!State.tokens.length) { this.showStatus(I18n.t('validation.empty'), 'error'); return; }
        const issues = Validator.validate(State.tokens);
        const errors = issues.filter(i => i.level === 'error');
        if (errors.length) { this.showStatus(`✕ ${errors.length} error${errors.length > 1 ? 's' : ''}`, 'error'); return; }

        const eq = this._buildEq();
        const btn = document.getElementById('calculateBtn');

        // Build POST body: expression + language + variables
        const params = new URLSearchParams({
            equation: eq,
            lang: I18n.lang,
        });
        Object.entries(State.varValues).forEach(([k, v]) => {
            if (v !== '' && !isNaN(parseFloat(v))) params.append('vars[' + k + ']', v);
        });

        const cacheKey = params.toString();

        // --- Cache hit: return stored result immediately ---
        if (this.resultCache[cacheKey]) {
            this.showStatus(I18n.t('status.success'), 'success');
            Results.open(this.resultCache[cacheKey], eq);
            return; // Stop further execution (no fetch, no button disable)
        }

        // --- Cache miss: proceed with network request ---
        this.showStatus(I18n.t('status.sending'), 'info');
        if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); }

        const ctrl = new AbortController();
        const timer = setTimeout(() => ctrl.abort(), 15000);

        fetch(CONFIG.API_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString(),
            signal: ctrl.signal
        })
            .then(r => {
                if (!r.ok) {
                    return r.json().then(d => {
                        throw Object.assign(new Error(d.error || 'HTTP ' + r.status), { httpStatus: r.status, backendMsg: d.error, errorType: d.error_type });
                    }).catch(parseErr => {
                        if (parseErr.httpStatus) throw parseErr;
                        throw Object.assign(new Error('HTTP ' + r.status), { httpStatus: r.status });
                    });
                }
                return r.json();
            })
            .then(data => {
                if (!data.valid) {
                    const techMsg = data.error || 'Invalid expression';
                    throw new Error(techMsg);
                }
                // Store successful response in cache
                this.resultCache[cacheKey] = data;
                this.showStatus(I18n.t('status.success'), 'success');
                Results.open(data, eq);
            })
            .catch(err => {
                let msg = '';
                const isFa = I18n.lang === 'fa';
                if (err.name === 'AbortError') {
                    msg = isFa ? '✕ وقفه زمانی — لطفاً دوباره تلاش کنید' : '✕ Timeout — please try again';
                } else if (err.httpStatus === 422) {
                    // Backend now returns translated friendly message
                    const backErr = err.backendMsg || '';
                    const errType = err.errorType || '';
                    if (backErr) {
                        msg = '✕ ' + backErr;
                    } else if (errType === 'overflow_error') {
                        msg = isFa ? '✕ نتیجه خیلی بزرگ است (سرریز) — توان‌های کوچک‌تر استفاده کنید' : '✕ Result too large (overflow) — use smaller exponent values';
                    } else if (errType === 'variable_error') {
                        msg = isFa ? '✕ خطای متغیر — مقادیر را در پانل متغیرها وارد کنید' : '✕ Variable error — enter values in the Variables panel';
                    } else if (errType === 'domain_error') {
                        msg = isFa ? '✕ خطای دامنه — مثلاً جذر منفی یا تقسیم بر صفر' : '✕ Domain error — e.g. square root of negative number or division by zero';
                    } else if (errType === 'syntax_error') {
                        msg = isFa ? '✕ فرمت معادله نادرست است — پرانتزها و عملگرها را بررسی کنید' : '✕ Expression format error — check parentheses and operators';
                    } else {
                        msg = isFa ? '✕ عبارت نامعتبر' : '✕ Invalid expression';
                    }
                } else if (err.httpStatus === 429) {
                    msg = isFa ? '✕ درخواست‌های بیش از حد — کمی صبر کنید' : '✕ Too many requests — please wait a moment';
                } else if (err.httpStatus === 400) {
                    msg = isFa ? '✕ ورودی نامعتبر — کاراکترهای مجاز را بررسی کنید' : '✕ Invalid input — check for unsupported characters';
                } else {
                    // Generic network/unknown error — don't show raw technical message
                    msg = isFa ? '✕ خطای ارتباطی — اتصال اینترنت را بررسی کنید' : '✕ Connection error — check your internet connection';
                }
                this.showStatus(msg, 'error');
            })
            .finally(() => {
                clearTimeout(timer);
                if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); }
            });
    },

    /* ── Helpers ─────────────────────────────────────────────── */
    showStatus(msg, type) {
        const s = document.getElementById('status'); if (!s) return;
        s.textContent = msg; s.className = 'status' + (type ? ' ' + type : '');
    },

    _toast(msg, type = '') {
        const c = document.getElementById('toastContainer'); if (!c) return;
        const t = document.createElement('div'); t.className = 'toast' + (type ? ' ' + type : ''); t.textContent = msg;
        c.appendChild(t); setTimeout(() => t.remove(), 2900);
    }
};


/* ═══════════════════════════════════════════════════════════════
   LatexConverter — converts backend symbolic string → LaTeX for KaTeX
   ═══════════════════════════════════════════════════════════════ */
const LatexConverter = {
    /* Known constant values → LaTeX */
    _CONSTS: [
        [Math.PI * Math.PI, '\\pi^{2}', 1e-4],
        [Math.PI, '\\pi', 1e-6],
        [Math.E * Math.E, 'e^{2}', 1e-4],
        [Math.E, 'e', 1e-6],
        [Math.SQRT2, '\\sqrt{2}', 1e-8],
        [Math.SQRT2 / 2, '\\frac{\\sqrt{2}}{2}', 1e-8],
        [Math.LOG2E, '\\log_2 e', 1e-7],
    ],

    convert(str) {
        if (!str || typeof str !== 'string') return '';
        try {
            const p = new _LatexParser(str.trim());
            return p.parseExpr();
        } catch (e) {
            // fallback: basic cleanup
            return str
                .replace(/\{([a-zA-Z_]\w*)\}/g, '\\mathit{$1}')
                .replace(/\bsqrt\b/g, '\\sqrt')
                .replace(/\bpi\b/g, '\\pi')
                .replace(/\*/g, '\\cdot ');
        }
    },

    /* Try to recognise common irrational constants in a numeric string */
    recogniseConst(numStr) {
        const v = parseFloat(numStr);
        if (isNaN(v)) return null;
        for (const [ref, latex, tol] of this._CONSTS) {
            if (Math.abs(v - ref) < tol) return latex;
        }
        return null;
    }
};

class _LatexParser {
    constructor(src) {
        this.s = src;
        this.pos = 0;
        this.len = src.length;
    }

    /* ── helpers ── */
    skip() { while (this.pos < this.len && ' \t\r\n'.includes(this.s[this.pos])) this.pos++; }
    peek() { this.skip(); return this.pos < this.len ? this.s[this.pos] : null; }
    eat(ch) { this.skip(); if (this.s[this.pos] === ch) { this.pos++; return true; } return false; }
    cur() { return this.pos < this.len ? this.s[this.pos] : null; }

    /* ── grammar ── */
    parseExpr() {
        let left = this.parseTerm();
        while (true) {
            this.skip();
            const c = this.cur();
            if (c === '+') { this.pos++; left = `${left}+${this.parseTerm()}`; }
            // Handle both ASCII hyphen-minus (-) and Unicode minus sign (−, U+2212)
            else if (c === '-' || c === '\u2212') { this.pos++; const r = this.parseTerm(); left = `${left}-${r}`; }
            else break;
        }
        return left;
    }

    parseTerm() {
        let left = this.parsePower();
        while (true) {
            this.skip();
            const c = this.cur();
            if (c === '*') { this.pos++; left = `${left} \\cdot ${this.parsePower()}`; }
            // Handle Unicode multiplication sign (×, U+00D7) from backend symbolic strings
            else if (c === '\u00D7') { this.pos++; left = `${left} \\cdot ${this.parsePower()}`; }
            else if (c === '/') {
                this.pos++;
                const right = this.parsePower();
                left = `\\dfrac{${left}}{${right}}`;
            }
            // Handle Unicode division sign (÷, U+00F7)
            else if (c === '\u00F7') {
                this.pos++;
                const right = this.parsePower();
                left = `\\dfrac{${left}}{${right}}`;
            }
            else break;
        }
        return left;
    }

    parsePower() {
        const base = this.parseUnary();
        this.skip();
        if (this.cur() === '^') {
            this.pos++;
            const exp = this.parseUnary();
            // Simple single-char base doesn't need braces
            return `{${base}}^{${exp}}`;
        }
        return base;
    }

    parseUnary() {
        this.skip();
        if (this.cur() === '-') { this.pos++; return `-${this.parsePrimary()}`; }
        if (this.cur() === '+') { this.pos++; }
        return this.parsePrimary();
    }

    parsePrimary() {
        this.skip();
        const c = this.cur();
        if (c === null) return '';

        /* number */
        if (/[\d.]/.test(c)) {
            let num = '';
            while (this.pos < this.len && /[\d.eE+\-]/.test(this.s[this.pos])) {
                // careful: only consume + / - right after e/E
                const ch = this.s[this.pos];
                if ((ch === '+' || ch === '-') && !/[eE]/.test(num.slice(-1))) break;
                num += ch; this.pos++;
            }
            // recognise irrational constants
            const known = LatexConverter.recogniseConst(num);
            if (known) return known;
            // pretty-print: trim trailing zeros
            const f = parseFloat(num);
            if (!isNaN(f) && Number.isFinite(f)) {
                if (Number.isInteger(f) && Math.abs(f) < 1e15) return String(f);
                const s = f.toPrecision(10).replace(/\.?0+$/, '');
                return s;
            }
            return num;
        }

        /* braced symbolic variable {x} */
        if (c === '{') {
            this.pos++;
            let name = '';
            while (this.pos < this.len && this.s[this.pos] !== '}') name += this.s[this.pos++];
            this.pos++; // consume }
            return `\\mathit{${name}}`;
        }

        /* parenthesised group */
        if (c === '(') {
            this.pos++;
            const inner = this.parseExpr();
            this.eat(')');
            // Decide if parens are needed (heuristic: omit if inner has no operators at top level)
            if (/^[\\a-zA-Z0-9_\-\.]+$/.test(inner)) return inner;
            return `\\left(${inner}\\right)`;
        }

        /* word: sqrt, radical, pi, variable name */
        if (/[a-zA-Z_]/.test(c)) {
            let word = '';
            while (this.pos < this.len && /[a-zA-Z0-9_]/.test(this.s[this.pos])) word += this.s[this.pos++];

            if (word === 'sqrt') {
                this.eat('(');
                const arg = this.parseExpr();
                this.eat(')');
                return `\\sqrt{${arg}}`;
            }
            if (word === 'radical') {
                this.eat('(');
                const deg = this.parseExpr();
                this.eat(',');
                const arg = this.parseExpr();
                this.eat(')');
                return `\\sqrt[${deg}]{${arg}}`;
            }
            if (word === 'pi') return '\\pi';
            if (word === 'inf' || word === 'infinity') return '\\infty';
            if (word === 'NaN') return '\\text{NaN}';
            // regular variable
            return word.length === 1 ? `${word}` : `\\mathrm{${word}}`;
        }

        /* unknown char — skip */
        this.pos++;
        return '';
    }
}

/* Render a LaTeX string using KaTeX if available, else return fallback HTML */
function renderMath(latex, displayMode = false) {
    if (!latex) return '';
    try {
        if (typeof katex !== 'undefined') {
            return katex.renderToString(latex, {
                displayMode,
                throwOnError: false,
                strict: false,
                output: 'html',
            });
        }
    } catch (e) { /* fallback below */ }
    // Plain fallback with basic symbol replacement
    return '<span class="math-plain">' +
        Utils.escapeHtml(latex)
            .replace(/\\pi/g, 'π')
            .replace(/\\sqrt\{([^}]+)\}/g, '√($1)')
            .replace(/\\dfrac\{([^}]+)\}\{([^}]+)\}/g, '($1)/($2)')
            .replace(/\\mathit\{([^}]+)\}/g, '<em>$1</em>')
            .replace(/\\left\(/g, '(').replace(/\\right\)/g, ')')
            .replace(/\^/g, '^') + '</span>';
}

/* ═══════════════════════════════════════════════════════════════
   Results — animated step-by-step solution viewer
   ═══════════════════════════════════════════════════════════════ */
const Results = {
    _data: null,   // full JSON response from backend
    _steps: [],     // shaped steps array
    _cur: -1,     // currently active step index (0-based)
    _raf: null,   // requestAnimationFrame handle for auto-play
    _playing: false,
    _STEP_DELAY: 1400, // ms between auto-play steps
    _stepsZoom: 1.0,   // zoom level for the steps list (0.6 – 2.0)
    _ZOOM_STEP: 0.15,
    _ZOOM_MIN: 0.6,
    _ZOOM_MAX: 2.0,
    _expandedIdx: -1,  // index of currently expanded step card (-1 = none)

    /* ── Public: open the panel with backend data ──────────────── */
    open(data, expressionStr) {
        this._data = data;
        this._steps = data.steps || [];
        this._cur = -1;
        this._playing = false;
        this._stepsZoom = 1.0;
        this._expandedIdx = -1;

        this._buildDOM(expressionStr, data);
        this._show();
        this._applyZoom();
        // Start auto-play after a brief moment
        setTimeout(() => this._startPlay(), 350);
    },

    /* ── Build DOM ─────────────────────────────────────────────── */
    _buildDOM(exprStr, data) {
        // Header expression
        const exprEl = document.getElementById('resultsExpr');
        if (exprEl) exprEl.textContent = exprStr || '';

        // Icon
        const icon = document.getElementById('resultsIcon');
        if (icon) icon.textContent = data.type === 'equation' ? '=' : '∑';

        // Final answer — render with KaTeX if symbolic/complex
        const ans = document.getElementById('resultsAnswerValue');
        if (ans) {
            const rawDisplay = data.final_display || '—';
            // Handle infinity symbol from overflow
            const isInfinity = rawDisplay === '∞' || rawDisplay === '-∞' || rawDisplay.includes('∞');
            const isSymbolic = typeof rawDisplay === 'string' && (
                rawDisplay.includes('{') || rawDisplay.includes('sqrt') ||
                rawDisplay.includes('^') || rawDisplay.includes('pi')
            );
            if (isInfinity) {
                ans.innerHTML = `<span class="math-overflow-result" title="${I18n.lang === 'fa' ? 'نتیجه سرریز شد' : 'Overflow — result too large'}">` +
                    Utils.escapeHtml(rawDisplay) + ' <span class="overflow-note">' +
                    (I18n.lang === 'fa' ? '(بزرگ‌تر از قابل‌محاسبه)' : '(exceeds max value)') + '</span></span>';
            } else if (isSymbolic || (data.type === 'equation' && rawDisplay !== '—')) {
                try {
                    const latex = LatexConverter.convert(rawDisplay);
                    ans.innerHTML = renderMath(latex, true);
                } catch (e) {
                    ans.textContent = rawDisplay;
                }
            } else {
                ans.textContent = rawDisplay;
            }
            ans.classList.remove('revealed');
        }

        // Clear steps container
        const stepsEl = document.getElementById('resultsSteps');
        if (!stepsEl) return;
        stepsEl.innerHTML = '';

        // Operation label map
        const opLabels = {
            addition: I18n.lang === 'fa' ? 'جمع' : 'Addition',
            subtraction: I18n.lang === 'fa' ? 'تفریق' : 'Subtraction',
            multiplication: I18n.lang === 'fa' ? 'ضرب' : 'Multiplication',
            division: I18n.lang === 'fa' ? 'تقسیم' : 'Division',
            exponentiation: I18n.lang === 'fa' ? 'توان' : 'Power',
            square_root: I18n.lang === 'fa' ? 'رادیکال' : 'Square Root',
            nth_root: I18n.lang === 'fa' ? 'ریشه n-ام' : 'Nth Root',
            constant_substitution: I18n.lang === 'fa' ? 'ثابت' : 'Constant',
            variable_substitution: I18n.lang === 'fa' ? 'متغیر' : 'Variable',
            parentheses_resolved: I18n.lang === 'fa' ? 'پرانتز' : 'Parentheses',
            equation_solving: I18n.lang === 'fa' ? 'حل معادله' : 'Equation',
            unary_negation: I18n.lang === 'fa' ? 'منفی' : 'Negation',
        };

        this._steps.forEach((step, idx) => {
            const op = step.operation || 'compute';
            const opLabel = opLabels[op] || op;
            const desc = step.description || step.description_en || '';

            const card = document.createElement('div');
            card.className = 'result-step';
            card.dataset.idx = String(idx);
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'button');
            card.setAttribute('aria-expanded', 'false');

            // Render formula + result cells with KaTeX when possible
            const fmtFormula = step.formula ? (() => {
                try { return renderMath(LatexConverter.convert(step.formula), false); }
                catch (e) { return Utils.escapeHtml(step.formula); }
            })() : '';
            const fmtResult = (() => {
                const raw = step.after || step.result_display || String(step.result || '');
                try { return renderMath(LatexConverter.convert(raw), false); }
                catch (e) { return Utils.escapeHtml(raw); }
            })();
            const miniResult = Utils.escapeHtml(step.result_display || String(step.result || ''));

            card.innerHTML = `
        <div class="result-step-header" data-idx="${idx}">
          <div class="result-step-num">${step.step_number}</div>
          <div class="result-step-op" data-op="${Utils.escapeHtml(op)}">${Utils.escapeHtml(opLabel)}</div>
        </div>
        <div class="result-step-body">
          ${step.before ? `<div class="result-step-subexpr" dir="ltr"><span class="result-step-subexpr-label">${I18n.lang === 'fa' ? 'عبارت:' : 'Expr:'}</span> <code>${Utils.escapeHtml(step.before)}</code></div>` : ''}
          <div class="result-step-desc">${Utils.escapeHtml(desc)}</div>
          <div class="result-step-math">
            <div class="result-step-math-cell highlight">
              <div class="result-step-math-cell-label">${I18n.lang === 'fa' ? 'فرمول' : 'Formula'}</div>
              <div class="result-step-math-cell-value result-math-render" dir="ltr">${fmtFormula}</div>
            </div>
            <div class="result-step-math-cell result">
              <div class="result-step-math-cell-label">${I18n.lang === 'fa' ? 'نتیجه' : 'Result'}</div>
              <div class="result-step-math-cell-value result-math-render" dir="ltr">${fmtResult}</div>
            </div>
          </div>
        </div>`;

            // Click header to jump to step; click body/card to expand
            card.querySelector('.result-step-header').addEventListener('click', (e) => {
                e.stopPropagation();
                this._pausePlay();
                this._goTo(idx);
                // Also toggle expand on header click
                this._toggleExpand(idx, card);
            });
            // Keyboard
            card.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this._pausePlay(); this._goTo(idx); this._toggleExpand(idx, card); }
            });

            stepsEl.appendChild(card);
        });

        this._updateProgress();
        this._updateControls();
    },




    /* ── Step expand/collapse (click-to-zoom a card) ──────────── */
    _toggleExpand(idx, card) {
        const stepsEl = document.getElementById('resultsSteps');
        if (!stepsEl) return;
        const cards = stepsEl.querySelectorAll('.result-step');
        const isExpanded = card.classList.contains('step-expanded');
        // Collapse all
        cards.forEach(c => {
            c.classList.remove('step-expanded');
            c.setAttribute('aria-expanded', c.classList.contains('active') ? 'true' : 'false');
        });
        if (!isExpanded) {
            card.classList.add('step-expanded');
            this._expandedIdx = idx;
            // Smooth scroll to it
            setTimeout(() => card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
        } else {
            this._expandedIdx = -1;
        }
    },

    /* ── Steps zoom ────────────────────────────────────────────── */
    _applyZoom() {
        const stepsEl = document.getElementById('resultsSteps');
        if (stepsEl) stepsEl.style.transform = `scale(${this._stepsZoom})`;
        const val = document.getElementById('resultsZoomVal');
        if (val) val.textContent = Math.round(this._stepsZoom * 100) + '%';
        const inBtn = document.getElementById('resultsZoomIn');
        const outBtn = document.getElementById('resultsZoomOut');
        if (inBtn) inBtn.disabled = this._stepsZoom >= this._ZOOM_MAX;
        if (outBtn) outBtn.disabled = this._stepsZoom <= this._ZOOM_MIN;
    },
    _zoomIn() { this._stepsZoom = Math.min(this._ZOOM_MAX, +(this._stepsZoom + this._ZOOM_STEP).toFixed(2)); this._applyZoom(); },
    _zoomOut() { this._stepsZoom = Math.max(this._ZOOM_MIN, +(this._stepsZoom - this._ZOOM_STEP).toFixed(2)); this._applyZoom(); },

    /* ── Show/Hide panel ───────────────────────────────────────── */
    _show() {
        const o = document.getElementById('resultsOverlay');
        o?.classList.remove('hidden');
        document.getElementById('resultsPanel')?.focus();
    },
    _hide() {
        const overlay = document.getElementById('resultsOverlay');
        const panel = document.getElementById('resultsPanel');
        overlay?.classList.add('hidden');
        this._pausePlay();
        // Reset inline drag styles so next open animates from center
        if (panel) panel.style.cssText = '';
        if (overlay) { overlay.style.alignItems = ''; overlay.style.justifyContent = ''; }
    },

    /* ── Navigation ────────────────────────────────────────────── */
    _goTo(idx, animate = true) {
        const total = this._steps.length;
        if (!total) return;
        idx = Math.max(0, Math.min(total - 1, idx));

        // Deactivate all, activate up to idx
        document.querySelectorAll('.result-step').forEach((card, i) => {
            card.classList.remove('active');
            card.setAttribute('aria-expanded', 'false');
            if (i < idx) { card.classList.add('visible', 'done'); }
            if (i === idx) { card.classList.add('visible', 'active'); card.setAttribute('aria-expanded', 'true'); }
            if (i > idx) { card.classList.remove('visible', 'done'); }
        });

        this._cur = idx;
        this._updateProgress();
        this._updateControls();

        // Reveal final answer on last step
        const ans = document.getElementById('resultsAnswerValue');
        if (idx === total - 1 && ans) {
            setTimeout(() => ans.classList.add('revealed'), 200);
        } else if (ans) {
            ans.classList.remove('revealed');
        }

        // Scroll active card into view
        const active = document.querySelector('.result-step.active');
        if (active && animate) {
            active.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    },

    /* ── Auto-play ─────────────────────────────────────────────── */
    _startPlay() {
        this._playing = true;
        this._updatePlayBtn(true);
        this._playNext();
    },

    _playNext() {
        if (!this._playing) return;
        const next = this._cur + 1;
        if (next >= this._steps.length) {
            this._playing = false;
            this._updatePlayBtn(false);
            return;
        }
        this._goTo(next);
        this._raf = setTimeout(() => this._playNext(), this._STEP_DELAY);
    },

    _pausePlay() {
        this._playing = false;
        clearTimeout(this._raf);
        this._updatePlayBtn(false);
    },

    _togglePlay() {
        if (this._playing) { this._pausePlay(); }
        else {
            // If at the end, restart from beginning
            if (this._cur >= this._steps.length - 1) { this._cur = -1; this._resetCards(); }
            this._startPlay();
        }
    },

    _resetCards() {
        document.querySelectorAll('.result-step').forEach(c => {
            c.classList.remove('active', 'done', 'visible');
            c.setAttribute('aria-expanded', 'false');
        });
        const ans = document.getElementById('resultsAnswerValue');
        if (ans) ans.classList.remove('revealed');
        this._updateProgress();
        this._updateControls();
    },

    /* ── UI helpers ────────────────────────────────────────────── */
    _updateProgress() {
        const bar = document.getElementById('resultsProgressBar');
        const counter = document.getElementById('resultsStepCounter');
        const total = this._steps.length;
        const pct = total ? Math.round(((this._cur + 1) / total) * 100) : 0;
        if (bar) bar.style.setProperty('--pct', pct + '%');
        if (counter) counter.textContent = `${Math.max(0, this._cur + 1)} / ${total}`;
    },

    _updateControls() {
        const prevBtn = document.getElementById('resultsPrev');
        const nextBtn = document.getElementById('resultsNext');
        if (prevBtn) prevBtn.disabled = this._cur <= 0;
        if (nextBtn) nextBtn.disabled = this._cur >= this._steps.length - 1;
    },

    _updatePlayBtn(playing) {
        const btn = document.getElementById('resultsPlayPause');
        const icon = btn?.querySelector('.play-icon');
        const label = btn?.querySelector('.play-label');
        if (!btn) return;
        btn.classList.toggle('paused', !playing);
        if (icon) icon.textContent = playing ? '⏸' : '▶';
        if (label) label.textContent = I18n.t(playing ? 'results.pause' : 'results.play');
    },

    /* ── Results panel drag-to-move ────────────────────────────── */
    _setupResultsDraggable() {
        const panel = document.getElementById('resultsPanel');
        const header = document.getElementById('resultsPanel')?.querySelector('.results-header');
        const overlay = document.getElementById('resultsOverlay');
        if (!panel || !header || !overlay) return;

        let dragging = false, sx = 0, sy = 0, px = 0, py = 0;

        const isMobile = () => window.innerWidth <= 600;

        const startDrag = (x, y) => {
            if (isMobile()) return; // bottom sheet on mobile — no drag
            dragging = true; sx = x; sy = y;
            const r = panel.getBoundingClientRect();
            px = r.left; py = r.top;
            panel.style.cssText = `position:fixed;left:${px}px;top:${py}px;right:auto;bottom:auto;margin:0;transform:none;width:${panel.offsetWidth}px;max-height:calc(100vh - 1.5rem)`;
            overlay.style.alignItems = 'unset';
            overlay.style.justifyContent = 'unset';
        };

        const moveDrag = (x, y) => {
            if (!dragging) return;
            const vw = window.innerWidth, vh = window.innerHeight;
            const pw = panel.offsetWidth, ph = panel.offsetHeight;
            panel.style.left = Math.min(Math.max(0, px + (x - sx)), vw - pw) + 'px';
            panel.style.top = Math.min(Math.max(0, py + (y - sy)), vh - ph) + 'px';
        };

        const stopDrag = () => { dragging = false; };

        header.addEventListener('mousedown', e => {
            if (e.target.tagName === 'BUTTON') return;
            e.preventDefault(); startDrag(e.clientX, e.clientY);
        });
        document.addEventListener('mousemove', e => { if (dragging) moveDrag(e.clientX, e.clientY); });
        document.addEventListener('mouseup', stopDrag);
        header.addEventListener('touchstart', e => {
            if (e.target.tagName === 'BUTTON') return;
            startDrag(e.touches[0].clientX, e.touches[0].clientY);
        }, { passive: true });
        document.addEventListener('touchmove', e => {
            if (dragging) { e.preventDefault(); moveDrag(e.touches[0].clientX, e.touches[0].clientY); }
        }, { passive: false });
        document.addEventListener('touchend', stopDrag, { passive: true });
    },

    /* ── Wire up controls ──────────────────────────────────────── */
    init() {
        this._setupResultsDraggable();
        // Close ONLY via X button — no backdrop click, no Escape
        document.getElementById('resultsClose')?.addEventListener('click', () => this._hide());
        document.getElementById('resultsZoomIn')?.addEventListener('click', () => this._zoomIn());
        document.getElementById('resultsZoomOut')?.addEventListener('click', () => this._zoomOut());
        // Mouse wheel zoom on the steps wrapper
        document.getElementById('resultsStepsWrapper')?.addEventListener('wheel', (e) => {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                if (e.deltaY < 0) this._zoomIn(); else this._zoomOut();
            }
        }, { passive: false });
        document.getElementById('resultsReplay')?.addEventListener('click', () => {
            this._pausePlay();
            this._cur = -1;
            this._resetCards();
            setTimeout(() => this._startPlay(), 120);
        });
        document.getElementById('resultsPrev')?.addEventListener('click', () => {
            this._pausePlay();
            this._goTo(this._cur - 1);
        });
        document.getElementById('resultsNext')?.addEventListener('click', () => {
            this._pausePlay();
            this._goTo(this._cur + 1);
        });
        document.getElementById('resultsPlayPause')?.addEventListener('click', () => this._togglePlay());

        // Keyboard: arrows navigate only (no close on Escape)
        document.addEventListener('keydown', e => {
            const overlay = document.getElementById('resultsOverlay');
            if (!overlay || overlay.classList.contains('hidden')) return;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); this._pausePlay(); this._goTo(this._cur + 1); }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); this._pausePlay(); this._goTo(this._cur - 1); }
            if (e.key === ' ') { e.preventDefault(); this._togglePlay(); }
        });
    }
};

/* ── Boot ──────────────────────────────────────────────────── */
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => App.init());
else App.init();

window.addEventListener('beforeunload', () => Render.cleanup());

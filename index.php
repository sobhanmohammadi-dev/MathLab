<?php

declare(strict_types=1);

// ── API mode: if 'equation' param present, route to backend and exit ──
if (isset($_REQUEST['equation'])) {
  // Prevent HTML error pages leaking into the JSON stream
  ini_set('display_errors', '0');
  require __DIR__ . '/calculate.php';
  exit;
}

// ── CSP header for the HTML page ─────────────────────────────────────
header(
  "Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' https://cdn.jsdelivr.net; "
    . "style-src  'self' https://fonts.googleapis.com https://cdn.jsdelivr.net 'unsafe-inline'; "
    . "font-src   'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
    . "img-src    'self' data:; "
    . "connect-src 'self';"
);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header_remove('X-Powered-By');
?>
<!--
    MathLab - Visual Mathematical Expression Builder
    Copyright (C) 2026 - Licensed under GPL-3.0
-->
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-lang="fa">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <meta name="theme-color" content="#0a0b10" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <link rel="shortcut icon" href="assets/img/icon.svg" type="image/svg+xml">
  <title>MathLab – میزکار ریاضی</title>
  <!-- <link rel="preconnect" href="https://fonts.googleapis.com" /> -->
  <!-- <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin /> -->
  <!-- <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Vazirmatn:wght@300;400;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" /> -->
  <link rel="stylesheet" href="assets/css/style.css" />
  <!-- KaTeX for math rendering -->
  <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous" /> -->
  <!-- <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script> -->
</head>

<body>

  <header class="header">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
      <span></span><span></span><span></span>
    </button>
    <div class="header-title">
      <h1 data-i18n="app.title">MathLab</h1>
      <span class="subtitle" data-i18n="app.subtitle">میزکار ریاضی</span>
    </div>
    <div class="header-actions">
      <button class="lang-btn" id="langBtn" aria-label="Switch Language">
        <span data-lang-switch="en">EN</span>
        <span data-lang-switch="fa">FA</span>
      </button>
      <span class="token-count" id="tokenCount"></span>
      <button class="icon-btn" id="undoBtn" title="Ctrl+Z">↩</button>
      <button class="icon-btn" id="redoBtn" title="Ctrl+Y">↪</button>
      <button class="icon-btn" id="clearBtn" title="Clear">✕</button>
    </div>
  </header>

  <div class="app-body">

    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <span data-i18n="sidebar.title">عناصر</span>
        <span class="sidebar-hint" data-i18n="sidebar.tapHint">ضربه = افزودن</span>
      </div>

      <section class="group">
        <button class="group-header" data-group="numbers" aria-expanded="true">
          <span class="arrow">›</span><span data-i18n="groups.numbers">اعداد</span>
        </button>
        <div class="group-content" id="group-numbers">
          <div class="element" data-type="number" tabindex="0" role="button">
            <span class="icon">5</span>
            <span class="label" data-i18n="elements.number">عدد</span>
          </div>
          <div class="element" data-type="decimal" tabindex="0" role="button">
            <span class="icon">3.14</span>
            <span class="label" data-i18n="elements.decimal">اعشاری</span>
          </div>
        </div>
      </section>

      <section class="group">
        <button class="group-header" data-group="ops" aria-expanded="true">
          <span class="arrow">›</span><span data-i18n="groups.operations">عملیات</span>
        </button>
        <div class="group-content" id="group-ops">
          <div class="element" data-type="add" tabindex="0" role="button">
            <span class="icon">+</span><span class="label" data-i18n="elements.add">جمع</span>
          </div>
          <div class="element" data-type="subtract" tabindex="0" role="button">
            <span class="icon">−</span><span class="label" data-i18n="elements.subtract">تفریق</span>
          </div>
          <div class="element" data-type="multiply" tabindex="0" role="button">
            <span class="icon">×</span><span class="label" data-i18n="elements.multiply">ضرب</span>
          </div>
          <div class="element" data-type="divide" tabindex="0" role="button">
            <span class="icon">÷</span><span class="label" data-i18n="elements.divide">تقسیم</span>
          </div>
        </div>
      </section>

      <section class="group">
        <button class="group-header" data-group="symbols" aria-expanded="true">
          <span class="arrow">›</span><span data-i18n="groups.symbols">نمادها</span>
        </button>
        <div class="group-content" id="group-symbols">
          <div class="element" data-type="equals" tabindex="0" role="button">
            <span class="icon">=</span><span class="label" data-i18n="elements.equals">مساوی</span>
          </div>
          <div class="element" data-type="openParen" tabindex="0" role="button" dir="ltr">
            <span class="icon">(</span><span class="label">(</span>
          </div>
          <div class="element" data-type="closeParen" tabindex="0" role="button" dir="ltr">
            <span class="icon">)</span><span class="label">)</span>
          </div>
          <div class="element" data-type="fraction" tabindex="0" role="button">
            <span class="icon">⁄</span><span class="label" data-i18n="elements.fraction">کسر</span>
          </div>
          <div class="element" data-type="radical" tabindex="0" role="button">
            <span class="icon">√</span><span class="label" data-i18n="elements.radical">رادیکال</span>
          </div>
          <div class="element" data-type="power" tabindex="0" role="button">
            <span class="icon">x<sup style="font-size:.55em">n</sup></span>
            <span class="label" data-i18n="elements.power">توان</span>
          </div>
        </div>
      </section>

      <section class="group">
        <button class="group-header" data-group="vars" aria-expanded="true">
          <span class="arrow">›</span><span data-i18n="groups.variables">متغیرها</span>
        </button>
        <div class="group-content" id="group-vars">
          <div class="element" data-type="variable" tabindex="0" role="button">
            <span class="icon"><em>x</em></span>
            <span class="label" data-i18n="elements.variable">متغیر</span>
          </div>
          <div class="element" data-type="pi" tabindex="0" role="button">
            <span class="icon">π</span><span class="label">π</span>
          </div>
        </div>
      </section>
    </aside>

    <main class="main">
      <div class="toolbar">
        <h2 class="toolbar-title" data-i18n="workspace.title">میزکار</h2>
        <div class="zoom-controls">
          <button class="zoom-btn" id="zoomOut" title="Zoom out">−</button>
          <span class="zoom-value" id="zoomValue">100%</span>
          <button class="zoom-btn" id="zoomIn" title="Zoom in">+</button>
          <button class="zoom-btn" id="zoomReset" title="Reset view">⟲</button>
        </div>
      </div>

      <div class="workspace-wrapper" id="workspaceWrapper">
        <div class="workspace" id="workspace" dir="ltr">
          <div class="workspace-hint" id="workspaceHint">
            <span class="hint-icon">⟵</span>
            <span class="hint-text" data-i18n="workspace.hint">عناصر را اینجا بکشید</span>
          </div>
        </div>
      </div>

      <div class="preview">
        <div class="preview-header">
          <span data-i18n="preview.title">معادله</span>
          <div class="preview-actions">
            <button class="parse-btn" id="parseBtn" title="Import from text">⌨</button>
            <button class="copy-btn" id="copyBtn" data-i18n="preview.copy">کپی</button>
          </div>
        </div>
        <code class="preview-content" id="previewContent" dir="ltr">—</code>
      </div>

      <div class="actions">
        <button class="primary-btn" id="calculateBtn">
          <span class="btn-icon">⟹</span>
          <span data-i18n="actions.calculate">محاسبه</span>
        </button>
        <span class="status" id="status"></span>
      </div>
    </main>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       RESULTS PANEL — animated step-by-step solution viewer
       ══════════════════════════════════════════════════════════ -->
  <div class="results-overlay hidden" id="resultsOverlay">
    <div class="results-panel" id="resultsPanel" role="dialog" aria-modal="true" aria-label="Solution Steps">

      <!-- Header -->
      <div class="results-header">
        <div class="results-header-left">
          <span class="results-icon" id="resultsIcon">⟹</span>
          <div class="results-title-group">
            <span class="results-title" data-i18n="results.title">نتیجه محاسبه</span>
            <span class="results-expr" id="resultsExpr" dir="ltr"></span>
          </div>
        </div>
        <div class="results-header-right">
          <div class="results-zoom-group">
            <button class="results-zoom-btn" id="resultsZoomOut" title="Zoom out steps">−</button>
            <span class="results-zoom-val" id="resultsZoomVal">100%</span>
            <button class="results-zoom-btn" id="resultsZoomIn" title="Zoom in steps">+</button>
          </div>
          <button class="results-replay" id="resultsReplay" title="Replay animation">↺</button>
          <button class="results-close" id="resultsClose" aria-label="Close">✕</button>
        </div>
      </div>

      <!-- Final answer banner -->
      <div class="results-answer" id="resultsAnswer">
        <span class="results-answer-label" data-i18n="results.answer">پاسخ نهایی</span>
        <div class="results-answer-value" id="resultsAnswerValue" dir="ltr">—</div>
      </div>

      <!-- Step progress bar -->
      <div class="results-progress">
        <div class="results-progress-bar" id="resultsProgressBar"></div>
        <span class="results-step-counter" id="resultsStepCounter">0 / 0</span>

      </div>

      <!-- Steps list (scrollable) -->
      <div class="results-steps-wrapper" id="resultsStepsWrapper">
        <div class="results-steps" id="resultsSteps">
          <!-- Steps injected by JS -->
        </div>
      </div>

      <!-- Controls -->
      <div class="results-controls">
        <button class="results-ctrl-btn" id="resultsPrev" disabled>← <span data-i18n="results.prev">قبلی</span></button>
        <button class="results-ctrl-btn results-ctrl-play" id="resultsPlayPause">
          <span class="play-icon">▶</span> <span class="play-label" data-i18n="results.play">پخش</span>
        </button>
        <button class="results-ctrl-btn" id="resultsNext"><span data-i18n="results.next">بعدی</span> →</button>
      </div>

    </div>
  </div>

  <!-- Variables Panel — persistent, draggable, always-visible when vars exist -->
  <div class="var-panel hidden" id="varPanel">
    <div class="var-panel-header" id="varPanelHeader">
      <span class="var-panel-icon">𝑥</span>
      <span class="var-panel-title" data-i18n="varPanel.title">Variables</span>
      <span class="var-panel-count" id="varPanelCount"></span>
      <button class="var-panel-toggle" id="varPanelToggle" aria-label="Toggle panel">▾</button>
    </div>
    <div class="var-panel-body" id="varPanelBody"></div>
  </div>

  <!-- Validation Panel -->
  <div class="validation-panel" id="validationPanel">
    <div class="panel-header" id="panelHeader">
      <span class="panel-icon" id="panelIcon">✓</span>
      <span class="panel-title" data-i18n="validation.title">بررسی</span>
      <button class="panel-close" id="panelClose">✕</button>
    </div>
    <div class="panel-body" id="panelBody"></div>
  </div>

  <!-- Import Panel -->
  <div class="import-panel hidden" id="importPanel">
    <div class="import-header">
      <span data-i18n="import.title">ورودی متنی</span>
      <button class="import-close" id="importClose">✕</button>
    </div>
    <div class="import-body">
      <input type="text" id="importInput" class="import-input"
        autocomplete="off" autocorrect="off" autocapitalize="off"
        spellcheck="false" dir="ltr"
        data-i18n-placeholder="import.placeholder" />
      <div class="import-feedback hidden" id="importFeedback"></div>
      <div class="import-syntax" id="importSyntax">
        <span data-i18n="import.syntax">sqrt(…) · frac(a,b) · pow(a,b) · x^2 · (a)/(b)</span>
      </div>
    </div>
    <div class="import-footer">
      <button class="import-cancel" id="importCancel" data-i18n="import.cancel">لغو</button>
      <button class="import-confirm disabled" id="importConfirm" data-i18n="import.confirm">تایید</button>
    </div>
  </div>

  <!-- Drag Ghost -->
  <div class="drag-ghost" id="dragGhost" aria-hidden="true"></div>

  <!-- Context Menu -->
  <div class="context-menu" id="contextMenu" role="menu">
    <button data-action="duplicate" role="menuitem">
      <span>⧉</span><span data-i18n="context.duplicate">تکرار</span>
    </button>
    <button data-action="moveLeft" role="menuitem">
      <span>←</span><span data-i18n="context.moveLeft">چپ</span>
    </button>
    <button data-action="moveRight" role="menuitem">
      <span>→</span><span data-i18n="context.moveRight">راست</span>
    </button>
    <button data-action="details" role="menuitem">
      <span>ℹ</span><span data-i18n="context.details">جزئیات</span>
    </button>
    <hr role="separator" />
    <button data-action="delete" class="danger" role="menuitem">
      <span>✕</span><span data-i18n="context.delete">حذف</span>
    </button>
  </div>

  <!-- Toast container -->
  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <!-- File drop overlay -->
  <div class="file-drop-overlay hidden" id="fileDropOverlay">
    <div class="file-drop-inner">
      <span class="file-drop-icon">📄</span>
      <span data-i18n="fileDrop.hint">فایل .txt را رها کنید</span>
    </div>
  </div>

  <!-- Details Panel -->
  <div class="details-overlay hidden" id="detailsOverlay">
    <div class="details-panel" id="detailsPanel" role="dialog" aria-modal="true" aria-label="Token Details">
      <div class="details-header" id="detailsHeader">
        <span class="details-title" data-i18n="details.title">جزئیات</span>
        <button class="details-close" id="detailsClose" aria-label="Close">✕</button>
      </div>
      <div class="details-body" id="detailsBody"></div>
    </div>
  </div>

  <script src="assets/js/script.js"></script>
</body>

</html>
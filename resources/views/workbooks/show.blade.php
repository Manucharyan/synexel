@extends('layouts.app')
@section('title', $workbook->name)
@section('content')
<link rel="stylesheet" href="{{ asset('css/spreadsheet.css') }}?v=30">

<div class="app-page">
@include('partials.app-header', ['active' => ''])

<div id="app" data-workbook-id="{{ $workbook->id }}" data-sheets='@json($workbook->sheets)'>

{{-- ══════════════ TITLE BAR ══════════════ --}}
<div class="xl-title">
  <div class="xl-qat">
    <a href="{{ route('workbooks.index') }}" class="xl-qat-btn" title="Back to workbooks">
      <svg viewBox="0 0 16 16"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <button id="btn-save" class="xl-qat-btn" title="Save (auto-save on)">
      <svg viewBox="0 0 16 16"><path d="M3 2h8l3 3v9a1 1 0 01-1 1H3a1 1 0 01-1-1V3a1 1 0 011-1zm5 11v-4H6v4m2-10V2" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <button id="btn-undo" class="xl-qat-btn" title="Undo (Ctrl+Z)" disabled>
      <svg viewBox="0 0 16 16"><path d="M3 7H10a3 3 0 010 6H7m-4-6l3-3-3-3" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <button id="btn-redo" class="xl-qat-btn" title="Redo (Ctrl+Y)" disabled>
      <svg viewBox="0 0 16 16"><path d="M13 7H6a3 3 0 000 6h3m4-6l-3-3 3-3" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <span class="xl-save-dot" id="save-dot" title="Saved"></span>
  </div>
  <div class="xl-title-center">
    <input id="workbook-name" class="xl-title-name" type="text" value="{{ $workbook->name }}" spellcheck="false" autocomplete="off">
    <span class="xl-title-app">— Synexel</span>
  </div>
  <div class="xl-title-right">
    <span id="save-status" class="xl-save-status"></span>
    <label class="xl-qat-btn" title="Import .xlsx / .xls">
      <svg viewBox="0 0 16 16"><path d="M8 2v9m-4-4l4 4 4-4M2 12v1a1 1 0 001 1h10a1 1 0 001-1v-1" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Import
      <input id="input-import" type="file" accept=".xlsx,.xls" style="display:none">
    </label>
    <button id="btn-export" class="xl-qat-btn" title="Export as .xlsx">
      <svg viewBox="0 0 16 16"><path d="M8 11V2m-4 5l4-4 4 4M2 12v1a1 1 0 001 1h10a1 1 0 001-1v-1" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Export
    </button>
  </div>
</div>

{{-- ══════════════ RIBBON ══════════════ --}}
<div class="xl-ribbon">
  {{-- Tab row --}}
  <div class="xl-ribbon-tabs">
    <button class="xl-tab xl-tab-active" data-tab="home">Home</button>
    <button class="xl-tab" data-tab="insert">Insert</button>
    <button class="xl-tab" data-tab="formulas">Formulas</button>
    <button class="xl-tab" data-tab="data">Data</button>
    <button class="xl-tab" data-tab="view">View</button>
  </div>

  {{-- ── HOME PANEL ── --}}
  <div class="xl-panel" id="panel-home">
    <div class="xl-group xl-group-wide">
      <div class="xl-group-body xl-home-panel">

        <div class="xl-fx-category">
          <div class="xl-fx-category-label">Clipboard</div>
          <div class="xl-ribbon-icon-row">
            <button class="xl-ribbon-icon xl-ribbon-icon-wide xl-home-chip-primary" id="btn-paste" title="Paste (Ctrl+V)">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><rect x="5" y="2" width="8" height="2" rx=".5" stroke="currentColor" stroke-width="1.2" fill="none"/><path d="M5 2H4a1 1 0 00-1 1v10a1 1 0 001 1h8a1 1 0 001-1V5H8" stroke="currentColor" stroke-width="1.2" fill="none"/><rect x="4" y="7" width="6" height="6" rx=".5" fill="currentColor" opacity=".12" stroke="currentColor" stroke-width="1.2"/></svg>
              <span>Paste</span>
            </button>
            <button class="xl-ribbon-icon" id="btn-cut" title="Cut (Ctrl+X)">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><circle cx="4" cy="12" r="2" stroke="currentColor" stroke-width="1.4" fill="none"/><circle cx="12" cy="12" r="2" stroke="currentColor" stroke-width="1.4" fill="none"/><path d="M4 10L8 7m4 3L8 7M8 7V2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            </button>
            <button class="xl-ribbon-icon" id="btn-copy" title="Copy (Ctrl+C)">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><rect x="5" y="5" width="9" height="9" rx="1" stroke="currentColor" stroke-width="1.4" fill="none"/><path d="M5 5V3a1 1 0 011-1h7a1 1 0 011 1v9a1 1 0 01-1 1h-2" stroke="currentColor" stroke-width="1.4" fill="none"/></svg>
            </button>
            <button class="xl-ribbon-icon" id="btn-paste-values" title="Paste values only">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M2 4h8v8H2z" stroke="currentColor" stroke-width="1.3" fill="none"/><path d="M6 2h8v8h-2" stroke="currentColor" stroke-width="1.3" fill="none" stroke-dasharray="2 1"/><path d="M4.5 8l1.5 1.5 3-3" stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round"/></svg>
            </button>
          </div>
        </div>

        <div class="xl-fx-category xl-home-font">
          <div class="xl-fx-category-label">Font</div>
          <div class="xl-home-block">
            <div class="xl-home-row">
              <select id="font-family" class="xl-combo xl-combo-font" title="Font">
                <option value="Calibri">Calibri</option>
                <option value="Arial">Arial</option>
                <option value="'Times New Roman'">Times New Roman</option>
                <option value="'Courier New'">Courier New</option>
                <option value="Verdana">Verdana</option>
                <option value="Georgia">Georgia</option>
                <option value="Tahoma">Tahoma</option>
              </select>
              <select id="font-size" class="xl-combo xl-combo-size" title="Font Size">
                <option>8</option><option>9</option><option>10</option>
                <option selected>11</option><option>12</option><option>14</option>
                <option>16</option><option>18</option><option>20</option>
                <option>24</option><option>28</option><option>36</option>
              </select>
              <button id="btn-inc-font" class="xl-home-mini-btn" title="Increase font size">A+</button>
              <button id="btn-dec-font" class="xl-home-mini-btn" title="Decrease font size">A−</button>
            </div>
            <div class="xl-home-row">
              <div class="xl-ribbon-icon-row">
                <button id="btn-bold"          class="xl-ribbon-icon xl-toggle" data-key="bold"          title="Bold (Ctrl+B)"><b>B</b></button>
                <button id="btn-italic"        class="xl-ribbon-icon xl-toggle" data-key="italic"        title="Italic (Ctrl+I)"><i>I</i></button>
                <button id="btn-underline"     class="xl-ribbon-icon xl-toggle" data-key="underline"     title="Underline (Ctrl+U)"><u>U</u></button>
                <button id="btn-strikethrough" class="xl-ribbon-icon xl-toggle" data-key="strikethrough" title="Strikethrough"><s>S</s></button>
              </div>
              <div class="xl-color-btn" title="Font color">
                <div class="xl-color-label" id="font-color-preview">
                  <span>A</span>
                  <div class="xl-color-bar" id="font-color-bar" style="background:#000"></div>
                </div>
                <button class="xl-color-drop" id="font-color-picker-btn">▾</button>
              </div>
              <div class="xl-color-btn" title="Fill color">
                <div class="xl-color-label" id="fill-color-preview">
                  <svg viewBox="0 0 12 14" class="xl-fill-icon"><path d="M2 12h8M4 10L1 7l5-7 5 7-3 3z" stroke="currentColor" stroke-width="1.2" fill="none" stroke-linejoin="round"/></svg>
                  <div class="xl-color-bar" id="fill-color-bar" style="background:#FFFF00"></div>
                </div>
                <button class="xl-color-drop" id="fill-color-picker-btn">▾</button>
              </div>
              <input type="color" id="font-color" value="#000000" style="display:none">
              <input type="color" id="fill-color" value="#FFFF00" style="display:none">
              <button id="btn-borders" class="xl-ribbon-icon" title="Cell borders">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><rect x="2" y="2" width="12" height="12" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M2 8h12M8 2v12" stroke="currentColor" stroke-width="1" opacity=".5"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div class="xl-fx-category">
          <div class="xl-fx-category-label">Alignment</div>
          <div class="xl-home-block">
            <div class="xl-ribbon-icon-row">
              <button id="btn-align-left"   class="xl-ribbon-icon xl-toggle" title="Align left">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M2 4h12M2 7h8M2 10h12M2 13h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              </button>
              <button id="btn-align-center" class="xl-ribbon-icon xl-toggle" title="Align center">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M2 4h12M4 7h8M2 10h12M5 13h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              </button>
              <button id="btn-align-right"  class="xl-ribbon-icon xl-toggle" title="Align right">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M2 4h12M6 7h8M2 10h12M8 13h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
              </button>
              <span class="xl-ribbon-icon-sep"></span>
              <button id="btn-valign-top" class="xl-ribbon-icon xl-toggle" title="Align top">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M2 3h12M5 6v7m6-7v7M5 6h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" fill="none"/></svg>
              </button>
              <button id="btn-valign-mid" class="xl-ribbon-icon xl-toggle" title="Align middle">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M2 8h12M5 4v8m6-8v8M5 4h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" fill="none"/></svg>
              </button>
              <button id="btn-valign-bot" class="xl-ribbon-icon xl-toggle" title="Align bottom">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M2 13h12M5 3v7m6-7v7M5 3h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" fill="none"/></svg>
              </button>
              <span class="xl-ribbon-icon-sep"></span>
              <button id="btn-wrap-text" class="xl-ribbon-icon xl-toggle" data-key="wrap" title="Wrap text">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M2 4h12M2 8h8a2 2 0 010 4H6m0 0l2-2m-2 2l2 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
              </button>
              <button id="btn-merge" class="xl-ribbon-icon" title="Merge cells">
                <svg viewBox="0 0 16 16" class="xl-icon-sm"><rect x="2" y="4" width="12" height="8" stroke="currentColor" stroke-width="1.4" fill="none"/><path d="M8 4v8" stroke="currentColor" stroke-width="1" opacity=".4"/><path d="M5 8h6m-3-2l-2 2 2 2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" fill="none"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div class="xl-fx-category xl-home-number">
          <div class="xl-fx-category-label">Number</div>
          <div class="xl-home-block">
            <div class="xl-home-row">
              <select id="number-format" class="xl-combo xl-combo-num" title="Number Format">
                <option value="">General</option>
                <option value="integer">Number (0)</option>
                <option value="decimal2">Number (0.00)</option>
                <option value="currency">Currency ($)</option>
                <option value="percent">Percentage (%)</option>
                <option value="date">Short Date</option>
                <option value="scientific">Scientific</option>
              </select>
            </div>
            <div class="xl-ribbon-icon-row">
              <button id="btn-fmt-currency" class="xl-ribbon-icon" title="Currency format">$</button>
              <button id="btn-fmt-percent"  class="xl-ribbon-icon" title="Percent format">%</button>
              <button id="btn-fmt-comma"    class="xl-ribbon-icon" title="Comma format">,</button>
              <button id="btn-dec-inc" class="xl-ribbon-icon xl-ribbon-icon-wide" title="Increase decimals">.0→.00</button>
              <button id="btn-dec-dec" class="xl-ribbon-icon xl-ribbon-icon-wide" title="Decrease decimals">.00→.0</button>
            </div>
          </div>
        </div>

        <div class="xl-fx-category">
          <div class="xl-fx-category-label">Cells</div>
          <div class="xl-fx-chips">
            <button class="xl-fn-chip" id="btn-ins-row" title="Insert row above">Insert Row</button>
            <button class="xl-fn-chip xl-ins-chip-warn" id="btn-del-row" title="Delete row">Delete Row</button>
            <button class="xl-fn-chip" id="btn-ins-col" title="Insert column left">Insert Col</button>
            <button class="xl-fn-chip xl-ins-chip-warn" id="btn-del-col" title="Delete column">Delete Col</button>
          </div>
        </div>

        <div class="xl-fx-category">
          <div class="xl-fx-category-label">Editing</div>
          <div class="xl-ribbon-icon-row">
            <button class="xl-ribbon-icon xl-ribbon-icon-wide xl-home-chip-primary" id="btn-autosum" title="AutoSum">
              <span class="xl-fx-sigma">Σ</span><span>Sum</span>
            </button>
            <button class="xl-ribbon-icon" id="btn-fill-down" title="Fill down (Ctrl+D)">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M8 3v10m-3-4l3 4 3-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
            </button>
            <button class="xl-ribbon-icon" id="btn-fill-right" title="Fill right (Ctrl+R)">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M3 8h10m-4-3l4 3-4 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
            </button>
            <button class="xl-ribbon-icon xl-ins-chip-warn" id="btn-clear" title="Clear contents">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            </button>
          </div>
        </div>

        <div class="xl-fx-category">
          <div class="xl-fx-category-label">Find</div>
          <div class="xl-ribbon-icon-row">
            <button class="xl-ribbon-icon xl-ribbon-icon-wide" id="btn-find" title="Find (Ctrl+F)">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><circle cx="6.5" cy="6.5" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M10 10l3.5 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              <span>Find</span>
            </button>
            <button class="xl-ribbon-icon xl-ribbon-icon-wide" id="btn-replace" title="Replace (Ctrl+H)">
              <svg viewBox="0 0 16 16" class="xl-icon-sm"><path d="M3 5a3 3 0 014 0M9 11a3 3 0 01-4 0" stroke="currentColor" stroke-width="1.4" fill="none"/><path d="M9 6l2-1v6l-2-1m-5 0L2 12V6l2 1" stroke="currentColor" stroke-width="1.3" fill="none" stroke-linecap="round"/></svg>
              <span>Replace</span>
            </button>
          </div>
        </div>

      </div>
      <div class="xl-group-label">Home</div>
    </div>
  </div>

  {{-- ── INSERT PANEL ── --}}
  <div class="xl-panel xl-panel-hidden" id="panel-insert">
    <div class="xl-group xl-group-wide">
      <div class="xl-group-body xl-fx-panel">
        <div class="xl-fx-actions">
          <button class="xl-fx-main-btn" id="btn-named-range" title="Create a named range from selection">
            <svg viewBox="0 0 20 20" class="xl-icon-sm"><path d="M10 2l2.2 4.5 4.9.7-3.5 3.4.8 4.9L10 13.8 5.6 15.5l.8-4.9L3 7.2l4.9-.7L10 2z" stroke="currentColor" stroke-width="1.2" fill="none" stroke-linejoin="round"/></svg>
            Named Range
          </button>
        </div>
        <div class="xl-fx-library">
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Rows</div>
            <div class="xl-fx-chips">
              <button class="xl-fn-chip" id="btn-ins-row-above" title="Insert row above selection">Insert Above</button>
              <button class="xl-fn-chip" id="btn-ins-row-below" title="Insert row below selection">Insert Below</button>
              <button class="xl-fn-chip xl-ins-chip-warn" id="btn-del-row2" title="Delete selected rows">Delete Rows</button>
            </div>
          </div>
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Columns</div>
            <div class="xl-fx-chips">
              <button class="xl-fn-chip" id="btn-ins-col-left" title="Insert column to the left">Insert Left</button>
              <button class="xl-fn-chip" id="btn-ins-col-right" title="Insert column to the right">Insert Right</button>
              <button class="xl-fn-chip xl-ins-chip-warn" id="btn-del-col2" title="Delete selected columns">Delete Columns</button>
            </div>
          </div>
        </div>
      </div>
      <div class="xl-group-label">Insert</div>
    </div>
  </div>

  {{-- ── FORMULAS PANEL ── --}}
  <div class="xl-panel xl-panel-hidden" id="panel-formulas">
    <div class="xl-group xl-group-wide">
      <div class="xl-group-body xl-fx-panel">
        <div class="xl-fx-actions">
          <button class="xl-fx-main-btn" id="btn-insert-fn" title="Insert Function">
            <svg viewBox="0 0 20 20" class="xl-icon-sm"><path d="M6 2h8l3 3v13a1 1 0 01-1 1H6a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.3" fill="none"/><path d="M9 7h6M9 11h6M9 15h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            Insert Function
          </button>
          <button class="xl-fx-main-btn xl-fx-main-btn-accent" id="btn-autosum2" title="AutoSum">
            <span class="xl-fx-sigma">Σ</span> AutoSum
          </button>
        </div>
        <div class="xl-fx-library">
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Aggregate</div>
            <div class="xl-fx-chips">
              <button class="xl-fn-chip" data-formula="=SUM()" title="Sum of values">Sum</button>
              <button class="xl-fn-chip" data-formula="=AVERAGE()" title="Average of values">Average</button>
              <button class="xl-fn-chip" data-formula="=COUNT()" title="Count numbers">Count</button>
              <button class="xl-fn-chip" data-formula="=COUNTA()" title="Count non-empty">CountA</button>
              <button class="xl-fn-chip" data-formula="=MIN()" title="Minimum value">Min</button>
              <button class="xl-fn-chip" data-formula="=MAX()" title="Maximum value">Max</button>
            </div>
          </div>
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Logic &amp; Math</div>
            <div class="xl-fx-chips">
              <button class="xl-fn-chip" data-formula="=IF(,,)" title="IF(condition, true, false)">IF</button>
              <button class="xl-fn-chip" data-formula="=IFERROR(,)" title="IFERROR(value, fallback)">IfError</button>
              <button class="xl-fn-chip" data-formula="=ROUND(,2)" title="ROUND(number, digits)">Round</button>
              <button class="xl-fn-chip" data-formula="=ABS()" title="Absolute value">Abs</button>
            </div>
          </div>
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Text &amp; Date</div>
            <div class="xl-fx-chips">
              <button class="xl-fn-chip" data-formula="=CONCAT(,)" title="Concatenate text">Concat</button>
              <button class="xl-fn-chip" data-formula="=TODAY()" title="Current date">Today</button>
            </div>
          </div>
        </div>
      </div>
      <div class="xl-group-label">Formulas</div>
    </div>
  </div>

  {{-- ── DATA PANEL ── --}}
  <div class="xl-panel xl-panel-hidden" id="panel-data">
    <div class="xl-group xl-group-wide">
      <div class="xl-group-body xl-fx-panel">
        <div class="xl-fx-actions">
          <button class="xl-fx-main-btn" id="btn-sort-dialog" title="Open custom sort dialog">
            <svg viewBox="0 0 20 20" class="xl-icon-sm"><path d="M3 5h14M6 10h8M9 15h2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            Custom Sort
          </button>
        </div>
        <div class="xl-fx-library">
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Sort</div>
            <div class="xl-fx-chips">
              <button class="xl-fn-chip" id="btn-sort-asc" title="Sort A to Z">A → Z</button>
              <button class="xl-fn-chip" id="btn-sort-desc" title="Sort Z to A">Z → A</button>
            </div>
          </div>
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Filter</div>
            <div class="xl-fx-chips">
              <button class="xl-fn-chip xl-toggle" id="btn-filter" title="Toggle filter arrows">Filter</button>
              <button class="xl-fn-chip xl-ins-chip-warn" id="btn-clear-filter" title="Clear all filters">Clear Filters</button>
            </div>
          </div>
        </div>
      </div>
      <div class="xl-group-label">Data</div>
    </div>
  </div>

  {{-- ── VIEW PANEL ── --}}
  <div class="xl-panel xl-panel-hidden" id="panel-view">
    <div class="xl-group xl-group-wide">
      <div class="xl-group-body xl-fx-panel">
        <div class="xl-fx-actions xl-ribbon-zoom">
          <div class="xl-ribbon-zoom-label">Zoom</div>
          <div class="xl-ribbon-zoom-controls">
            <button id="btn-zoom-out" class="xl-ribbon-zoom-btn" title="Zoom out">−</button>
            <input id="zoom-slider" type="range" min="50" max="200" step="10" value="100" class="xl-zoom-slider">
            <button id="btn-zoom-in" class="xl-ribbon-zoom-btn" title="Zoom in">+</button>
          </div>
          <span id="zoom-pct" class="xl-zoom-pct">100%</span>
        </div>
        <div class="xl-fx-library">
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Freeze Panes</div>
            <div class="xl-fx-chips">
              <button class="xl-fn-chip xl-toggle" id="btn-freeze-row" title="Freeze top row">Freeze Top Row</button>
              <button class="xl-fn-chip xl-toggle" id="btn-freeze-col" title="Freeze first column">Freeze First Column</button>
              <button class="xl-fn-chip" id="btn-freeze-none" title="Remove all frozen panes">Unfreeze All</button>
            </div>
          </div>
          <div class="xl-fx-category">
            <div class="xl-fx-category-label">Display</div>
            <div class="xl-fx-chips">
              <label class="xl-fn-chip xl-chip-check" title="Show gridlines">
                <input type="checkbox" id="chk-gridlines" checked> Gridlines
              </label>
              <label class="xl-fn-chip xl-chip-check" title="Show row and column headers">
                <input type="checkbox" id="chk-headers" checked> Headers
              </label>
              <label class="xl-fn-chip xl-chip-check" title="Show formulas in cells">
                <input type="checkbox" id="chk-formulas"> Show Formulas
              </label>
            </div>
          </div>
        </div>
      </div>
      <div class="xl-group-label">View</div>
    </div>
  </div>
</div>

{{-- ══════════════ FORMULA BAR ══════════════ --}}
<div class="xl-fxbar">
  <div class="xl-namebox-wrap">
    <input id="name-box" class="xl-namebox" type="text" value="A1" spellcheck="false" autocomplete="off">
    <button class="xl-namebox-drop" title="Navigate to named range">▾</button>
  </div>
  <div class="xl-fxbar-sep"></div>
  <button id="btn-fx-cancel" class="xl-fxbtn xl-fxbtn-cancel" style="display:none" title="Cancel (Esc)">
    <svg viewBox="0 0 12 12"><path d="M2 2l8 8M10 2L2 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
  </button>
  <button id="btn-fx-commit" class="xl-fxbtn xl-fxbtn-commit" style="display:none" title="Enter">
    <svg viewBox="0 0 12 12"><path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
  </button>
  <span class="xl-fx-label">
    <svg viewBox="0 0 20 14"><text x="0" y="11" font-size="13" font-style="italic" fill="#217346" font-family="Georgia,serif">fx</text></svg>
  </span>
  <input id="formula-bar" class="xl-formula-input" type="text" spellcheck="false" autocomplete="off">
  <div id="autocomplete-list" class="xl-ac-list" style="display:none"></div>
</div>

{{-- ══════════════ COL HEADER BAR (always visible) ══════════════ --}}
<div id="col-header-bar" class="xl-col-header-bar">
  <table class="xl-grid">
    <thead><tr id="col-headers"></tr></thead>
  </table>
</div>

{{-- ══════════════ GRID ══════════════ --}}
<div id="grid-scroll" class="xl-grid-scroll">
  <div id="grid-inner" class="xl-grid-inner">
    <div id="fill-handle" class="xl-fill-handle" style="display:none"></div>
    <table id="spreadsheet-grid" class="xl-grid">
      <tbody id="grid-body"></tbody>
    </table>
  </div>
</div>

{{-- ══════════════ BOTTOM ══════════════ --}}
<div class="xl-bottom">
  <div class="xl-sheetbar">
    <button id="btn-add-sheet" class="xl-new-sheet" title="New sheet">
      <svg viewBox="0 0 16 16"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>
    <div id="sheet-tabs" class="xl-sheet-tabs"></div>
  </div>
  <div class="xl-statusbar">
    <span id="status-mode" class="xl-status-mode">Ready</span>
    <span class="xl-status-spacer"></span>
    <span id="status-avg"   class="xl-stat"></span>
    <span id="status-count" class="xl-stat"></span>
    <span id="status-sum"   class="xl-stat"></span>
    <span class="xl-status-sep"></span>
    <span id="zoom-label" class="xl-stat">100%</span>
  </div>
</div>

{{-- ══════════════ CONTEXT MENU ══════════════ --}}
<div id="ctx-menu" class="xl-ctx" style="display:none">
  <div class="xl-ctx-item" data-action="edit-cell">
    <svg viewBox="0 0 16 16"><path d="M3 13h10M11 3l2 2-7 7H4V10l7-7z" stroke="currentColor" stroke-width="1.3" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Edit Cell
    <span class="xl-ctx-shortcut">F2</span>
  </div>
  <div class="xl-ctx-sep"></div>
  <div class="xl-ctx-item" data-action="cut">
    <svg viewBox="0 0 16 16"><circle cx="4" cy="12" r="2" stroke="currentColor" stroke-width="1.3" fill="none"/><circle cx="12" cy="12" r="2" stroke="currentColor" stroke-width="1.3" fill="none"/><path d="M4 10L8 7m4 3L8 7M8 7V2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
    Cut
    <span class="xl-ctx-shortcut">Ctrl+X</span>
  </div>
  <div class="xl-ctx-item" data-action="copy">
    <svg viewBox="0 0 16 16"><rect x="5" y="5" width="9" height="9" rx="1" stroke="currentColor" stroke-width="1.3" fill="none"/><path d="M5 5V3a1 1 0 011-1h7a1 1 0 011 1v9a1 1 0 01-1 1h-2" stroke="currentColor" stroke-width="1.3" fill="none"/></svg>
    Copy
    <span class="xl-ctx-shortcut">Ctrl+C</span>
  </div>
  <div class="xl-ctx-item" data-action="paste">
    <svg viewBox="0 0 16 16"><rect x="3" y="6" width="10" height="9" rx="1" stroke="currentColor" stroke-width="1.3" fill="none"/><path d="M6 6V4a1 1 0 011-1h2a1 1 0 011 1v2" stroke="currentColor" stroke-width="1.3" fill="none"/></svg>
    Paste
    <span class="xl-ctx-shortcut">Ctrl+V</span>
  </div>
  <div class="xl-ctx-item" data-action="paste-values">
    <svg viewBox="0 0 16 16"><path d="M2 4h8v8H2z" stroke="currentColor" stroke-width="1.2" fill="none"/><path d="M4.5 8l1.5 1.5 3-3" stroke="#107C41" stroke-width="1.3" fill="none" stroke-linecap="round"/></svg>
    Paste Values
  </div>
  <div class="xl-ctx-sep"></div>
  <div class="xl-ctx-item" data-action="undo">
    ↶ Undo
    <span class="xl-ctx-shortcut">Ctrl+Z</span>
  </div>
  <div class="xl-ctx-item" data-action="redo">
    ↷ Redo
    <span class="xl-ctx-shortcut">Ctrl+Y</span>
  </div>
  <div class="xl-ctx-sep"></div>
  <div class="xl-ctx-item" data-action="merge-cells">⊞ Merge Cells</div>
  <div class="xl-ctx-item" data-action="unmerge-cells">⊟ Unmerge Cells</div>
  <div class="xl-ctx-sep"></div>
  <div class="xl-ctx-item" data-action="bold"><b>B</b> Bold <span class="xl-ctx-shortcut">Ctrl+B</span></div>
  <div class="xl-ctx-item" data-action="italic"><i>I</i> Italic <span class="xl-ctx-shortcut">Ctrl+I</span></div>
  <div class="xl-ctx-item" data-action="underline"><u>U</u> Underline <span class="xl-ctx-shortcut">Ctrl+U</span></div>
  <div class="xl-ctx-sep"></div>
  <div class="xl-ctx-item" data-action="insert-row-above">↑ Insert Row Above</div>
  <div class="xl-ctx-item" data-action="insert-row-below">↓ Insert Row Below</div>
  <div class="xl-ctx-item" data-action="delete-row">✕ Delete Rows</div>
  <div class="xl-ctx-sep"></div>
  <div class="xl-ctx-item" data-action="insert-col-left">← Insert Column Left</div>
  <div class="xl-ctx-item" data-action="insert-col-right">→ Insert Column Right</div>
  <div class="xl-ctx-item" data-action="delete-col">✕ Delete Columns</div>
  <div class="xl-ctx-sep"></div>
  <div class="xl-ctx-item" data-action="clear">
    <svg viewBox="0 0 16 16"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
    Clear Contents
    <span class="xl-ctx-shortcut">Del</span>
  </div>
  <div class="xl-ctx-item" data-action="fill-down">↓ Fill Down</div>
  <div class="xl-ctx-item" data-action="fill-right">→ Fill Right</div>
  <div class="xl-ctx-sep"></div>
  <div class="xl-ctx-item" data-action="find">🔍 Find…<span class="xl-ctx-shortcut">Ctrl+F</span></div>
  <div class="xl-ctx-item" data-action="replace">🔁 Replace…<span class="xl-ctx-shortcut">Ctrl+H</span></div>
</div>

{{-- ══════════════ MODALS ══════════════ --}}
{{-- Find / Replace --}}
<div id="modal-find" class="xl-modal" style="display:none">
  <div class="xl-modal-box">
    <div class="xl-modal-hdr">
      <span id="find-title" class="xl-modal-title">Find</span>
      <button class="xl-modal-x" data-close="modal-find">✕</button>
    </div>
    <div class="xl-modal-body">
      <div class="xl-field-row"><label>Find what</label><input id="find-input" class="xl-field" type="text" autocomplete="off"></div>
      <div id="replace-row" class="xl-field-row" style="display:none"><label>Replace with</label><input id="replace-input" class="xl-field" type="text"></div>
      <div class="xl-find-opts">
        <label class="xl-check-row"><input type="checkbox" id="find-case"> Match case</label>
        <label class="xl-check-row"><input type="checkbox" id="find-whole"> Entire cell only</label>
        <label class="xl-check-row"><input type="checkbox" id="find-formulas"> Look in formulas</label>
      </div>
      <div id="find-result" class="xl-find-result"></div>
    </div>
    <div class="xl-modal-ftr">
      <button id="btn-find-prev"    class="xl-mbtn">◂ Previous</button>
      <button id="btn-find-next"    class="xl-mbtn xl-mbtn-primary">Find Next ▸</button>
      <button id="btn-replace-one"  class="xl-mbtn" style="display:none">Replace</button>
      <button id="btn-replace-all"  class="xl-mbtn" style="display:none">Replace All</button>
      <button data-close="modal-find" class="xl-mbtn">Close</button>
    </div>
  </div>
</div>

{{-- Sort Dialog --}}
<div id="modal-sort" class="xl-modal" style="display:none">
  <div class="xl-modal-box xl-modal-sm">
    <div class="xl-modal-hdr">
      <span class="xl-modal-title">Sort</span>
      <button class="xl-modal-x" data-close="modal-sort">✕</button>
    </div>
    <div class="xl-modal-body">
      <div class="xl-field-row"><label>Sort by column</label><select id="sort-col" class="xl-field"></select></div>
      <div class="xl-field-row"><label>Order</label>
        <select id="sort-dir" class="xl-field">
          <option value="asc">A → Z (Ascending)</option>
          <option value="desc">Z → A (Descending)</option>
        </select>
      </div>
    </div>
    <div class="xl-modal-ftr">
      <button id="btn-sort-ok" class="xl-mbtn xl-mbtn-primary">Sort</button>
      <button data-close="modal-sort" class="xl-mbtn">Cancel</button>
    </div>
  </div>
</div>

{{-- Named Range --}}
<div id="modal-named" class="xl-modal" style="display:none">
  <div class="xl-modal-box xl-modal-sm">
    <div class="xl-modal-hdr">
      <span class="xl-modal-title">New Name</span>
      <button class="xl-modal-x" data-close="modal-named">✕</button>
    </div>
    <div class="xl-modal-body">
      <div class="xl-field-row"><label>Name</label><input id="named-name" class="xl-field" type="text" placeholder="e.g. Revenue"></div>
      <div class="xl-field-row"><label>Refers to</label><input id="named-range" class="xl-field" type="text" placeholder="=Sheet1!A1:B10"></div>
    </div>
    <div class="xl-modal-ftr">
      <button id="btn-named-ok" class="xl-mbtn xl-mbtn-primary">OK</button>
      <button data-close="modal-named" class="xl-mbtn">Cancel</button>
    </div>
  </div>
</div>

{{-- Border picker --}}
<div id="border-picker-panel" class="xl-borderpanel" style="display:none">
  <div class="xl-borderpanel-title">Borders</div>
  <div class="xl-border-grid">
    <button type="button" class="xl-border-opt" data-border="bottom" title="Bottom border"><span class="xl-border-icon xl-bi-bottom"></span></button>
    <button type="button" class="xl-border-opt" data-border="top" title="Top border"><span class="xl-border-icon xl-bi-top"></span></button>
    <button type="button" class="xl-border-opt" data-border="left" title="Left border"><span class="xl-border-icon xl-bi-left"></span></button>
    <button type="button" class="xl-border-opt" data-border="none" title="No border"><span class="xl-border-icon xl-bi-none">&#10005;</span></button>
    <button type="button" class="xl-border-opt" data-border="all" title="All borders"><span class="xl-border-icon xl-bi-all"></span></button>
    <button type="button" class="xl-border-opt" data-border="outer" title="Outside borders"><span class="xl-border-icon xl-bi-outer"></span></button>
    <button type="button" class="xl-border-opt" data-border="right" title="Right border"><span class="xl-border-icon xl-bi-right"></span></button>
  </div>
</div>

{{-- Color picker overlay --}}
<div id="color-picker-panel" class="xl-colorpanel" style="display:none">
  <div class="xl-colorpanel-title" id="color-picker-title">Color</div>
  <div class="xl-color-swatches" id="color-swatches"></div>
  <div class="xl-colorpanel-custom">
    <input type="color" id="color-custom" value="#000000">
    <button id="btn-color-custom" class="xl-mbtn xl-mbtn-primary" style="padding:3px 8px;font-size:11px">Custom…</button>
  </div>
</div>

{{-- Toast container --}}
<div id="toast-wrap" class="xl-toasts"></div>

</div>{{-- #app --}}
</div>{{-- .app-page --}}

<script src="{{ asset('js/spreadsheet.js') }}?v=30" defer></script>
@endsection

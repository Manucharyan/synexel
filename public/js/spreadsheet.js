/* Synexel Spreadsheet Engine v21 */
(function(){
'use strict';

const APP  = document.getElementById('app');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const tok  = document.querySelector('meta[name="api-token"]')?.content;
if(!APP||!tok) return;

/* ─── API ─── */
async function api(path,opts={}){
  const isForm=opts.body instanceof FormData;
  const h={
    'X-Requested-With':'XMLHttpRequest',
    Accept:'application/json',
    Authorization:'Bearer '+tok,
    ...(csrf?{'X-CSRF-TOKEN':csrf}:{}),
    ...(opts.headers||{}),
  };
  if(!isForm)h['Content-Type']='application/json';
  const r=await fetch('/api/v1'+path,{
    credentials:'same-origin',method:opts.method||'GET',headers:h,
    body:isForm?opts.body:opts.body?JSON.stringify(opts.body):undefined,
  });
  if(!r.ok)throw new Error(await r.text());
  const ct=r.headers.get('content-type')||'';
  return ct.includes('json')?r.json():r;
}

async function dlBlob(path,name){
  const r=await fetch('/api/v1'+path,{headers:{Authorization:'Bearer '+tok}});
  if(!r.ok)throw new Error('Download failed');
  const a=document.createElement('a');
  a.href=URL.createObjectURL(await r.blob());
  a.download=name;
  document.body.appendChild(a);a.click();a.remove();
}

/* ─── cell ref helpers ─── */
const colLetter=n=>{let l='';while(n>0){n--;l=String.fromCharCode(65+n%26)+l;n=(n/26)|0;}return l;};
const a1ToRC=ref=>{const m=ref.match(/^([A-Za-z]+)(\d+)$/);if(!m)return null;let c=0;for(const ch of m[1].toUpperCase())c=c*26+ch.charCodeAt(0)-64;return{r:+m[2],c};};
const cellStyle=st=>(st&&typeof st==='object'&&!Array.isArray(st))?st:{};
const parseRange=a1=>{
  const parts=a1.toUpperCase().split(':');
  const a=a1ToRC(parts[0]);const b=a1ToRC(parts[1]||parts[0]);
  if(!a||!b)return null;
  return{r1:Math.min(a.r,b.r),c1:Math.min(a.c,b.c),r2:Math.max(a.r,b.r),c2:Math.max(a.c,b.c)};
};
const rangeA1=(r1,c1,r2,c2)=>r1===r2&&c1===c2?colLetter(c1)+r1:colLetter(c1)+r1+':'+colLetter(c2)+r2;
const rangesOverlap=(a,b)=>!(a.r2<b.r1||a.r1>b.r2||a.c2<b.c1||a.c1>b.c2);
const BORDER_LINE='2px solid #000000';
const BORDER_COLOR='#000000';
const BORDER_WIDTH=2;

/* ─── colour swatches ─── */
const SWATCHES=[
  '#000000','#242424','#595959','#808080','#A6A6A6','#BFBFBF','#D9D9D9','#EDEDED','#F2F2F2','#FFFFFF',
  '#FF0000','#FF7F00','#FFFF00','#00FF00','#00FFFF','#0000FF','#7F00FF','#FF00FF','#FF007F','#7F3300',
  '#C00000','#E26B0A','#FFCC00','#70AD47','#00B0F0','#0070C0','#7030A0','#FF33CC','#00B050','#4472C4',
  '#FF9999','#FFCC99','#FFFF99','#CCFFCC','#CCFFFF','#99CCFF','#CCCCFF','#FF99FF','#99FFCC','#C9C9FF',
  '#7F0000','#7F3F00','#7F7F00','#003300','#003333','#00007F','#33004C','#7F0033','#004C33','#1F3864',
];

/* ─── formula autocomplete ─── */
const FNS=[
  ['SUM','Sum of numbers'],['AVERAGE','Average'],['MIN','Minimum'],['MAX','Maximum'],
  ['COUNT','Count numbers'],['COUNTA','Count non-empty'],['COUNTIF','Count with condition'],
  ['SUMIF','Sum with condition'],['AVERAGEIF','Avg with condition'],
  ['IF','IF(test,true,false)'],['AND','Logical AND'],['OR','Logical OR'],['NOT','Logical NOT'],
  ['IFERROR','IFERROR(val,fallback)'],['ISERROR','Test for error'],['ISBLANK','Test if empty'],
  ['ROUND','ROUND(n,digits)'],['ROUNDUP','Round up'],['ROUNDDOWN','Round down'],
  ['ABS','Absolute value'],['POWER','POWER(base,exp)'],['SQRT','Square root'],
  ['MOD','Modulo'],['INT','Integer part'],['CEILING','Ceil to multiple'],['FLOOR','Floor to multiple'],
  ['MEDIAN','Middle value'],['STDEV','Standard deviation'],['VAR','Variance'],
  ['CONCAT','Concatenate'],['LEN','Text length'],['LEFT','Left N chars'],['RIGHT','Right N chars'],
  ['MID','MID(text,start,len)'],['UPPER','Uppercase'],['LOWER','Lowercase'],['TRIM','Strip spaces'],
  ['SUBSTITUTE','Replace text'],['FIND','Find text position'],['TEXT','Format as text'],
  ['TODAY','Current date'],['NOW','Current datetime'],['YEAR','Year'],['MONTH','Month'],['DAY','Day'],
  ['DATE','DATE(y,m,d)'],['DATEVALUE','Convert to date'],
  ['VLOOKUP','Vertical lookup'],['HLOOKUP','Horizontal lookup'],
  ['INDEX','INDEX(range,row,col)'],['MATCH','MATCH(val,range,type)'],
  ['OFFSET','Offset range'],['INDIRECT','Ref from text'],
];

/* ═══════════════════════════════════════════
   SynexelApp
   ═══════════════════════════════════════════ */
class SynexelApp{
  constructor(){
    this.wbId      = APP.dataset.workbookId;
    this.sheets    = JSON.parse(APP.dataset.sheets||'[]');
    this.activeSht = this.sheets[0]?.id??null;
    this.readOnly  = APP.dataset.access === 'read';
    this.isOwner   = APP.dataset.isOwner === '1';
    this.lastSyncAt = new Date().toISOString();
    this.lastOwnOp  = null;
    this.presenceTimer = null;
    this.syncTimer = null;

    this.ROWS=200; this.COLS=26;

    this.cells    = new Map();
    this.cellEls  = [];           // [r-1][c-1]→<td>
    this.colW     = Array(this.COLS).fill(80);
    this.rowH     = Array(this.ROWS).fill(20);

    this.sel      = {r1:1,c1:1,r2:1,c2:1};
    this.anchor   = {r:1,c:1};
    this.editing  = false;
    this.editMode = null; /* 'cell' | 'fx' */
    this.editAt   = null; /* {r,c} merge anchor while editing */
    this.clip     = null;
    this.copySrc  = null;
    this.undoStk  = []; this.redoStk=[];
    this.opChanges = new Map();
    this.pendingUpdates = new Map();
    this.saveTimer = null;
    this.saving = false;
    this.mergedCells = [];
    this.zoom     = 1;
    this.frozenR  = 0; this.frozenC=0;
    this.showFml  = false;
    this.findList = []; this.findIdx=-1;
    this.colorTarget=null; // 'font'|'fill'

    this.$={
      gridBody:  APP.querySelector('#grid-body'),
      colHdr:    APP.querySelector('#col-headers'),
      fxBar:     APP.querySelector('#formula-bar'),
      nameBox:   APP.querySelector('#name-box'),
      saveDot:   APP.querySelector('#save-dot'),
      saveStatus:APP.querySelector('#save-status'),
      sheetTabs: APP.querySelector('#sheet-tabs'),
      wbName:    APP.querySelector('#workbook-name'),
      statusMode:APP.querySelector('#status-mode'),
      statusAvg: APP.querySelector('#status-avg'),
      statusCnt: APP.querySelector('#status-count'),
      statusSum: APP.querySelector('#status-sum'),
      ctx:       APP.querySelector('#ctx-menu'),
      btnUndo:   APP.querySelector('#btn-undo'),
      btnRedo:   APP.querySelector('#btn-redo'),
      fillHnd:   APP.querySelector('#fill-handle'),
      acList:    APP.querySelector('#autocomplete-list'),
      gridWrap:  APP.querySelector('#grid-scroll'),
      gridInner: APP.querySelector('#grid-inner'),
      grid:      APP.querySelector('#spreadsheet-grid'),
      zoomSlider:APP.querySelector('#zoom-slider'),
      zoomPct:   APP.querySelector('#zoom-pct'),
      zoomLbl:   APP.querySelector('#zoom-label'),
      toasts:    APP.querySelector('#toast-wrap'),
      colorPanel:APP.querySelector('#color-picker-panel'),
      borderPanel:APP.querySelector('#border-picker-panel'),
      presenceBar:APP.querySelector('#presence-bar'),
    };

    if(this.readOnly) APP.classList.add('xl-readonly');

    this.buildGrid();
    this.renderTabs();
    this.initSwatches();
    this.initBorderPanel();
    this.bindAll();
    this.loadSheet();
    this.initCollaboration();
  }

  guardWrite(){
    if(this.readOnly){this.toast('This workbook is read-only','error');return false;}
    return true;
  }

  initCollaboration(){
    const beat=async()=>{
      try{
        const ac=this.activeCellCoords();
        const res=await api(`/workbooks/${this.wbId}/presence`,{
          method:'POST',
          body:{sheet_id:this.activeSht,row:ac.r,col:ac.c},
        });
        this.renderPresence(res.data||[]);
      }catch(_e){}
    };
    const sync=async()=>{
      try{
        const res=await api(`/workbooks/${this.wbId}/sync?since=${encodeURIComponent(this.lastSyncAt)}${this.lastOwnOp?'&exclude_operation='+encodeURIComponent(this.lastOwnOp):''}`);
        const ops=res.data||[];
        if(ops.length)this.applyRemoteOps(ops);
        this.lastSyncAt=new Date().toISOString();
      }catch(_e){}
    };
    beat();
    this.presenceTimer=setInterval(beat,15000);
    this.syncTimer=setInterval(sync,4000);
    window.addEventListener('beforeunload',()=>{
      fetch('/api/v1/workbooks/'+this.wbId+'/presence',{
        method:'DELETE',
        keepalive:true,
        headers:{Authorization:'Bearer '+tok,'X-Requested-With':'XMLHttpRequest',Accept:'application/json'},
      }).catch(()=>{});
    });
  }

  renderPresence(viewers){
    const bar=this.$.presenceBar;if(!bar)return;
    if(!viewers.length){bar.innerHTML='';return;}
    bar.innerHTML=viewers.map(v=>`<span class="xl-presence-chip" title="${v.email||v.name}">${(v.name||'?').split(' ').map(p=>p[0]).join('').slice(0,2)}</span>`).join('');
  }

  applyRemoteOps(ops){
    let touched=false;
    for(const op of ops){
      if(op.sheet_id!==this.activeSht)continue;
      for(const ch of (op.cells||[])){
        const after=ch.after;
        const k=this.key(ch.row,ch.col);
        if(!after){
          this.cells.delete(k);
        }else{
          this.cells.set(k,{
            row:ch.row,col:ch.col,
            value:after.raw_value,
            formula:after.formula,
            computed:after.computed_value,
            style:cellStyle(after.style),
          });
        }
        this.renderCell(ch.row,ch.col);
        touched=true;
      }
    }
    if(touched){
      this.toast('Sheet updated by a collaborator','info');
      this.refreshComputed().catch(()=>{});
    }
  }

  /* ── key helpers ── */
  key(r,c){return r+':'+c}
  norm(){return{
    r1:Math.min(this.sel.r1,this.sel.r2),c1:Math.min(this.sel.c1,this.sel.c2),
    r2:Math.max(this.sel.r1,this.sel.r2),c2:Math.max(this.sel.c1,this.sel.c2),
  }}
  inSel(r,c){const s=this.norm();return r>=s.r1&&r<=s.r2&&c>=s.c1&&c<=s.c2}
  cellBounds(r,c){
    const span=this.getMergeSpan(r,c);
    if(span)return{r1:r,c1:c,r2:r+span.rowspan-1,c2:c+span.colspan-1};
    return{r1:r,c1:c,r2:r,c2:c};
  }
  expandSelForMerges(){
    let{r1,c1,r2,c2}=this.norm();
    let changed=true;
    while(changed){
      changed=false;
      for(const m of this.mergedCells){
        const rng=parseRange(m);if(!rng)continue;
        if(!rangesOverlap(rng,{r1,c1,r2,c2}))continue;
        const nr1=Math.min(r1,rng.r1),nc1=Math.min(c1,rng.c1);
        const nr2=Math.max(r2,rng.r2),nc2=Math.max(c2,rng.c2);
        if(nr1!==r1||nc1!==c1||nr2!==r2||nc2!==c2){
          r1=nr1;c1=nc1;r2=nr2;c2=nc2;changed=true;
        }
      }
    }
    this.sel={r1,c1,r2,c2};
  }
  mergeAnchorCoords(r,c){
    for(const m of this.mergedCells){
      const rng=parseRange(m);if(!rng)continue;
      if(r>=rng.r1&&r<=rng.r2&&c>=rng.c1&&c<=rng.c2)return{r:rng.r1,c:rng.c1};
    }
    return null;
  }
  isMergeCovered(r,c){
    const a=this.mergeAnchorCoords(r,c);
    return!!(a&&(a.r!==r||a.c!==c));
  }
  getMergeSpan(r,c){
    for(const m of this.mergedCells){
      const rng=parseRange(m);if(!rng)continue;
      if(rng.r1===r&&rng.c1===c)return{rowspan:rng.r2-rng.r1+1,colspan:rng.c2-rng.c1+1};
    }
    return null;
  }
  cellEl(r,c){return this.cellEls[r-1]?.[c-1]??null}
  anchorCellEl(r,c){
    const a=this.mergeAnchorCoords(r,c);
    return this.cellEl(a?.r??r,a?.c??c);
  }
  resolveEventCell(e){
    let td=e.target?.closest?.('td[data-row]');
    if(!td&&e.clientX!=null){
      const el=document.elementFromPoint(e.clientX,e.clientY);
      td=el?.closest?.('td[data-row]');
    }
    if(!td)return null;
    let r=+td.dataset.row,c=+td.dataset.col;
    const a=this.mergeAnchorCoords(r,c);
    if(a){r=a.r;c=a.c;td=this.cellEl(r,c);}
    return td?{td,r,c}:null;
  }
  isActiveCell(r,c){
    const ac=this.activeCellCoords();
    const ma=this.mergeAnchorCoords(r,c);
    const rr=ma?ma.r:r, cc=ma?ma.c:c;
    return ac.r===rr&&ac.c===cc;
  }
  activeCellCoords(){
    let r=this.sel.r1,c=this.sel.c1;
    const ma=this.mergeAnchorCoords(r,c);
    if(ma){r=ma.r;c=ma.c;}
    return{r,c};
  }
  isEditAnchor(r,c){
    if(!this.editing||!this.editAt)return false;
    return r===this.editAt.r&&c===this.editAt.c;
  }
  endEdit(){
    this.editing=false;this.editMode=null;this.editAt=null;
    const fxc=APP.querySelector('#btn-fx-cancel'),fxo=APP.querySelector('#btn-fx-commit');
    if(fxc)fxc.style.display='none';if(fxo)fxo.style.display='none';
    this.hideAC();
  }
  syncFxPreview(){
    if(!this.editing||this.editMode!=='fx'||!this.editAt)return;
    const td=this.anchorCellEl(this.editAt.r,this.editAt.c);
    const d=td?.querySelector('.cell-display');
    if(d)d.textContent=this.$.fxBar.value;
  }
  consolidateMergeCells(sel){
    const ar=sel.r1,ac=sel.c1;
    const anchorKey=this.key(ar,ac);
    let anchor=this.cells.get(anchorKey);
    const hasContent=cell=>!!(cell&&(this.dispVal(cell)||cell.formula));

    if(!hasContent(anchor)){
      for(let r=sel.r1;r<=sel.r2;r++){
        for(let c=sel.c1;c<=sel.c2;c++){
          if(r===ar&&c===ac)continue;
          const cell=this.cells.get(this.key(r,c));
          if(hasContent(cell)){
            anchor={...cell,row:ar,col:ac};
            this.cells.set(anchorKey,anchor);
            break;
          }
        }
        if(hasContent(anchor))break;
      }
    }

    const clears=[],updates=[];
    for(let r=sel.r1;r<=sel.r2;r++)for(let c=sel.c1;c<=sel.c2;c++){
      if(r===ar&&c===ac)continue;
      if(this.cells.has(this.key(r,c))){
        this.cells.delete(this.key(r,c));
        clears.push({row:r,col:c,clear:true});
      }
    }
    if(hasContent(anchor)){
      const u={row:ar,col:ac};
      if(anchor.formula)u.formula=anchor.formula;
      else u.value=anchor.value??'';
      if(anchor.style)u.style=cellStyle(anchor.style);
      updates.push(u);
    }
    return{clears,updates};
  }
  dispVal(cell){if(!cell)return'';return cell.formula?cell.computed??'':cell.value??cell.computed??''}
  editVal(cell){if(!cell)return'';return cell.formula??cell.value??cell.computed??''}

  /* ── grid build ── */
  buildGrid(){
    this.$.colHdr.innerHTML='';

    /* corner */
    const corner=document.createElement('th');
    corner.className='xl-corner';corner.onclick=()=>this.selectAll();
    this.$.colHdr.appendChild(corner);

    for(let c=1;c<=this.COLS;c++){
      const th=document.createElement('th');
      th.textContent=colLetter(c);th.dataset.col=c;
      th.style.width=th.style.minWidth=this.colW[c-1]+'px';
      th.onclick=e=>this.selectCol(c,e.shiftKey);
      const rz=document.createElement('div');rz.className='col-resizer';
      rz.addEventListener('mousedown',e=>this.resizeCol(e,c,th));
      th.appendChild(rz);this.$.colHdr.appendChild(th);
    }

    this.$.gridBody.innerHTML='';this.cellEls=[];
    for(let r=1;r<=this.ROWS;r++){
      const rowEls=[];
      const tr=document.createElement('tr');
      const rh=document.createElement('th');
      rh.textContent=r;rh.dataset.row=r;rh.style.height=this.rowH[r-1]+'px';
      rh.onclick=e=>this.selectRow(r,e.shiftKey);
      const rzr=document.createElement('div');rzr.className='row-resizer';
      rzr.addEventListener('mousedown',e=>this.resizeRow(e,r,rh,tr));
      rh.appendChild(rzr);tr.appendChild(rh);
      for(let c=1;c<=this.COLS;c++){
        const td=document.createElement('td');
        td.dataset.row=r;td.dataset.col=c;
        td.style.width=td.style.minWidth=this.colW[c-1]+'px';
        td.style.height=this.rowH[r-1]+'px';
        tr.appendChild(td);rowEls.push(td);
      }
      this.$.gridBody.appendChild(tr);this.cellEls.push(rowEls);
    }
  }

  resizeCol(e,col,th){
    e.preventDefault();e.stopPropagation();
    const x0=e.clientX,w0=this.colW[col-1];
    const move=ev=>{
      const w=Math.max(24,w0+ev.clientX-x0);
      this.colW[col-1]=w;th.style.width=th.style.minWidth=w+'px';
      this.cellEls.forEach(row=>{if(row[col-1]){row[col-1].style.width=row[col-1].style.minWidth=w+'px';}});
      this.positionFillHandle();
    };
    const up=()=>{document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',up)};
    document.addEventListener('mousemove',move);document.addEventListener('mouseup',up);
  }

  resizeRow(e,row,rh,tr){
    e.preventDefault();e.stopPropagation();
    const y0=e.clientY,h0=this.rowH[row-1];
    const move=ev=>{
      const h=Math.max(12,h0+ev.clientY-y0);
      this.rowH[row-1]=h;tr.style.height=h+'px';rh.style.height=h+'px';
      this.cellEls[row-1]?.forEach(td=>{if(td)td.style.height=h+'px';});
    };
    const up=()=>{document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',up)};
    document.addEventListener('mousemove',move);document.addEventListener('mouseup',up);
  }

  /* ── tabs ── */
  renderTabs(){
    this.$.sheetTabs.innerHTML='';
    this.sheets.forEach(s=>{
      const b=document.createElement('button');
      b.type='button';b.textContent=s.name;
      b.className=s.id===this.activeSht?'active':'';
      b.onclick=()=>this.switchSheet(s.id);
      b.ondblclick=()=>this.renameSheet(s);
      this.$.sheetTabs.appendChild(b);
    });
  }

  /* ── load / save ── */
  async loadSheet(){
    if(!this.activeSht)return;
    this.setMode('Loading…');this.cells.clear();
    try{
      const d=await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/cells?range=A1:${colLetter(this.COLS)}${this.ROWS}`);
      this.buildGrid();
      d.data.forEach(c=>{
        c.style=cellStyle(c.style);
        this.cells.set(this.key(c.row,c.col),c);
      });
      this.loadMergeLayout();
      this.renderAll();this.setMode('Ready');
    }catch(e){this.toast('Load error: '+e.message,'error');this.setMode('Error')}
  }

  changesToUpdates(changes){
    return changes.map(ch=>{
      if(!ch.after)return{row:ch.row,col:ch.col,clear:true};
      const a=ch.after,u={row:ch.row,col:ch.col};
      if(a.formula)u.formula=a.formula;
      else u.value=a.raw_value??a.value??'';
      if(a.style)u.style=a.style;
      return u;
    });
  }

  normalizeCellFromApi(c){
    return{
      row:c.row,col:c.col,
      value:c.value??c.raw_value??null,
      formula:c.formula??null,
      computed:c.computed??c.computed_value??null,
      style:cellStyle(c.style),
    };
  }

  applyLocalUpdate(u){
    let r=u.row,c=u.col;
    const a=this.mergeAnchorCoords(r,c);
    if(a){r=a.r;c=a.c;u={...u,row:r,col:c};}
    const k=this.key(r,c);
    if(u.clear){
      this.cells.delete(k);
      this.renderCell(r,c);
      return;
    }
    const existing=this.cells.get(k);
    const cell=existing?{...existing}:{row:r,col:c};
    if(u.formula!==undefined){
      cell.formula=u.formula||null;
      cell.value=null;
      if(!u.formula)cell.computed=null;
    }else if(u.value!==undefined){
      cell.value=u.value;
      cell.formula=null;
      cell.computed=null;
    }
    if(u.style)cell.style={...cellStyle(cell.style),...u.style};
    this.cells.set(k,cell);
    this.renderCell(r,c);
  }

  async switchSheet(id){
    if(id===this.activeSht)return;
    this.activeSht=id;this.mergedCells=[];this.buildGrid();
    this.renderTabs();await this.loadSheet();
  }

  activeSheet(){
    return this.sheets.find(s=>s.id===this.activeSht)||null;
  }

  loadMergeLayout(){
    const sheet=this.activeSheet();
    this.mergedCells=[...(sheet?.layout?.merged_cells||[])];
    for(const m of this.mergedCells)this.applyMergeDOM(m);
  }

  applyCellAlign(d,align){
    if(!d)return;
    const a=align||'left';
    d.style.textAlign=a;
    if(d.style.display==='flex'){
      d.style.justifyContent=a==='center'?'center':a==='right'?'flex-end':'flex-start';
    }
  }

  applyCellValign(td,d,valign){
    if(!td||td.classList.contains('cell-merge-anchor'))return;
    const v=valign||'bottom';
    td.style.verticalAlign=v==='top'?'top':v==='middle'?'middle':'bottom';
  }

  mergeValignJustify(valign){
    const v=valign||'bottom';
    return v==='top'?'top':v==='middle'?'middle':'bottom';
  }

  resetTdLayout(td){
    td.style.display='';
    td.style.flexDirection='';
    td.style.justifyContent='';
    td.style.alignItems='';
    td.style.height='';
    td.style.minHeight='';
  }

  layoutMergeAnchor(td,r,c,cell){
    const st=cellStyle(cell?.style);
    const wrap=!!st.wrap;
    this.resetTdLayout(td);
    td.style.padding='0';
    td.style.overflow=wrap?'visible':'hidden';
    td.style.verticalAlign=this.mergeValignJustify(st.valign);

    const d=td.querySelector('.cell-display');
    if(!d)return;

    d.style.display='block';
    d.style.width='100%';
    d.style.maxWidth='100%';
    d.style.flex='';
    d.style.alignItems='';
    d.style.justifyContent='';
    d.style.textAlign=st.align||'left';
    d.style.whiteSpace=wrap?'pre-wrap':'nowrap';
    d.style.overflow=wrap?'visible':'hidden';
    d.style.textOverflow=wrap?'clip':'ellipsis';
    d.style.height=wrap?'auto':'19px';
    d.style.minHeight='19px';
    d.style.maxHeight=wrap?'':'19px';
    d.style.lineHeight=wrap?'1.4':'19px';

    this.applyCellBorders(td,st.border);
  }

  applyMergeDOM(a1){
    const r=parseRange(a1);if(!r)return;
    const rowspan=r.r2-r.r1+1,colspan=r.c2-r.c1+1;
    const anchor=this.cellEl(r.r1,r.c1);if(!anchor)return;
    anchor.rowSpan=rowspan;
    anchor.colSpan=colspan;
    anchor.classList.add('cell-merge-anchor');
    this.resetTdLayout(anchor);
    for(let rr=r.r1;rr<=r.r2;rr++)for(let cc=r.c1;cc<=r.c2;cc++){
      if(rr===r.r1&&cc===r.c1)continue;
      const td=this.cellEl(rr,cc);
      if(td){td.remove();this.cellEls[rr-1][cc-1]=null;}
    }
  }

  applyCellBorders(td,border){
    if(!td)return;
    const b=border&&typeof border==='object'&&!Array.isArray(border)?border:null;
    const parts=[];
    if(b?.top)    parts.push(`inset 0 ${BORDER_WIDTH}px 0 0 ${BORDER_COLOR}`);
    if(b?.bottom) parts.push(`inset 0 -${BORDER_WIDTH}px 0 0 ${BORDER_COLOR}`);
    if(b?.left)   parts.push(`inset ${BORDER_WIDTH}px 0 0 0 ${BORDER_COLOR}`);
    if(b?.right)  parts.push(`inset -${BORDER_WIDTH}px 0 0 0 ${BORDER_COLOR}`);
    td.dataset.customBorder=parts.length?'1':'';
    if(parts.length)td.style.setProperty('box-shadow',parts.join(', '),'important');
    else td.style.removeProperty('box-shadow');
  }

  syncCellChrome(td,r,c,cell){
    if(!td)return;
    const s=this.norm();
    const b=this.cellBounds(r,c);
    const inS=rangesOverlap(b,s);
    const ac=this.activeCellCoords();
    const isAct=ac.r===r&&ac.c===c;
    td.classList.toggle('cell-sel',inS&&!isAct);
    td.classList.toggle('cell-act',isAct);
    const cs=this.copySrc;
    td.classList.toggle('cell-copy',!!(cs&&rangesOverlap(b,{r1:cs.r1,c1:cs.c1,r2:cs.r2,c2:cs.c2})));
    this.applyCellBorders(td,cellStyle(cell?.style).border);
  }

  borderPatchForPreset(preset,b,sel){
    if(preset==='none')return{};
    const border={};
    const onTop=b.r1===sel.r1,onBot=b.r2===sel.r2,onLeft=b.c1===sel.c1,onRight=b.c2===sel.c2;
    if(preset==='all'){
      border.top=border.right=border.bottom=border.left=BORDER_LINE;
    }else if(preset==='outer'){
      if(onTop)border.top=BORDER_LINE;
      if(onBot)border.bottom=BORDER_LINE;
      if(onLeft)border.left=BORDER_LINE;
      if(onRight)border.right=BORDER_LINE;
    }else if(preset==='inner'){
      if(b.r1>sel.r1)border.top=BORDER_LINE;
      if(b.r2<sel.r2)border.bottom=BORDER_LINE;
      if(b.c1>sel.c1)border.left=BORDER_LINE;
      if(b.c2<sel.c2)border.right=BORDER_LINE;
    }else if(preset==='top'&&onTop)border.top=BORDER_LINE;
    else if(preset==='bottom'&&onBot)border.bottom=BORDER_LINE;
    else if(preset==='left'&&onLeft)border.left=BORDER_LINE;
    else if(preset==='right'&&onRight)border.right=BORDER_LINE;
    return border;
  }

  async applyBorders(preset){
    const sel=this.norm();
    const updates=[];
    const seen=new Set();
    for(let r=sel.r1;r<=sel.r2;r++)for(let c=sel.c1;c<=sel.c2;c++){
      const ma=this.mergeAnchorCoords(r,c);
      const ar=ma?ma.r:r,ac=ma?ma.c:c;
      const k=ar+':'+ac;
      if(seen.has(k))continue;
      seen.add(k);
      const b=this.cellBounds(ar,ac);
      const patch=this.borderPatchForPreset(preset,b,sel);
      const cell=this.cells.get(this.key(ar,ac));
      const prev=cellStyle(cell?.style).border||{};
      const border=preset==='none'?{}:{...prev,...patch};
      const style={border};
      updates.push({row:ar,col:ac,style});
    }
    if(!updates.length)return;
    updates.forEach(u=>{
      const k=this.key(u.row,u.col);
      const existing=this.cells.get(k);
      const cell=existing?{...existing}:{row:u.row,col:u.col};
      const st=cellStyle(cell.style);
      if(Object.keys(u.style.border||{}).length)st.border=u.style.border;
      else delete st.border;
      cell.style=st;
      if(!existing&&!cell.value&&!cell.formula)cell.value='';
      this.cells.set(k,cell);
      this.renderCell(u.row,u.col);
    });
    await this.flush(updates);
    this.$.borderPanel&&(this.$.borderPanel.style.display='none');
  }

  reapplyMergeLayout(){
    this.buildGrid();
    for(const m of this.mergedCells)this.applyMergeDOM(m);
    this.renderAll();
  }

  findMergeOverlapping(sel){
    for(const m of this.mergedCells){
      const r=parseRange(m);
      if(r&&rangesOverlap(r,sel))return m;
    }
    return null;
  }

  mergesInSelection(sel=this.norm()){
    return this.mergedCells.filter(m=>{
      const r=parseRange(m);
      return r&&rangesOverlap(r,sel);
    });
  }

  async unmergeSelection(sel=this.norm()){
    const remove=new Set(this.mergesInSelection(sel));
    if(!remove.size)return false;
    this.mergedCells=this.mergedCells.filter(m=>!remove.has(m));
    this.reapplyMergeLayout();
    await this.saveMergeLayout();
    return true;
  }

  async saveMergeLayout(){
    const sheet=this.activeSheet();if(!sheet)return;
    const layout={...(sheet.layout||{}),merged_cells:this.mergedCells};
    try{
      const res=await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}`,{method:'PATCH',body:{layout}});
      if(res.data?.layout)sheet.layout=res.data.layout;
      else sheet.layout=layout;
    }catch(e){this.toast('Failed to save merge: '+e.message,'error');}
  }

  async mergeOrUnmerge(){
    const s=this.norm();
    const existing=this.findMergeOverlapping(s);
    if(existing){
      this.mergedCells=this.mergedCells.filter(m=>m!==existing);
      this.reapplyMergeLayout();
      await this.saveMergeLayout();
      this.toast('Cells unmerged','info');
      return;
    }
    if(s.r1===s.r2&&s.c1===s.c2){
      this.toast('Select at least 2 cells to merge','info');
      return;
    }
    const {clears,updates}=this.consolidateMergeCells(s);
    this.mergedCells=this.mergedCells.filter(m=>{
      const r=parseRange(m);
      return r&&!rangesOverlap(r,s);
    });
    this.mergedCells.push(rangeA1(s.r1,s.c1,s.r2,s.c2));
    this.anchor={r:s.r1,c:s.c1};
    this.sel={r1:s.r1,c1:s.c1,r2:s.r2,c2:s.c2};
    this.expandSelForMerges();
    this.reapplyMergeLayout();
    await this.saveMergeLayout();
    const save=[...updates,...clears];
    if(save.length)await this.flush(save);
    this.toast('Cells merged','success');
  }

  applyCurrentFontColor(){
    const hex=(APP.querySelector('#font-color')?.value||'#000000').replace('#','');
    this.applyProp('color',hex);
  }

  applyCurrentFillColor(){
    const hex=(APP.querySelector('#fill-color')?.value||'#FFFF00').replace('#','');
    this.applyProp('bg',hex);
  }

  async renameSheet(s){
    const n=prompt('Sheet name:',s.name);
    if(!n||n===s.name)return;
    try{await api(`/workbooks/${this.wbId}/sheets/${s.id}`,{method:'PATCH',body:{name:n}});s.name=n;this.renderTabs();this.toast('Renamed','success');}
    catch(e){this.toast('Rename failed','error')}
  }

  buildUpd(r,c,raw){
    const t=(raw??'').trim();const u={row:r,col:c};
    if(!t)u.clear=true;else if(t.startsWith('='))u.formula=t;else u.value=t;
    return u;
  }

  selRangeA1(){
    const s=this.norm();
    return colLetter(s.c1)+s.r1+(s.r1!==s.r2||s.c1!==s.c2?':'+colLetter(s.c2)+s.r2:'');
  }

  buildFormulaTemplate(tpl){
    const rng=this.selRangeA1();
    const ac=colLetter(this.activeCellCoords().c)+this.activeCellCoords().r;
    if(tpl.includes('()'))return tpl.replace('()','('+rng+')');
    if(tpl==='=IF(,,)')return '=IF('+ac+'>0,TRUE,FALSE)';
    if(tpl==='=ROUND(,2)')return '=ROUND('+ac+',2)';
    if(tpl==='=CONCAT(,)'||tpl==='=CONCAT(,)')return '=CONCAT('+ac+',"-")';
    if(tpl==='=IFERROR(,)'||tpl==='=IFERROR(,)')return '=IFERROR('+ac+',0)';
    return tpl;
  }

  finishFxEdit(){
    if(this.editing&&this.editMode==='fx'){
      const ac=this.editAt||this.activeCellCoords();
      const upd=this.buildUpd(ac.r,ac.c,this.$.fxBar.value);
      this.applyLocalUpdate(upd);
      this.endEdit();
      if(upd.formula||upd.value!==undefined||upd.clear)this.flush([upd]);
      else this.queueSave([upd]);
    }
  }

  async applyFormula(fx){
    const ac=this.activeCellCoords();
    const upd=this.buildUpd(ac.r,ac.c,fx);
    this.applyLocalUpdate(upd);
    this.$.fxBar.value=fx;
    this.endEdit();
    await this.flush([upd]);
  }

  queueSave(updates){
    for(const u of updates){
      const k=u.row+':'+u.col;
      const prev=this.pendingUpdates.get(k)||{};
      this.pendingUpdates.set(k,{...prev,...u});
    }
    clearTimeout(this.saveTimer);
    this.saveTimer=setTimeout(()=>this.flushPending(),220);
  }

  async flushPending(){
    if(this.saving){
      return;
    }
    if(!this.pendingUpdates.size)return;
    const updates=[...this.pendingUpdates.values()];
    this.pendingUpdates.clear();
    this.saving=true;
    try{
      await this.flush(updates);
    }finally{
      this.saving=false;
      if(this.pendingUpdates.size)await this.flushPending();
    }
  }

  needsRecalc(updates){
    return updates.some(u=>u.clear||u.formula!==undefined||u.value!==undefined);
  }

  async flush(updates,opts={}){
    if(!this.activeSht||!updates.length)return;
    if(!this.guardWrite())return;
    this.setMode('Saving…');this.$.saveDot.className='xl-save-dot saving';
    try{
      const res=await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/cells`,{
        method:'PATCH',body:{updates,recalculate:this.needsRecalc(updates)},
      });
      if(res.data?.operation_id){
        this.lastOwnOp=res.data.operation_id;
        this.opChanges.set(res.data.operation_id,res.data?.changes||[]);
        this.undoStk.push(res.data.operation_id);
        if(!opts.fromRedo)this.redoStk=[];
        this.$.btnUndo.disabled=false;this.$.btnRedo.disabled=this.redoStk.length===0;
      }
      const changes=res.data?.changes||[];
      if(changes.length){
        for(const ch of changes){
          if(ch.after){
            this.cells.set(this.key(ch.row,ch.col),this.normalizeCellFromApi(ch.after));
          }else{
            this.cells.delete(this.key(ch.row,ch.col));
          }
          this.renderCell(ch.row,ch.col);
        }
      }
      if(this.needsRecalc(updates)){
        await this.refreshComputed();
      }
      this.syncRibbon();
      this.setMode('Ready');this.$.saveDot.className='xl-save-dot';
    }catch(e){
      this.toast('Save failed: '+e.message,'error');this.setMode('Error');this.$.saveDot.className='xl-save-dot error';
      await this.loadSheet();
    }
  }

  async refreshComputed(){
    try{
      const d=await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/cells?range=A1:${colLetter(this.COLS)}${this.ROWS}`);
      d.data.forEach(c=>{
        const k=this.key(c.row,c.col);
        const existing=this.cells.get(k);
        if(existing){
          existing.computed=c.computed;
          if(c.formula)existing.formula=c.formula;
          if(c.value!==undefined)existing.value=c.value;
          this.renderCell(c.row,c.col);
        }else if(c.formula||c.value){
          c.style=cellStyle(c.style);
          this.cells.set(k,c);
          this.renderCell(c.row,c.col);
        }
      });
    }catch(_e){}
  }

  updateSelectionChrome(){
    for(let r=1;r<=this.ROWS;r++)for(let c=1;c<=this.COLS;c++){
      if(this.isMergeCovered(r,c))continue;
      const td=this.cellEl(r,c);if(!td)continue;
      this.syncCellChrome(td,r,c,this.cells.get(this.key(r,c)));
    }
    this.renderHdrs();this.updateName();this.updateStatus();this.syncRibbon();this.positionFillHandle();
  }

  /* ── rendering ── */
  applyStyleEl(td,cell){
    const st=cellStyle(cell?.style);
    const d=td.querySelector('.cell-display')||td;
    d.style.fontWeight    =st.bold          ?'bold':'';
    d.style.fontStyle     =st.italic        ?'italic':'';
    d.style.textDecoration=[st.underline?'underline':'',st.strikethrough?'line-through':''].filter(Boolean).join(' ')||'';
    if(st.hyperlink?.url)d.style.textDecoration='underline';
    d.style.color         =st.color?(st.color.startsWith('#')?st.color:'#'+st.color):(st.hyperlink?.url?'#0563c1':'');
    this.applyCellAlign(d,st.align);
    this.applyCellValign(td,d,st.valign);
    d.style.fontSize      =st.fontSize?st.fontSize+'px':'';
    d.style.fontFamily    =st.fontFamily||'';
    td.classList.toggle('cell-wrap',!!st.wrap);
    /* Fill color: apply to td via setProperty('important') so it overrides
       the !important CSS rules on .cell-act and .cell-sel selection states */
    const bgColor=st.bg?(st.bg.startsWith('#')?st.bg:'#'+st.bg):'';
    if(bgColor){td.style.setProperty('background-color',bgColor,'important');}
    else{td.style.removeProperty('background-color');}
    d.style.backgroundColor='';

    const raw=this.dispVal(cell);const num=parseFloat(raw);
    if(!isNaN(num)&&raw!==''){
      if(st.format==='integer')     d.textContent=Math.round(num).toLocaleString();
      else if(st.format==='decimal2')d.textContent=num.toFixed(2);
      else if(st.format==='currency')d.textContent='$'+num.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
      else if(st.format==='percent') d.textContent=(num*100).toFixed(2)+'%';
      else if(st.format==='scientific')d.textContent=num.toExponential(2);
      else d.textContent=raw;
    }else d.textContent=raw;

    /* store formula for show-formulas mode */
    if(this.showFml&&cell?.formula)d.textContent=cell.formula;
  }

  renderCell(r,c){
    if(this.isMergeCovered(r,c))return;
    const td=this.cellEl(r,c);if(!td)return;
    td.innerHTML='';
    if(!td.classList.contains('cell-merge-anchor'))this.resetTdLayout(td);
    const cell=this.cells.get(this.key(r,c));
    const div=document.createElement('div');
    div.className='cell-display';
    const showFxPreview=this.editing&&this.editMode==='fx'&&this.isEditAnchor(r,c);
    const st=cellStyle(cell?.style);
    const link=st.hyperlink?.url;
    const display=showFxPreview?this.$.fxBar.value:this.dispVal(cell);
    if(link&&!showFxPreview&&!this.showFml){
      const a=document.createElement('a');
      a.href=link;a.target='_blank';a.rel='noopener noreferrer';
      a.className='cell-link';
      a.textContent=st.hyperlink?.display||display||link;
      a.addEventListener('click',e=>e.stopPropagation());
      div.appendChild(a);
    }else{
      div.textContent=display;
    }
    if(cell?.formula&&!showFxPreview)div.title=cell.formula+' → '+(cell.computed??'');
    if(this.showFml&&cell?.formula)div.textContent=cell.formula;
    td.appendChild(div);
    this.applyStyleEl(td,cell);
    if(td.classList.contains('cell-merge-anchor'))this.layoutMergeAnchor(td,r,c,cell);
    this.syncCellChrome(td,r,c,cell);
  }

  renderAll(){
    for(let r=1;r<=this.ROWS;r++)for(let c=1;c<=this.COLS;c++)this.renderCell(r,c);
    this.renderHdrs();this.updateName();this.updateStatus();this.syncRibbon();this.positionFillHandle();
  }

  renderHdrs(){
    const s=this.norm();
    const ac=this.activeCellCoords();
    for(let c=1;c<=this.COLS;c++){
      const th=this.$.colHdr.children[c];if(!th)continue;
      th.classList.toggle('col-sel',c>=s.c1&&c<=s.c2);
      th.classList.toggle('col-act',c===ac.c);
    }
    for(let r=1;r<=this.ROWS;r++){
      const rh=this.$.gridBody.children[r-1]?.children[0];if(!rh)continue;
      rh.classList.toggle('row-sel',r>=s.r1&&r<=s.r2);
      rh.classList.toggle('row-act',r===ac.r);
    }
  }

  updateName(){
    const s=this.norm();
    this.$.nameBox.value=s.r1===s.r2&&s.c1===s.c2?colLetter(s.c1)+s.r1:colLetter(s.c1)+s.r1+':'+colLetter(s.c2)+s.r2;
    if(!this.editing){
      const ac=this.activeCellCoords();
      this.$.fxBar.value=this.editVal(this.cells.get(this.key(ac.r,ac.c)));
    }
  }

  updateStatus(){
    const s=this.norm();const nums=[];
    for(let r=s.r1;r<=s.r2;r++)for(let c=s.c1;c<=s.c2;c++){
      const v=this.dispVal(this.cells.get(this.key(r,c)));
      if(v!==''&&!isNaN(+v))nums.push(+v);
    }
    if(nums.length>1){
      const sum=nums.reduce((a,b)=>a+b,0);
      this.$.statusAvg.textContent ='Average: '+(sum/nums.length).toLocaleString(undefined,{maximumFractionDigits:4});
      this.$.statusCnt.textContent ='Count: '+nums.length;
      this.$.statusSum.textContent ='Sum: '+sum.toLocaleString(undefined,{maximumFractionDigits:4});
    }else{this.$.statusAvg.textContent='';this.$.statusCnt.textContent='';this.$.statusSum.textContent='';}
  }

  syncRibbon(){
    let r=this.sel.r1,c=this.sel.c1;
    const ma=this.mergeAnchorCoords(r,c);if(ma){r=ma.r;c=ma.c;}
    const st=cellStyle(this.cells.get(this.key(r,c))?.style);
    const tog=(id,v)=>{const el=APP.querySelector('#'+id);if(el)el.classList.toggle('active',!!v)};
    tog('btn-bold',         st.bold);
    tog('btn-italic',       st.italic);
    tog('btn-underline',    st.underline);
    tog('btn-strikethrough',st.strikethrough);
    tog('btn-wrap-text',    st.wrap);
    tog('btn-align-left',   st.align==='left'||!st.align);
    tog('btn-align-center', st.align==='center');
    tog('btn-align-right',  st.align==='right');
    tog('btn-valign-top',   st.valign==='top');
    tog('btn-valign-mid',   st.valign==='middle');
    tog('btn-valign-bot',   st.valign==='bottom'||!st.valign);
    const fc=APP.querySelector('#font-color');if(fc&&st.color)fc.value=st.color.startsWith('#')?st.color:'#'+st.color;
    const fl=APP.querySelector('#fill-color');if(fl&&st.bg)fl.value=st.bg.startsWith('#')?st.bg:'#'+st.bg;
    const nf=APP.querySelector('#number-format');if(nf)nf.value=st.format||'';
    const fs=APP.querySelector('#font-size');if(fs&&st.fontSize)fs.value=st.fontSize;
    const ff=APP.querySelector('#font-family');if(ff&&st.fontFamily)ff.value=st.fontFamily;
    if(st.color){const b=APP.querySelector('#font-color-bar');if(b)b.style.background=st.color.startsWith('#')?st.color:'#'+st.color;}
    if(st.bg){const b=APP.querySelector('#fill-color-bar');if(b)b.style.background=st.bg.startsWith('#')?st.bg:'#'+st.bg;}
  }

  /* ── fill handle ── */
  positionFillHandle(){
    const fh=this.$.fillHnd;
    if(this.editing){fh.style.display='none';return;}
    const s=this.norm();
    const td=this.anchorCellEl(s.r2,s.c2);
    if(!td){fh.style.display='none';return;}
    const ir=this.$.gridInner.getBoundingClientRect();
    const tr=td.getBoundingClientRect();
    fh.style.left=(tr.right-ir.left-3)+'px';
    fh.style.top=(tr.bottom-ir.top-3)+'px';
    fh.style.display='block';
  }

  startFillDrag(e){
    e.preventDefault();e.stopPropagation();
    const src=this.norm();let lastR=src.r2,lastC=src.c2;
    const paint=()=>{
      for(let r=1;r<=this.ROWS;r++)for(let c=1;c<=this.COLS;c++){
        if(this.isMergeCovered(r,c))continue;
        const td=this.cellEl(r,c);if(!td)continue;
        const inFill=r>=src.r1&&r<=lastR&&c>=src.c1&&c<=lastC;
        const ac=this.activeCellCoords();
        const isAct=ac.r===r&&ac.c===c;
        td.classList.toggle('cell-sel',inFill&&!isAct);
      }
      this.positionFillHandle();
    };
    const move=ev=>{
      const el=document.elementFromPoint(ev.clientX,ev.clientY);
      const td=el?.closest('td[data-row]');if(!td)return;
      let r=+td.dataset.row,c=+td.dataset.col;
      const ma=this.mergeAnchorCoords(r,c);if(ma){r=ma.r;c=ma.c;}
      if(Math.abs(r-src.r2)>=Math.abs(c-src.c2)){lastR=Math.max(src.r2,r);lastC=src.c2;}
      else{lastR=src.r2;lastC=Math.max(src.c2,c);}
      paint();
    };
    const up=async()=>{
      document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',up);
      this.updateSelectionChrome();
      await this.fillRange(src,lastR,lastC);
    };
    document.addEventListener('mousemove',move);document.addEventListener('mouseup',up);
  }

  async fillRange(src,toR,toC){
    const updates=[];
    for(let r=src.r1;r<=toR;r++)for(let c=src.c1;c<=toC;c++){
      if(r<=src.r2&&c<=src.c2)continue;
      const sr=((r-src.r1)%(src.r2-src.r1+1))+src.r1;
      const sc=((c-src.c1)%(src.c2-src.c1+1))+src.c1;
      const srcCell=this.cells.get(this.key(sr,sc));
      if(!srcCell){updates.push({row:r,col:c,clear:true});continue;}
      const u={row:r,col:c};
      if(srcCell.formula)u.formula=srcCell.formula;else u.value=srcCell.value??srcCell.computed??'';
      if(srcCell.style)u.style=srcCell.style;
      updates.push(u);
    }
    if(updates.length){
      updates.forEach(u=>this.applyLocalUpdate(u));
      await this.flush(updates);
    }
  }

  /* ── selection ── */
  select(r,c,ext=false){
    r=Math.max(1,Math.min(this.ROWS,r));c=Math.max(1,Math.min(this.COLS,c));
    const a=this.mergeAnchorCoords(r,c);if(a){r=a.r;c=a.c;}
    if(ext){
      if(this.sel.r2===r&&this.sel.c2===c)return;
      this.sel.r2=r;this.sel.c2=c;
    }else{
      if(this.sel.r1===r&&this.sel.c1===c&&this.sel.r2===r&&this.sel.c2===c)return;
      this.sel={r1:r,c1:c,r2:r,c2:c};this.anchor={r,c};
    }
    this.expandSelForMerges();
    this.renderAll();
    this.anchorCellEl(r,c)?.scrollIntoView({block:'nearest',inline:'nearest'});
  }
  selectRow(r,ext=false){
    if(ext){this.sel.r2=r;this.sel.c1=1;this.sel.c2=this.COLS;}
    else{this.sel={r1:r,c1:1,r2:r,c2:this.COLS};}
    this.anchor={r,c:1};
    this.expandSelForMerges();
    this.renderAll();
  }
  selectCol(c,ext=false){
    if(ext){this.sel.c2=c;this.sel.r1=1;this.sel.r2=this.ROWS;}
    else{this.sel={r1:1,c1:c,r2:this.ROWS,c2:c};}
    this.anchor={r:1,c};
    this.expandSelForMerges();
    this.renderAll();
  }
  selectAll(){this.sel={r1:1,c1:1,r2:this.ROWS,c2:this.COLS};this.renderAll();}

  move(dr,dc,ext=false){
    const r=Math.max(1,Math.min(this.ROWS,(ext?this.sel.r2:this.sel.r1)+dr));
    const c=Math.max(1,Math.min(this.COLS,(ext?this.sel.c2:this.sel.c1)+dc));
    if(ext){
      this.sel.r2=r;this.sel.c2=c;
      this.expandSelForMerges();
      this.updateSelectionChrome();
    }else this.select(r,c);
  }

  jumpEdge(dr,dc,ext=false){
    let r=ext?this.sel.r2:this.sel.r1,c=ext?this.sel.c2:this.sel.c1;
    const has=(rr,cc)=>!!this.dispVal(this.cells.get(this.key(rr,cc)));
    const step=()=>{const nr=r+dr,nc=c+dc;if(nr<1||nr>this.ROWS||nc<1||nc>this.COLS)return false;r=nr;c=nc;return true;};
    if(!has(r,c)){while(step()&&!has(r,c));}
    else{while(has(r+dr,c+dc)&&step());}
    if(ext){
      this.sel.r2=r;this.sel.c2=c;
      this.expandSelForMerges();
      this.updateSelectionChrome();
    }else this.select(r,c);
  }

  /* ── edit ── */
  startEdit(td,initial=null,{selectAll=false}={}){
    if(this.editing){
      if(this.editMode==='cell')return;
      if(this.editMode==='fx')this.finishFxEdit();
    }
    let r=+td.dataset.row,c=+td.dataset.col;
    const ma=this.mergeAnchorCoords(r,c);
    if(ma){r=ma.r;c=ma.c;td=this.cellEl(r,c);if(!td)return;}
    if(!this.isActiveCell(r,c))this.select(r,c);
    this.editing=true;
    this.editMode='cell';
    this.editAt={r,c};
    const fxc=APP.querySelector('#btn-fx-cancel'),fxo=APP.querySelector('#btn-fx-commit');
    if(fxc)fxc.style.display='';if(fxo)fxo.style.display='';
    this.$.fillHnd.style.display='none';
    const cell=this.cells.get(this.key(r,c));
    const val=initial??this.editVal(cell);
    td.innerHTML='';
    const inp=document.createElement('input');
    inp.className='cell-input';
    if(td.classList.contains('cell-merge-anchor'))inp.classList.add('cell-input-merge');
    inp.value=val;
    td.appendChild(inp);
    inp.focus();
    const span=this.getMergeSpan(r,c);
    if(span){
      inp.style.width='100%';
      inp.style.height='19px';
      inp.style.minHeight='19px';
      inp.style.top='0';
      inp.style.bottom='auto';
    }
    if(selectAll)inp.select();
    else if(initial!==null)inp.setSelectionRange(val.length,val.length);
    else inp.setSelectionRange(val.length,val.length);
    this.$.fxBar.value=val;

    const syncFx=()=>{
      this.$.fxBar.value=inp.value;
      if(inp.value.startsWith('='))this.showAC(inp.value,inp);else this.hideAC();
    };
    inp.addEventListener('input',syncFx);

    const commit=async()=>{
      if(!this.editing)return;
      inp.removeEventListener('blur',commit);
      const upd=this.buildUpd(r,c,inp.value);
      this.applyLocalUpdate(upd);
      this.endEdit();
      this.positionFillHandle();
      if(upd.formula)await this.flush([upd]);
      else this.queueSave([upd]);
    };
    inp.addEventListener('blur',commit);
    inp.addEventListener('keydown',ev=>{
      if(ev.key==='Enter') {ev.preventDefault();inp.removeEventListener('blur',commit);commit().then(()=>this.move(1,0));}
      if(ev.key==='Tab')   {ev.preventDefault();inp.removeEventListener('blur',commit);commit().then(()=>this.move(0,ev.shiftKey?-1:1));}
      if(ev.key==='Escape'){ev.preventDefault();inp.removeEventListener('blur',commit);this.endEdit();this.renderCell(r,c);this.positionFillHandle();}
      if(ev.key==='ArrowDown'&&this.$.acList.style.display!=='none'){ev.preventDefault();this.acNav(1);}
      if(ev.key==='ArrowUp'  &&this.$.acList.style.display!=='none'){ev.preventDefault();this.acNav(-1);}
      if(ev.key==='Tab'      &&this.$.acList.style.display!=='none'){ev.preventDefault();this.acDo(inp);}
    });
  }

  async commitFx(){
    const ac=this.activeCellCoords();
    const upd=this.buildUpd(ac.r,ac.c,this.$.fxBar.value);
    this.applyLocalUpdate(upd);
    this.endEdit();
    if(upd.formula||upd.value!==undefined||upd.clear)await this.flush([upd]);
    else this.queueSave([upd]);
  }

  /* ── autocomplete ── */
  showAC(val,inp){
    const m=val.match(/=([A-Za-z]*)$/);
    const prefix=m?m[1].toUpperCase():null;
    if(prefix===null){this.hideAC();return;}
    const matches=prefix===''?FNS:FNS.filter(([f])=>f.startsWith(prefix));
    if(!matches.length){this.hideAC();return;}
    this.$.acList.innerHTML='';this.acSel=-1;
    matches.slice(0,12).forEach(([fn,desc],i)=>{
      const d=document.createElement('div');d.className='xl-ac-item';
      d.innerHTML=`<span>${fn}</span><span class="xl-ac-desc">${desc}</span>`;
      d.onmousedown=ev=>{ev.preventDefault();this.acDoFn(fn,inp);};
      this.$.acList.appendChild(d);
    });
    this.$.acList.style.display='block';
  }
  hideAC(){this.$.acList.style.display='none';this.$.acList.innerHTML='';this.acSel=-1;}
  acNav(d){
    const items=this.$.acList.querySelectorAll('.xl-ac-item');if(!items.length)return;
    if(this.acSel>=0)items[this.acSel].classList.remove('xl-ac-sel');
    this.acSel=(this.acSel+d+items.length)%items.length;
    items[this.acSel].classList.add('xl-ac-sel');
  }
  acDo(inp){
    const items=this.$.acList.querySelectorAll('.xl-ac-item');
    const fn=this.acSel>=0?items[this.acSel]?.querySelector('span')?.textContent:items[0]?.querySelector('span')?.textContent;
    if(fn)this.acDoFn(fn,inp);
  }
  acDoFn(fn,inp){
    const m=inp.value.match(/^(.*=)([A-Za-z]*)$/);
    if(m){inp.value=m[1]+fn+'(';this.$.fxBar.value=inp.value;}
    this.hideAC();inp.focus();
  }

  /* ── clipboard ── */
  copy(cut=false){
    const s=this.norm();
    const data=[];
    for(let r=s.r1;r<=s.r2;r++){
      const row=[];
      for(let c=s.c1;c<=s.c2;c++){const cell=this.cells.get(this.key(r,c));row.push(cell?{...cell}:null);}
      data.push(row);
    }
    this.clip={data,rows:s.r2-s.r1+1,cols:s.c2-s.c1+1};
    this.copySrc=cut?null:{...s};
    this.renderAll();
    const text=data.map(row=>row.map(cell=>cell?(cell.formula?cell.computed??'':cell.value??''):'').join('\t')).join('\n');
    navigator.clipboard?.writeText(text).catch(()=>{});
    this.toast(cut?'Cut':'Copied '+this.clip.rows*this.clip.cols+' cell(s)','info');
    if(cut)this.clearSel();
  }

  async paste(valOnly=false){
    if(!this.clip)return;
    const updates=[];
    const{data,rows,cols}=this.clip;
    for(let dr=0;dr<rows;dr++)for(let dc=0;dc<cols;dc++){
      const src=data[dr]?.[dc];const r=this.sel.r1+dr,c=this.sel.c1+dc;
      if(r>this.ROWS||c>this.COLS)continue;
      if(!src){updates.push({row:r,col:c,clear:true});continue;}
      const u={row:r,col:c};
      if(!valOnly&&src.formula)u.formula=src.formula;else u.value=src.value??src.computed??'';
      if(!valOnly&&src.style)u.style=src.style;
      updates.push(u);
    }
    updates.forEach(u=>this.applyLocalUpdate(u));
    this.copySrc=null;await this.flush(updates);
  }

  async clearSel(){
    const s=this.norm();
    await this.unmergeSelection(s);
    const updates=[];
    for(let r=s.r1;r<=s.r2;r++)for(let c=s.c1;c<=s.c2;c++)updates.push({row:r,col:c,clear:true});
    updates.forEach(u=>this.applyLocalUpdate(u));
    await this.flush(updates);
  }

  /* ── styles ── */
  toggleStyle(key){const cell=this.cells.get(this.key(this.sel.r1,this.sel.c1));this.applyProp(key,!cellStyle(cell?.style)[key]);}

  applyProp(key,value){
    const s=this.norm();const updates=[];
    for(let r=s.r1;r<=s.r2;r++)for(let c=s.c1;c<=s.c2;c++){
      const cell=this.cells.get(this.key(r,c));
      const style={...cellStyle(cell?.style),[key]:value};
      const u={row:r,col:c,style};
      if(!cell)u.value='';
      updates.push(u);
      this.applyLocalUpdate(u);
    }
    this.syncRibbon();
    this.queueSave(updates);
  }

  /* ── fill ── */
  async fillDown(){
    const s=this.norm();if(s.r1===s.r2)return;
    const updates=[];
    for(let c=s.c1;c<=s.c2;c++){
      const src=this.cells.get(this.key(s.r1,c));
      for(let r=s.r1+1;r<=s.r2;r++){
        const u={row:r,col:c};
        if(src?.formula)u.formula=src.formula;else u.value=src?.value??src?.computed??'';
        if(src?.style)u.style=src.style;
        updates.push(u);
      }
    }
    if(updates.length){
      updates.forEach(u=>this.applyLocalUpdate(u));
      await this.flush(updates);
    }
  }

  async fillRight(){
    const s=this.norm();if(s.c1===s.c2)return;
    const updates=[];
    for(let r=s.r1;r<=s.r2;r++){
      const src=this.cells.get(this.key(r,s.c1));
      for(let c=s.c1+1;c<=s.c2;c++){
        const u={row:r,col:c};
        if(src?.formula)u.formula=src.formula;else u.value=src?.value??src?.computed??'';
        if(src?.style)u.style=src.style;
        updates.push(u);
      }
    }
    if(updates.length){
      updates.forEach(u=>this.applyLocalUpdate(u));
      await this.flush(updates);
    }
  }

  autoSum(){
    const s=this.norm();const tR=s.r2+1<=this.ROWS?s.r2+1:s.r2;
    const range=colLetter(s.c1)+s.r1+':'+colLetter(s.c2)+s.r2;
    this.select(tR,s.c1);
    this.applyFormula('=SUM('+range+')');
  }

  /* ── undo / redo ── */
  async undo(){
    const op=this.undoStk.pop();if(!op){this.toast('Nothing to undo','info');return;}
    const forward=this.opChanges.get(op)||[];
    try{
      await api('/operations/'+op+'/revert',{method:'POST'});
      this.redoStk.push({op,forward});
      this.opChanges.delete(op);
      this.$.btnUndo.disabled=this.undoStk.length===0;
      this.$.btnRedo.disabled=false;
      await this.loadSheet();
      this.toast('Undone','info');
    }catch{
      this.undoStk.push(op);
      this.toast('Undo failed','error');
    }
  }

  async redo(){
    const entry=this.redoStk.pop();if(!entry){this.toast('Nothing to redo','info');return;}
    try{
      const updates=this.changesToUpdates(entry.forward);
      if(!updates.length){this.toast('Nothing to redo','info');return;}
      await this.flush(updates,{fromRedo:true});
      this.$.btnRedo.disabled=this.redoStk.length===0;
      this.toast('Redone','info');
    }catch{
      this.redoStk.push(entry);
      this.toast('Redo failed','error');
    }
  }

  editActiveCell(){
    const td=this.anchorCellEl(this.sel.r1,this.sel.c1);
    if(td)this.startEdit(td);
  }

  syncCtxMenu(){
    const s=this.norm();
    const multi=s.r1!==s.r2||s.c1!==s.c2;
    const merged=!!this.findMergeOverlapping(s);
    const set=(action,on)=>{const el=this.$.ctx?.querySelector(`[data-action="${action}"]`);if(el)el.classList.toggle('disabled',!on);};
    set('paste',!!this.clip);
    set('paste-values',!!this.clip);
    set('merge-cells',multi&&!merged);
    set('unmerge-cells',merged);
    set('undo',this.undoStk.length>0);
    set('redo',this.redoStk.length>0);
  }

  /* ── insert / delete rows & cols ── */
  applySheetLayout(layout){
    const sheet=this.activeSheet();
    const next=layout||{};
    if(sheet)sheet.layout=next;
    this.mergedCells=[...(next.merged_cells||[])];
  }

  async insertRow(above=true){
    const s=this.norm();
    const atRow=above?s.r1:s.r2+1;
    try{
      const res=await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/rows/insert`,{
        method:'POST',body:{at_row:atRow,count:1},
      });
      this.applySheetLayout(res.data?.layout);
      await this.loadSheet();
      this.select(atRow,s.c1);
      this.toast('Row inserted','success');
    }catch(e){this.toast('Insert row failed: '+e.message,'error');}
  }

  async deleteRow(){
    const s=this.norm();
    const count=s.r2-s.r1+1;
    if(count>=this.ROWS){this.toast('Cannot delete all rows','error');return;}
    try{
      const res=await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/rows/delete`,{
        method:'POST',body:{start_row:s.r1,end_row:s.r2},
      });
      this.applySheetLayout(res.data?.layout);
      this.reapplyMergeLayout();
      await this.loadSheet();
      this.select(Math.min(s.r1,this.ROWS),s.c1);
      this.toast(count+' row(s) deleted','success');
    }catch(e){this.toast('Delete rows failed: '+e.message,'error');}
  }

  async insertCol(left=true){
    const s=this.norm();
    const atCol=left?s.c1:s.c2+1;
    try{
      const res=await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/columns/insert`,{
        method:'POST',body:{at_col:atCol,count:1},
      });
      this.applySheetLayout(res.data?.layout);
      await this.loadSheet();
      this.select(s.r1,atCol);
      this.toast('Column inserted','success');
    }catch(e){this.toast('Insert column failed: '+e.message,'error');}
  }

  async deleteCol(){
    const s=this.norm();
    const count=s.c2-s.c1+1;
    if(count>=this.COLS){this.toast('Cannot delete all columns','error');return;}
    try{
      const res=await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/columns/delete`,{
        method:'POST',body:{start_col:s.c1,end_col:s.c2},
      });
      this.applySheetLayout(res.data?.layout);
      this.reapplyMergeLayout();
      await this.loadSheet();
      this.select(s.r1,Math.min(s.c1,this.COLS));
      this.toast(count+' column(s) deleted','success');
    }catch(e){this.toast('Delete columns failed: '+e.message,'error');}
  }

  /* ── sort ── */
  async doSort(col,dir){
    try{
      await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/sort`,{method:'POST',body:{column:col,direction:dir}});
      await this.loadSheet();this.toast('Sorted column '+colLetter(col)+' '+dir,'success');
    }catch(e){this.toast('Sort failed: '+e.message,'error');}
  }

  /* ── freeze ── */
  setFreeze(rows,cols){
    this.frozenR=rows;this.frozenC=cols;
    const hc=this.$.colHdr.children;
    for(let c=1;c<=this.COLS;c++)if(hc[c])hc[c].classList.toggle('col-frozen',c<=cols);
    for(let r=1;r<=this.ROWS;r++){
      const rh=this.$.gridBody.children[r-1]?.children[0];
      if(rh)rh.classList.toggle('row-frozen',r<=rows);
    }
    APP.querySelector('#btn-freeze-row')?.classList.toggle('active',rows>0);
    APP.querySelector('#btn-freeze-col')?.classList.toggle('active',cols>0);
    this.toast(rows||cols?'Panes frozen':'Panes unfrozen','info');
  }

  /* ── zoom ── */
  setZoom(z){
    this.zoom=Math.max(0.5,Math.min(2,z));
    const pct=Math.round(this.zoom*100)+'%';
    this.$.grid.style.transform=`scale(${this.zoom})`;
    this.$.grid.style.transformOrigin='top left';
    if(this.$.zoomPct)this.$.zoomPct.textContent=pct;
    if(this.$.zoomLbl)this.$.zoomLbl.textContent=pct;
    if(this.$.zoomSlider)this.$.zoomSlider.value=Math.round(this.zoom*100);
  }

  /* ── find/replace ── */
  openFind(replace=false){
    const m=APP.querySelector('#modal-find');
    APP.querySelector('#replace-row').style.display=replace?'':'none';
    APP.querySelector('#btn-replace-one').style.display=replace?'':'none';
    APP.querySelector('#btn-replace-all').style.display=replace?'':'none';
    APP.querySelector('#find-title').textContent=replace?'Find & Replace':'Find';
    m.style.display='flex';APP.querySelector('#find-input')?.focus();
  }

  buildMatches(){
    const q=APP.querySelector('#find-input')?.value||'';
    const mc=APP.querySelector('#find-case')?.checked;
    const mw=APP.querySelector('#find-whole')?.checked;
    this.findList=[];if(!q)return;
    for(let r=1;r<=this.ROWS;r++)for(let c=1;c<=this.COLS;c++){
      let v=this.dispVal(this.cells.get(this.key(r,c)));let qi=q;
      if(!mc){v=v.toLowerCase();qi=qi.toLowerCase();}
      if(mw?v===qi:v.includes(qi))this.findList.push({r,c});
    }
    APP.querySelector('#find-result').textContent=this.findList.length?this.findList.length+' match(es) found':'No matches';
  }

  findNext(dir=1){
    this.buildMatches();if(!this.findList.length)return;
    this.findIdx=(this.findIdx+dir+this.findList.length)%this.findList.length;
    const m=this.findList[this.findIdx];this.select(m.r,m.c);
  }

  async replaceOne(){
    this.buildMatches();if(!this.findList.length)return;
    const m=this.findList[this.findIdx>=0?this.findIdx:0];
    const repl=APP.querySelector('#replace-input')?.value??'';
    await this.flush([this.buildUpd(m.r,m.c,repl)]);this.findIdx--;this.findNext(1);
    this.toast('Replaced 1 cell','success');
  }

  async replaceAll(){
    this.buildMatches();if(!this.findList.length){this.toast('No matches','warn');return;}
    const repl=APP.querySelector('#replace-input')?.value??'';
    await this.flush(this.findList.map(m=>this.buildUpd(m.r,m.c,repl)));
    this.toast('Replaced '+this.findList.length+' cell(s)','success');
  }

  /* ── named range ── */
  async createNamedRange(){
    const name=APP.querySelector('#named-name')?.value.trim();
    const range=APP.querySelector('#named-range')?.value.trim();
    if(!name||!range)return;
    try{
      await api(`/workbooks/${this.wbId}/named-ranges`,{method:'POST',body:{name,range,sheet_id:this.activeSht}});
      this.toast(`Named range "${name}" created`,'success');this.closeModal('modal-named');
    }catch(e){this.toast('Failed: '+e.message,'error');}
  }

  /* ── sheets ── */
  nextSheetName(){
    const names=new Set(this.sheets.map(s=>s.name));
    for(let i=2;i<1000;i++){
      const c='Sheet'+i;
      if(!names.has(c))return c;
    }
    return 'Sheet'+Date.now();
  }

  apiErrMsg(e){
    const raw=e?.message||'';
    try{
      const j=JSON.parse(raw);
      return j.message||j.errors?.name?.[0]||raw;
    }catch{return raw||'Unknown error';}
  }

  async addSheet(){
    const name=this.nextSheetName();
    try{
      const res=await api(`/workbooks/${this.wbId}/sheets`,{method:'POST',body:{name}});
      const sheet=res?.data;
      if(!sheet?.id)throw new Error('Invalid server response');
      this.sheets.push(sheet);
      this.activeSht=sheet.id;
      this.mergedCells=[];
      this.renderTabs();
      await this.loadSheet();
      this.toast('Added '+sheet.name,'success');
    }catch(e){this.toast('Could not add sheet: '+this.apiErrMsg(e),'error');}
  }

  /* ── color panel ── */
  initSwatches(){
    const sw=APP.querySelector('#color-swatches');if(!sw)return;
    SWATCHES.forEach(hex=>{
      const d=document.createElement('div');d.className='xl-swatch';d.style.background=hex;d.title=hex;
      d.onclick=()=>this.applyColor(hex);sw.appendChild(d);
    });
  }

  openColorPanel(target,anchorEl){
    this.colorTarget=target;
    const p=this.$.colorPanel;
    APP.querySelector('#color-picker-title').textContent=target==='font'?'Font Color':'Fill Color';
    const rect=anchorEl.getBoundingClientRect();
    p.style.left=rect.left+'px';p.style.top=(rect.bottom+2)+'px';
    p.style.display='block';
    const close=ev=>{if(!p.contains(ev.target)&&ev.target!==anchorEl){p.style.display='none';document.removeEventListener('mousedown',close);}};
    setTimeout(()=>document.addEventListener('mousedown',close),10);
  }

  applyColor(hex){
    const h=hex.startsWith('#')?hex:'#'+hex;
    if(this.colorTarget==='font'){
      this.applyProp('color',h.replace('#',''));
      const b=APP.querySelector('#font-color-bar');if(b)b.style.background=h;
      const inp=APP.querySelector('#font-color');if(inp)inp.value=h;
    }else{
      this.applyProp('bg',h.replace('#',''));
      const b=APP.querySelector('#fill-color-bar');if(b)b.style.background=h;
      const inp=APP.querySelector('#fill-color');if(inp)inp.value=h;
    }
    this.$.colorPanel.style.display='none';
  }

  initBorderPanel(){
    const p=this.$.borderPanel;if(!p)return;
    p.addEventListener('mousedown',e=>e.stopPropagation());
    p.addEventListener('mousedown',e=>{
      const btn=e.target.closest('[data-border]');
      if(!btn)return;
      e.preventDefault();
      this.applyBorders(btn.dataset.border);
    });
  }

  openBorderPanel(anchorEl){
    const p=this.$.borderPanel;if(!p)return;
    const rect=anchorEl.getBoundingClientRect();
    p.style.left=rect.left+'px';
    p.style.top=(rect.bottom+2)+'px';
    p.style.display='block';
    const close=ev=>{
      if(!p.contains(ev.target)&&!anchorEl.contains(ev.target)){
        p.style.display='none';
        document.removeEventListener('click',close,true);
      }
    };
    setTimeout(()=>document.addEventListener('click',close,true),0);
  }

  /* ── modals ── */
  openModal(id){const m=APP.querySelector('#'+id);if(m)m.style.display='flex';}
  closeModal(id){const m=APP.querySelector('#'+id);if(m)m.style.display='none';}

  /* ── toast ── */
  toast(msg,type='info'){
    const d=document.createElement('div');d.className=`xl-toast xl-toast-${type}`;d.textContent=msg;
    this.$.toasts?.appendChild(d);setTimeout(()=>d.remove(),3000);
  }

  setMode(msg){
    this.$.statusMode.textContent=msg;
    this.$.saveStatus.textContent=msg==='Ready'?'':msg;
  }

  /* ══════════════════════════════════════════════
     EVENT BINDING
     ══════════════════════════════════════════════ */
  bindAll(){
    /* ── drag-select ── */
    let dragging=false;
    this.$.gridBody.addEventListener('mousedown',e=>{
      if(e.button!==0)return;
      if(this.editing&&this.editMode==='fx')this.finishFxEdit();
      const hit=this.resolveEventCell(e);if(!hit)return;
      const{r,c}=hit;
      if(e.shiftKey){this.select(r,c,true);return;}
      if(!this.inSel(r,c))this.select(r,c);
      else if(!this.isActiveCell(r,c))this.select(r,c);
      dragging=true;
    });
    document.addEventListener('mousemove',e=>{
      if(!dragging)return;
      const el=document.elementFromPoint(e.clientX,e.clientY);
      const td=el?.closest?.('td[data-row]');if(!td)return;
      let r=+td.dataset.row,c=+td.dataset.col;
      const ma=this.mergeAnchorCoords(r,c);if(ma){r=ma.r;c=ma.c;}
      if(r!==this.sel.r2||c!==this.sel.c2){
        this.sel.r2=r;this.sel.c2=c;
        this.expandSelForMerges();
        this.updateSelectionChrome();
      }
    });
    document.addEventListener('mouseup',()=>{
      if(dragging){
        this.expandSelForMerges();
        this.updateSelectionChrome();
      }
      dragging=false;
    });

    /* dblclick to edit — avoid re-render on 1st click so dblclick works */
    this.$.gridBody.addEventListener('dblclick',e=>{
      e.preventDefault();
      const hit=this.resolveEventCell(e);if(!hit)return;
      this.startEdit(hit.td);
    });

    /* type to start edit */
    document.addEventListener('keydown',e=>{
      /* skip if inside modal/input/textarea */
      if(e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA'||e.target.tagName==='SELECT') return;
      if(e.target.closest('.xl-modal')||e.target.closest('.xl-fxbar'))return;

      const ctrl=e.ctrlKey||e.metaKey,sh=e.shiftKey;

      if(ctrl){
        if(e.key==='c'){e.preventDefault();this.copy();return;}
        if(e.key==='x'){e.preventDefault();this.copy(true);return;}
        if(e.key==='v'){e.preventDefault();this.paste();return;}
        if(e.key==='z'){e.preventDefault();sh?this.redo():this.undo();return;}
        if(e.key==='y'){e.preventDefault();this.redo();return;}
        if(e.key==='b'){e.preventDefault();this.toggleStyle('bold');return;}
        if(e.key==='i'){e.preventDefault();this.toggleStyle('italic');return;}
        if(e.key==='u'){e.preventDefault();this.toggleStyle('underline');return;}
        if(e.key==='d'){e.preventDefault();this.fillDown();return;}
        if(e.key==='r'){e.preventDefault();this.fillRight();return;}
        if(e.key==='f'){e.preventDefault();this.openFind();return;}
        if(e.key==='h'){e.preventDefault();this.openFind(true);return;}
        if(e.key==='a'){e.preventDefault();this.selectAll();return;}
        if(e.key==='Home'){e.preventDefault();this.select(1,1);return;}
        if(e.key==='ArrowUp')   {e.preventDefault();this.jumpEdge(-1, 0,sh);return;}
        if(e.key==='ArrowDown') {e.preventDefault();this.jumpEdge( 1, 0,sh);return;}
        if(e.key==='ArrowLeft') {e.preventDefault();this.jumpEdge( 0,-1,sh);return;}
        if(e.key==='ArrowRight'){e.preventDefault();this.jumpEdge( 0, 1,sh);return;}
      }

      const arrows={ArrowUp:[-1,0],ArrowDown:[1,0],ArrowLeft:[0,-1],ArrowRight:[0,1]};
      if(arrows[e.key]){e.preventDefault();this.move(...arrows[e.key],sh);return;}
      if(e.key==='Home')     {e.preventDefault();this.select(this.sel.r1,1);return;}
      if(e.key==='End')      {e.preventDefault();this.jumpEdge(0,1);return;}
      if(e.key==='PageDown') {e.preventDefault();this.move(20,0,sh);return;}
      if(e.key==='PageUp')   {e.preventDefault();this.move(-20,0,sh);return;}
      if(e.key==='Tab')      {e.preventDefault();this.move(0,sh?-1:1);return;}
      if(e.key==='Enter')    {e.preventDefault();this.move(sh?-1:1,0);return;}
      if(e.key==='Delete'||e.key==='Backspace'){e.preventDefault();this.clearSel();return;}
      if(e.key==='F2')       {e.preventDefault();this.editActiveCell();return;}
      if(e.key==='Escape')   {this.copySrc=null;this.renderAll();return;}

      if(!e.ctrlKey&&!e.metaKey&&!e.altKey&&e.key.length===1){
        const td=this.anchorCellEl(this.sel.r1,this.sel.c1);
        if(td){e.preventDefault();this.startEdit(td,e.key);}
      }
    });

    /* ── context menu ── */
    this.$.gridBody.addEventListener('contextmenu',e=>{
      e.preventDefault();
      const hit=this.resolveEventCell(e);
      if(hit&&!this.inSel(hit.r,hit.c))this.select(hit.r,hit.c);
      this.syncCtxMenu();
      const m=this.$.ctx;m.style.display='block';
      m.style.left=Math.min(e.clientX,window.innerWidth-230)+'px';
      m.style.top= Math.min(e.clientY,window.innerHeight-420)+'px';
    });
    document.addEventListener('click',e=>{if(!e.target.closest('#ctx-menu'))this.$.ctx.style.display='none';});
    this.$.ctx.querySelectorAll('[data-action]').forEach(el=>{
      el.addEventListener('click',()=>{
        if(el.classList.contains('disabled'))return;
        const a=el.dataset.action;this.$.ctx.style.display='none';
        if(a==='edit-cell')       this.editActiveCell();
        if(a==='cut')             this.copy(true);
        if(a==='copy')            this.copy();
        if(a==='paste')           this.paste();
        if(a==='paste-values')    this.paste(true);
        if(a==='clear')           this.clearSel();
        if(a==='fill-down')       this.fillDown();
        if(a==='fill-right')      this.fillRight();
        if(a==='merge-cells')     this.mergeOrUnmerge();
        if(a==='unmerge-cells')   this.mergeOrUnmerge();
        if(a==='bold')            this.toggleStyle('bold');
        if(a==='italic')          this.toggleStyle('italic');
        if(a==='underline')       this.toggleStyle('underline');
        if(a==='undo')            this.undo();
        if(a==='redo')            this.redo();
        if(a==='insert-row-above')this.insertRow(true);
        if(a==='insert-row-below')this.insertRow(false);
        if(a==='delete-row')      this.deleteRow();
        if(a==='insert-col-left') this.insertCol(true);
        if(a==='insert-col-right')this.insertCol(false);
        if(a==='delete-col')      this.deleteCol();
        if(a==='find')            this.openFind();
        if(a==='replace')         this.openFind(true);
        if(a==='hyperlink')       this.openHyperlinkModal();
      });
    });

    /* ── formula bar ── */
    this.$.fxBar.addEventListener('focus',()=>{
      this.editing=true;
      this.editMode='fx';
      this.editAt=this.activeCellCoords();
      const ac=this.editAt;
      this.$.fxBar.value=this.editVal(this.cells.get(this.key(ac.r,ac.c)));
      this.syncFxPreview();
      const fxc=APP.querySelector('#btn-fx-cancel'),fxo=APP.querySelector('#btn-fx-commit');
      if(fxc)fxc.style.display='';if(fxo)fxo.style.display='';
    });
    this.$.fxBar.addEventListener('input',()=>{
      const v=this.$.fxBar.value;
      if(v.startsWith('='))this.showAC(v,this.$.fxBar);else this.hideAC();
      this.syncFxPreview();
    });
    this.$.fxBar.addEventListener('keydown',e=>{
      if(e.key==='Enter'){e.preventDefault();this.commitFx();}
      if(e.key==='Escape'){
        this.endEdit();
        this.updateName();
        const ac=this.activeCellCoords();
        this.renderCell(ac.r,ac.c);
      }
    });
    APP.querySelector('#btn-fx-commit')?.addEventListener('click',()=>this.commitFx());
    APP.querySelector('#btn-fx-cancel').onclick=()=>{
      this.endEdit();
      this.updateName();
      const ac=this.activeCellCoords();
      this.renderCell(ac.r,ac.c);
    };

    /* name box navigation */
    this.$.nameBox.addEventListener('keydown',e=>{
      if(e.key!=='Enter')return;
      const ref=this.$.nameBox.value.trim().toUpperCase();
      if(ref.includes(':')){
        const[a,b]=ref.split(':');const ra=a1ToRC(a),rb=a1ToRC(b);
        if(ra&&rb){
          this.sel={r1:Math.min(ra.r,rb.r),c1:Math.min(ra.c,rb.c),r2:Math.max(ra.r,rb.r),c2:Math.max(ra.c,rb.c)};
          this.expandSelForMerges();
          this.renderAll();
        }
      }else{const rc=a1ToRC(ref);if(rc)this.select(rc.r,rc.c);}
      this.$.nameBox.blur();
    });

    /* fill handle */
    this.$.fillHnd.addEventListener('mousedown',e=>this.startFillDrag(e));

    /* ── ribbon tabs ── */
    APP.querySelectorAll('.xl-tab[data-tab]').forEach(tab=>{
      tab.onclick=()=>{
        APP.querySelectorAll('.xl-tab[data-tab]').forEach(t=>t.classList.remove('xl-tab-active'));
        tab.classList.add('xl-tab-active');
        ['home','insert','formulas','data','view'].forEach(p=>{
          const panel=APP.querySelector('#panel-'+p);
          if(panel)panel.classList.toggle('xl-panel-hidden',tab.dataset.tab!==p);
        });
      };
    });

    /* ── HOME panel ── */
    this._btn('btn-cut',    ()=>this.copy(true));
    this._btn('btn-copy',   ()=>this.copy());
    this._btn('btn-paste',  ()=>this.paste());
    this._btn('btn-paste-values',()=>this.paste(true));
    this._btn('btn-clear',  ()=>this.clearSel());
    this._btn('btn-fill-down', ()=>this.fillDown());
    this._btn('btn-fill-right',()=>this.fillRight());
    this._btn('btn-undo',   ()=>this.undo());
    this._btn('btn-redo',   ()=>this.redo());
    this._btn('btn-find',   ()=>this.openFind());
    this._btn('btn-replace',()=>this.openFind(true));
    this._btn('btn-autosum',()=>this.autoSum());
    this._btn('btn-ins-row',()=>this.insertRow(true));
    this._btn('btn-del-row',()=>this.deleteRow());
    this._btn('btn-ins-col',()=>this.insertCol(true));
    this._btn('btn-del-col',()=>this.deleteCol());

    APP.querySelector('#btn-bold')         ?.addEventListener('click',()=>this.toggleStyle('bold'));
    APP.querySelector('#btn-italic')       ?.addEventListener('click',()=>this.toggleStyle('italic'));
    APP.querySelector('#btn-underline')    ?.addEventListener('click',()=>this.toggleStyle('underline'));
    APP.querySelector('#btn-strikethrough')?.addEventListener('click',()=>this.toggleStyle('strikethrough'));
    APP.querySelector('#btn-wrap-text')    ?.addEventListener('click',()=>this.toggleStyle('wrap'));
    APP.querySelector('#btn-merge')        ?.addEventListener('click',()=>this.mergeOrUnmerge());
    APP.querySelector('#btn-align-left')   ?.addEventListener('click',()=>this.applyProp('align','left'));
    APP.querySelector('#btn-align-center') ?.addEventListener('click',()=>this.applyProp('align','center'));
    APP.querySelector('#btn-align-right')  ?.addEventListener('click',()=>this.applyProp('align','right'));
    APP.querySelector('#btn-valign-top')   ?.addEventListener('click',()=>this.applyProp('valign','top'));
    APP.querySelector('#btn-valign-mid')   ?.addEventListener('click',()=>this.applyProp('valign','middle'));
    APP.querySelector('#btn-valign-bot')   ?.addEventListener('click',()=>this.applyProp('valign','bottom'));
    APP.querySelector('#number-format')    ?.addEventListener('change',e=>this.applyProp('format',e.target.value));
    APP.querySelector('#font-size')        ?.addEventListener('change',e=>this.applyProp('fontSize',+e.target.value));
    APP.querySelector('#font-family')      ?.addEventListener('change',e=>this.applyProp('fontFamily',e.target.value));

    APP.querySelector('#btn-fmt-currency') ?.addEventListener('click',()=>this.applyProp('format','currency'));
    APP.querySelector('#btn-fmt-percent')  ?.addEventListener('click',()=>this.applyProp('format','percent'));
    APP.querySelector('#btn-fmt-comma')    ?.addEventListener('click',()=>this.applyProp('format','integer'));
    APP.querySelector('#btn-inc-font')     ?.addEventListener('click',()=>{
      const st=cellStyle(this.cells.get(this.key(this.sel.r1,this.sel.c1))?.style);
      this.applyProp('fontSize',(st.fontSize||11)+2);
    });
    APP.querySelector('#btn-dec-font')?.addEventListener('click',()=>{
      const st=cellStyle(this.cells.get(this.key(this.sel.r1,this.sel.c1))?.style);
      this.applyProp('fontSize',Math.max(6,(st.fontSize||11)-2));
    });

    /* color pickers — click preview to apply current color, dropdown to open palette */
    APP.querySelector('#font-color-preview')?.addEventListener('click',()=>this.applyCurrentFontColor());
    APP.querySelector('#fill-color-preview')?.addEventListener('click',()=>this.applyCurrentFillColor());
    APP.querySelector('#font-color-picker-btn')?.addEventListener('click',e=>{
      e.stopPropagation();
      this.openColorPanel('font',e.currentTarget);
    });
    APP.querySelector('#fill-color-picker-btn')?.addEventListener('click',e=>{
      e.stopPropagation();
      this.openColorPanel('fill',e.currentTarget);
    });
    /* fallback direct inputs */
    APP.querySelector('#font-color')?.addEventListener('change',e=>this.applyProp('color',e.target.value.replace('#','')));
    APP.querySelector('#fill-color')?.addEventListener('change',e=>this.applyProp('bg',e.target.value.replace('#','')));
    APP.querySelector('#btn-borders')?.addEventListener('click',e=>{
      e.stopPropagation();
      this.openBorderPanel(e.currentTarget);
    });
    APP.querySelector('#btn-color-custom')?.addEventListener('click',()=>{
      const inp=APP.querySelector('#color-custom');inp?.click();
      inp?.addEventListener('change',e=>{this.applyColor(e.target.value);},{once:true});
    });

    /* ── INSERT panel ── */
    this._btn('btn-ins-row-above',()=>this.insertRow(true));
    this._btn('btn-ins-row-below',()=>this.insertRow(false));
    this._btn('btn-ins-col-left', ()=>this.insertCol(true));
    this._btn('btn-ins-col-right',()=>this.insertCol(false));
    this._btn('btn-del-row2',     ()=>this.deleteRow());
    this._btn('btn-del-col2',     ()=>this.deleteCol());
    this._btn('btn-named-range',  ()=>{
      const s=this.norm();
      const rng=colLetter(s.c1)+s.r1+(s.r1!==s.r2||s.c1!==s.c2?':'+colLetter(s.c2)+s.r2:'');
      const el=APP.querySelector('#named-range');if(el)el.value='='+rng;
      this.openModal('modal-named');
    });

    /* ── FORMULAS panel ── */
    this._btn('btn-autosum2',()=>this.autoSum());
    this._btn('btn-insert-fn',()=>{
      this.$.fxBar.focus();
      this.$.fxBar.value='=';
      this.editing=true;
      this.editMode='fx';
      this.editAt=this.activeCellCoords();
      const fxc=APP.querySelector('#btn-fx-cancel'),fxo=APP.querySelector('#btn-fx-commit');
      if(fxc)fxc.style.display='';if(fxo)fxo.style.display='';
      this.showAC('=',this.$.fxBar);
    });
    APP.querySelectorAll('[data-formula]').forEach(btn=>{
      btn.addEventListener('click',()=>this.applyFormula(this.buildFormulaTemplate(btn.dataset.formula)));
    });

    /* ── DATA panel ── */
    this._btn('btn-sort-asc', ()=>this.doSort(this.sel.c1,'asc'));
    this._btn('btn-sort-desc',()=>this.doSort(this.sel.c1,'desc'));
    this._btn('btn-sort-dialog',()=>{
      const sel=APP.querySelector('#sort-col');sel.innerHTML='';
      for(let c=1;c<=this.COLS;c++){
        const o=document.createElement('option');o.value=c;
        o.textContent=colLetter(c)+' — '+(this.dispVal(this.cells.get(this.key(1,c)))||'Column '+colLetter(c));
        sel.appendChild(o);
      }
      sel.value=this.sel.c1;this.openModal('modal-sort');
    });
    this._btn('btn-sort-ok',()=>{
      const c=+APP.querySelector('#sort-col').value;
      const d=APP.querySelector('#sort-dir').value;
      this.closeModal('modal-sort');this.doSort(c,d);
    });
    this._btn('btn-filter',async()=>{
      const on=!APP.querySelector('#btn-filter')?.classList.contains('active');
      APP.querySelector('#btn-filter')?.classList.toggle('active',on);
      try{await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/filter`,{method:'POST',body:{enabled:on}});await this.loadSheet();}catch{}
    });
    this._btn('btn-clear-filter',async()=>{
      APP.querySelector('#btn-filter')?.classList.remove('active');
      try{await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/filter`,{method:'POST',body:{enabled:false}});await this.loadSheet();}catch{}
    });

    /* ── VIEW panel ── */
    this._btn('btn-freeze-row', ()=>this.setFreeze(this.frozenR?0:1,this.frozenC));
    this._btn('btn-freeze-col', ()=>this.setFreeze(this.frozenR,this.frozenC?0:1));
    this._btn('btn-freeze-none',()=>this.setFreeze(0,0));
    this._btn('btn-zoom-in',  ()=>this.setZoom(this.zoom+0.1));
    this._btn('btn-zoom-out', ()=>this.setZoom(this.zoom-0.1));
    this.$.zoomSlider?.addEventListener('input',e=>this.setZoom(+e.target.value/100));

    APP.querySelector('#chk-gridlines')?.addEventListener('change',e=>{
      this.$.grid.classList.toggle('hide-gridlines',!e.target.checked);
    });
    APP.querySelector('#chk-headers')?.addEventListener('change',e=>{
      this.$.colHdr.style.display=e.target.checked?'':'none';
      this.$.gridBody.querySelectorAll('th').forEach(th=>th.style.display=e.target.checked?'':'none');
    });
    APP.querySelector('#chk-formulas')?.addEventListener('change',e=>{
      this.showFml=e.target.checked;this.renderAll();
    });

    /* ── modals generic close ── */
    APP.querySelectorAll('[data-close]').forEach(btn=>{
      btn.addEventListener('click',()=>this.closeModal(btn.dataset.close));
    });
    APP.querySelectorAll('.xl-modal').forEach(m=>{
      m.addEventListener('click',e=>{if(e.target===m)this.closeModal(m.id);});
    });

    /* find/replace */
    this._btn('btn-find-next',  ()=>this.findNext(1));
    this._btn('btn-find-prev',  ()=>this.findNext(-1));
    this._btn('btn-replace-one',()=>this.replaceOne());
    this._btn('btn-replace-all',()=>this.replaceAll());
    APP.querySelector('#find-input')?.addEventListener('keydown',e=>{
      if(e.key==='Enter')this.findNext(e.shiftKey?-1:1);
      if(e.key==='Escape')this.closeModal('modal-find');
    });

    /* named range */
    this._btn('btn-named-ok',()=>this.createNamedRange());

    /* ── import/export ── */
    APP.querySelector('#input-import')?.addEventListener('change',async e=>{
      const file=e.target.files?.[0];if(!file)return;
      const form=new FormData();form.append('file',file);
      this.setMode('Importing…');
      try{
        await api(`/workbooks/${this.wbId}/sheets/${this.activeSht}/import`,{method:'POST',body:form,headers:{}});
        await this.loadSheet();this.toast('Imported successfully','success');
      }catch(err){this.toast('Import failed: '+err.message,'error');}
      e.target.value='';
    });
    this._btn('btn-export',async()=>{
      try{await dlBlob(`/workbooks/${this.wbId}/export`,(this.$.wbName.value.trim()||'workbook')+'.xlsx');this.toast('Exported','success');}
      catch{this.toast('Export failed','error');}
    });
    this._btn('btn-export-csv',async()=>{
      try{
        await dlBlob(`/workbooks/${this.wbId}/export/csv?sheet_id=${this.activeSht}`,(this.$.wbName.value.trim()||'workbook')+'.csv');
        this.toast('CSV exported','success');
      }catch{this.toast('CSV export failed','error');}
    });
    this._btn('btn-import-google',()=>this.openModal('modal-google'));
    this._btn('btn-google-import',async()=>{
      const url=APP.querySelector('#google-url')?.value?.trim();
      const name=APP.querySelector('#google-name')?.value?.trim();
      if(!url)return this.toast('Google Sheet URL required','error');
      this.setMode('Importing Google Sheet…');
      try{
        await api('/workbooks/import/google-sheets',{method:'POST',body:{url,name:name||undefined}});
        this.toast('Google Sheet imported — open it from Workbooks','success');
        this.closeModal('modal-google');
      }catch(e){this.toast('Import failed: '+e.message,'error');}
      this.setMode('Ready');
    });
    this._btn('btn-share',()=>this.openShareModal());
    this._btn('btn-share-add',()=>this.addShare());
    this._btn('btn-hyperlink',()=>this.openHyperlinkModal());
    this._btn('btn-hyperlink-ok',()=>this.applyHyperlink());
    this._btn('btn-hyperlink-remove',()=>this.removeHyperlink());

    /* ── sheet bar ── */
    this._btn('btn-add-sheet',()=>this.addSheet());

    /* ── workbook name ── */
    let nt;
    this.$.wbName.addEventListener('input',()=>{
      clearTimeout(nt);nt=setTimeout(async()=>{
        const name=this.$.wbName.value.trim();if(!name)return;
        try{await api(`/workbooks/${this.wbId}`,{method:'PATCH',body:{name}});}catch{}
      },600);
    });
  }

  _btn(id,fn){const el=APP.querySelector('#'+id);if(el)el.addEventListener('click',fn);}

  async openShareModal(){
    if(!this.isOwner)return;
    this.openModal('modal-share');
    await this.loadShares();
  }

  async loadShares(){
    const list=APP.querySelector('#share-list');
    if(!list)return;
    try{
      const res=await api(`/workbooks/${this.wbId}/shares`);
      const shares=res.data||[];
      list.innerHTML=shares.length?shares.map(s=>`
        <div class="share-row">
          <div><strong>${s.user?.name||'User'}</strong><br><span class="audit-muted">${s.user?.email||''}</span></div>
          <select data-share-id="${s.id}" class="field share-perm">
            <option value="read" ${s.permission==='read'?'selected':''}>View only</option>
            <option value="write" ${s.permission==='write'?'selected':''}>Can edit</option>
          </select>
          <button type="button" class="btn btn-secondary btn-sm" data-remove-share="${s.id}">Remove</button>
        </div>
      `).join(''):'<p class="audit-muted">Not shared with anyone yet.</p>';
      list.querySelectorAll('.share-perm').forEach(sel=>{
        sel.addEventListener('change',async()=>{
          try{
            await api(`/workbooks/${this.wbId}/shares/${sel.dataset.shareId}`,{method:'PATCH',body:{permission:sel.value}});
            this.toast('Permission updated','success');
          }catch(e){this.toast(e.message,'error');}
        });
      });
      list.querySelectorAll('[data-remove-share]').forEach(btn=>{
        btn.addEventListener('click',async()=>{
          try{
            await api(`/workbooks/${this.wbId}/shares/${btn.dataset.removeShare}`,{method:'DELETE'});
            await this.loadShares();
          }catch(e){this.toast(e.message,'error');}
        });
      });
    }catch(e){list.innerHTML='<p class="audit-empty">'+e.message+'</p>';}
  }

  async addShare(){
    const email=APP.querySelector('#share-email')?.value?.trim();
    const permission=APP.querySelector('#share-permission')?.value||'read';
    if(!email)return this.toast('Email required','error');
    try{
      await api(`/workbooks/${this.wbId}/shares`,{method:'POST',body:{email,permission}});
      APP.querySelector('#share-email').value='';
      await this.loadShares();
      this.toast('Workbook shared','success');
    }catch(e){this.toast(e.message,'error');}
  }

  openHyperlinkModal(){
    const ac=this.activeCellCoords();
    const cell=this.cells.get(this.key(ac.r,ac.c));
    const link=cellStyle(cell?.style).hyperlink||{};
    APP.querySelector('#hyperlink-url').value=link.url||'';
    APP.querySelector('#hyperlink-text').value=link.display||this.dispVal(cell)||'';
    this.openModal('modal-hyperlink');
  }

  async applyHyperlink(){
    if(!this.guardWrite())return;
    const ac=this.activeCellCoords();
    const url=APP.querySelector('#hyperlink-url')?.value?.trim();
    const display=APP.querySelector('#hyperlink-text')?.value?.trim();
    if(!url)return this.toast('URL required','error');
    const cell=this.cells.get(this.key(ac.r,ac.c))||{row:ac.r,col:ac.c};
    const style={...cellStyle(cell.style),hyperlink:{url,display:display||undefined}};
    const upd={row:ac.r,col:ac.c,style};
    if(display&&!cell.value&&!cell.formula)upd.value=display;
    this.applyLocalUpdate(upd);
    await this.flush([upd]);
    this.closeModal('modal-hyperlink');
  }

  async removeHyperlink(){
    if(!this.guardWrite())return;
    const ac=this.activeCellCoords();
    const cell=this.cells.get(this.key(ac.r,ac.c));
    if(!cell)return this.closeModal('modal-hyperlink');
    const style={...cellStyle(cell.style)};delete style.hyperlink;
    const upd={row:ac.r,col:ac.c,style};
    this.applyLocalUpdate(upd);
    await this.flush([upd]);
    this.closeModal('modal-hyperlink');
  }
}

new SynexelApp();
})();

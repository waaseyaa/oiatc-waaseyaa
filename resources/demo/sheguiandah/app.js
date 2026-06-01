/* ============================================================
   Anokii app logic (vanilla JS, in-memory state only)
   ============================================================ */
const $=s=>document.querySelector(s);
const $$=s=>[...document.querySelectorAll(s)];
const el=(h)=>{const t=document.createElement('template');t.innerHTML=h.trim();return t.content.firstElementChild;};
const esc=s=>String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const initials=n=>n.replace(/[^A-Za-z ].*/,'').trim().split(/\s+/).slice(0,2).map(w=>w[0]||'').join('').toUpperCase()||'??';

/* ---- small icon set (inline svg) ---- */
const I={
  pdf:'<svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.6"/><path d="M9 13h6M9 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
  doc:'<svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.6"/><path d="M9 11h6M9 14h6M9 17h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
  xls:'<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="4" width="14" height="16" rx="1.5" stroke="currentColor" stroke-width="1.6"/><path d="M9 8h6M9 12h6M9 16h6M12 8v8" stroke="currentColor" stroke-width="1.4"/></svg>',
  img:'<svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><circle cx="9" cy="10" r="1.6" fill="currentColor"/><path d="m5 18 4-4 3 3 3-4 4 5" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>',
  gen:'<svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.6"/></svg>',
  fold:'<svg viewBox="0 0 24 24" fill="none"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.6"/></svg>',
  share:'<svg viewBox="0 0 24 24" fill="none"><circle cx="6" cy="12" r="2.4" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="6" r="2.4" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="18" r="2.4" stroke="currentColor" stroke-width="1.6"/><path d="m8 11 7-4M8 13l7 4" stroke="currentColor" stroke-width="1.6"/></svg>',
  ai:'<svg viewBox="0 0 24 24" fill="none"><path d="M5 5h14v10H8l-3 3V5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
  clock:'<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
  upload:'<svg viewBox="0 0 24 24" fill="none"><path d="M12 16V5m0 0 4 4m-4-4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 19h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  rename:'<svg viewBox="0 0 24 24" fill="none"><path d="M4 16.5 14 6.5l3.5 3.5L7.5 20H4v-3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
  vault:'<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.6"/></svg>',
  add:'<svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  trash:'<svg viewBox="0 0 24 24" fill="none"><path d="M5 7h14M9 7V5h6v2M7 7l1 13h8l1-13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  check:'<svg viewBox="0 0 24 24" fill="none"><path d="m5 13 4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  eye:'<svg viewBox="0 0 24 24" fill="none"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.6"/></svg>',
  eyeoff:'<svg viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18M10 5.5A9.8 9.8 0 0 1 12 5c6 0 10 7 10 7a16 16 0 0 1-3 3.6M6 7.5A16 16 0 0 0 2 12s4 7 10 7a9.7 9.7 0 0 0 3.5-.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
  doc2:'<svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.6"/></svg>',
  globe:'<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.6"/><path d="M4 12h16M12 4c2.4 2.6 2.4 13.4 0 16M12 4c-2.4 2.6-2.4 13.4 0 16" stroke="currentColor" stroke-width="1.4"/></svg>',
  megaphone:'<svg viewBox="0 0 24 24" fill="none"><path d="M4 10v4l9 4V6l-9 4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M13 8a4 4 0 0 1 0 8" stroke="currentColor" stroke-width="1.6"/></svg>',
  users:'<svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M3 19a6 6 0 0 1 12 0" stroke="currentColor" stroke-width="1.6"/><path d="M16 6a3 3 0 0 1 0 6M21 19a6 6 0 0 0-5-5.9" stroke="currentColor" stroke-width="1.5"/></svg>',
  spark:'<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17l-1.9-5.1L4.5 10l5.6-1.4L12 3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>'
};
const fileIcon=t=>({pdf:'pdf',doc:'doc',xls:'xls',img:'img',gen:'gen'}[t]||'gen');
const fileGlyph=t=>I[t]||I.gen;

/* ---- in-memory state ---- */
const state={module:'home',driveFolder:null,room:null,workspace:null,vaultTab:'credentials',portalTab:'pages',chat:{messages:[],busy:false}};

/* ===================== toasts / modal / panels ===================== */
function toast(msg){
  const t=el(`<div class="toast">${I.check}<span>${esc(msg)}</span></div>`);
  $('#toasts').appendChild(t);
  setTimeout(()=>{t.classList.add('out');setTimeout(()=>t.remove(),300);},2600);
}
function closePanel(){$('#sidepanel').classList.remove('open');}
function openPanel(html){const p=$('#sidepanel');p.innerHTML=html;p.classList.add('open');}
function closeMenu(){$('#ctxmenu').classList.remove('open');}
function modal(html){const o=$('#overlay');o.innerHTML=`<div class="modal">${html}</div>`;o.classList.add('open');}
function closeModal(){$('#overlay').classList.remove('open');$('#overlay').innerHTML='';}
$('#overlay').addEventListener('click',e=>{if(e.target.id==='overlay')closeModal();});
document.addEventListener('click',e=>{
  if(!e.target.closest('#ctxmenu')&&!e.target.closest('.dots'))closeMenu();
  if(!e.target.closest('#bellBtn')&&!e.target.closest('#notifPop'))$('#notifPop').classList.remove('open');
  if(!e.target.closest('.search'))$('#searchResults').classList.remove('open');
});

/* ===================== nav ===================== */
function buildNav(){
  const nav=$('#nav');nav.innerHTML='';
  nav.appendChild(el('<div class="group-label">Workspace</div>'));
  MODULES.forEach((m,i)=>{
    if(m.id==='governance')nav.appendChild(el('<div class="group-label">Administration</div>'));
    const a=el(`<a class="navlink" data-mod="${m.id}"><svg viewBox="0 0 24 24">${m.icon}</svg><span>${m.label}</span></a>`);
    if(m.id==='ai')a.appendChild(el('<span class="badge">AI</span>'));
    a.onclick=()=>navigate(m.id);
    nav.appendChild(a);
  });
}
function setActive(){$$('.navlink').forEach(a=>a.classList.toggle('active',a.dataset.mod===state.module));}

function navigate(mod,opts={}){
  state.module=mod;
  if(mod!=='drive')state.driveFolder=opts.folder??state.driveFolder;
  if(opts.reset)state.driveFolder=null;
  setActive();closePanel();closeMenu();
  const c=$('#content');c.scrollTop=0;
  const r={home:renderHome,drive:renderDrive,ai:renderAI,rooms:renderRooms,workspaces:renderWorkspaces,portal:renderPortal,vault:renderVault,governance:renderGovernance}[mod];
  c.innerHTML='';c.appendChild(r(opts));
}

/* ===================== DASHBOARD ===================== */
function renderHome(){
  const wrap=el('<div></div>');
  wrap.appendChild(el(`<div class="hero">
    <h1>Aanii, Matthew</h1>
    <div style="position:relative;color:#cfc7ee;font-size:12.5px;font-style:italic;margin:-2px 0 8px">Anokii · Anishinaabemowin for &ldquo;she/he works&rdquo;</div>
    <p>Welcome to Anokii, your Nation&rsquo;s sovereign workspace. Files, AI, secure rooms, projects and a vault in one place. Owned by your Nation, hosted where your Nation decides.</p>
    <div class="chips"><span>7 departments in Drive</span><span>3 active Data Rooms</span><span>3 Workspaces</span><span>Data residency: Toronto, ON</span></div>
  </div>`));
  const tiles=el('<div class="tiles"></div>');
  const cfg={
    drive:["Drive","#4632DA","Department file storage, scoped to the Nation."],
    ai:["Co-Intelligence","#1FB7DD","Ask questions of your Nation&rsquo;s documents and decisions."],
    rooms:["Data Rooms","#2E1A6B","Secure, time-bound spaces with full audit trails."],
    workspaces:["Workspaces","#7a44d6","Run projects without them living in someone&rsquo;s inbox."],
    portal:["Portal","#1591b0","Run the public website and members portal from one place."],
    vault:["Vault","#241b3f","Credentials and confidential records, locked down."],
    governance:["Governance","#138a55","See who has access and where data lives."]
  };
  Object.entries(cfg).forEach(([id,[t,col,d]])=>{
    const m=MODULES.find(x=>x.id===id);
    const tile=el(`<div class="card tile"><div class="tic" style="background:${col}"><svg viewBox="0 0 24 24" style="width:22px;height:22px">${m.icon}</svg></div><h3>${t}</h3><p>${d}</p></div>`);
    tile.onclick=()=>navigate(id,{reset:true});
    tiles.appendChild(tile);
  });
  wrap.appendChild(tiles);
  wrap.appendChild(el('<hr class="soft">'));
  const recent=el('<div class="card" style="padding:6px 0"></div>');
  recent.appendChild(el('<div class="sectitle" style="padding:14px 18px 6px">Recently active across the Nation</div>'));
  [["2026-05-RHT-Settlement-Update.pdf","pdf","Shared by Karen McGraw · Communications"],
   ["Budget-Variance-May-2026.xlsx","xls","Updated by Sandra Thompson · Finance"],
   ["Council-Minutes-2026-05-15.docx","doc","Added by Karen McGraw · Council"],
   ["TKLU-Study-Extract-NorthVein.pdf","pdf","Updated by Marcel Recollet · Lands"]].forEach(([n,t,s])=>{
    const row=el(`<div class="row"><div class="ic ${fileIcon(t)}">${fileGlyph(t)}</div><div class="meta"><b>${esc(n)}</b><small>${esc(s)}</small></div></div>`);
    row.onclick=()=>navigate('drive',{reset:true});
    recent.appendChild(row);
  });
  wrap.appendChild(recent);
  return wrap;
}

/* ===================== DRIVE ===================== */
function renderDrive(){
  const wrap=el('<div></div>');
  if(!state.driveFolder){
    wrap.appendChild(pageHead('Drive','Private file storage for the Nation, organized by department.'));
    const tb=el('<div class="toolbar"></div>');
    tb.appendChild(el(`<span class="pill grey">${DATA.drive.departments.length} departments</span>`));
    tb.appendChild(el('<div class="spacer"></div>'));
    const up=el(`<button class="btn primary">${I.upload}Upload</button>`);
    up.onclick=()=>toast('Open a department folder to upload files');
    tb.appendChild(up);
    wrap.appendChild(tb);
    const card=el('<div class="card"></div>');
    DATA.drive.departments.forEach(dep=>{
      const n=DATA.drive.files[dep].length;
      const row=el(`<div class="row"><div class="ic fold">${I.fold}</div><div class="meta"><b>${dep}</b><small>${n} items</small></div><div class="col">Department</div></div>`);
      row.onclick=()=>{state.driveFolder=dep;navigate('drive',{folder:dep});};
      card.appendChild(row);
    });
    wrap.appendChild(card);
    return wrap;
  }
  // inside a folder
  const dep=state.driveFolder;
  const crumbs=el('<div class="crumbs"></div>');
  const root=el('<a>Drive</a>');root.onclick=()=>{state.driveFolder=null;navigate('drive',{reset:true});};
  crumbs.appendChild(root);
  crumbs.appendChild(el('<span class="sep">/</span>'));
  crumbs.appendChild(el(`<span class="cur">${dep}</span>`));
  wrap.appendChild(crumbs);
  wrap.appendChild(pageHead(dep+' files',`${DATA.drive.files[dep].length} items · stored on Sheguiandah First Nation infrastructure`));
  const tb=el('<div class="toolbar"></div>');
  const up=el(`<button class="btn primary">${I.upload}Upload</button>`);
  up.onclick=()=>fakeUpload(dep,card);
  tb.appendChild(up);
  tb.appendChild(el('<div class="spacer"></div>'));
  tb.appendChild(el('<span class="muted" style="font-size:12.5px">Name &middot; Owner &middot; Modified</span>'));
  wrap.appendChild(tb);
  const card=el('<div class="card" id="driveList"></div>');
  DATA.drive.files[dep].forEach(f=>card.appendChild(driveRow(dep,f)));
  wrap.appendChild(card);
  return wrap;
}
function driveRow(dep,f){
  const [name,type,owner,mod,size,ver,share]=f;
  const row=el(`<div class="row">
    <div class="ic ${fileIcon(type)}">${fileGlyph(type)}</div>
    <div class="meta"><b>${esc(name)}</b><small>${esc(owner)} · ${esc(size)}</small></div>
    <div class="col">${esc(mod)}</div>
    <button class="dots" title="More">⋯</button>
  </div>`);
  row._file=f;row._dep=dep;
  row.onclick=(e)=>{if(e.target.closest('.dots'))return;openFilePanel(f);};
  row.querySelector('.dots').onclick=(e)=>{e.stopPropagation();openFileMenu(e.currentTarget,dep,f,row);};
  return row;
}
function openFilePanel(f){
  const [name,type,owner,mod,size,ver,share]=f;
  openPanel(`
    <div class="sp-head">
      <div class="ic ${fileIcon(type)}" style="width:42px;height:42px">${fileGlyph(type)}</div>
      <div style="flex:1;min-width:0"><div style="font-family:var(--head-font);font-size:15px;word-break:break-word">${esc(name)}</div><small class="muted">${esc(type.toUpperCase())} · ${esc(size)}</small></div>
      <button class="sp-x" onclick="closePanel()">×</button>
    </div>
    <div class="sp-body">
      <div class="kv"><span>Owner</span><b>${esc(owner)}</b></div>
      <div class="kv"><span>Modified</span><b>${esc(mod)}</b></div>
      <div class="kv"><span>Versions</span><b>${ver} version${ver>1?'s':''}</b></div>
      <div class="kv"><span>Size</span><b>${esc(size)}</b></div>
      <div class="kv"><span>Shared with</span><b>${share.length?esc(share.join(', ')):'Only you'}</b></div>
      <div class="kv"><span>Location</span><b>Toronto, ON node</b></div>
      <div style="margin-top:18px;display:flex;gap:8px">
        <button class="btn primary" onclick="toast('Opening preview…')">Open</button>
        <button class="btn" onclick="toast('Share link copied')">Share</button>
        <button class="btn" onclick="toast('Download started (demo)')">Download</button>
      </div>
    </div>`);
}
function openFileMenu(anchor,dep,f,row){
  const m=$('#ctxmenu');
  m.innerHTML=`
    <button data-a="rename">${I.rename}Rename</button>
    <button data-a="share">${I.share}Share</button>
    <button data-a="vault">${I.vault}Move to Vault</button>
    <button data-a="ws">${I.add}Add to Workspace</button>
    <div class="div"></div>
    <button class="danger" data-a="del">${I.trash}Delete</button>`;
  const r=anchor.getBoundingClientRect();
  m.style.left=Math.min(r.left-150,window.innerWidth-200)+'px';
  m.style.top=(r.bottom+6)+'px';
  m.classList.add('open');
  m.querySelectorAll('button').forEach(b=>b.onclick=()=>{
    closeMenu();
    const a=b.dataset.a,name=f[0];
    if(a==='rename')toast('Renamed (demo) · '+name);
    if(a==='share')toast('Share link created for '+name);
    if(a==='vault')toast('Moved to Vault · '+name);
    if(a==='ws')toast('Added to a Workspace · '+name);
    if(a==='del')confirmDelete(name,row);
  });
}
function confirmDelete(name,row){
  modal(`<h3>Delete file?</h3><p>“${esc(name)}” will be removed from Drive. This is a demo, nothing is permanently deleted.</p>
    <div class="actions"><button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" id="delYes">Delete</button></div>`);
  $('#delYes').onclick=()=>{closeModal();row.classList.add('removing');setTimeout(()=>{row.remove();toast('Deleted · '+name);},360);};
}
function fakeUpload(dep,card){
  const prog=el(`<div class="row" style="cursor:default"><div class="ic gen">${I.upload}</div><div class="meta"><b>Uploading New-Document-2026.pdf…</b><div class="bar" style="margin-top:6px"><i style="width:4%"></i></div></div><div class="col"><span class="muted">0%</span></div></div>`);
  card.insertBefore(prog,card.firstChild);
  let p=0;const bar=prog.querySelector('.bar > i');const pct=prog.querySelector('.col span');
  const iv=setInterval(()=>{
    p=Math.min(100,p+Math.random()*22+8);bar.style.width=p+'%';pct.textContent=Math.round(p)+'%';
    if(p>=100){clearInterval(iv);setTimeout(()=>{
      prog.remove();
      const f=["New-Document-2026.pdf","pdf","Matthew Owl","May 29, 2026","1.1 MB",1,[]];
      DATA.drive.files[dep].unshift(f);
      const row=driveRow(dep,f);card.insertBefore(row,card.firstChild);
      toast('Upload complete · New-Document-2026.pdf');
    },350);}
  },240);
}

/* ===================== CO-INTELLIGENCE ===================== */
const CANNED={
  "Draft a community notice for the Comprehensive Community Plan engagement sessions.":{
    steps:[
      ["Opening Workspaces","Comprehensive Community Planning Engagement"],
      ["Reading the engagement plan","Engagement-Plan-CCP.docx"],
      ["Confirming session dates and venue","142 Ogemah Miikan · June 4, 11, 18"],
      ["Drafting in the Nation's voice","Aanii / Miigwetch"]
    ],
    text:`Here is a draft notice for the three CCP engagement sessions. It pulls the dates and venue from the engagement workspace and keeps to the Nation's voice. Review it, then save it to Drive or push it straight to the Portal.`,
    src:["Community-Notice-CCP-Sessions.docx","Engagement-Plan-CCP.docx"],
    artifact:{
      title:"Community notice · CCP engagement sessions",type:"Draft notice",icon:"doc",
      preview:`Aanii Sheguiandah First Nation members,\n\nOur Nation is building its Comprehensive Community Plan, a 10-year roadmap shaped by your voice. Please join us for three engagement sessions at the community hall, 142 Ogemah Miikan:\n\n  Wednesday, June 4: Housing & Lands\n  Wednesday, June 11: Health & Wellness\n  Wednesday, June 18: Education & Economy\n\nDoors open at 5:30 PM. A meal and childcare will be provided at each session. Your input directly guides how the Nation invests over the next decade.\n\nMiigwetch,\nMatthew Owl, Communications Officer`,
      actions:[["Publish to Portal","portal"],["Save to Drive","drive"],["Add to Workspace","workspace"]]
    }
  },
  "Show me all council decisions about housing from the last twelve months.":{
    steps:[
      ["Searching Drive · Council","14 records"],
      ["Reading minutes and BCRs","Sep 2025 to May 2026"],
      ["Filtering for housing decisions","4 matches"],
      ["Building the timeline",""]
    ],
    text:`I found 4 Council decisions on housing in the past twelve months:\n\n1. BCR 2026-031 (May 15, 2026): Approved the 2026 housing allocation framework and prioritized the renovation tender.\n2. Council Minutes (Apr 17, 2026): Directed staff to complete the mould remediation report before reallocating units.\n3. BCR 2025-098 (Nov 2025): Adopted the updated Tenant Handbook and arrears policy.\n4. Council Minutes (Sep 2025): Endorsed applying to the renovation funding stream.\n\nThe Housing Audit 2026 Data Room holds the supporting allocation findings.`,
    src:["BCR-2026-031-Housing-Allocation.pdf","Council-Minutes-2026-05-15.docx","Housing-Waitlist-2026.xlsx"]
  },
  "Summarize the Robinson Huron Treaty settlement updates from May.":{
    steps:[
      ["Opening Data Room","RHT Settlement: Inter-Nation Review"],
      ["Reading 4 documents",""],
      ["Checking the audit trail","who viewed what, when"],
      ["Summarizing May activity",""]
    ],
    text:`Summary of the May Robinson Huron Treaty settlement activity:\n\n• The settlement summary was updated to v3 on May 22 and circulated to Council and external counsel.\n• Counsel's memo on annuities distribution options was reviewed in the Inter-Nation Data Room, with neighbouring Nation representatives now added.\n• A draft inter-Nation response letter is in progress; comments from counsel are pending incorporation.\n• A distribution framework options worksheet was uploaded to model per-member scenarios.\n\nNext step flagged: Council to confirm the preferred distribution approach before the response letter is finalized.`,
    src:["2026-05-RHT-Settlement-Update.pdf","Counsel-Memo-Annuities.pdf","Distribution-Framework-Options.xlsx"]
  },
  "Who is on the Housing Committee right now?":{
    steps:[
      ["Searching Council records","Council-Minutes-2026-05-15.docx"],
      ["Resolving names against the directory",""]
    ],
    text:`The current Housing Committee members are:\n\n• Lorraine Pheasant, Housing Coordinator (chair)\n• Daniel McGregor, Chief\n• Sandra Thompson, Finance Manager\n• Karen McGraw, Director of Operations\n\nThis committee oversees the 2026 allocation and the active Housing Audit. Membership was last confirmed in the May 15 Council minutes.`,
    src:["Council-Minutes-2026-05-15.docx"]
  },
  "Generate a budget variance one-pager for the May Finance review.":{
    steps:[
      ["Opening Drive · Finance",""],
      ["Reading the May variance sheet","Budget-Variance-May-2026.xlsx"],
      ["Comparing to the annual budget","Annual-Budget-2026-27.xlsx"],
      ["Writing the one-pager",""]
    ],
    text:`Here is a one-page budget variance summary for the May Finance review, built from the variance sheet against the annual budget. Save it to Drive or attach it to the Annual Report workspace.`,
    src:["Budget-Variance-May-2026.xlsx","Annual-Budget-2026-27.xlsx"],
    artifact:{
      title:"May 2026 budget variance · one-pager",type:"Generated summary",icon:"doc",
      preview:`MAY 2026 BUDGET VARIANCE\n\nOverall: operating spending is tracking 6% under budget year-to-date, driven mainly by delayed housing renovation starts.\n\n  Administration   on budget (+1%)\n  Housing          14% under, renovation tender awarded late; spend shifts to Q3\n  Health           3% over, additional mental wellness programming\n  Education        on budget; PSE sponsorships fully committed\n  Lands            5% over, NorthVein consultation costs\n\nRecommendation: reallocate the housing underspend into the renovation reserve and flag the Lands overage to Council.`,
      actions:[["Save to Drive","drive"],["Add to Workspace","workspace"]]
    }
  }
};
function genericAnswer(q){
  return {
    steps:[["Searching the Nation's workspace",""],["Reading the relevant records",""],["Composing a response on Nation infrastructure",""]],
    text:`I can help with that, scoped to records held on Sheguiandah First Nation infrastructure. Nothing leaves your environment.\n\nIn a live deployment I would cite the specific files and decisions behind this answer and offer to save or share the result. Try one of the suggested prompts on the right for a fully worked example.`,
    src:["Governance-Manual-2026.pdf"]
  };
}

function renderAI(){
  const wrap=el('<div></div>');
  wrap.appendChild(pageHead('Co-Intelligence','An assistant that knows your Nation&rsquo;s documents, decisions and people, without that data ever leaving your infrastructure.'));
  const layout=el('<div class="chat-wrap"></div>');
  const left=el('<div class="card chat-col" style="padding:16px"></div>');
  const stream=el('<div class="chat-stream" id="chatStream"></div>');
  if(!state.chat.messages.length){
    stream.appendChild(aiMsg(`Aanii Matthew. I can search the Nation's Drive, Workspaces and Council records to answer questions or draft content. What would you like to do?`,[]));
  }else{
    state.chat.messages.forEach(m=>{
      if(m.role==='user'){stream.appendChild(userMsg(m.text));return;}
      const n=aiMsg(m.text,m.src);
      if(m.artifact)n.querySelector('.bubble').appendChild(artifactCard(m.artifact));
      stream.appendChild(n);
    });
  }
  left.appendChild(stream);
  const input=el(`<div class="chat-input"><textarea id="chatBox" placeholder="Ask about a document, decision, or draft something…"></textarea><button class="btn primary" id="sendBtn" style="height:46px">Send</button></div>`);
  left.appendChild(input);
  layout.appendChild(left);

  const right=el('<div></div>');
  const sug=el('<div class="suggested"><h4>Suggested prompts</h4></div>');
  Object.keys(CANNED).forEach(q=>{
    const b=el(`<button class="sug">${esc(q)}</button>`);
    b.onclick=()=>ask(q);
    sug.appendChild(b);
  });
  right.appendChild(sug);
  const rec=el('<div class="recent"><h4>Recent</h4></div>');
  DATA.conversations.forEach(c=>{
    const r=el(`<div class="rconv"><b>${esc(c.title)}</b><small>${esc(c.when)}</small></div>`);
    r.onclick=()=>toast('Opening conversation: '+c.title);
    rec.appendChild(r);
  });
  right.appendChild(rec);
  layout.appendChild(right);
  wrap.appendChild(layout);

  setTimeout(()=>{
    $('#sendBtn').onclick=()=>{const v=$('#chatBox').value.trim();if(v)ask(v);};
    $('#chatBox').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();const v=$('#chatBox').value.trim();if(v)ask(v);}});
  },0);
  return wrap;
}
function userMsg(t){return el(`<div class="msg user"><div class="mav">MO</div><div class="bubble">${esc(t)}</div></div>`);}
function aiMsg(t,src){
  const b=el(`<div class="msg ai"><div class="mav">CI</div><div class="bubble"></div></div>`);
  b.querySelector('.bubble').innerHTML=fmt(t)+srcLine(src);
  return b;
}
function fmt(t){return esc(t).replace(/\n/g,'<br>');}
function srcLine(src){
  if(!src||!src.length)return'';
  const pills=src.map(s=>`<span class="src">${I.doc2}${esc(s)}</span>`).join(' ');
  return `<div class="srcline">Sources · ${pills}</div>`;
}
function artifactCard(a){
  const ic=a.icon||'doc';
  const card=el(`<div class="artifact">
    <div class="art-head"><div class="ic ${fileIcon(ic)}" style="display:flex;align-items:center;justify-content:center">${fileGlyph(ic)}</div><div style="flex:1;min-width:0"><b>${esc(a.title)}</b><small>${esc(a.type||'Draft')} · generated by Co-Intelligence</small></div></div>
    <div class="art-body">${esc(a.preview)}</div>
    <div class="art-actions"></div>
  </div>`);
  const acts=card.querySelector('.art-actions');
  (a.actions||[["Save to Drive","drive"]]).forEach(([label,kind],i)=>{
    const b=el(`<button class="btn sm ${i===0?'primary':''}">${esc(label)}</button>`);
    b.onclick=()=>artifactAction(kind,label,a);
    acts.appendChild(b);
  });
  return card;
}
function artifactAction(kind,label,a){
  if(kind==='drive')toast('Saved to Drive · '+a.title);
  else if(kind==='workspace')toast('Attached to a Workspace · '+a.title);
  else if(kind==='portal')toast('Published to website + members portal · '+a.title);
  else if(kind==='room')toast('Posted to the Data Room · '+a.title);
  else if(kind==='contract')toast('Linked to the contract record · '+a.title);
  else toast(label+' · '+a.title);
}
function ask(q){
  if(state.chat.busy)return;
  const stream=$('#chatStream');if(!stream)return;
  const box=$('#chatBox');if(box)box.value='';
  state.chat.messages.push({role:'user',text:q});
  stream.appendChild(userMsg(q));stream.scrollTop=stream.scrollHeight;
  state.chat.busy=true;
  const ans=CANNED[q]||genericAnswer(q);
  const steps=(ans.steps&&ans.steps.length)?ans.steps:[["Searching the workspace",""],["Reading the relevant records",""],["Composing a response",""]];
  const node=el(`<div class="msg ai"><div class="mav">CI</div><div class="bubble"></div></div>`);
  const bub=node.querySelector('.bubble');
  const stepsWrap=el(`<div class="agent-steps"><div class="as-head">${I.spark}<span>Working</span><span class="as-count"></span></div></div>`);
  bub.appendChild(stepsWrap);
  stream.appendChild(node);stream.scrollTop=stream.scrollHeight;
  const rows=steps.map(s=>{
    const label=typeof s==='string'?s:s[0];
    const meta=(typeof s!=='string'&&s[1])?` <span class="smeta">${esc(s[1])}</span>`:'';
    const r=el(`<div class="astep pending"><div class="sdot"></div><div class="stext">${esc(label)}${meta}</div></div>`);
    stepsWrap.appendChild(r);return r;
  });
  let idx=0;
  const runStep=()=>{
    if(idx>0){const prev=rows[idx-1];prev.classList.remove('pending');prev.querySelector('.sdot').innerHTML=I.check;}
    if(idx>=rows.length){finishAgent(stream,bub,stepsWrap,ans);return;}
    const r=rows[idx];r.classList.remove('pending');r.querySelector('.sdot').innerHTML='<div class="mini-spin"></div>';
    stream.scrollTop=stream.scrollHeight;idx++;
    setTimeout(runStep,500+Math.random()*260);
  };
  setTimeout(runStep,420);
}
function finishAgent(stream,bub,stepsWrap,ans){
  stepsWrap.classList.add('agent-collapsed');
  const head=stepsWrap.querySelector('.as-head>span');if(head)head.textContent='Done';
  const cnt=stepsWrap.querySelector('.as-count');if(cnt)cnt.textContent=stepsWrap.querySelectorAll('.astep').length+' steps';
  stepsWrap.querySelector('.as-head').onclick=()=>stepsWrap.classList.toggle('rolled');
  const ansEl=el('<div class="agent-answer"></div>');bub.appendChild(ansEl);
  const full=ans.text;let i=0;
  const tick=()=>{
    const step=Math.max(1,Math.round(full.length/120));
    i=Math.min(full.length,i+step);
    ansEl.innerHTML=fmt(full.slice(0,i));
    stream.scrollTop=stream.scrollHeight;
    if(i<full.length){setTimeout(tick,16);return;}
    ansEl.innerHTML=fmt(full);
    if(ans.src&&ans.src.length){
      const sl=el('<div></div>');sl.innerHTML=srcLine(ans.src);
      const node=sl.firstElementChild;if(node){bub.appendChild(node);node.querySelectorAll('.src').forEach(p=>p.onclick=()=>navigate('drive',{reset:true}));}
    }
    if(ans.artifact)bub.appendChild(artifactCard(ans.artifact));
    state.chat.messages.push({role:'ai',text:full,src:ans.src||[],artifact:ans.artifact||null});
    state.chat.busy=false;stream.scrollTop=stream.scrollHeight;
  };
  setTimeout(tick,240);
}

/* ===================== DATA ROOMS ===================== */
function renderRooms(){
  const wrap=el('<div></div>');
  if(!state.room){
    wrap.appendChild(pageHead('Data Rooms','Secure, time-bound spaces with a full audit trail of every view and download.'));
    const grid=el('<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))"></div>');
    DATA.rooms.forEach(r=>{
      const card=el(`<div class="card" style="padding:18px;cursor:pointer">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px"><span class="pill ${r.expColor}">${r.expires}</span></div>
        <div class="sectitle" style="margin:0 0 6px">${esc(r.name)}</div>
        <p class="muted" style="margin:0 0 14px;font-size:13px">${esc(r.desc)}</p>
        <div class="muted" style="font-size:12.5px">${r.members.length} members · ${r.docs.length} documents · ${r.audit.length} log entries</div>
      </div>`);
      card.onclick=()=>{state.room=r.id;navigate('rooms');};
      grid.appendChild(card);
    });
    wrap.appendChild(grid);
    return wrap;
  }
  const r=DATA.rooms.find(x=>x.id===state.room);
  const crumbs=el('<div class="crumbs"></div>');
  const back=el('<a>Data Rooms</a>');back.onclick=()=>{state.room=null;navigate('rooms');};
  crumbs.appendChild(back);crumbs.appendChild(el('<span class="sep">/</span>'));crumbs.appendChild(el(`<span class="cur">${esc(r.name)}</span>`));
  wrap.appendChild(crumbs);
  const ph=pageHead(r.name,r.desc);
  ph.querySelector('h1').appendChild(el(`<span class="pill ${r.expColor}" style="margin-left:10px;vertical-align:middle">${r.expires}</span>`));
  wrap.appendChild(ph);
  const two=el('<div class="two"></div>');
  // left: docs + audit
  const main=el('<div></div>');
  const dcard=el('<div class="card" style="margin-bottom:16px"></div>');
  dcard.appendChild(el('<div class="sectitle" style="padding:14px 16px 4px">Documents</div>'));
  r.docs.forEach(([n,t,s])=>{
    const row=el(`<div class="row"><div class="ic ${fileIcon(t)}">${fileGlyph(t)}</div><div class="meta"><b>${esc(n)}</b><small>${esc(s)}</small></div><button class="btn sm">View</button></div>`);
    row.querySelector('button').onclick=(e)=>{e.stopPropagation();toast('Logged: you viewed '+n);};
    row.onclick=()=>toast('Logged: you viewed '+n);
    dcard.appendChild(row);
  });
  main.appendChild(dcard);
  const acard=el('<div class="card" style="padding:14px 16px"></div>');
  acard.appendChild(el('<div class="sectitle" style="margin:0 0 8px">Audit log</div>'));
  const a=el('<div class="audit"></div>');
  r.audit.forEach(([t,who,act])=>a.appendChild(el(`<div class="arow"><div class="aav"></div><div class="at">${esc(t)}</div><div><b>${esc(who)}</b> ${esc(act)}</div></div>`)));
  acard.appendChild(a);main.appendChild(acard);
  two.appendChild(main);
  // right: members
  const side=el('<div class="card" style="padding:16px"></div>');
  side.appendChild(el('<div class="sectitle" style="margin:0 0 6px">Members</div>'));
  r.members.forEach(name=>{
    const p=DATA.people[name]||{role:'',ext:false};
    side.appendChild(el(`<div class="member"><div class="avatar">${initials(name)}</div><div style="flex:1;min-width:0"><b style="font-size:13px">${esc(name)}</b><br><small>${esc(p.role)}</small></div>${p.ext?'<span class="tag">External</span>':''}</div>`));
  });
  const inv=el('<button class="btn primary sm" style="margin-top:12px;width:100%">Invite member</button>');
  inv.onclick=()=>toast('Invitation sent · access logged');
  side.appendChild(inv);
  two.appendChild(side);
  wrap.appendChild(two);
  return wrap;
}

/* ===================== WORKSPACES ===================== */
function renderWorkspaces(){
  const wrap=el('<div></div>');
  if(!state.workspace){
    wrap.appendChild(pageHead('Workspaces','Collaborative project pages: overview, tasks, documents and activity in one place.'));
    const grid=el('<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))"></div>');
    DATA.workspaces.forEach(w=>{
      const done=w.tasks.filter(t=>t[1]).length;
      const card=el(`<div class="card" style="padding:18px;cursor:pointer">
        <div class="sectitle" style="margin:0 0 6px">${esc(w.name)}</div>
        <p class="muted" style="margin:0 0 14px;font-size:13px">${esc(w.overview.slice(0,120))}…</p>
        <div class="bar"><i style="width:${Math.round(done/w.tasks.length*100)}%"></i></div>
        <div class="muted" style="font-size:12.5px;margin-top:8px">${done}/${w.tasks.length} tasks · ${w.members.length} members · ${w.docs.length} docs</div>
      </div>`);
      card.onclick=()=>{state.workspace=w.id;navigate('workspaces');};
      grid.appendChild(card);
    });
    wrap.appendChild(grid);
    return wrap;
  }
  const w=DATA.workspaces.find(x=>x.id===state.workspace);
  const crumbs=el('<div class="crumbs"></div>');
  const back=el('<a>Workspaces</a>');back.onclick=()=>{state.workspace=null;navigate('workspaces');};
  crumbs.appendChild(back);crumbs.appendChild(el('<span class="sep">/</span>'));crumbs.appendChild(el(`<span class="cur">${esc(w.name)}</span>`));
  wrap.appendChild(crumbs);
  wrap.appendChild(pageHead(w.name,''));
  const card=el('<div class="card" style="padding:24px;max-width:900px"></div>');
  // overview
  card.appendChild(el('<div class="ws-section"><div class="sectitle">Overview</div><p style="margin:0;color:var(--body)">'+esc(w.overview)+'</p></div>'));
  // tasks
  const ts=el('<div class="ws-section"></div>');
  const done=w.tasks.filter(t=>t[1]).length;
  ts.appendChild(el(`<div class="sectitle">Tasks <span class="muted" style="font-weight:400;font-size:12.5px">(${done}/${w.tasks.length})</span></div>`));
  w.tasks.forEach((t,i)=>{
    const row=el(`<div class="task ${t[1]?'done':''}"><div class="chk">${I.check}</div><div class="tlabel">${esc(t[0])}</div></div>`);
    row.querySelector('.chk').onclick=()=>{t[1]=!t[1];row.classList.toggle('done',t[1]);toast(t[1]?'Task completed':'Task reopened');};
    ts.appendChild(row);
  });
  card.appendChild(ts);
  // documents
  const ds=el('<div class="ws-section"><div class="sectitle">Linked documents</div></div>');
  const dwrap=el('<div class="card" style="box-shadow:none"></div>');
  w.docs.forEach(n=>{
    const t=n.endsWith('.pdf')?'pdf':n.endsWith('.xlsx')?'xls':'doc';
    const row=el(`<div class="row"><div class="ic ${fileIcon(t)}">${fileGlyph(t)}</div><div class="meta"><b>${esc(n)}</b><small>Linked from Drive</small></div><span class="tag">Open in Drive</span></div>`);
    row.onclick=()=>navigate('drive',{reset:true});
    dwrap.appendChild(row);
  });
  ds.appendChild(dwrap);card.appendChild(ds);
  // members
  const ms=el('<div class="ws-section"><div class="sectitle">Members</div></div>');
  const mwrap=el('<div style="display:flex;flex-wrap:wrap;gap:14px"></div>');
  w.members.forEach(name=>{
    const p=DATA.people[name]||{role:'Facilitator',ext:false};
    mwrap.appendChild(el(`<div style="display:flex;align-items:center;gap:9px"><div class="avatar">${initials(name)}</div><div><b style="font-size:13px">${esc(name)}</b><br><small class="muted">${esc(p.role)}</small></div></div>`));
  });
  ms.appendChild(mwrap);card.appendChild(ms);
  // activity
  const as=el('<div class="ws-section" style="margin-bottom:0"><div class="sectitle">Recent activity</div></div>');
  const act=el('<div class="activity"></div>');
  w.activity.forEach(([who,what,when])=>act.appendChild(el(`<div class="arow2"><div class="avatar" style="width:26px;height:26px;font-size:10px">${initials(who)}</div><div><b>${esc(who)}</b> ${esc(what)}<br><small class="muted">${esc(when)}</small></div></div>`)));
  as.appendChild(act);card.appendChild(as);
  wrap.appendChild(card);
  return wrap;
}

/* ===================== PORTAL (web properties) ===================== */
function renderPortal(){
  const p=DATA.portal;
  const wrap=el('<div></div>');
  wrap.appendChild(pageHead('Portal','Run the Nation&rsquo;s public website and gated members portal from one place, on infrastructure the Nation controls.'));
  const tabs=el('<div class="vault-tabs"></div>');
  [['pages','Website pages'],['news','News & notices'],['member','Members portal'],['directory','Members']].forEach(([id,label])=>{
    const b=el(`<button class="vtab ${(state.portalTab||'pages')===id?'active':''}">${label}</button>`);
    b.onclick=()=>{state.portalTab=id;navigate('portal');};
    tabs.appendChild(b);
  });
  wrap.appendChild(tabs);
  const tab=state.portalTab||'pages';
  if(tab==='pages')wrap.appendChild(portalPages(p));
  else if(tab==='news')wrap.appendChild(portalNews(p));
  else if(tab==='member')wrap.appendChild(portalMemberView(p));
  else wrap.appendChild(portalDirectory(p));
  return wrap;
}
function portalPages(p){
  const w=el('<div></div>');
  const tb=el('<div class="toolbar"></div>');
  tb.appendChild(el(`<span class="pill green">${esc(p.domain)}</span>`));
  tb.appendChild(el(`<span class="pill grey">${p.pages.length} pages</span>`));
  tb.appendChild(el('<div class="spacer"></div>'));
  const np=el(`<button class="btn primary">${I.add}New page</button>`);
  np.onclick=()=>toast('New page created (demo)');
  tb.appendChild(np);
  w.appendChild(tb);
  const card=el('<div class="card"></div>');
  p.pages.forEach(pg=>{
    const [name,path,status]=pg;
    const pill=status==='Published'?'green':'amber';
    const row=el(`<div class="row"><div class="ic gen">${I.globe}</div><div class="meta"><b>${esc(name)}</b><small>${esc(p.domain)}${esc(path)} · edited by ${esc(pg[3])}</small></div><span class="pill ${pill}">${esc(status)}</span><div class="col" style="width:110px">${esc(pg[4])}</div></div>`);
    row.onclick=()=>openPagePanel(p,pg);
    card.appendChild(row);
  });
  w.appendChild(card);
  return w;
}
function openPagePanel(p,pg){
  const [name,path,status,who,date]=pg;
  openPanel(`
    <div class="sp-head">
      <div class="ic gen" style="width:42px;height:42px">${I.globe}</div>
      <div style="flex:1;min-width:0"><div style="font-family:var(--head-font);font-size:15px">${esc(name)}</div><small class="muted">${esc(p.domain)}${esc(path)}</small></div>
      <button class="sp-x" onclick="closePanel()">×</button>
    </div>
    <div class="sp-body">
      <div class="kv"><span>Status</span><b>${esc(status)}</b></div>
      <div class="kv"><span>Last edited</span><b>${esc(date)} · ${esc(who)}</b></div>
      <div class="kv"><span>Hosting</span><b>Toronto, ON node</b></div>
      <div class="sectitle" style="margin:18px 0 8px">Content blocks</div>
      <div class="card" style="box-shadow:none">
        <div class="row" style="cursor:default"><div class="ic img">${I.img}</div><div class="meta"><b>Hero banner</b><small>Image + headline</small></div></div>
        <div class="row" style="cursor:default"><div class="ic doc">${I.doc}</div><div class="meta"><b>Introduction</b><small>Rich text</small></div></div>
        <div class="row" style="cursor:default"><div class="ic gen">${I.gen}</div><div class="meta"><b>Quick links</b><small>Button group</small></div></div>
      </div>
      <div style="margin-top:18px;display:flex;gap:8px">
        <button class="btn primary" onclick="toast('Opening page editor…')">Edit page</button>
        <button class="btn" onclick="toast('Published to ${esc(p.domain)}${esc(path)}')">Publish</button>
      </div>
    </div>`);
}
function portalNews(p){
  const w=el('<div></div>');
  const tb=el('<div class="toolbar"></div>');
  const comp=el(`<button class="btn primary">${I.megaphone}Compose notice</button>`);
  comp.onclick=composeNotice;
  tb.appendChild(comp);
  const ai=el(`<button class="btn">${I.ai}Draft with Co-Intelligence</button>`);
  ai.onclick=()=>navigate('ai',{reset:true});
  tb.appendChild(ai);
  tb.appendChild(el('<div class="spacer"></div>'));
  tb.appendChild(el('<span class="muted" style="font-size:12.5px">Status · Channels</span>'));
  w.appendChild(tb);
  const card=el('<div class="card"></div>');
  p.notices.forEach(([title,status,channels,date])=>{
    const pill=status==='Published'?'green':status==='Scheduled'?'amber':'grey';
    const row=el(`<div class="row"><div class="ic gen">${I.megaphone}</div><div class="meta"><b>${esc(title)}</b><small>${esc(channels)} · ${esc(date)}</small></div><span class="pill ${pill}">${esc(status)}</span></div>`);
    row.onclick=()=>toast('Opening notice: '+title);
    card.appendChild(row);
  });
  w.appendChild(card);
  return w;
}
function composeNotice(){
  modal(`<h3>Compose community notice</h3>
    <p>Publish to the website and push to the members portal. Pre-filled from the Co-Intelligence draft.</p>
    <textarea style="width:100%;height:150px;border:1px solid var(--line);border-radius:10px;padding:11px;font-family:var(--body-font);font-size:13.5px">Aanii Sheguiandah First Nation members,

Our Nation is building its Comprehensive Community Plan. Please join us for three engagement sessions at the community hall, 142 Ogemah Miikan, on June 4, 11 and 18. A meal and childcare will be provided.

Miigwetch,
Matthew Owl, Communications Officer</textarea>
    <div style="display:flex;gap:16px;margin:14px 0;font-size:13px;flex-wrap:wrap">
      <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" checked> Website</label>
      <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" checked> Members portal</label>
      <label style="display:flex;gap:6px;align-items:center"><input type="checkbox"> Social</label>
    </div>
    <div class="actions"><button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" id="pubNotice">Publish</button></div>`);
  $('#pubNotice').onclick=()=>{closeModal();toast('Notice published to website + members portal');};
}
function portalMemberView(p){
  const w=el('<div></div>');
  const tb=el('<div class="toolbar"></div>');
  tb.appendChild(el('<span class="muted" style="font-size:13px">What members see when they sign in. Access is controlled in Governance.</span>'));
  tb.appendChild(el('<div class="spacer"></div>'));
  const ed=el(`<button class="btn">${I.rename}Edit layout</button>`);
  ed.onclick=()=>toast('Opening portal layout editor…');
  tb.appendChild(ed);
  const pv=el(`<button class="btn primary">${I.eye}Preview as member</button>`);
  pv.onclick=()=>toast('Previewing the portal as a signed-in member');
  tb.appendChild(pv);
  w.appendChild(tb);
  const frame=el('<div class="card" style="padding:0;overflow:hidden"></div>');
  frame.appendChild(el(`<div style="background:linear-gradient(120deg,var(--deep),var(--purple));color:#fff;padding:20px 22px"><div style="font-family:var(--head-font);font-size:18px">Aanii, Wesley</div><div style="color:#d9d5f3;font-size:12.5px">Sheguiandah First Nation · Members Portal</div></div>`));
  const grid=el('<div class="tiles" style="padding:18px"></div>');
  p.portalBlocks.forEach(([t,tag,d])=>{
    grid.appendChild(el(`<div class="card tile" style="cursor:default"><div style="display:flex;align-items:center;justify-content:space-between"><div class="tic" style="width:34px;height:34px;background:var(--cyan)">${I.gen}</div><span class="tag">${esc(tag)}</span></div><h3 style="font-size:15px">${esc(t)}</h3><p>${esc(d)}</p></div>`));
  });
  frame.appendChild(grid);
  w.appendChild(frame);
  return w;
}
function portalDirectory(p){
  const w=el('<div></div>');
  const c=p.counts;
  const stats=el('<div class="gov-grid" style="margin-bottom:16px"></div>');
  stats.appendChild(el(`<div class="card stat"><div class="num">${c.members}</div><div class="lbl">Registered members</div></div>`));
  stats.appendChild(el(`<div class="card stat"><div class="num">${c.active}</div><div class="lbl">Portal accounts active</div></div>`));
  stats.appendChild(el(`<div class="card stat"><div class="num">${c.offReserve}</div><div class="lbl">Off-reserve members</div></div>`));
  stats.appendChild(el(`<div class="card stat"><div class="num">${c.pending}</div><div class="lbl">Pending approval</div></div>`));
  w.appendChild(stats);
  const card=el('<div class="card" style="padding:0;overflow:hidden"></div>');
  card.appendChild(el('<div class="sectitle" style="padding:16px 18px 4px">Member directory &amp; portal access</div>'));
  const tbl=el('<table class="gtab"><thead><tr><th>Member</th><th>Residency</th><th>Status</th><th>Portal access</th><th></th></tr></thead><tbody></tbody></table>');
  const tb=tbl.querySelector('tbody');
  p.members.forEach(m=>tb.appendChild(memberRow(m)));
  card.appendChild(tbl);
  w.appendChild(card);
  return w;
}
function memberRow(m){
  const [name,res,status,access]=m;
  const sp=status==='Active'?'pill green':status==='Pending approval'?'pill amber':'pill grey';
  const acc=access==='Yes'?'<span class="pill green">Enabled</span>':'<span class="pill grey">Off</span>';
  const tr=el(`<tr><td><div style="display:flex;align-items:center;gap:9px"><div class="avatar" style="width:28px;height:28px;font-size:11px">${initials(name)}</div>${esc(name)}</div></td><td class="muted">${esc(res)}</td><td><span class="${sp}">${esc(status)}</span></td><td>${acc}</td><td style="text-align:right"></td></tr>`);
  const cell=tr.querySelector('td:last-child');
  if(status!=='Active'){
    const btn=el('<button class="btn sm primary">Approve</button>');
    btn.onclick=()=>{m[2]='Active';m[3]='Yes';DATA.portal.counts.active++;if(DATA.portal.counts.pending>0)DATA.portal.counts.pending--;toast('Approved · '+name+' now has portal access');navigate('portal');};
    cell.appendChild(btn);
  }else{
    const btn=el('<button class="btn sm ghost">Manage</button>');
    btn.onclick=()=>toast('Managing '+name);
    cell.appendChild(btn);
  }
  return tr;
}

/* ===================== VAULT ===================== */
function renderVault(){
  const wrap=el('<div></div>');
  wrap.appendChild(el(`<div class="vault-banner"><div class="vlock">${I.vault.replace(/currentColor/g,'#fff')}</div><div><h2>You are in a privileged area</h2><p>Vault holds the Nation&rsquo;s credentials and confidential records. Every view and download is verified with the Member Clerk and written to the audit log.</p></div></div>`));
  const tabs=el('<div class="vault-tabs"></div>');
  [['credentials','Credentials'],['confidential','Confidential Documents'],['audit','Audit']].forEach(([id,label])=>{
    const b=el(`<button class="vtab ${state.vaultTab===id?'active':''}">${label}</button>`);
    b.onclick=()=>{state.vaultTab=id;navigate('vault');};
    tabs.appendChild(b);
  });
  wrap.appendChild(tabs);
  const card=el('<div class="card"></div>');
  if(state.vaultTab==='credentials'){
    DATA.vault.credentials.forEach(([svc,user,pw])=>{
      const row=el(`<div class="cred"><div class="ic gen" style="width:34px;height:34px">${I.vault}</div><div class="meta" style="flex:1"><b>${esc(svc)}</b><br><small class="muted">${esc(user)}</small></div><span class="pw">••••••••••</span><button class="eye" title="Reveal">${I.eye}</button></div>`);
      const pwEl=row.querySelector('.pw');const eye=row.querySelector('.eye');let shown=false;
      eye.onclick=()=>{
        if(shown){shown=false;pwEl.textContent='••••••••••';eye.innerHTML=I.eye;return;}
        clerkVerify(()=>{shown=true;pwEl.textContent=pw;eye.innerHTML=I.eyeoff;toast('Credential revealed · logged to audit');});
      };
      card.appendChild(row);
    });
  }else if(state.vaultTab==='confidential'){
    DATA.vault.confidential.forEach(([n,t,mark,when])=>{
      const row=el(`<div class="row"><div class="ic ${fileIcon(t)}">${fileGlyph(t)}</div><div class="meta"><b>${esc(n)}</b><small>${esc(mark)} · ${esc(when)}</small></div><span class="pill red">Confidential</span><button class="btn sm" style="margin-left:10px">View</button></div>`);
      row.querySelector('button').onclick=(e)=>{e.stopPropagation();clerkVerify(()=>toast('Opening '+n+' · logged'));};
      card.appendChild(row);
    });
  }else{
    const a=el('<div class="audit" style="padding:14px 16px;max-height:none"></div>');
    DATA.vault.audit.forEach(([t,who,act])=>a.appendChild(el(`<div class="arow"><div class="aav" style="background:#a96a00"></div><div class="at">${esc(t)}</div><div><b>${esc(who)}</b> ${esc(act)}</div></div>`)));
    card.appendChild(a);
  }
  wrap.appendChild(card);
  return wrap;
}
function clerkVerify(onOk){
  modal(`<h3>Confirm with Member Clerk</h3><p>This privileged action requires Member Clerk verification before it proceeds.</p>
    <div class="actions"><button class="btn" onclick="closeModal()">Cancel</button><button class="btn primary" id="verBtn">Verify</button></div>`);
  $('#verBtn').onclick=()=>{
    const m=$('#overlay .modal');
    m.innerHTML=`<div class="spinner"></div><p style="text-align:center">Verifying with Member Clerk…</p>`;
    setTimeout(()=>{m.innerHTML=`<h3>Verified</h3><p>Member Clerk confirmed. Action authorized and recorded.</p><div class="actions"><button class="btn primary" id="okBtn">Continue</button></div>`;
      $('#okBtn').onclick=()=>{closeModal();onOk&&onOk();};},1000);
  };
}

/* ===================== GOVERNANCE ===================== */
function renderGovernance(){
  const g=DATA.governance;
  const wrap=el('<div></div>');
  wrap.appendChild(pageHead('Governance','Who has access, where your data lives, and a record of every administrative action, visible to Council.'));
  const stats=el('<div class="gov-grid" style="margin-bottom:18px"></div>');
  stats.appendChild(el(`<div class="card stat"><div class="lbl">Data residency</div><div class="num" style="font-size:18px">${esc(g.residency)}</div><div class="muted" style="font-size:12px;margin-top:6px">${esc(g.region)}</div></div>`));
  stats.appendChild(el(`<div class="card stat"><div class="lbl">Members with access</div><div class="num">${g.access.length}</div><div class="muted" style="font-size:12px;margin-top:6px">${g.access.filter(a=>a[2]==='Administrator').length} administrators · ${g.access.filter(a=>a[2]==='Guest').length} guest</div></div>`));
  stats.appendChild(el(`<div class="card stat"><div class="lbl">Governing policy</div><div class="num" style="font-size:18px">${esc(g.policy)}</div><div class="muted" style="font-size:12px;margin-top:6px">Approved by Council · current</div></div>`));
  wrap.appendChild(stats);
  // access table
  const ac=el('<div class="card" style="padding:0;margin-bottom:18px;overflow:hidden"></div>');
  ac.appendChild(el('<div class="sectitle" style="padding:16px 18px 4px">Access &amp; roles</div>'));
  const tbl=el('<table class="gtab"><thead><tr><th>Member</th><th>Title</th><th>Role</th><th>Scope</th></tr></thead><tbody></tbody></table>');
  const tb=tbl.querySelector('tbody');
  g.access.forEach(([n,title,role,scope])=>{
    const rc={Administrator:'pill',Editor:'pill green',Guest:'pill amber'}[role]||'pill grey';
    tb.appendChild(el(`<tr><td><div style="display:flex;align-items:center;gap:9px"><div class="avatar" style="width:28px;height:28px;font-size:11px">${initials(n)}</div>${esc(n)}</div></td><td class="muted">${esc(title)}</td><td><span class="${rc}">${role}</span></td><td class="muted">${esc(scope)}</td></tr>`));
  });
  ac.appendChild(tbl);
  wrap.appendChild(ac);
  // log
  const lc=el('<div class="card" style="padding:14px 18px"></div>');
  lc.appendChild(el('<div class="sectitle" style="margin:0 0 8px">Administrative audit log</div>'));
  const a=el('<div class="audit" style="max-height:none"></div>');
  g.log.forEach(([t,who,act])=>a.appendChild(el(`<div class="arow"><div class="aav"></div><div class="at">${esc(t)}</div><div><b>${esc(who)}</b> ${esc(act)}</div></div>`)));
  lc.appendChild(a);
  const exp=el('<button class="btn sm" style="margin-top:12px">Export access review for Council</button>');
  exp.onclick=()=>toast('Access review exported (demo)');
  lc.appendChild(exp);
  wrap.appendChild(lc);
  return wrap;
}

/* ===================== shared bits ===================== */
function pageHead(title,sub){
  return el(`<div class="page-head"><h1>${esc(title)}</h1>${sub?`<p>${sub}</p>`:''}</div>`);
}

/* ===================== notifications ===================== */
function buildNotif(){
  const pop=$('#notifPop');
  pop.innerHTML='<div class="pv-head">Notifications</div>';
  DATA.notifications.forEach(n=>{
    pop.appendChild(el(`<div class="nrow"><div class="nic">${I[n.ic]||I.share}</div><div><b style="font-size:13px;color:var(--head)">${esc(n.t)}</b><br><small>${esc(n.d)}</small></div></div>`));
  });
  $('#bellBtn').onclick=(e)=>{e.stopPropagation();pop.classList.toggle('open');$('#searchResults').classList.remove('open');};
}

/* ===================== search ===================== */
function buildSearch(){
  const inp=$('#searchInput'),res=$('#searchResults');
  inp.addEventListener('input',()=>{
    const q=inp.value.trim();
    if(!q){res.classList.remove('open');return;}
    res.innerHTML=`<div class="sr-head">Results for “${esc(q)}”</div>`;
    const items=[
      ['drive',`3 files in Drive`,`“${q}”, incl. 2026-05-RHT-Settlement-Update.pdf`,I.fold],
      ['ai',`2 conversations in Co-Intelligence`,`Drafts and summaries mentioning “${q}”`,I.ai],
      ['workspaces',`1 task in Workspaces`,`CCP Engagement, “${q}”`,I.add],
      ['rooms',`1 Data Room match`,`RHT Settlement: Inter-Nation Review`,I.share]
    ];
    items.forEach(([mod,t,s,ic])=>{
      const it=el(`<div class="sr-item"><div class="sr-ic">${ic}</div><div><b>${esc(t)}</b><br><small>${esc(s)}</small></div></div>`);
      it.onclick=()=>{res.classList.remove('open');inp.value='';navigate(mod,{reset:true});};
      res.appendChild(it);
    });
    res.classList.add('open');
  });
}

/* ===================== boot ===================== */
buildNav();buildNotif();buildSearch();navigate('home');

<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Task Input</title>
  <style>
    body{font-family:system-ui, -apple-system; margin:20px;}
    .top{display:flex; justify-content:space-between; align-items:center;}
    table{border-collapse:collapse; width:100%; margin-top:12px;}
    th,td{border:1px solid #ddd; padding:8px; vertical-align:top;}
    input,select{width:100%;}
    .row-actions{display:flex; gap:6px;}
    .center{max-width:1100px; margin:12px auto;}
    .sum{margin-top:10px; font-weight:700;}
    .btn{padding:8px 12px; border:1px solid #333; background:#fff; cursor:pointer;}
    .btn-primary{background:#111; color:#fff;}
    .muted{color:#666; font-size:12px;}
  </style>
</head>
<body>
  <div class="top">
    <div>
      <div class="muted">Member</div>
      <div style="font-size:20px;font-weight:800;" id="memberName">{{ $userName }}</div>
      <div class="muted" id="memberEmail">{{ $userEmail }}</div>
    </div>
    <div>
      <label class="muted">日付</label><br>
      <input id="date" type="date" />
      <div style="margin-top:8px;">
      <label class="muted">メンバー切り替え</label><br>
      <select id="memberSelect" style="width:240px;">
        @foreach(($members ?? []) as $m)
            <option value="{{ $m['email'] }}" data-name="{{ $m['name'] }}"
                {{ ($m['email'] ?? '') === $userEmail ? 'selected' : '' }}>
                {{ $m['name'] ?? '' }}（{{ $m['email'] ?? '' }}）
            </option>
        @endforeach
      </select>
    </div>
    </div>
  </div>

  <div class="center">
    <div style="display:flex; gap:10px; margin-top:10px;">
      <button class="btn" id="btnImport">カレンダー引用</button>
      <button class="btn" id="btnRegister">カレンダーに登録</button>
      <select id="mode" style="width:140px;">
        <option value="plan">予定</option>
        <option value="actual">実績</option>
      </select>
      <button class="btn btn-primary" id="btnAdd">+ 行追加</button>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width:90px;">優先</th>
          <th style="width:100px;">分</th>
          <th style="width:220px;">クライアント</th>
          <th style="width:260px;">タスク</th>
          <th style="width:90px;">件数</th>
          <th style="width:170px;">時間帯</th>
          <th>メモ</th>
          <th style="width:90px;"></th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>

    <div class="sum" id="sum"></div>
    <div id="errorBox" style="display:none; margin-top:10px; padding:10px; border:1px solid #f00; background:#fff3f3; color:#900;"></div>
    <div class="muted" id="msg"></div>
  </div>

<script>
    let CURRENT_USER_NAME = @json($userName);
    let CURRENT_USER_EMAIL = @json($userEmail);

    const elError = document.getElementById('errorBox');
    const elMemberSelect = document.getElementById('memberSelect');
    const elMemberName = document.getElementById('memberName');
    const elMemberEmail = document.getElementById('memberEmail');

    function showError(message){
    elError.style.display = 'block';
    elError.textContent = message;
    }
    function clearError(){
    elError.style.display = 'none';
    elError.textContent = '';
    }

let masters = { tasks: [], clients: [] };
let rows = [];

const elTbody = document.getElementById('tbody');
const elSum = document.getElementById('sum');
const elMsg = document.getElementById('msg');
const elDate = document.getElementById('date');
const elMode = document.getElementById('mode');

function todayStr(){
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const dd = String(d.getDate()).padStart(2,'0');
  return `${y}-${m}-${dd}`;
}

function uuid(){
  return (crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random();
}

function calcSum(){
  const total = rows.reduce((a,r)=>a+(Number(r.minutes)||0),0);
  const h = Math.floor(total/60);
  const m = total%60;
  elSum.textContent = `合計：${total}分（${h}時間${m}分）`;
}

function taskLabel(r){
  // 範囲指定があるなら "2-3〜2-8" 形式
  if (r.task_to) return `${r.task_from}〜${r.task_to}`;
  return r.task_from || '';
}

function render(){
  elTbody.innerHTML = '';
  rows.forEach((r, idx)=>{
    const tr = document.createElement('tr');

    tr.innerHTML = `
      <td><input type="number" min="1" value="${r.priority}" data-k="priority" data-i="${idx}"></td>
      <td><input type="number" min="1" value="${r.minutes}" data-k="minutes" data-i="${idx}"></td>
      <td>
        <select data-k="client_name" data-i="${idx}">
          <option value="">（空白）</option>
          ${masters.clients.map(c=>`<option ${c.client_name===r.client_name?'selected':''} value="${escapeHtml(c.client_name)}">${escapeHtml(c.client_name)}</option>`).join('')}
        </select>
      </td>
      <td>
        <div style="display:flex; gap:6px;">
          <select data-k="task_from" data-i="${idx}">
            <option value="">（選択）</option>
            ${masters.tasks.map(t=>`<option ${t.label===r.task_from?'selected':''} value="${escapeHtml(t.label)}">${escapeHtml(t.label)}</option>`).join('')}
          </select>
          <span style="padding-top:6px;">〜</span>
          <select data-k="task_to" data-i="${idx}">
            <option value="">（なし）</option>
            ${masters.tasks.map(t=>`<option ${t.label===r.task_to?'selected':''} value="${escapeHtml(t.label)}">${escapeHtml(t.label)}</option>`).join('')}
          </select>
        </div>
        <div class="muted">※範囲が不要なら右側を（なし）</div>
      </td>
      <td><input type="number" min="0" value="${r.count ?? ''}" data-k="count" data-i="${idx}"></td>
      <td style="display:flex; gap:6px;">
        <input type="time" value="${r.start_time}" data-k="start_time" data-i="${idx}">
        <input type="time" value="${r.end_time}" data-k="end_time" data-i="${idx}">
      </td>
      <td><input type="text" value="${escapeAttr(r.note||'')}" data-k="note" data-i="${idx}"></td>
      <td>
        <div class="row-actions">
          <button class="btn" data-del="${idx}">削除</button>
        </div>
      </td>
    `;

    elTbody.appendChild(tr);
  });

  calcSum();
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
function escapeAttr(s){ return escapeHtml(s); }

async function api(url, method='GET', body=null){
  const opt = { method, headers:{'Content-Type':'application/json'} };
  if (body) opt.body = JSON.stringify(body);
  const res = await fetch(url, opt);
  let text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch(e) {}
  if (!res.ok) {
    const msg = (json && (json.message || json.error)) ? (json.message || json.error) : text;
    throw new Error(msg);
  }
  if (json && json.ok === false) {
    throw new Error(json.error || 'unknown error');
  }
  return json ?? {};
}

async function loadMasters(){
  const json = await api('/api/masters');
  masters = json.data;
}

function addRow(prefill={}){
  rows.push({
    id: prefill.id || null,
    priority: prefill.priority || (rows.length+1),
    minutes: prefill.minutes || 60,
    client_name: prefill.client_name || '',
    task_from: prefill.task_from || '',
    task_to: prefill.task_to || '',
    count: (typeof prefill.count === 'undefined') ? '' : prefill.count,
    start_time: prefill.start_time || '10:00',
    end_time: prefill.end_time || '11:00',
    note: prefill.note || '',
    _local: uuid(),
  });
  render();
}

let saveTimer = null;
function scheduleSave(idx){
  clearTimeout(saveTimer);
  saveTimer = setTimeout(()=>saveRow(idx), 400);
}

async function saveRow(idx){
  const r = rows[idx];
  const date = elDate.value;
  const mode = elMode.value;

  // バリデーション最小
  if (!r.priority || r.priority < 1) return;
  if (!r.minutes || r.minutes < 1) return;
  if (!r.task_from) return;
  if (!r.start_time || !r.end_time) return;

  const payload = {
    id: r.id,
    date,
    user_email: CURRENT_USER_EMAIL,
    user_name: CURRENT_USER_NAME,
    mode,
    priority: Number(r.priority),
    minutes: Number(r.minutes),
    client_name: r.client_name,
    task_from: r.task_from,
    task_to: r.task_to,
    task_label: taskLabel(r),
    count: (r.count === '' ? null : Number(r.count)),
    start_time: r.start_time,
    end_time: r.end_time,
    note: r.note || '',
    source: 'manual',
  };

  try{
    clearError();
    elMsg.textContent = '保存中...';
    const json = await api('/api/task/upsert', 'POST', payload);
    // id採番されたら反映
    if (json.data?.id) r.id = json.data.id;
    elMsg.textContent = '保存しました';
  }catch(e){
    elMsg.textContent = '保存に失敗: ' + e.message;
    showError('保存に失敗: ' + e.message);
  }
}

async function deleteRow(idx){
  const r = rows[idx];
  if (r.id) {
    await api('/api/task/delete','POST',{id:r.id});
  }
  rows.splice(idx,1);
  render();
}

async function importCalendar(){
  const date = elDate.value;
  const json = await api('/api/calendar/import','POST',{
    date, from_time:'00:00', to_time:'23:59'
  });

  // 取得した予定を行として追加（source=calendar_importで保存までやる）
  const imported = json.data.imported || [];
  for (const ev of imported) {
    addRow({
      priority: rows.length+1,
      minutes: ev.minutes,
      client_name: '',
      task_from: 'MTG', // デフォルト（あとで選び直しOK）
      task_to: '',
      count: '',
      start_time: ev.start_time,
      end_time: ev.end_time,
      note: ev.title,
    });
    // 追加した行は自動保存（引用元として）
    const i = rows.length - 1;
    rows[i].note = `【Calendar】${rows[i].note}`;
    // task_from が必須なので、仮に MTG を入れて保存
    await api('/api/task/upsert','POST',{
      id: null,
      date,
      user_email: CURRENT_USER_EMAIL,
      user_name: CURRENT_USER_NAME,
      mode: elMode.value,
      priority: Number(rows[i].priority),
      minutes: Number(rows[i].minutes),
      client_name: rows[i].client_name,
      task_from: rows[i].task_from,
      task_to: rows[i].task_to,
      task_label: taskLabel(rows[i]),
      count: null,
      start_time: rows[i].start_time,
      end_time: rows[i].end_time,
      note: rows[i].note,
      source: 'calendar_import',
    }).then(j=>{ rows[i].id = j.data.id; }).catch(()=>{});
  }
  elMsg.textContent = `カレンダー引用：${imported.length}件`;
}

async function registerCalendar(){
  const date = elDate.value;
  const items = rows.map(r => ({
    title: `${r.client_name ? r.client_name + ' ' : ''}${taskLabel(r)}`,
    task_label: taskLabel(r),
    client_name: r.client_name,
    start_time: r.start_time,
    end_time: r.end_time,
    minutes: Number(r.minutes||0),
    count: (r.count===''?null:Number(r.count)),
    note: r.note || ''
  })).filter(x => x.start_time && x.end_time);

  if (items.length===0) { elMsg.textContent='登録する時間帯がありません'; return; }

  const json = await api('/api/calendar/register','POST',{date, items});
  elMsg.textContent = `カレンダー登録：${json.data.created}件`;
}

function bindEvents(){
  elTbody.addEventListener('input', (e)=>{
    const k = e.target?.dataset?.k;
    const i = e.target?.dataset?.i;
    if (typeof k === 'undefined') return;
    const idx = Number(i);
    rows[idx][k] = e.target.value;
    // 数値化したいもの
    if (k==='minutes' || k==='priority' || k==='count') {
      // countは空許容
      rows[idx][k] = e.target.value === '' ? '' : Number(e.target.value);
    }
    calcSum();
    scheduleSave(idx);
  });

  elTbody.addEventListener('click', (e)=>{
    const del = e.target?.dataset?.del;
    if (typeof del !== 'undefined') deleteRow(Number(del));
  });

  document.getElementById('btnAdd').addEventListener('click', ()=>{ alert('clicked'); addRow(); });
  document.getElementById('btnImport').addEventListener('click', importCalendar);
  document.getElementById('btnRegister').addEventListener('click', registerCalendar);

  // mode変更時も保存の意味が変わるので注意（必要なら別タブ運用）
}

async function init(){
  elDate.value = todayStr();

  try{
    await loadMasters();
  } catch(e){
    showError('マスタ取得に失敗: ' + e.message);
  }

  addRow();      // ★先に1行出す
  bindEvents();  // ★先にイベントを必ず貼る

  try{
    await loadMemberTasks();
  } catch(e){
    showError('読み込みに失敗: ' + e.message);
  }
}
init();

async function loadMemberTasks(){
  const date = elDate.value;
  const mode = elMode.value;

  try{
    clearError();
    const json = await api(`/api/tasks?date=${encodeURIComponent(date)}&mode=${encodeURIComponent(mode)}&user_email=${encodeURIComponent(CURRENT_USER_EMAIL)}`);
    const list = json.data.rows || [];

    // rows を置き換え
    rows = list.map(x => ({
      id: x.id,
      priority: x.priority || 1,
      minutes: x.minutes || 60,
      client_name: x.client_name || '',
      task_from: x.task_from || x.task_label || '',
      task_to: x.task_to || '',
      count: (x.count === null || typeof x.count === 'undefined') ? '' : x.count,
      start_time: x.start_time || '10:00',
      end_time: x.end_time || '11:00',
      note: x.note || '',
      _local: uuid(),
    }));

    if (rows.length === 0) addRow(); // 空なら1行
    render();
    elMsg.textContent = '読み込みました';
  }catch(e){
    showError('読み込みに失敗: ' + e.message);
  }
}

elMemberSelect.addEventListener('change', async ()=>{
  const opt = elMemberSelect.selectedOptions[0];
  CURRENT_USER_EMAIL = opt.value;
  CURRENT_USER_NAME = opt.dataset.name;

  elMemberName.textContent = CURRENT_USER_NAME;
  elMemberEmail.textContent = CURRENT_USER_EMAIL;

  await loadMemberTasks();
});

elDate.addEventListener('change', loadMemberTasks);
elMode.addEventListener('change', loadMemberTasks);
</script>
</body>
</html>

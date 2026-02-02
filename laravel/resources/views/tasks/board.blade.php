<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Task Board</title>
  <style>
    body{font-family:system-ui; margin:20px;}
    table{border-collapse:collapse; width:100%; margin-top:12px;}
    th,td{border:1px solid #ddd; padding:8px; vertical-align:top;}
    .top{display:flex; justify-content:space-between; align-items:center;}
    .name{font-weight:800;}
    .muted{color:#666; font-size:12px;}
  </style>
</head>
<body>
  <div class="top">
    <div>
      <div style="font-size:20px;font-weight:900;">一覧（リアルタイム）</div>
      <div class="muted">3秒ごとに更新（SSE）</div>
    </div>
    <div>
      <label class="muted">日付</label><br>
      <input id="date" type="date">
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>メンバー</th>
        <th>優先</th>
        <th>分</th>
        <th>時間帯</th>
        <th>クライアント</th>
        <th>タスク</th>
        <th>件数</th>
        <th>メモ</th>
        <th>mode</th>
      </tr>
    </thead>
    <tbody id="tbody"></tbody>
  </table>

<script>
const elDate = document.getElementById('date');
const elTbody = document.getElementById('tbody');
let es = null;

function todayStr(){
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const dd = String(d.getDate()).padStart(2,'0');
  return `${y}-${m}-${dd}`;
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

function render(rows){
  elTbody.innerHTML = rows.map(r=>`
    <tr>
      <td class="name">${escapeHtml(r.user_name || r.user_email)}</td>
      <td>${r.priority}</td>
      <td>${r.minutes}</td>
      <td>${escapeHtml(r.start_time||'') }〜${escapeHtml(r.end_time||'')}</td>
      <td>${escapeHtml(r.client_name||'')}</td>
      <td>${escapeHtml(r.task_label||'')}</td>
      <td>${(r.count===null || typeof r.count==='undefined')?'':escapeHtml(r.count)}</td>
      <td>${escapeHtml(r.note||'')}</td>
      <td>${escapeHtml(r.mode||'')}</td>
    </tr>
  `).join('');
}

function startStream(){
  if (es) es.close();
  const date = elDate.value;
  es = new EventSource(`/api/stream/tasks?date=${encodeURIComponent(date)}`);
  es.addEventListener('tasks', (e)=>{
    const data = JSON.parse(e.data);
    render(data.rows || []);
  });
  es.onerror = () => {
    // 切れたら再接続（簡易）
    es.close();
    setTimeout(startStream, 1000);
  };
}

elDate.value = todayStr();
elDate.addEventListener('change', startStream);
startStream();
</script>
</body>
</html>

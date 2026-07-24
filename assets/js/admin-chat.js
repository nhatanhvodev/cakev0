// assets/js/admin-chat.js
(function () {
  const API = '/cakev0/api/chat';
  const root = document.getElementById('admin-chat-root');
  if (!root) return;
  root.innerHTML = `<div class="ac-layout">
      <div class="ac-list"><table><tbody id="ac-rows"></tbody></table></div>
      <div class="ac-panel">
        <div id="ac-msgs" class="ac-msgs">Chọn hội thoại</div>
        <form id="ac-form" hidden><input id="ac-input" placeholder="Trả lời khách..."><button>Gửi</button></form>
      </div></div>`;
  let current = null;

  function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function renderStats(stats) {
    if (!stats) return;
    const elToday = document.getElementById('ac-stat-today');
    const elHandoff = document.getElementById('ac-stat-handoff');
    const elIntents = document.getElementById('ac-stat-intents');
    if (elToday) elToday.textContent = stats.today_sessions ?? 0;
    if (elHandoff) elHandoff.textContent = stats.handoff_sessions ?? 0;
    if (elIntents) {
      const entries = Object.entries(stats.intent_counts || {});
      elIntents.textContent = entries.length
        ? entries.map(([k, v]) => `${k}: ${v}`).join(', ')
        : '—';
    }
  }

  async function loadSessions() {
    const r = await fetch(API + '/sessions.php');
    const data = await r.json();
    renderStats(data.stats);
    document.getElementById('ac-rows').innerHTML = (data.sessions || []).map(s => `
      <tr data-id="${s.id}" class="${current === s.id ? 'ac-active' : ''}">
        <td>${s.source === 'messenger' ? 'Ⓜ' : '💬'} #${s.id}</td>
        <td>${esc((s.last_message || '').slice(0, 40))}</td>
        <td>${s.status === 'handoff' ? '<span class="ac-badge">HANDOFF</span>' : esc(s.status)}</td>
      </tr>`).join('');
    document.querySelectorAll('#ac-rows tr').forEach(tr =>
      tr.onclick = () => { current = +tr.dataset.id; loadHistory(); });
  }

  async function loadHistory() {
    if (!current) return;
    const r = await fetch(`${API}/history.php?session_id=${current}`);
    const data = await r.json();
    document.getElementById('ac-msgs').innerHTML = (data.messages || []).map(m =>
      `<div class="ac-m ac-${m.sender}"><b>${m.sender}</b>: ${esc(m.content)}</div>`).join('');
    document.getElementById('ac-form').hidden = false;
  }

  document.addEventListener('submit', async e => {
    if (e.target.id !== 'ac-form') return;
    e.preventDefault();
    const inp = document.getElementById('ac-input');
    if (!inp.value.trim() || !current) return;
    await fetch(API + '/agent_reply.php', { method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: current, content: inp.value.trim() }) });
    inp.value = ''; loadHistory();
  });

  loadSessions();
  setInterval(() => { loadSessions(); loadHistory(); }, 5000);
})();

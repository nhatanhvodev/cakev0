// assets/js/gau-chat-widget.js
(function () {
  const API = '/cakev0/api/chat';
  const token = localStorage.gau_chat_token || (localStorage.gau_chat_token = 'g-' + Math.random().toString(36).slice(2));
  let sessionId = parseInt(localStorage.gau_chat_session || '0', 10) || null;
  let lastMsgId = 0, pollTimer = null;

  const root = document.createElement('div');
  root.id = 'gau-chat';
  root.innerHTML = `
    <button id="gau-chat-toggle">💬</button>
    <div id="gau-chat-window" hidden>
      <div class="gau-chat-header">Gấu Bakery – Hỗ trợ</div>
      <div class="gau-chat-messages"></div>
      <div class="gau-chat-quick">
        <button data-q="Xem menu bánh kem">Menu bánh kem</button>
        <button data-q="Kiểm tra đơn hàng">Kiểm tra đơn</button>
        <button data-q="Chính sách giao hàng">Giao hàng</button>
      </div>
      <form class="gau-chat-input"><input placeholder="Nhập tin nhắn..." /><button>Gửi</button></form>
    </div>`;
  document.body.appendChild(root);

  const win = root.querySelector('#gau-chat-window');
  const list = root.querySelector('.gau-chat-messages');
  const input = root.querySelector('input');

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function bubble(sender, html) {
    const el = document.createElement('div');
    el.className = 'gau-msg gau-msg-' + sender;
    el.innerHTML = html;
    list.appendChild(el);
    list.scrollTop = list.scrollHeight;
  }

  function productCards(products) {
    return '<div class="gau-cards">' + products.map(p =>
      `<a class="gau-card" href="/cakev0/pages/product.php?id=${p.id}">
         <img src="${esc(p.hinh_anh || '')}" alt=""><div>${esc(p.ten_banh)}</div>
         <strong>${Number(p.gia).toLocaleString('vi-VN')} VNĐ</strong></a>`).join('') + '</div>';
  }

  async function send(text) {
    bubble('customer', esc(text));
    const body = { message: text, guest_token: token };
    if (sessionId) body.session_id = sessionId;
    try {
      const r = await fetch(API + '/send.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      const data = await r.json();
      if (data.session_id) { sessionId = data.session_id; localStorage.gau_chat_session = sessionId; }
      const rep = data.reply || {};
      let html = esc(rep.content || 'Xin lỗi, có lỗi xảy ra.');
      if (rep.products && rep.products.length) html += productCards(rep.products);
      if (rep.citations && rep.citations.length) html += '<div class="gau-cite">Nguồn: ' + rep.citations.map(c => esc(c.source)).join(', ') + '</div>';
      bubble('bot', html);
    } catch (e) { bubble('bot', 'Không kết nối được, thử lại sau nhé.'); }
  }

  async function poll() {
    if (!sessionId) return;
    const r = await fetch(`${API}/history.php?session_id=${sessionId}&guest_token=${encodeURIComponent(token)}`);
    const data = await r.json();
    (data.messages || []).forEach(m => {
      if (m.id > lastMsgId) { lastMsgId = m.id; if (m.sender === 'agent') bubble('agent', esc(m.content)); }
    });
  }

  root.querySelector('#gau-chat-toggle').onclick = () => {
    win.hidden = !win.hidden;
    if (!win.hidden && !pollTimer) pollTimer = setInterval(poll, 4000);
    if (win.hidden && pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  };
  root.querySelector('.gau-chat-input').onsubmit = e => {
    e.preventDefault();
    const t = input.value.trim(); if (!t) return;
    input.value = ''; send(t);
  };
  root.querySelectorAll('.gau-chat-quick button').forEach(b => b.onclick = () => send(b.dataset.q));
})();

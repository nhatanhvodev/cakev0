// assets/js/gau-chat-widget.js
(function () {
  const API = '/cakev0/api/chat';
  const token = localStorage.gau_chat_token || (localStorage.gau_chat_token = 'g-' + Math.random().toString(36).slice(2));
  let sessionId = parseInt(localStorage.gau_chat_session || '0', 10) || null;
  let lastMsgId = 0, pollTimer = null, greeted = false;

  const root = document.createElement('div');
  root.id = 'gau-chat';
  root.innerHTML = `
    <button id="gau-chat-toggle" aria-label="Mở chat">💬</button>
    <div id="gau-chat-window" hidden>
      <div class="gau-chat-header" title="Thu nhỏ">
        <div class="gau-ava"><img src="/cakev0/assets/img/logo.png" alt="Gấu Bakery"></div>
        <div class="gau-htext">
          <div class="gau-htitle">Gấu Bakery</div>
          <div class="gau-hsub"><span class="gau-dot"></span> Trợ lý luôn sẵn sàng</div>
        </div>
        <div class="gau-hbtns">
          <button class="gau-hbtn" data-act="min" aria-label="Thu nhỏ" title="Thu nhỏ">–</button>
          <button class="gau-hbtn" data-act="close" aria-label="Đóng" title="Đóng">×</button>
        </div>
      </div>
      <div class="gau-chat-body">
        <div class="gau-chat-messages"></div>
        <div class="gau-chat-quick">
          <button data-q="Xem menu bánh kem">🎂 Menu bánh kem</button>
          <button data-q="Kiểm tra đơn hàng">📦 Kiểm tra đơn</button>
          <button data-q="Chính sách giao hàng">🚚 Giao hàng</button>
          <button data-q="Cho mình gặp nhân viên">🙋 Gặp nhân viên</button>
        </div>
        <form class="gau-chat-input">
          <input placeholder="Nhập tin nhắn..." aria-label="Tin nhắn" />
          <button type="submit" aria-label="Gửi">➤</button>
        </form>
      </div>
    </div>`;
  document.body.appendChild(root);

  const toggle = root.querySelector('#gau-chat-toggle');
  const win = root.querySelector('#gau-chat-window');
  const header = root.querySelector('.gau-chat-header');
  const list = root.querySelector('.gau-chat-messages');
  const input = root.querySelector('input');

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function bubble(sender, html) {
    const el = document.createElement('div');
    el.className = 'gau-msg gau-msg-' + sender;
    el.innerHTML = html;
    list.appendChild(el);
    list.scrollTop = list.scrollHeight;
    return el;
  }

  function showTyping() {
    const el = document.createElement('div');
    el.className = 'gau-typing';
    el.innerHTML = '<span></span><span></span><span></span>';
    list.appendChild(el);
    list.scrollTop = list.scrollHeight;
    return el;
  }

  function imgUrl(path) {
    if (!path) return '';
    if (/^(https?:)?\/\//i.test(path) || path.startsWith('data:')) return path;
    return '/cakev0/' + path.replace(/^\/+/, '');
  }

  function productCards(products) {
    return '<div class="gau-cards">' + products.map(p =>
      `<a class="gau-card" href="/cakev0/pages/product-detail.php?slug=${p.slug || ('san-pham-' + p.id)}">
         <img src="${esc(imgUrl(p.hinh_anh))}" alt="${esc(p.ten_banh)}"><div>${esc(p.ten_banh)}</div>
         <strong>${Number(p.gia).toLocaleString('vi-VN')} VNĐ</strong></a>`).join('') + '</div>';
  }

  async function send(text) {
    bubble('customer', esc(text));
    const typing = showTyping();
    const body = { message: text, guest_token: token };
    if (sessionId) body.session_id = sessionId;
    try {
      const r = await fetch(API + '/send.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      const data = await r.json();
      typing.remove();
      if (data.session_id) { sessionId = data.session_id; localStorage.gau_chat_session = sessionId; }
      const rep = data.reply || {};
      let html = esc(rep.content || 'Xin lỗi, có lỗi xảy ra.');
      if (rep.products && rep.products.length) html += productCards(rep.products);
      if (rep.citations && rep.citations.length) html += '<div class="gau-cite">Nguồn: ' + rep.citations.map(c => esc(c.source)).join(', ') + '</div>';
      bubble('bot', html);
    } catch (e) { typing.remove(); bubble('bot', 'Không kết nối được, thử lại sau nhé.'); }
  }

  let historyLoaded = false;

  // Restore the full conversation (customer + bot + agent) after a reload or
  // page change. Renders in order and advances lastMsgId so poll won't dup.
  async function loadHistory() {
    if (!sessionId) return false;
    try {
      const r = await fetch(`${API}/history.php?session_id=${sessionId}&guest_token=${encodeURIComponent(token)}`);
      const data = await r.json();
      const msgs = data.messages || [];
      if (!msgs.length) return false;
      list.innerHTML = '';
      msgs.forEach(m => {
        const who = m.sender === 'customer' ? 'customer' : (m.sender === 'agent' ? 'agent' : 'bot');
        let html = esc(m.content);
        const meta = m.metadata;
        if (meta && meta.products && meta.products.length) html += productCards(meta.products);
        if (meta && meta.citations && meta.citations.length) html += '<div class="gau-cite">Nguồn: ' + meta.citations.map(c => esc(c.source)).join(', ') + '</div>';
        bubble(who, html);
        if (m.id > lastMsgId) lastMsgId = m.id;
      });
      return true;
    } catch (e) { return false; }
  }

  async function poll() {
    if (!sessionId) return;
    try {
      const r = await fetch(`${API}/history.php?session_id=${sessionId}&guest_token=${encodeURIComponent(token)}`);
      const data = await r.json();
      (data.messages || []).forEach(m => {
        if (m.id > lastMsgId) { lastMsgId = m.id; if (m.sender === 'agent') bubble('agent', esc(m.content)); }
      });
    } catch (e) { /* silent */ }
  }

  async function openWin() {
    win.hidden = false;
    win.classList.remove('gau-min');
    toggle.classList.add('gau-hidden');
    void win.offsetWidth;                 // reflow to restart animation
    win.classList.add('gau-enter');
    if (!historyLoaded) {
      historyLoaded = true;
      const restored = await loadHistory();
      if (restored) greeted = true;
    }
    if (!greeted) {
      greeted = true;
      bubble('bot', 'Xin chào! 🧁 Mình là trợ lý Gấu Bakery. Bạn cần tư vấn bánh, kiểm tra đơn hay chính sách gì ạ?');
    }
    if (!pollTimer) pollTimer = setInterval(poll, 4000);
    setTimeout(() => input.focus(), 200);
  }

  function closeWin() {
    win.hidden = true;
    win.classList.remove('gau-enter', 'gau-min');
    toggle.classList.remove('gau-hidden');
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  toggle.onclick = openWin;

  header.querySelector('[data-act="min"]').onclick = (e) => { e.stopPropagation(); win.classList.toggle('gau-min'); };
  header.querySelector('[data-act="close"]').onclick = (e) => { e.stopPropagation(); closeWin(); };
  // Click header bar (not buttons) toggles minimize
  header.onclick = () => win.classList.toggle('gau-min');

  root.querySelector('.gau-chat-input').onsubmit = e => {
    e.preventDefault();
    const t = input.value.trim(); if (!t) return;
    input.value = ''; send(t);
  };
  root.querySelectorAll('.gau-chat-quick button').forEach(b => b.onclick = () => {
    if (win.classList.contains('gau-min')) win.classList.remove('gau-min');
    send(b.dataset.q);
  });
})();

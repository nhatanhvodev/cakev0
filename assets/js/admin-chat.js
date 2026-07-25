// Responsive admin chat workspace.
(function () {
  const API = '/cakev0/api/chat';
  const POLL_MS = 5000;
  const root = document.getElementById('admin-chat-root');
  if (!root) return;
  const csrfToken = root.dataset.csrfToken || '';

  const state = {
    view: 'all',
    currentId: null,
    currentSession: null,
    sessions: [],
    oldestId: null,
    latestId: null,
    hasMore: false,
    workflowReady: false,
    polling: false,
    sessionsRequestId: 0,
    sending: false,
    unread: 0
  };
  const drafts = new Map();

  root.innerHTML = `
    <section class="ac-shell" aria-label="Quản lý hội thoại khách hàng">
      <div class="ac-filterbar" role="toolbar" aria-label="Lọc hội thoại">
        <button type="button" class="ac-filter is-active" data-view="all" aria-pressed="true">
          Tất cả
        </button>
        <button type="button" class="ac-filter" data-view="waiting" aria-pressed="false">
          Chờ hỗ trợ <span id="ac-count-waiting">0</span>
        </button>
        <button type="button" class="ac-filter" data-view="mine" aria-pressed="false">
          Của tôi <span id="ac-count-mine">0</span>
        </button>
        <button type="button" class="ac-filter" data-view="in_progress" aria-pressed="false">
          Đang xử lý <span id="ac-count-progress">0</span>
        </button>
        <button type="button" class="ac-filter" data-view="closed" aria-pressed="false">
          Đã đóng <span id="ac-count-closed">0</span>
        </button>
      </div>
      <div id="ac-notice" class="ac-notice" role="status" aria-live="polite" hidden></div>
      <div class="ac-layout">
        <aside class="ac-list-pane" aria-label="Danh sách hội thoại">
          <div class="ac-list-heading">
            <strong>Hội thoại</strong>
            <span id="ac-list-total">0</span>
          </div>
          <div id="ac-rows" class="ac-rows" role="listbox" aria-label="Chọn hội thoại"></div>
        </aside>
        <section class="ac-panel" aria-label="Nội dung hội thoại">
          <header id="ac-panel-head" class="ac-panel-head">
            <button type="button" id="ac-back" class="ac-back" aria-label="Quay lại danh sách">←</button>
            <div>
              <strong id="ac-title">Chọn một hội thoại</strong>
              <span id="ac-meta">Tin nhắn mới nhất sẽ hiển thị tại đây</span>
            </div>
            <div id="ac-actions" class="ac-actions"></div>
          </header>
          <div class="ac-transcript">
            <button type="button" id="ac-older" class="ac-older" hidden>Tải tin nhắn cũ hơn</button>
            <div id="ac-msgs" class="ac-msgs" role="log" aria-live="polite" aria-relevant="additions" tabindex="0">
              <div class="ac-empty">Chọn hội thoại để bắt đầu hỗ trợ.</div>
            </div>
            <button type="button" id="ac-new" class="ac-new" hidden>0 tin mới ↓</button>
          </div>
          <form id="ac-form" class="ac-form" hidden>
            <label for="ac-input">Trả lời khách hàng</label>
            <div class="ac-compose-row">
              <textarea id="ac-input" rows="1" maxlength="5000" placeholder="Nhập phản hồi…" aria-describedby="ac-compose-help"></textarea>
              <button id="ac-send" type="submit">Gửi</button>
            </div>
            <div class="ac-compose-foot">
              <span id="ac-compose-help">Ctrl/⌘ + Enter để gửi</span>
              <span id="ac-form-status" role="status" aria-live="polite"></span>
            </div>
          </form>
        </section>
      </div>
    </section>`;

  const shell = root.querySelector('.ac-shell');
  const rows = root.querySelector('#ac-rows');
  const listTotal = root.querySelector('#ac-list-total');
  const notice = root.querySelector('#ac-notice');
  const title = root.querySelector('#ac-title');
  const meta = root.querySelector('#ac-meta');
  const actions = root.querySelector('#ac-actions');
  const msgs = root.querySelector('#ac-msgs');
  const olderButton = root.querySelector('#ac-older');
  const newButton = root.querySelector('#ac-new');
  const form = root.querySelector('#ac-form');
  const input = root.querySelector('#ac-input');
  const sendButton = root.querySelector('#ac-send');
  const formStatus = root.querySelector('#ac-form-status');
  const backButton = root.querySelector('#ac-back');

  function esc(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
  }

  function errorMessage(data, fallback) {
    const detail = data && (data.detail || data.error);
    if (typeof detail === 'string') return detail;
    if (detail && typeof detail === 'object') {
      return detail.message || detail.error || fallback;
    }
    return fallback;
  }

  async function request(path, options) {
    const requestOptions = { ...(options || {}) };
    const method = String(requestOptions.method || 'GET').toUpperCase();
    if (method !== 'GET' && csrfToken) {
      requestOptions.headers = {
        ...(requestOptions.headers || {}),
        'X-CSRF-Token': csrfToken
      };
    }
    const response = await fetch(API + path, {
      cache: 'no-store',
      ...requestOptions
    });
    let data = {};
    try {
      data = await response.json();
    } catch (_error) {
      data = {};
    }
    if (!response.ok) {
      const error = new Error(errorMessage(data, `HTTP ${response.status}`));
      error.status = response.status;
      throw error;
    }
    return data;
  }

  function nearBottom() {
    return msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight < 72;
  }

  function scrollBottom() {
    msgs.scrollTop = msgs.scrollHeight;
    state.unread = 0;
    newButton.hidden = true;
  }

  function autosize() {
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
  }

  function formatTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    try {
      return new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short'
      }).format(date);
    } catch (_error) {
      return date.toLocaleString('vi-VN');
    }
  }

  function statusLabel(status) {
    return {
      waiting: 'Chờ hỗ trợ',
      in_progress: 'Đang xử lý',
      closed: 'Đã đóng',
      active: 'Tự động'
    }[status] || status || 'Tự động';
  }

  function normalizedStatus(status) {
    if (status === 'handoff' || status === 'open') return 'waiting';
    if (status === 'in_progress' || status === 'closed') return status;
    return 'active';
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = String(value ?? 0);
  }

  function renderStats(stats) {
    if (!stats) return;
    const waiting = stats.waiting_sessions ?? stats.handoff_sessions ?? 0;
    setText('ac-stat-today', stats.today_sessions ?? 0);
    setText('ac-stat-handoff', waiting);
    setText('ac-count-waiting', waiting);
    setText('ac-count-mine', stats.mine_sessions ?? 0);
    setText('ac-count-progress', stats.in_progress_sessions ?? 0);
    setText('ac-count-closed', stats.closed_sessions ?? 0);

    const intents = document.getElementById('ac-stat-intents');
    if (intents) {
      const entries = Object.entries(stats.intent_counts || {});
      intents.textContent = entries.length
        ? entries.map(([key, value]) => `${key}: ${value}`).join(', ')
        : '—';
    }
  }

  function activeSession() {
    return state.sessions.find(session => Number(session.id) === state.currentId)
      || state.currentSession;
  }

  function renderSessions() {
    listTotal.textContent = String(state.sessions.length);
    if (!state.sessions.length) {
      rows.innerHTML = '<div class="ac-empty">Không có hội thoại trong bộ lọc này.</div>';
      return;
    }

    rows.innerHTML = state.sessions.map(session => {
      const id = Number(session.id);
      const status = normalizedStatus(session.status);
      const source = session.source === 'messenger' ? 'M' : '💬';
      const assignment = session.assigned_admin_name
        ? `Phụ trách: ${session.assigned_admin_name}`
        : 'Chưa phân công';
      return `
        <button type="button" class="ac-row${state.currentId === id ? ' is-active' : ''}"
                role="option" aria-selected="${state.currentId === id ? 'true' : 'false'}"
                data-id="${id}">
          <span class="ac-row-top">
            <span class="ac-row-title">${source} #${id}</span>
            <span class="ac-status ac-status--${status}">${esc(statusLabel(status))}</span>
          </span>
          <span class="ac-row-preview">${esc(session.last_message || 'Chưa có tin nhắn')}</span>
          <span class="ac-row-meta">
            <span>${esc(assignment)}</span>
            <time>${esc(formatTime(session.updated_at))}</time>
          </span>
        </button>`;
    }).join('');

    rows.querySelectorAll('.ac-row').forEach(row => {
      row.addEventListener('click', () => selectSession(Number(row.dataset.id)));
    });
  }

  function setActiveFilter() {
    root.querySelectorAll('.ac-filter').forEach(button => {
      const selected = button.dataset.view === state.view;
      button.classList.toggle('is-active', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
  }

  function showNotice(message) {
    notice.textContent = message || '';
    notice.hidden = !message;
  }

  function persistDraft() {
    if (state.currentId !== null) {
      drafts.set(state.currentId, input.value);
    }
  }

  function resetTranscript(message) {
    msgs.innerHTML = '';
    const empty = document.createElement('div');
    empty.className = 'ac-empty';
    empty.textContent = message;
    msgs.appendChild(empty);
  }

  function clearSelection() {
    persistDraft();
    state.currentId = null;
    state.currentSession = null;
    state.oldestId = null;
    state.latestId = null;
    state.hasMore = false;
    state.unread = 0;
    shell.classList.remove('ac-conversation-open');
    olderButton.hidden = true;
    newButton.hidden = true;
    form.hidden = true;
    actions.innerHTML = '';
    title.textContent = 'Chọn một hội thoại';
    meta.textContent = 'Tin nhắn mới nhất sẽ hiển thị tại đây';
    resetTranscript('Chọn hội thoại để bắt đầu hỗ trợ.');
    renderSessions();
  }

  async function loadSessions({ quiet = false } = {}) {
    const requestedView = state.view;
    const requestId = ++state.sessionsRequestId;
    try {
      const data = await request(`/sessions.php?view=${encodeURIComponent(requestedView)}`);
      if (requestId !== state.sessionsRequestId || state.view !== requestedView) {
        return false;
      }
      state.sessions = Array.isArray(data.sessions) ? data.sessions : [];
      state.workflowReady = data.workflow_ready === true;
      renderStats(data.stats || {});

      if (state.workflowReady) {
        showNotice('');
      } else {
        showNotice('Chế độ quy trình chưa được kích hoạt trong cơ sở dữ liệu. Phản hồi vẫn hoạt động ở chế độ tương thích.');
      }

      if (state.currentId !== null) {
        const refreshed = state.sessions.find(
          session => Number(session.id) === state.currentId
        );
        if (refreshed) {
          state.currentSession = refreshed;
        } else {
          clearSelection();
          return;
        }
      }
      renderSessions();
      renderHeader();
      return true;
    } catch (error) {
      if (requestId !== state.sessionsRequestId || state.view !== requestedView) {
        return false;
      }
      if (!quiet) {
        showNotice(`Không tải được danh sách hội thoại: ${error.message}`);
      }
      return false;
    }
  }

  function messageNode(message) {
    const sender = ['customer', 'bot', 'agent'].includes(message.sender)
      ? message.sender
      : 'bot';
    const labels = {
      customer: 'Khách hàng',
      bot: 'Trợ lý',
      agent: 'Admin'
    };
    const article = document.createElement('article');
    article.className = `ac-message ac-message--${sender}`;
    article.dataset.messageId = String(message.id);

    const header = document.createElement('header');
    const author = document.createElement('strong');
    author.textContent = labels[sender];
    const time = document.createElement('time');
    time.textContent = formatTime(message.created_at);
    header.append(author, time);

    const content = document.createElement('p');
    content.textContent = message.content || '';
    article.append(header, content);
    return article;
  }

  function appendMessages(messages) {
    const fragment = document.createDocumentFragment();
    let added = 0;
    messages.forEach(message => {
      if (msgs.querySelector(`[data-message-id="${Number(message.id)}"]`)) return;
      fragment.appendChild(messageNode(message));
      added += 1;
    });
    msgs.appendChild(fragment);
    return added;
  }

  async function loadInitial() {
    const sessionId = state.currentId;
    if (!sessionId) return;
    resetTranscript('Đang tải hội thoại…');
    try {
      const data = await request(`/history.php?session_id=${sessionId}&limit=50`);
      if (state.currentId !== sessionId) return;
      msgs.innerHTML = '';
      const messages = Array.isArray(data.messages) ? data.messages : [];
      if (messages.length) {
        appendMessages(messages);
      } else {
        resetTranscript('Hội thoại chưa có tin nhắn.');
      }
      state.oldestId = data.oldest_id;
      state.latestId = data.latest_id;
      state.hasMore = Boolean(data.has_more);
      olderButton.hidden = !state.hasMore;
      scrollBottom();
    } catch (error) {
      if (state.currentId === sessionId) {
        resetTranscript(`Không tải được hội thoại: ${error.message}`);
      }
    }
  }

  async function loadOlder() {
    if (!state.currentId || !state.oldestId || !state.hasMore) return;
    const sessionId = state.currentId;
    const previousHeight = msgs.scrollHeight;
    const previousTop = msgs.scrollTop;
    olderButton.disabled = true;
    olderButton.textContent = 'Đang tải…';
    try {
      const data = await request(
        `/history.php?session_id=${sessionId}&before_id=${state.oldestId}&limit=50`
      );
      if (state.currentId !== sessionId) return;
      const fragment = document.createDocumentFragment();
      (data.messages || []).forEach(message => {
        if (!msgs.querySelector(`[data-message-id="${Number(message.id)}"]`)) {
          fragment.appendChild(messageNode(message));
        }
      });
      msgs.prepend(fragment);
      state.oldestId = data.oldest_id || state.oldestId;
      state.hasMore = Boolean(data.has_more);
      olderButton.hidden = !state.hasMore;
      msgs.scrollTop = previousTop + (msgs.scrollHeight - previousHeight);
    } catch (error) {
      formStatus.textContent = `Không tải được tin cũ: ${error.message}`;
    } finally {
      olderButton.disabled = false;
      olderButton.textContent = 'Tải tin nhắn cũ hơn';
    }
  }

  async function pollNew() {
    if (!state.currentId) return;
    if (!state.latestId) {
      await loadInitial();
      return;
    }
    const sessionId = state.currentId;
    const wasNearBottom = nearBottom();
    try {
      const data = await request(
        `/history.php?session_id=${sessionId}&after_id=${state.latestId}&limit=100`
      );
      if (state.currentId !== sessionId) return;
      const messages = Array.isArray(data.messages) ? data.messages : [];
      const added = appendMessages(messages);
      if (data.latest_id) state.latestId = data.latest_id;
      if (!added) return;
      if (wasNearBottom) {
        scrollBottom();
      } else {
        state.unread += added;
        newButton.textContent = `${state.unread} tin mới ↓`;
        newButton.hidden = false;
      }
    } catch (_error) {
      // Polling errors remain silent; the next interval retries.
    }
  }

  function addAction(action, label, className) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `ac-action ${className || ''}`.trim();
    button.dataset.action = action;
    button.textContent = label;
    button.addEventListener('click', () => doAction(action));
    actions.appendChild(button);
  }

  function renderHeader() {
    const session = activeSession();
    if (!session || state.currentId === null) {
      form.hidden = true;
      return;
    }
    const status = normalizedStatus(session.status);
    const assignment = session.assigned_admin_name
      ? ` · ${session.assigned_admin_name}`
      : '';
    title.textContent = `Hội thoại #${state.currentId}`;
    meta.textContent = `${statusLabel(status)}${assignment}`;
    actions.innerHTML = '';

    if (state.workflowReady) {
      if (status === 'waiting' || status === 'active') {
        addAction('claim', 'Nhận xử lý', 'ac-action--primary');
      } else if (status === 'in_progress') {
        addAction('close', 'Đóng hội thoại', 'ac-action--danger');
      } else if (status === 'closed') {
        addAction('reopen', 'Mở lại', 'ac-action--primary');
      }
    }

    form.hidden = status === 'closed';
  }

  async function selectSession(id) {
    if (!id) return;
    persistDraft();
    state.currentId = id;
    state.currentSession = state.sessions.find(session => Number(session.id) === id) || null;
    state.oldestId = null;
    state.latestId = null;
    state.hasMore = false;
    state.unread = 0;
    shell.classList.add('ac-conversation-open');
    input.value = drafts.get(id) || '';
    autosize();
    formStatus.textContent = '';
    renderSessions();
    renderHeader();
    await loadInitial();
    if (window.matchMedia('(min-width: 761px)').matches && !form.hidden) {
      input.focus();
    }
  }

  async function doAction(action) {
    if (!state.currentId) return;
    const sessionId = state.currentId;
    actions.querySelectorAll('button').forEach(button => {
      button.disabled = true;
    });
    formStatus.textContent = 'Đang cập nhật…';
    try {
      const data = await request('/session_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: sessionId,
          action
        })
      });
      if (state.currentId !== sessionId) {
        await loadSessions({ quiet: true });
        return;
      }
      state.currentSession = data.session || state.currentSession;
      state.view = action === 'claim'
        ? 'mine'
        : (action === 'close' ? 'closed' : 'waiting');
      setActiveFilter();
      await loadSessions();
      formStatus.textContent = action === 'claim'
        ? 'Đã nhận xử lý.'
        : (action === 'close' ? 'Đã đóng hội thoại.' : 'Đã mở lại hội thoại.');
      renderHeader();
    } catch (error) {
      if (state.currentId === sessionId) {
        formStatus.textContent = `Không cập nhật được: ${error.message}`;
        renderHeader();
      }
    }
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (state.sending || !state.currentId) return;
    const sessionId = state.currentId;
    const content = input.value.trim();
    if (!content) {
      input.focus();
      return;
    }

    state.sending = true;
    sendButton.disabled = true;
    formStatus.textContent = 'Đang gửi…';
    try {
      const data = await request('/agent_reply.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId, content })
      });
      const savedDraft = drafts.get(sessionId);
      if (typeof savedDraft === 'string' && savedDraft.trim() === content) {
        drafts.delete(sessionId);
      }
      if (state.currentId !== sessionId) return;
      if (input.value.trim() === content) {
        input.value = '';
        drafts.delete(sessionId);
        autosize();
      } else {
        drafts.set(sessionId, input.value);
      }
      if (data.session) state.currentSession = data.session;
      formStatus.textContent = 'Đã gửi.';
      if (data.workflow_ready === true) {
        state.view = 'mine';
        setActiveFilter();
        await loadSessions({ quiet: true });
      }
      await pollNew();
      input.focus();
    } catch (error) {
      if (state.currentId === sessionId) {
        drafts.set(sessionId, input.value);
        formStatus.textContent = `Gửi thất bại: ${error.message}`;
        input.focus();
      } else if (!drafts.has(sessionId)) {
        drafts.set(sessionId, content);
      }
    } finally {
      state.sending = false;
      sendButton.disabled = false;
    }
  });

  input.addEventListener('input', () => {
    if (state.currentId !== null) drafts.set(state.currentId, input.value);
    autosize();
  });

  input.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
      event.preventDefault();
      if (state.sending) return;
      form.requestSubmit();
    }
    if (event.key === 'Escape' && window.matchMedia('(max-width: 760px)').matches) {
      event.preventDefault();
      backButton.click();
    }
  });

  olderButton.addEventListener('click', loadOlder);
  newButton.addEventListener('click', scrollBottom);
  backButton.addEventListener('click', () => {
    const activeId = state.currentId;
    persistDraft();
    shell.classList.remove('ac-conversation-open');
    const activeRow = rows.querySelector(`[data-id="${activeId}"]`);
    if (activeRow) activeRow.focus();
  });

  root.querySelectorAll('.ac-filter').forEach(button => {
    button.addEventListener('click', async () => {
      if (state.view === button.dataset.view) return;
      persistDraft();
      state.view = button.dataset.view;
      setActiveFilter();
      await loadSessions();
    });
  });

  setInterval(async () => {
    if (document.hidden || state.polling) return;
    state.polling = true;
    try {
      await loadSessions({ quiet: true });
      await pollNew();
    } finally {
      state.polling = false;
    }
  }, POLL_MS);

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      loadSessions({ quiet: true }).then(pollNew);
    }
  });

  setActiveFilter();
  renderHeader();
  loadSessions();
})();

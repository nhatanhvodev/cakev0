(function(){
  var data = window.ADMIN_TOPBAR || {};
  var searchItems = Array.isArray(data.search) ? data.search : [];

  function normalize(value){
    return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function hide(el){
    if (el) el.hidden = true;
  }

  function show(el){
    if (el) el.hidden = false;
  }

  function closePanel(button, panel){
    hide(panel);
    if (button) button.setAttribute('aria-expanded', 'false');
  }

  function openPanel(button, panel){
    document.querySelectorAll('.topbar-popover').forEach(function(other){
      if (other !== panel) other.hidden = true;
    });
    document.querySelectorAll('[aria-haspopup="true"], #adminSearchInput').forEach(function(btn){
      if (btn !== button) btn.setAttribute('aria-expanded', 'false');
    });
    show(panel);
    if (button) button.setAttribute('aria-expanded', 'true');
  }

  function bindPopover(buttonId, panelId){
    var button = document.getElementById(buttonId);
    var panel = document.getElementById(panelId);
    if (!button || !panel) return;
    button.addEventListener('click', function(event){
      event.stopPropagation();
      if (panel.hidden) openPanel(button, panel);
      else closePanel(button, panel);
    });
  }

  bindPopover('notifyBtn', 'notifyPanel');
  bindPopover('profileBtn', 'profilePanel');

  var input = document.getElementById('adminSearchInput');
  var panel = document.getElementById('adminSearchPanel');
  var results = document.getElementById('adminSearchResults');
  var empty = document.getElementById('adminSearchEmpty');
  var activeIndex = -1;
  var currentResults = [];

  function setActive(index){
    activeIndex = index;
    Array.prototype.slice.call(results.querySelectorAll('.search-result-item')).forEach(function(item, i){
      item.classList.toggle('is-active', i === activeIndex);
    });
  }

  function resultNode(item){
    var link = document.createElement('a');
    link.href = item.href || '#';
    link.className = 'search-result-item';
    link.setAttribute('role', 'option');

    var icon = document.createElement('span');
    icon.className = 'search-result-icon';
    var i = document.createElement('i');
    i.className = 'bi ' + (item.icon || 'bi-search');
    icon.appendChild(i);

    var text = document.createElement('span');
    var title = document.createElement('strong');
    title.textContent = item.title || '';
    var meta = document.createElement('small');
    meta.textContent = (item.type ? item.type + ' · ' : '') + (item.meta || '');
    text.appendChild(title);
    text.appendChild(meta);

    link.appendChild(icon);
    link.appendChild(text);
    return link;
  }

  function renderSearch(){
    if (!input || !panel || !results) return;
    var query = normalize(input.value).trim();
    results.innerHTML = '';
    activeIndex = -1;

    if (query.length < 2) {
      currentResults = searchItems.slice(0, 6);
    } else {
      currentResults = searchItems.filter(function(item){
        var haystack = normalize([item.title, item.meta, item.type, item.keywords].join(' '));
        return haystack.indexOf(query) !== -1;
      }).slice(0, 8);
    }

    currentResults.forEach(function(item){
      results.appendChild(resultNode(item));
    });

    if (empty) empty.hidden = currentResults.length > 0;
    openPanel(input, panel);
  }

  if (input && panel && results) {
    input.addEventListener('focus', renderSearch);
    input.addEventListener('input', renderSearch);
    input.addEventListener('keydown', function(event){
      if (panel.hidden && event.key !== 'Escape') {
        renderSearch();
      }
      if (event.key === 'Escape') {
        closePanel(input, panel);
        input.blur();
        return;
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActive(Math.min(currentResults.length - 1, activeIndex + 1));
        return;
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(Math.max(0, activeIndex - 1));
        return;
      }
      if (event.key === 'Enter' && currentResults.length) {
        event.preventDefault();
        var target = currentResults[activeIndex >= 0 ? activeIndex : 0];
        if (target && target.href) window.location.href = target.href;
      }
    });
  }

  document.addEventListener('click', function(event){
    if (!event.target.closest('.topbar-menu') && !event.target.closest('.admin-search')) {
      document.querySelectorAll('.topbar-popover').forEach(hide);
      document.querySelectorAll('[aria-expanded="true"]').forEach(function(btn){
        btn.setAttribute('aria-expanded', 'false');
      });
    }
  });

  document.addEventListener('keydown', function(event){
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.topbar-popover').forEach(hide);
    document.querySelectorAll('[aria-expanded="true"]').forEach(function(btn){
      btn.setAttribute('aria-expanded', 'false');
    });
  });
})();

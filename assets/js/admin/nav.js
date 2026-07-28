(function(){
  var app=document.getElementById('app');
  var collapse=document.getElementById('collapseBtn');
  var sidebar=document.getElementById('adminSidebar')||document.querySelector('.sidebar');
  var storageKey='admin-sidebar-collapsed';

  function isDrawerViewport(){
    return matchMedia('(max-width:760px)').matches;
  }

  function savedCollapsed(){
    try{return localStorage.getItem(storageKey)==='1';}
    catch(e){return false;}
  }

  function persistCollapsed(collapsed){
    try{localStorage.setItem(storageKey,collapsed?'1':'0');}
    catch(e){}
  }

  function setCollapsed(collapsed,persist){
    if(!app)return;
    app.classList.toggle('collapsed',!!collapsed);
    if(persist)persistCollapsed(!!collapsed);
  }

  function syncCollapsed(){
    if(!app)return;
    if(isDrawerViewport()){
      app.classList.remove('collapsed');
      return;
    }
    setCollapsed(savedCollapsed(),false);
  }

  function closeDrawer(){
    if(app)app.classList.remove('drawer-open');
    if(collapse)collapse.setAttribute('aria-expanded','false');
  }

  syncCollapsed();

  if(collapse)collapse.addEventListener('click',function(){
    if(!app)return;
    if(isDrawerViewport()){
      var open=!app.classList.contains('drawer-open');
      app.classList.toggle('drawer-open',open);
      collapse.setAttribute('aria-expanded',open?'true':'false');
    }
    else{setCollapsed(!app.classList.contains('collapsed'),true);}
  });
  var bd=document.querySelector('.backdrop');
  if(bd)bd.addEventListener('click',closeDrawer);
  function navLinkFromEvent(event){
    if(!event.target||!event.target.closest)return null;
    return event.target.closest('#adminSidebar a[href], .sidebar a[href]');
  }

  document.addEventListener('click',function(event){
    var link=navLinkFromEvent(event);
    if(!link)return;
    if(isDrawerViewport())closeDrawer();
    else setCollapsed(true,true);
  },true);

  if(sidebar)sidebar.addEventListener('click',function(event){
    var link=navLinkFromEvent(event);
    if(!link)return;
    if(isDrawerViewport())closeDrawer();
    else setCollapsed(true,true);
  });
  addEventListener('resize',function(){
    if(!isDrawerViewport())closeDrawer();
    syncCollapsed();
  });
})();

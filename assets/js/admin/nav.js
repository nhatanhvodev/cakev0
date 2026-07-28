(function(){
  var app=document.getElementById('app');
  var collapse=document.getElementById('collapseBtn');
  var sidebar=document.getElementById('adminSidebar')||document.querySelector('.sidebar');

  function isDrawerViewport(){
    return matchMedia('(max-width:760px)').matches;
  }

  function closeDrawer(){
    if(app)app.classList.remove('drawer-open');
    if(collapse)collapse.setAttribute('aria-expanded','false');
  }

  if(collapse)collapse.addEventListener('click',function(){
    if(!app)return;
    if(isDrawerViewport()){
      var open=!app.classList.contains('drawer-open');
      app.classList.toggle('drawer-open',open);
      collapse.setAttribute('aria-expanded',open?'true':'false');
    }
    else{app.classList.toggle('collapsed');}
  });
  var bd=document.querySelector('.backdrop');
  if(bd)bd.addEventListener('click',closeDrawer);
  function navLinkFromEvent(event){
    if(!event.target||!event.target.closest)return null;
    return event.target.closest('#adminSidebar a[href], .sidebar a[href]');
  }

  document.addEventListener('click',function(event){
    var link=navLinkFromEvent(event);
    if(link&&isDrawerViewport())closeDrawer();
  },true);

  if(sidebar)sidebar.addEventListener('click',function(event){
    var link=navLinkFromEvent(event);
    if(link&&isDrawerViewport())closeDrawer();
  });
  addEventListener('resize',function(){
    if(!isDrawerViewport())closeDrawer();
  });
})();

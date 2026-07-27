(function(){
  var root=document.documentElement, btn=document.getElementById('themeBtn');
  var saved=localStorage.getItem('admin-theme');
  if(saved){root.setAttribute('data-theme',saved);}
  function isDark(){var a=root.getAttribute('data-theme');
    return a?a==='dark':matchMedia('(prefers-color-scheme:dark)').matches;}
  function sync(){if(btn)btn.innerHTML=isDark()?'<i class="bi bi-sun"></i>':'<i class="bi bi-moon-stars"></i>';}
  if(btn)btn.addEventListener('click',function(){
    var next=isDark()?'light':'dark';
    root.setAttribute('data-theme',next);
    localStorage.setItem('admin-theme',next); sync();
    document.dispatchEvent(new CustomEvent('admin:themechange'));
  });
  sync();
})();

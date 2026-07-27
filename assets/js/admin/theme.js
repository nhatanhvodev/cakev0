(function(){
  var root=document.documentElement, btn=document.getElementById('themeBtn');
  var saved=localStorage.getItem('admin-theme');
  if(saved){root.setAttribute('data-theme',saved);}
  function isDark(){
    return document.documentElement.getAttribute('data-theme') !== 'light';}
  function sync(){if(btn)btn.innerHTML=isDark()?'<i class="bi bi-sun"></i>':'<i class="bi bi-moon-stars"></i>';}
  if(btn)btn.addEventListener('click',function(){
    var next=isDark()?'light':'dark';
    root.setAttribute('data-theme',next);
    localStorage.setItem('admin-theme',next); sync();
    document.dispatchEvent(new CustomEvent('admin:themechange'));
  });
  sync();
})();

(function(){
  var app=document.getElementById('app');
  var collapse=document.getElementById('collapseBtn');
  if(collapse)collapse.addEventListener('click',function(){
    if(matchMedia('(max-width:760px)').matches){app.classList.toggle('drawer-open');}
    else{app.classList.toggle('collapsed');}
  });
  var bd=document.querySelector('.backdrop');
  if(bd)bd.addEventListener('click',function(){app.classList.remove('drawer-open');});
})();

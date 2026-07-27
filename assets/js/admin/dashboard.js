(function(){
  var el=document.getElementById('revenueChart');
  if(!el||!window.Chart||!window.ADMIN_CHART)return;
  function tok(n){return getComputedStyle(document.body).getPropertyValue(n).trim();}
  var chart;
  function draw(){
    if(chart)chart.destroy();
    var ctx=el.getContext('2d');
    var grad=ctx.createLinearGradient(0,0,0,240);
    grad.addColorStop(0,tok('--accent')); grad.addColorStop(1,'transparent');
    chart=new Chart(ctx,{type:'line',data:{labels:window.ADMIN_CHART.labels,
      datasets:[{label:'Doanh thu (VNĐ)',data:window.ADMIN_CHART.values,
        borderColor:tok('--accent'),backgroundColor:grad,fill:true,tension:.35,
        pointRadius:0,pointHoverRadius:5,borderWidth:2.5}]},
      options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{labels:{color:tok('--muted')}}},
        scales:{x:{grid:{color:tok('--border')},ticks:{color:tok('--faint')}},
          y:{grid:{color:tok('--border')},ticks:{color:tok('--faint')}}}}});
  }
  draw();
  document.addEventListener('admin:themechange',draw);
})();

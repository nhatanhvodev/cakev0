(function(){
  function token(name){
    return getComputedStyle(document.body).getPropertyValue(name).trim();
  }

  function formatVnd(value){
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND',
      maximumFractionDigits: 0
    }).format(Number(value || 0));
  }

  function syncRevenueFilters(){
    var form = document.querySelector('[data-revenue-toolbar]');
    if (!form) return;
    var checked = form.querySelector('input[name="chart_view"]:checked');
    var view = checked ? checked.value : 'week';
    var month = form.querySelector('.revenue-filter-month');
    var year = form.querySelector('.revenue-filter-year');
    if (month) month.hidden = view !== 'month';
    if (year) year.hidden = view !== 'month' && view !== 'year';
  }

  var toolbar = document.querySelector('[data-revenue-toolbar]');
  if (toolbar) {
    toolbar.addEventListener('change', function(event){
      if (event.target && event.target.name === 'chart_view') {
        syncRevenueFilters();
      }
    });
    syncRevenueFilters();
  }

  var el = document.getElementById('revenueChart');
  if (!el || !window.Chart || !window.ADMIN_CHART) return;

  var chart;
  function draw(){
    if (chart) chart.destroy();
    var ctx = el.getContext('2d');
    var accent = token('--accent') || '#9a5a20';
    var border = token('--border') || 'rgba(0,0,0,.12)';
    var muted = token('--muted') || '#7b736b';
    var faint = token('--faint') || '#9a948d';
    var surface = token('--surface') || '#fff';
    var gradient = ctx.createLinearGradient(0, 0, 0, el.clientHeight || 280);
    gradient.addColorStop(0, accent);
    gradient.addColorStop(.45, 'rgba(180, 117, 45, .22)');
    gradient.addColorStop(1, 'rgba(180, 117, 45, 0)');

    chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: window.ADMIN_CHART.labels || [],
        datasets: [{
          label: 'Doanh thu',
          data: window.ADMIN_CHART.values || [],
          borderColor: accent,
          backgroundColor: gradient,
          fill: true,
          tension: .42,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBorderWidth: 2,
          pointHoverBorderColor: surface,
          pointHoverBackgroundColor: accent,
          borderWidth: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            displayColors: false,
            backgroundColor: surface,
            titleColor: token('--text') || '#2a241f',
            bodyColor: muted,
            borderColor: border,
            borderWidth: 1,
            padding: 12,
            callbacks: {
              label: function(context){
                return 'Doanh thu: ' + formatVnd(context.parsed.y);
              },
              afterLabel: function(context){
                var counts = window.ADMIN_CHART.orderCounts || [];
                return 'Số đơn: ' + new Intl.NumberFormat('vi-VN').format(Number(counts[context.dataIndex] || 0));
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { color: faint, maxRotation: 0, autoSkipPadding: 18 }
          },
          y: {
            beginAtZero: true,
            grid: { color: border, drawBorder: false },
            ticks: {
              color: faint,
              callback: function(value){
                var n = Number(value || 0);
                if (n >= 1000000) return (n / 1000000).toLocaleString('vi-VN') + 'tr';
                if (n >= 1000) return (n / 1000).toLocaleString('vi-VN') + 'k';
                return n.toLocaleString('vi-VN');
              }
            }
          }
        }
      }
    });
  }

  draw();
  document.addEventListener('admin:themechange', draw);
})();

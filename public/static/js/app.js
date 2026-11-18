(function () {
  'use strict';

  var faDigits = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };
  function normalize(s) { return String(s || '').replace(/[۰-۹٠-٩]/g, function (d) { return faDigits[d]; }); }
  function group(s) {
    s = normalize(s).replace(/[^\d.]/g, '');
    var parts = s.split('.');
    var intPart = parts[0].replace(/^0+(?=\d)/, '');
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.length > 1 ? intPart + '.' + parts.slice(1).join('').slice(0, 8) : intPart;
  }

  // Thousand-separated amount inputs. The server strips separators, so the stored value stays exact.
  document.querySelectorAll('input.money-input').forEach(function (el) {
    el.setAttribute('inputmode', 'decimal');
    if (el.value) el.value = group(el.value);
    el.addEventListener('input', function () {
      var pos = el.value.length - el.selectionStart;
      el.value = group(el.value);
      var p = Math.max(0, el.value.length - pos);
      try { el.setSelectionRange(p, p); } catch (e) { /* ignore */ }
    });
  });
  document.querySelectorAll('input.digits-input').forEach(function (el) {
    el.addEventListener('input', function () { el.value = normalize(el.value); });
  });

  // Confirmation for sensitive actions + double-submit prevention.
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      var msg = form.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) { ev.preventDefault(); return; }
      if (form.dataset.submitted === '1') { ev.preventDefault(); return; }
      form.dataset.submitted = '1';
      form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (b) {
        b.disabled = true;
        if (b.tagName === 'BUTTON') { b.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm ms-1" aria-hidden="true"></span>'); }
      });
      // A submit button's own name/value would be lost when disabled; keep it.
      if (ev.submitter && ev.submitter.name) {
        var h = document.createElement('input'); h.type = 'hidden'; h.name = ev.submitter.name; h.value = ev.submitter.value; form.appendChild(h);
      }
    });
  });

  // Show / hide password.
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-toggle-password'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });

  // Investment form: toggle fields by operation type.
  var typeSel = document.querySelector('[data-investment-type]');
  if (typeSel) {
    var sync = function () {
      var t = typeSel.value;
      document.querySelectorAll('[data-show-for]').forEach(function (el) {
        var types = el.getAttribute('data-show-for').split(',');
        el.classList.toggle('d-none', types.indexOf(t) === -1);
      });
    };
    typeSel.addEventListener('change', sync); sync();
  }
  var contribType = document.querySelector('[data-contribution-type]');
  if (contribType) {
    var syncC = function () {
      var t = contribType.value;
      document.querySelectorAll('[data-contrib-show]').forEach(function (el) {
        el.classList.toggle('d-none', el.getAttribute('data-contrib-show').split(',').indexOf(t) === -1);
      });
    };
    contribType.addEventListener('change', syncC); syncC();
  }

  // Charts: <canvas data-chart="line|bar|doughnut" data-source="json-script-id">
  if (window.Chart) {
    Chart.defaults.font.family = "'Vazirmatn', Tahoma, sans-serif";
    Chart.defaults.color = '#51625e';
    var palette = ['#0f5c4d', '#e0a526', '#3b7dd8', '#c2504b', '#7a5cc4', '#2f9e8f', '#8c6d46', '#5b6b73', '#d16ba5', '#6aa84f'];
    var fmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });
    document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
      var src = document.getElementById(canvas.getAttribute('data-source'));
      if (!src) return;
      var data = JSON.parse(src.textContent);
      var type = canvas.getAttribute('data-chart');
      (data.datasets || []).forEach(function (ds, i) {
        var c = ds.color || palette[i % palette.length];
        if (type === 'doughnut') { ds.backgroundColor = ds.backgroundColor || palette; ds.borderColor = '#fff'; }
        else { ds.borderColor = c; ds.backgroundColor = type === 'bar' ? c : c + '22'; ds.fill = type === 'line' && i === 0; ds.tension = .25; ds.pointRadius = 2; }
      });
      new Chart(canvas, {
        type: type,
        data: data,
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', rtl: true, labels: { boxWidth: 12 } },
            tooltip: { rtl: true, callbacks: { label: function (ctx) {
              var v = ctx.parsed && typeof ctx.parsed === 'object' ? ctx.parsed.y : ctx.parsed;
              return (ctx.dataset.label || ctx.label || '') + ': ' + fmt.format(v / (data.divisor || 1)) + (data.unit ? ' ' + data.unit : '');
            } } }
          },
          scales: type === 'doughnut' ? {} : { y: { ticks: { callback: function (v) { return fmt.format(v / (data.divisor || 1)); } } } }
        }
      });
    });
  }
})();

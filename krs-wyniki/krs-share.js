/* KartRace Series — grafika do relacji (Stories) 1080×1920, generowana w przeglądarce po kliknięciu */
window.KRSShare = (function () {
  var W = 1080, H = 1920;
  var MEDAL = {
    1: ['#fff4b8', '#f3c94e', '#a8780a', '#6b4a00'],
    2: ['#ffffff', '#d7dbe0', '#8a9097', '#4b5056'],
    3: ['#ffd9b5', '#d58c4c', '#86471a', '#4a2408']
  };
  var cache = {};
  function img(src) {
    if (!cache[src]) cache[src] = new Promise(function (res, rej) { var i = new Image(); i.onload = function () { res(i); }; i.onerror = rej; i.src = src; });
    return cache[src];
  }
  function fonts() {
    return Promise.all([
      document.fonts.load('italic 400 120px "Bebas Neue"'),
      document.fonts.load('400 120px "Bebas Neue"'),
      document.fonts.load('400 30px "Space Mono"'),
      document.fonts.load('700 30px "Space Mono"')
    ]).catch(function () {});
  }
  function grad(x, y0, y1, c) { var g = x.createLinearGradient(0, y0, 0, y1); g.addColorStop(0, c[0]); g.addColorStop(0.45, c[1]); g.addColorStop(1, c[2]); return g; }
  /* rysuje tekst tak, żeby środek WIDOCZNEGO kształtu wypadł dokładnie w (cx, cy) */
  function centered(x, text, cx, cy) {
    x.textAlign = 'left'; x.textBaseline = 'alphabetic';
    var m = x.measureText(text);
    var L = m.actualBoundingBoxLeft || 0, R = m.actualBoundingBoxRight || m.width, A = m.actualBoundingBoxAscent || 0, D = m.actualBoundingBoxDescent || 0;
    var px = cx - (R - L) / 2, py = cy + (A - D) / 2;
    x.fillText(text, px, py);
    return { x: px, y: py, top: py - A, bottom: py + D };
  }
  function spaced(x, text, cx, y, spacing) {
    var chars = text.split(''), w = 0, i;
    for (i = 0; i < chars.length; i++) w += x.measureText(chars[i]).width + (i < chars.length - 1 ? spacing : 0);
    var px = cx - w / 2; x.textAlign = 'left';
    for (i = 0; i < chars.length; i++) { x.fillText(chars[i], px, y); px += x.measureText(chars[i]).width + spacing; }
  }
  function leaf(x, len, wid) {
    x.beginPath(); x.moveTo(0, 0);
    x.quadraticCurveTo(len * 0.5, -wid, len, 0);
    x.quadraticCurveTo(len * 0.5, wid, 0, 0);
    x.closePath();
  }
  function wreath(x, cx, cy, r, c) {
    var side, k, n = 12, s = r / 170;
    for (side = -1; side <= 1; side += 2) {
      x.save(); x.strokeStyle = c[2]; x.lineWidth = 6 * s; x.lineCap = 'round'; x.beginPath();
      if (side < 0) x.arc(cx, cy, r, Math.PI * 0.62, Math.PI * 1.38, false); else x.arc(cx, cy, r, Math.PI * 0.38, -Math.PI * 0.38, true);
      x.stroke(); x.restore();
      for (k = 0; k < n; k++) {
        var t = k / (n - 1);
        var ang = side < 0 ? Math.PI * (0.62 + 0.76 * t) : Math.PI * (0.38 - 0.76 * t);
        var px = cx + Math.cos(ang) * r, py = cy + Math.sin(ang) * r;
        var tangent = ang + (side < 0 ? Math.PI / 2 : -Math.PI / 2);
        var size = (1 - t * 0.35) * s;
        for (var lay = -1; lay <= 1; lay += 2) {
          x.save(); x.translate(px, py); x.rotate(tangent - lay * 0.55 * side);
          leaf(x, 62 * size, 17 * size); x.fillStyle = grad(x, -20, 20, c); x.fill();
          x.lineWidth = 2; x.strokeStyle = c[3]; x.stroke(); x.restore();
        }
      }
      var endA = side < 0 ? Math.PI * 1.38 : -Math.PI * 0.38;
      x.save(); x.translate(cx + Math.cos(endA) * r, cy + Math.sin(endA) * r); x.rotate(endA + (side < 0 ? Math.PI / 2 : -Math.PI / 2));
      leaf(x, 52 * s, 15 * s); x.fillStyle = grad(x, -20, 20, c); x.fill(); x.strokeStyle = c[3]; x.lineWidth = 2; x.stroke(); x.restore();
    }
    var b = cy + r;
    x.save(); x.fillStyle = grad(x, b - 20 * s, b + 80 * s, c); x.strokeStyle = c[3]; x.lineWidth = 2;
    [-1, 1].forEach(function (d) {
      x.beginPath(); x.moveTo(cx + d * 14 * s, b - 6 * s); x.lineTo(cx + d * 70 * s, b + 62 * s); x.lineTo(cx + d * 44 * s, b + 52 * s); x.lineTo(cx + d * 30 * s, b + 76 * s); x.lineTo(cx - d * 4 * s, b + 4 * s); x.closePath(); x.fill(); x.stroke();
    });
    x.beginPath(); x.arc(cx, b, 16 * s, 0, Math.PI * 2); x.fill(); x.stroke(); x.restore();
    return b + 76 * s;
  }
  function fit(x, text, maxW, size, tpl) {
    var s = size; x.font = tpl.replace('{s}', s);
    while (x.measureText(text).width > maxW && s > 40) { s -= 4; x.font = tpl.replace('{s}', s); }
    return s;
  }
  function glitchText(x, text, cx, y, tpl, size, maxW) {
    x.save(); x.translate(cx, y); x.transform(1, 0, -0.16, 1, 0, 0);
    fit(x, text, maxW, size, tpl); x.textAlign = 'center'; x.textBaseline = 'alphabetic';
    x.fillStyle = 'rgba(255,40,30,.85)'; x.fillText(text, 5, 0);
    x.fillStyle = 'rgba(34,227,255,.6)'; x.fillText(text, -5, 0);
    x.fillStyle = '#ffffff'; x.fillText(text, 0, 0);
    x.restore();
  }

  /* o = { n, p, s, b, sc:'general'|'round', r, d, t, g, bg, logo } */
  function draw(o) {
    return Promise.all([fonts(), img(o.bg), img(o.logo)]).then(function (r) {
      var bg = r[1], logo = r[2], cx = W / 2;
      var c = document.createElement('canvas'); c.width = W; c.height = H;
      var x = c.getContext('2d');
      x.fillStyle = '#050506'; x.fillRect(0, 0, W, H);
      var sc = Math.max(W / bg.width, H / bg.height), bw = bg.width * sc, bh = bg.height * sc;
      x.drawImage(bg, (W - bw) / 2, (H - bh) / 2 + 60, bw, bh);
      var g = x.createLinearGradient(0, 0, 0, H);
      g.addColorStop(0, 'rgba(5,5,6,.9)'); g.addColorStop(0.18, 'rgba(5,5,6,.45)'); g.addColorStop(0.42, 'rgba(5,5,6,.2)'); g.addColorStop(0.6, 'rgba(5,5,6,.6)'); g.addColorStop(0.75, 'rgba(5,5,6,.92)'); g.addColorStop(1, 'rgba(5,5,6,.98)');
      x.fillStyle = g; x.fillRect(0, 0, W, H);
      var rg = x.createRadialGradient(cx, 760, 60, cx, 760, 700); rg.addColorStop(0, 'rgba(225,6,0,.2)'); rg.addColorStop(1, 'rgba(225,6,0,0)');
      x.fillStyle = rg; x.fillRect(0, 0, W, H);
      x.fillStyle = 'rgba(255,255,255,.035)'; for (var yy = 0; yy < H; yy += 4) x.fillRect(0, yy, W, 1);
      x.strokeStyle = '#e10600'; x.lineWidth = 6;
      x.beginPath(); x.moveTo(50, 320); x.lineTo(50, 230); x.lineTo(140, 230); x.stroke();
      x.beginPath(); x.moveTo(W - 50, H - 320); x.lineTo(W - 50, H - 230); x.lineTo(W - 140, H - 230); x.stroke();

      // logo i nagłówek (poza górną strefą interfejsu relacji ~200 px)
      var lw = 190, lh = logo.height * lw / logo.width; x.drawImage(logo, cx - lw / 2, 275, lw, lh);
      var head = o.sc === 'general' ? 'KLASYFIKACJA GENERALNA · SEZON 2026' : ('RUNDA ' + o.r + (o.d ? ' · ' + o.d : ''));
      x.fillStyle = '#e10600'; x.font = '700 28px "Space Mono"'; x.textBaseline = 'alphabetic';
      spaced(x, head.toUpperCase(), cx, 275 + lh + 66, 6);

      // miejsce
      var place = String(o.p || '').trim(), num = /^\d+$/.test(place) ? parseInt(place, 10) : null;
      var mainFinal = o.sc === 'general' || !o.g || /fina[łl]\s*a\b/i.test(o.g) || /^wyniki$/i.test(o.g);
      var med = (num && num <= 3 && mainFinal) ? MEDAL[num] : null;
      var cy = 760, below;
      if (med) {
        below = wreath(x, cx, cy, 190, med);
        x.save(); x.translate(cx, cy - 4); x.transform(1, 0, -0.17, 1, 0, 0); x.font = '400 280px "Bebas Neue"'; x.shadowColor = 'rgba(0,0,0,.6)'; x.shadowBlur = 24;
        x.fillStyle = grad(x, -130, 130, med); centered(x, String(num), 0, 0); x.restore();
      } else {
        x.save(); x.translate(cx, cy); x.rotate(-0.06);
        x.fillStyle = 'rgba(10,10,11,.85)'; x.fillRect(-160, -160, 320, 320);
        x.strokeStyle = '#e10600'; x.lineWidth = 9; x.strokeRect(-160, -160, 320, 320);
        x.transform(1, 0, -0.17, 1, 0, 0); x.font = '400 ' + (place.length > 2 ? 160 : 250) + 'px "Bebas Neue"'; x.fillStyle = '#fff'; centered(x, place || '–', 0, 0);
        x.restore(); below = cy + 180;
      }
      x.fillStyle = '#c9c9ce'; x.font = '700 30px "Space Mono"'; x.textBaseline = 'alphabetic';
      var lbl = num ? 'MIEJSCE' : 'WYNIK';
      if (o.sc !== 'general' && o.g && !/^wyniki$/i.test(o.g)) lbl += ' · ' + o.g.toUpperCase();
      spaced(x, lbl, cx, below + 60, 12);

      // zawodnik
      glitchText(x, String(o.n || '').toUpperCase(), cx, below + 225, 'italic 400 {s}px "Bebas Neue"', 160, 960);
      var y = below + 290;
      if (o.sc !== 'general' && o.t) { x.fillStyle = '#d9d9de'; x.font = '400 32px "Space Mono"'; spaced(x, String(o.t).toUpperCase(), cx, y, 4); y += 25; } else { y -= 25; }

      // statystyki
      var chips = [];
      if (o.s !== '' && o.s != null && o.s !== '-') chips.push({ t: o.s + ' PKT', red: true });
      if (o.b) chips.push({ t: 'BEST LAP ' + o.b, red: false });
      x.font = '700 34px "Space Mono"';
      var gap = 26, widths = chips.map(function (ch) { return x.measureText(ch.t).width + 64; });
      var tot = widths.reduce(function (a, b) { return a + b; }, 0) + gap * Math.max(0, chips.length - 1), px = cx - tot / 2, cyc = y + 20;
      chips.forEach(function (ch, i) {
        x.fillStyle = ch.red ? '#e10600' : 'rgba(255,255,255,.08)'; x.fillRect(px, cyc, widths[i], 74);
        if (!ch.red) { x.strokeStyle = 'rgba(255,255,255,.35)'; x.lineWidth = 2; x.strokeRect(px, cyc, widths[i], 74); }
        x.fillStyle = '#fff'; centered(x, ch.t, px + widths[i] / 2, cyc + 37); px += widths[i] + gap;
      });

      // stopka (nad dolną strefą interfejsu relacji)
      glitchText(x, 'KART RACE SERIES', cx, H - 360, 'italic 400 {s}px "Bebas Neue"', 110, 900);
      x.fillStyle = '#9a9da3'; x.font = '400 26px "Space Mono"'; spaced(x, 'KARTRACESERIES.PL  ·  @KARTRACE_SERIES', cx, H - 308, 3);
      return c;
    });
  }

  /* okienko z podglądem i przyciskami */
  function open(o) {
    var ov = document.createElement('div'); ov.className = 'krs-modal'; ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-label', 'Grafika do udostępnienia');
    ov.innerHTML = '<div class="krs-modal-box"><button type="button" class="krs-modal-x" aria-label="Zamknij">×</button><div class="krs-modal-prev"><span class="krs-modal-load">Generuję grafikę…</span></div><div class="krs-modal-act"></div><p class="krs-modal-hint">Na telefonie kliknij „Udostępnij" i wybierz Instagram albo Facebook → Relacja. Na komputerze pobierz grafikę i dodaj ją w aplikacji.</p></div>';
    document.body.appendChild(ov); document.documentElement.classList.add('krs-noscroll');
    function close() { ov.remove(); document.documentElement.classList.remove('krs-noscroll'); document.removeEventListener('keydown', esc); }
    function esc(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', esc);
    ov.addEventListener('click', function (e) { if (e.target === ov || e.target.classList.contains('krs-modal-x')) close(); });
    draw(o).then(function (c) {
      c.toBlob(function (blob) {
        var url = URL.createObjectURL(blob);
        var fname = 'kartrace-' + String(o.n || 'wynik').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ł/g, 'l').replace(/[^a-z0-9]+/g, '-') + (o.sc === 'general' ? '-generalna' : '-runda-' + o.r) + '.png';
        var prev = ov.querySelector('.krs-modal-prev'); prev.innerHTML = ''; var im = new Image(); im.src = url; im.alt = 'Podgląd grafiki'; prev.appendChild(im);
        var act = ov.querySelector('.krs-modal-act'), file = null;
        try { file = new File([blob], fname, { type: 'image/png' }); } catch (e) {}
        if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
          var sb = document.createElement('button'); sb.type = 'button'; sb.className = 'krs-btn krs-btn-red'; sb.textContent = 'Udostępnij';
          sb.addEventListener('click', function () { navigator.share({ files: [file], title: 'KartRace Series' }).catch(function () {}); });
          act.appendChild(sb);
        }
        var a = document.createElement('a'); a.className = 'krs-btn krs-btn-outline'; a.href = url; a.download = fname; a.textContent = 'Pobierz grafikę'; act.appendChild(a);
      }, 'image/png');
    }).catch(function () { ov.querySelector('.krs-modal-prev').innerHTML = '<span class="krs-modal-load">Nie udało się wygenerować grafiki. Spróbuj ponownie.</span>'; });
  }
  return { draw: draw, open: open, W: W, H: H };
})();

(function(){
  var root=document.querySelector('[data-krs-res]'); if(!root) return;
  var tabs=root.querySelectorAll('[data-krs-tab]'), panels=root.querySelectorAll('[data-krs-panel]');
  function go(y){ window.scrollTo({top:y,behavior:'smooth'}); }
  function show(id, scroll){
    var drv=null;
    if(id.indexOf('kierowca-')===0){ drv=document.getElementById(id); id='generalna'; }
    var found=false; panels.forEach(function(p){ if(p.getAttribute('data-krs-panel')===id) found=true; });
    if(!found) id='generalna';
    panels.forEach(function(p){ p.hidden = p.getAttribute('data-krs-panel')!==id; });
    tabs.forEach(function(t){ var on=t.getAttribute('data-krs-tab')===id; t.classList.toggle('is-active',on); if(on){ t.setAttribute('aria-current','true'); } else t.removeAttribute('aria-current'); });
    if(drv){ drv.open=true; drv.classList.add('is-hit'); setTimeout(function(){ go(drv.getBoundingClientRect().top+window.pageYOffset-140); },50); return; }
    if(scroll){ go(root.getBoundingClientRect().top+window.pageYOffset-110); }
  }
  root.classList.add('is-js');
  var h=location.hash.slice(1);
  show(h||'generalna', !!h);
  window.addEventListener('hashchange', function(){ show(location.hash.slice(1), true); });
  function toggle(tr){ var n=tr.nextElementSibling; if(!n||!n.classList.contains('krs-genrow')) return; var open=n.hidden; n.hidden=!open; tr.classList.toggle('is-open',open); tr.setAttribute('aria-expanded',open?'true':'false'); }
  root.addEventListener('click', function(e){
    var sb=e.target.closest('.krs-share');
    if(sb){ e.preventDefault(); e.stopPropagation(); var o={}; try{o=JSON.parse(sb.getAttribute('data-krs-share'));}catch(err){} o.bg=root.getAttribute('data-krs-bg'); o.logo=root.getAttribute('data-krs-logo'); if(window.KRSShare) KRSShare.open(o); return; }
    if(e.target.closest('a')) return; var tr=e.target.closest('tr.krs-click'); if(tr) toggle(tr); });
  root.addEventListener('keydown', function(e){ if(e.key!=='Enter'&&e.key!==' ') return; if(e.target.closest&&e.target.closest('.krs-share')) return; var tr=e.target.closest&&e.target.closest('tr.krs-click'); if(tr){ e.preventDefault(); toggle(tr); } });
})();

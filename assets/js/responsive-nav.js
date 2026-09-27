(function(){
  function init(){
    var sidebar=document.querySelector('.saas-sidebar,.employee-global-sidebar,.finance-global-sidebar');
    if(!sidebar||document.querySelector('.mobile-menu-toggle'))return;
    var button=document.createElement('button');button.type='button';button.className='mobile-menu-toggle';button.setAttribute('aria-label','Open menu');button.setAttribute('aria-expanded','false');button.textContent='☰';
    var overlay=document.createElement('div');overlay.className='mobile-nav-overlay';
    document.body.append(button,overlay);
    function sync(){button.style.display=window.matchMedia('(max-width: 767px)').matches?'grid':'none';}
    sync();window.addEventListener('resize',sync);
    function close(){sidebar.classList.remove('is-open');overlay.classList.remove('is-open');button.setAttribute('aria-expanded','false');button.textContent='☰';}
    button.addEventListener('click',function(){var open=!sidebar.classList.contains('is-open');sidebar.classList.toggle('is-open',open);overlay.classList.toggle('is-open',open);button.setAttribute('aria-expanded',String(open));button.textContent=open?'×':'☰';});
    overlay.addEventListener('click',close);sidebar.querySelectorAll('a').forEach(function(link){link.addEventListener('click',close);});window.addEventListener('keydown',function(event){if(event.key==='Escape')close();});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
}());

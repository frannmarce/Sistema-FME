const userBtn  = document.getElementById('userBtn');
const userMenu = document.getElementById('userMenu');
if (userBtn && userMenu) {
  userBtn.addEventListener('click', () => {
    const isHidden = userMenu.hasAttribute('hidden');
    if (isHidden) userMenu.removeAttribute('hidden');
    else userMenu.setAttribute('hidden','');
    userBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
  });

  document.addEventListener('click', (e) => {
    if (!userMenu.contains(e.target) && !userBtn.contains(e.target)) {
      userMenu.setAttribute('hidden','');
      userBtn.setAttribute('aria-expanded', 'false');
    }
  });
}

document.querySelectorAll('.card-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const href = btn.dataset.href;
    if(href && href !== '#' && href !== ''){
      window.location.href = href;
    } else {
      alert('Este módulo está en construcción.');
    }
  });
});

document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const href = btn.dataset.href;
    if(href){
      window.location.href = href;
    }
  });
});

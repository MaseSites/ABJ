(() => {
  const progress = document.querySelector('.scroll-progress');
  const update = () => {
    if (!progress) return;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    progress.style.width = max > 0 ? `${(window.scrollY / max) * 100}%` : '0';
  };
  addEventListener('scroll', update, { passive: true });
  update();
})();

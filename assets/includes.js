(() => {
  const load = async (el, url) => {
    const html = await fetch(url).then((r) => r.text());
    el.outerHTML = html;
  };
  document.querySelectorAll('[data-include]').forEach((el) => {
    const name = el.getAttribute('data-include');
    load(el, `partials/${name}.html`);
  });
})();

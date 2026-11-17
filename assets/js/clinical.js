(() => {
  const form = document.getElementById('clinical-item-form');
  if (!form) return;
  const input = form.elements.namedItem('teeth');
  const buttons = [...form.querySelectorAll('[data-tooth]')];
  const selected = () => new Set(input.value.split(/[\s,]+/).filter(Boolean));
  const update = () => { const values = selected(); buttons.forEach(b => b.setAttribute('aria-pressed', String(values.has(b.dataset.tooth)))); };
  buttons.forEach(b => b.addEventListener('click', () => { const values = selected(); const tooth = b.dataset.tooth; values.has(tooth) ? values.delete(tooth) : values.add(tooth); input.value = [...values].sort((a,b) => Number(a)-Number(b)).join(','); update(); }));
  input.addEventListener('input', update); update();
})();

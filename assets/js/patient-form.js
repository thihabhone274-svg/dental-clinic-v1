(() => {
  const form = document.getElementById('patient-form');
  if (!form) return;
  const field = name => form.elements.namedItem(name);
  const mode = field('nrc_mode');
  const digits = value => value.replace(/[၀-၉]/g, d => '၀၁၂၃၄၅၆၇၈၉'.indexOf(d));
  function visibility() {
    form.querySelectorAll('[data-nrc-fields]').forEach(el => { el.hidden = el.dataset.nrcFields !== mode.value; });
    form.querySelector('[data-nationality-other]').hidden = field('nationality_choice').value !== 'OTHER';
  }
  mode.addEventListener('change', visibility);
  field('nationality_choice').addEventListener('change', visibility);
  visibility();
  const region = field('nrc_region'), town = field('nrc_township'), type = field('nrc_type'), serial = field('nrc_serial');
  const search = document.getElementById('nrc-search');
  let data;
  function preview() {
    const number = digits(serial.value.trim());
    document.getElementById('nrc-preview').textContent = region.value && town.value && type.value && /^[0-9]{6}$/.test(number)
      ? `${region.value}/${town.value}(${type.value})${number}` : 'Complete the four fields';
  }
  function options(reset = false) {
    if (!data) return;
    const previous = reset ? '' : town.value;
    const query = search.value.trim().toLocaleLowerCase();
    const items = data.townships[region.value] || {};
    town.replaceChildren(new Option(region.value ? 'Choose township' : 'Choose a region first', ''));
    Object.entries(items).forEach(([code, label]) => {
      if (!query || label.toLocaleLowerCase().includes(query) || code === previous) town.add(new Option(label, code));
    });
    town.value = Object.hasOwn(items, previous) ? previous : '';
    town.disabled = !region.value;
    preview();
  }
  region.addEventListener('change', () => { search.value = ''; options(true); preview(); });
  search.addEventListener('input', () => options());
  [town,type,serial].forEach(el => el.addEventListener('input', preview));
  fetch(form.querySelector('.nrc-picker').dataset.nrcUrl)
    .then(r => { if (!r.ok) throw new Error(); return r.json(); })
    .then(json => { data = json; options(); })
    .catch(() => { search.disabled = true; search.placeholder = 'Filtering unavailable — use township list'; });
  preview();
  const invalid = form.querySelector('[aria-invalid="true"]');
  if (invalid) { invalid.focus(); }
})();

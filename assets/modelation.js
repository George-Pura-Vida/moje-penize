"use strict";
(() => {
  const ENDPOINT = '/api/modelations/calculate.php';
  const pct = value => Number(value) / 100;
  const uid = () => (globalThis.crypto?.randomUUID?.() || `asset_${Date.now()}_${Math.random().toString(16).slice(2)}`);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function assetRow(asset = {}) {
    const id = asset.id || uid();
    return `<fieldset class="card modelation-asset" data-asset-id="${esc(id)}"><legend>Aktivum</legend><div class="fields">
      <label>Název / ID aktiva<input name="asset_id" value="${esc(id)}" required maxlength="120" autocomplete="off"><span class="error field-error" data-error-for="asset_id"></span></label>
      <label>Počáteční vklad (Kč)<input name="initial_contribution" type="number" min="0" step="0.01" value="${asset.initial_contribution ?? 0}" required><span class="error field-error" data-error-for="initial_contribution"></span></label>
      <label>Měsíční vklad (Kč)<input name="monthly_contribution" type="number" min="0" step="0.01" value="${asset.monthly_contribution ?? 1000}" required><span class="error field-error" data-error-for="monthly_contribution"></span></label>
      <label>Roční výnos (%)<input name="annual_return" type="number" step="0.01" value="${asset.annual_return ?? 5}" required><span class="error field-error" data-error-for="annual_return"></span></label>
      <label>Vstupní poplatek z prvního vkladu (%)<input name="entry_fee_initial_pct" type="number" min="0" max="100" step="0.01" value="${asset.entry_fee_initial_pct ?? 0}" required></label>
      <label>Vstupní poplatek z měsíčního vkladu (%)<input name="entry_fee_monthly_pct" type="number" min="0" max="100" step="0.01" value="${asset.entry_fee_monthly_pct ?? 0}" required></label>
      <label>Průběžný poplatek ročně (%)<input name="ongoing_fee_pct_pa" type="number" min="0" max="99.999" step="0.001" value="${asset.ongoing_fee_pct_pa ?? 0}" required></label>
      <label>Fixní poplatek měsíčně (Kč)<input name="fixed_fee_monthly" type="number" min="0" step="0.01" value="${asset.fixed_fee_monthly ?? 0}" required></label>
    </div><button type="button" class="danger small" data-remove-modelation-asset>Odebrat aktivum</button></fieldset>`;
  }

  function page() {
    return `<section class="hero"><p class="eyebrow">CE-1.0.0</p><h1 tabindex="-1">Nová modelace</h1><p>Zadejte parametry modelace. Procenta zadáváte běžně (např. 5 %); před odesláním se převedou na desetinná čísla (0,05).</p></section>
    <form id="modelation-form" novalidate>
      <section class="card"><h2>Základní parametry</h2><div class="fields">
        <label>Horizont (roky)<input name="horizon_years" type="number" min="1" max="100" step="1" value="10" required><span class="error field-error" data-error-for="horizon_years"></span></label>
        <label>Inflace ročně (%)<input name="inflation_rate" type="number" step="0.01" value="2.5" required><span class="error field-error" data-error-for="inflation_rate"></span></label>
        <label>Roky snapshotů <input name="snapshot_years" type="text" value="1,5,10" placeholder="např. 1,5,10"><span class="hint">Celá čísla 1 až horizont, oddělená čárkou.</span><span class="error field-error" data-error-for="snapshot_years"></span></label>
      </div></section>
      <div id="modelation-assets">${assetRow({})}</div>
      <div class="actions"><button type="button" class="secondary" id="add-modelation-asset">Přidat aktivum</button><button type="submit">Připravit modelaci</button></div>
      <p id="modelation-error" class="error" role="alert"></p>
      <section id="modelation-preview" class="card section-heading" aria-live="polite"><h2>Vstup pro CE-1.0.0</h2><p class="muted">Po validaci se zde zobrazí JSON připravený pro POST na <code>${ENDPOINT}</code>. V této fázi se nic na server neodesílá.</p></section>
    </form>`;
  }

  function snapshots(raw, horizon) {
    if (!raw.trim()) return [];
    const values = raw.split(',').map(v => v.trim()).filter(Boolean).map(Number);
    if (values.some(v => !Number.isInteger(v) || v < 1 || v > horizon)) throw new Error('Snapshot musí být celé číslo od 1 do zvoleného horizontu.');
    if (new Set(values).size !== values.length) throw new Error('Roky snapshotů se nesmí opakovat.');
    return values;
  }

  function number(row, name) {
    const value = Number(row.querySelector(`[name="${name}"]`).value);
    if (!Number.isFinite(value)) throw new Error(`Neplatná číselná hodnota: ${name}.`);
    return value;
  }

  function formToCERequest(form) {
    const horizon = Number(form.elements.horizon_years.value);
    if (!Number.isInteger(horizon) || horizon < 1 || horizon > 100) throw new Error('Horizont musí být celé číslo 1 až 100 let.');
    const inflationPct = Number(form.elements.inflation_rate.value);
    if (!Number.isFinite(inflationPct) || inflationPct <= -100) throw new Error('Inflace musí být větší než -100 %.');
    const rows = [...form.querySelectorAll('.modelation-asset')];
    if (!rows.length) throw new Error('Přidejte alespoň jedno aktivum.');
    const assets = rows.map(row => {
      const id = row.querySelector('[name="asset_id"]').value.trim();
      if (!id) throw new Error('Každé aktivum musí mít ID.');
      const annualReturn = number(row, 'annual_return');
      if (annualReturn <= -100) throw new Error(`Roční výnos aktiva ${id} musí být větší než -100 %.`);
      const asset = {
        id,
        initial_contribution: number(row, 'initial_contribution'),
        monthly_contribution: number(row, 'monthly_contribution'),
        annual_return: pct(annualReturn),
        entry_fee_initial_pct: pct(number(row, 'entry_fee_initial_pct')),
        entry_fee_monthly_pct: pct(number(row, 'entry_fee_monthly_pct')),
        ongoing_fee_pct_pa: pct(number(row, 'ongoing_fee_pct_pa')),
        fixed_fee_monthly: number(row, 'fixed_fee_monthly')
      };
      if (asset.initial_contribution < 0 || asset.monthly_contribution < 0 || asset.fixed_fee_monthly < 0) throw new Error(`Vklady a fixní poplatek aktiva ${id} nesmí být záporné.`);
      if (asset.entry_fee_initial_pct < 0 || asset.entry_fee_initial_pct > 1 || asset.entry_fee_monthly_pct < 0 || asset.entry_fee_monthly_pct > 1 || asset.ongoing_fee_pct_pa < 0 || asset.ongoing_fee_pct_pa >= 1) throw new Error(`Procentní poplatky aktiva ${id} jsou mimo povolený rozsah.`);
      return asset;
    });
    const ids = assets.map(a => a.id);
    if (new Set(ids).size !== ids.length) throw new Error('ID aktiv musí být unikátní.');
    return { horizon_years: horizon, inflation_rate: pct(inflationPct), snapshot_years: snapshots(form.elements.snapshot_years.value, horizon), assets };
  }

  function renderModelation() {
    if (location.hash !== '#modelation') return;
    const app = document.getElementById('app');
    app.innerHTML = page();
    document.querySelectorAll('nav a').forEach(a => { a.removeAttribute('aria-current'); if (a.hash === '#modelation') a.setAttribute('aria-current','page'); });
    document.title = 'Nová modelace · Moje peníze';
    app.querySelector('h1')?.focus();
  }

  document.addEventListener('click', event => {
    if (location.hash !== '#modelation') return;
    if (event.target.id === 'add-modelation-asset') document.getElementById('modelation-assets').insertAdjacentHTML('beforeend', assetRow({}));
    if (event.target.matches('[data-remove-modelation-asset]')) {
      const rows = document.querySelectorAll('.modelation-asset');
      if (rows.length === 1) { document.getElementById('modelation-error').textContent = 'Modelace musí obsahovat alespoň jedno aktivum.'; return; }
      event.target.closest('.modelation-asset').remove();
    }
  });

  document.addEventListener('submit', event => {
    if (event.target.id !== 'modelation-form') return;
    event.preventDefault();
    const error = document.getElementById('modelation-error');
    const preview = document.getElementById('modelation-preview');
    error.textContent = '';
    try {
      const request = formToCERequest(event.target);
      preview.innerHTML = `<h2>Vstup připraven</h2><pre>${esc(JSON.stringify(request, null, 2))}</pre><p class="hint">Validní struktura pro CE-1.0.0; další krok je bezpečné napojení POST na <code>${ENDPOINT}</code>.</p>`;
    } catch (e) {
      error.textContent = e.message || 'Modelaci se nepodařilo připravit.';
      preview.innerHTML = '<h2>Vstup pro CE-1.0.0</h2><p class="muted">Nejdřív opravte chyby ve formuláři.</p>';
    }
  });

  addEventListener('hashchange', () => setTimeout(renderModelation, 0));
  addEventListener('DOMContentLoaded', renderModelation);
  globalThis.MojePenizeModelation = { ENDPOINT, formToCERequest };
})();

(function initializeAttendanceExtension() {
  'use strict';

  const SETTINGS_KEY = 'attendanceDefaults';
  const ROOT_ID = 'hwa-oa-attendance-root';
  const LAUNCHER_ID = 'hwa-oa-attendance-launcher';

  function hasAttendanceForm() {
    return Boolean(
      document.querySelector('#btnSave') &&
      document.querySelector('#ddlDate') &&
      document.querySelector('#txtWork')
    );
  }

  function selectedText(select) {
    return select && select.selectedIndex >= 0
      ? select.options[select.selectedIndex].text.trim()
      : '';
  }

  function copyOptions(source, target, desiredValue) {
    target.replaceChildren();
    for (const option of Array.from(source.options)) {
      target.append(new Option(option.text, option.value, false, option.value === desiredValue));
    }
    if (desiredValue && Array.from(target.options).some((option) => option.value === desiredValue)) {
      target.value = desiredValue;
    } else {
      target.value = source.value;
    }
  }

  function dispatchChange(select) {
    select.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function optionsSignature(select) {
    return Array.from(select.options).map((option) => `${option.value}:${option.text}`).join('|');
  }

  async function waitUntil(test, timeout = 6000) {
    const startedAt = Date.now();
    while (Date.now() - startedAt < timeout) {
      if (test()) return true;
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
    return false;
  }

  async function setProvince(value) {
    const province = document.querySelector('#tbUserProvince');
    const city = document.querySelector('#tbUserCity');
    if (!province || !city || !value) return false;
    if (province.value === value) return true;

    const before = optionsSignature(city);
    province.value = value;
    dispatchChange(province);
    await waitUntil(() => optionsSignature(city) !== before && city.options.length > 1);
    return province.value === value;
  }

  async function setLodging(value) {
    const lodging = document.querySelector('#ddlZhushu');
    if (!lodging || !value) return false;
    if (lodging.value !== value) {
      lodging.value = value;
      dispatchChange(lodging);
      await new Promise((resolve) => setTimeout(resolve, 80));
    }
    return lodging.value === value;
  }

  function readProjects() {
    return Array.from(document.querySelectorAll('.proinfo select'))
      .map((select, index) => {
        const suffix = select.id.split('_dropProName_')[1] || String(index + 1);
        const percent = Number(document.querySelector(`#percent_${suffix}`)?.value || 0);
        return { id: select.value, name: selectedText(select), percent };
      })
      .filter((project) => project.id && project.name);
  }

  function validateFixedFields() {
    const date = document.querySelector('#ddlDate');
    const projects = readProjects();
    const totalPercent = projects.reduce((sum, project) => sum + project.percent, 0);

    if (!date?.value) return 'OA 没有可申报的日期。';
    if (selectedText(date).includes('已申报')) return `“${selectedText(date)}”已经申报。`;
    if (!document.querySelector('input[name="status"]:checked')) return 'OA 没有默认工作状态。';
    if (!projects.length) return 'OA 没有默认项目。';
    if (totalPercent !== 100) return `OA 默认项目占比合计为 ${totalPercent}%，不是 100%。`;
    return '';
  }

  function field(labelText, control) {
    const wrapper = document.createElement('label');
    wrapper.className = 'hwa-oa-field';
    const label = document.createElement('span');
    label.textContent = labelText;
    wrapper.append(label, control);
    return wrapper;
  }

  function selectControl(className = '') {
    const select = document.createElement('select');
    select.className = `hwa-oa-select ${className}`.trim();
    return select;
  }

  function removeDialog() {
    document.querySelector(`#${ROOT_ID}`)?.remove();
  }

  async function readSettings() {
    try {
      const result = await chrome.storage.local.get(SETTINGS_KEY);
      return result[SETTINGS_KEY] || {};
    } catch (_) {
      return {};
    }
  }

  async function saveSettings(settings) {
    await chrome.storage.local.set({ [SETTINGS_KEY]: settings });
  }

  async function openDialog() {
    if (!hasAttendanceForm()) return;
    removeDialog();

    const fixedFieldError = validateFixedFields();
    if (fixedFieldError) {
      window.alert(`考勤快捷申报：${fixedFieldError}`);
      return;
    }

    const settings = await readSettings();
    const pageProvince = document.querySelector('#tbUserProvince');
    const pageCity = document.querySelector('#tbUserCity');
    const pageLodging = document.querySelector('#ddlZhushu');
    const pageDorm = document.querySelector('#ddlsushe');

    if (settings.province && settings.province !== pageProvince.value) {
      await setProvince(settings.province);
    }
    if (settings.city && Array.from(pageCity.options).some((option) => option.value === settings.city)) {
      pageCity.value = settings.city;
      dispatchChange(pageCity);
    }
    if (settings.lodging) await setLodging(settings.lodging);
    if (settings.dorm && Array.from(pageDorm.options).some((option) => option.value === settings.dorm)) {
      pageDorm.value = settings.dorm;
      dispatchChange(pageDorm);
    }

    const root = document.createElement('div');
    root.id = ROOT_ID;
    root.innerHTML = `
      <div class="hwa-oa-backdrop"></div>
      <section class="hwa-oa-dialog" role="dialog" aria-modal="true" aria-labelledby="hwa-oa-title">
        <header class="hwa-oa-header">
          <div>
            <p>${selectedText(document.querySelector('#ddlDate'))}</p>
            <h2 id="hwa-oa-title">一键报考勤</h2>
          </div>
          <button class="hwa-oa-close" type="button" aria-label="关闭">×</button>
        </header>
        <div class="hwa-oa-body"></div>
        <footer class="hwa-oa-footer">
          <button class="hwa-oa-cancel" type="button">取消</button>
          <button class="hwa-oa-submit" type="button">保存考勤</button>
        </footer>
      </section>
    `;

    const provinceInput = selectControl();
    const cityInput = selectControl();
    const regionRow = document.createElement('div');
    regionRow.className = 'hwa-oa-control-row';
    copyOptions(pageProvince, provinceInput, pageProvince.value);
    copyOptions(pageCity, cityInput, pageCity.value);
    regionRow.append(provinceInput, cityInput);

    const workInput = document.createElement('textarea');
    workInput.className = 'hwa-oa-textarea';
    workInput.rows = 4;
    workInput.placeholder = '填写今天完成的工作内容';
    workInput.value = document.querySelector('#txtWork').value.trim();

    const lodgingInput = selectControl();
    const dormInput = selectControl();
    const lodgingRow = document.createElement('div');
    lodgingRow.className = 'hwa-oa-control-row';
    copyOptions(pageLodging, lodgingInput, pageLodging.value);
    copyOptions(pageDorm, dormInput, pageDorm.value);
    lodgingRow.append(lodgingInput, dormInput);

    const hint = document.createElement('p');
    hint.className = 'hwa-oa-hint';
    const projects = readProjects().map((project) => `${project.name} ${project.percent}%`).join('、');
    hint.textContent = `项目和工作状态沿用 OA 默认值：${projects}`;

    const error = document.createElement('p');
    error.className = 'hwa-oa-error';
    error.hidden = true;

    const body = root.querySelector('.hwa-oa-body');
    body.append(
      field('地区', regionRow),
      field('简述工作概要（必填）', workInput),
      field('住宿地点', lodgingRow),
      hint,
      error
    );
    document.body.append(root);
    workInput.focus();

    const close = () => removeDialog();
    root.querySelector('.hwa-oa-close').addEventListener('click', close);
    root.querySelector('.hwa-oa-cancel').addEventListener('click', close);
    root.querySelector('.hwa-oa-backdrop').addEventListener('click', close);
    root.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });

    provinceInput.addEventListener('change', async () => {
      cityInput.disabled = true;
      cityInput.replaceChildren(new Option('正在加载地市…', ''));
      await setProvince(provinceInput.value);
      copyOptions(pageCity, cityInput, pageCity.value);
      cityInput.disabled = false;
    });

    lodgingInput.addEventListener('change', async () => {
      await setLodging(lodgingInput.value);
      copyOptions(pageDorm, dormInput, pageDorm.value);
      const isDorm = lodgingInput.value === '158';
      dormInput.disabled = !isDorm;
      dormInput.style.display = isDorm ? '' : 'none';
    });

    const isDorm = lodgingInput.value === '158';
    dormInput.disabled = !isDorm;
    dormInput.style.display = isDorm ? '' : 'none';

    root.querySelector('.hwa-oa-submit').addEventListener('click', async () => {
      const submitButton = root.querySelector('.hwa-oa-submit');
      const work = workInput.value.trim();
      error.hidden = true;

      if (!provinceInput.value || !cityInput.value) {
        error.textContent = '请选择完整的地区。';
        error.hidden = false;
        return;
      }
      if (!work) {
        error.textContent = '请填写工作概要。';
        error.hidden = false;
        workInput.focus();
        return;
      }
      if (!lodgingInput.value || lodgingInput.value === '-1') {
        error.textContent = '请选择住宿地点。';
        error.hidden = false;
        return;
      }
      if (lodgingInput.value === '158' && (!dormInput.value || dormInput.value === '-2')) {
        error.textContent = '请选择具体宿舍。';
        error.hidden = false;
        return;
      }

      submitButton.disabled = true;
      submitButton.textContent = '正在保存…';

      await setProvince(provinceInput.value);
      pageCity.value = cityInput.value;
      dispatchChange(pageCity);
      await setLodging(lodgingInput.value);
      if (lodgingInput.value === '158') {
        pageDorm.value = dormInput.value;
        dispatchChange(pageDorm);
      }

      const pageWork = document.querySelector('#txtWork');
      pageWork.value = work;
      pageWork.dispatchEvent(new Event('input', { bubbles: true }));
      pageWork.dispatchEvent(new Event('change', { bubbles: true }));

      await saveSettings({
        province: provinceInput.value,
        city: cityInput.value,
        lodging: lodgingInput.value,
        dorm: lodgingInput.value === '158' ? dormInput.value : ''
      });

      const latestError = validateFixedFields();
      if (latestError) {
        submitButton.disabled = false;
        submitButton.textContent = '保存考勤';
        error.textContent = latestError;
        error.hidden = false;
        return;
      }

      removeDialog();
      document.querySelector('#btnSave').click();
    });
  }

  function addLauncher() {
    if (!hasAttendanceForm() || document.querySelector(`#${LAUNCHER_ID}`)) return;
    const launcher = document.createElement('button');
    launcher.id = LAUNCHER_ID;
    launcher.type = 'button';
    launcher.textContent = '一键报考勤';
    launcher.title = '只填写地区、工作概要和住宿地点';
    launcher.addEventListener('click', openDialog);
    document.body.append(launcher);
  }

  window.addEventListener('hwa-oa-attendance-open', openDialog);
  addLauncher();
})();

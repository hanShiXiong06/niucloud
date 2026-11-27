/**
 * 华网 OA 考勤快捷申报（书签脚本源码）
 *
 * 用法：
 * 1. 在浏览器中打开 OA，并进入“考勤录入”页面。
 * 2. 通过 scripts/oa-attendance-bookmarklet.html 安装书签按钮。
 * 3. 点击书签，填写工作概要，核对信息后确认保存。
 *
 * 设计说明：
 * - 不读取、保存或传出 Cookie。
 * - 不重新实现服务器协议，而是调用 OA 页面已有的“保存”按钮，继续使用
 *   网站原有的表单校验和 POST /Attendance/Create/ 提交逻辑。
 * - 脚本不会自动切换日期、项目、地区或住宿信息，避免误报。
 */
(function quickSubmitAttendance() {
  'use strict';

  function isVisible(element) {
    return Boolean(
      element &&
      (element.offsetWidth || element.offsetHeight || element.getClientRects().length)
    );
  }

  function findAttendanceWindow(currentWindow, visited) {
    if (!currentWindow || visited.has(currentWindow)) return null;
    visited.add(currentWindow);

    try {
      const currentDocument = currentWindow.document;
      if (
        currentDocument.querySelector('#btnSave') &&
        currentDocument.querySelector('#ddlDate') &&
        currentDocument.querySelector('#txtWork')
      ) {
        return currentWindow;
      }

      const frames = Array.from(currentDocument.querySelectorAll('iframe'))
        .filter(isVisible)
        .concat(Array.from(currentDocument.querySelectorAll('iframe')).filter((frame) => !isVisible(frame)));

      for (const frame of frames) {
        try {
          const found = findAttendanceWindow(frame.contentWindow, visited);
          if (found) return found;
        } catch (_) {
          // 跨域 iframe 无法读取时直接跳过。
        }
      }
    } catch (_) {
      // 当前窗口不可读时直接跳过。
    }

    return null;
  }

  function selectedText(select) {
    return select && select.selectedIndex >= 0
      ? select.options[select.selectedIndex].text.trim()
      : '';
  }

  function readProjects(document) {
    return Array.from(document.querySelectorAll('.proinfo select'))
      .map((select, index) => {
        const suffix = select.id.split('_dropProName_')[1] || String(index + 1);
        const percent = document.querySelector(`#percent_${suffix}`);
        return {
          name: selectedText(select),
          percent: percent ? percent.value : ''
        };
      })
      .filter((project) => project.name);
  }

  function fail(message) {
    window.alert(`考勤快捷申报：${message}`);
  }

  const attendanceWindow = findAttendanceWindow(window, new Set());
  if (!attendanceWindow) {
    fail('没有找到“考勤录入”表单。请先在 OA 中打开考勤申报，再点击此书签。');
    return;
  }

  const document = attendanceWindow.document;
  const dateSelect = document.querySelector('#ddlDate');
  const workInput = document.querySelector('#txtWork');
  const memoInput = document.querySelector('#txtmemo');
  const provinceSelect = document.querySelector('#tbUserProvince');
  const citySelect = document.querySelector('#tbUserCity');
  const lodgingSelect = document.querySelector('#ddlZhushu');
  const dormSelect = document.querySelector('#ddlsushe');
  const statusInput = document.querySelector('input[name="status"]:checked');
  const saveButton = document.querySelector('#btnSave');

  const dateLabel = selectedText(dateSelect);
  if (!dateSelect || !dateSelect.value) {
    fail('请先选择申报日期。');
    return;
  }
  if (dateLabel.includes('已申报')) {
    fail(`“${dateLabel}”已经申报，脚本已停止。`);
    return;
  }
  if (!statusInput) {
    fail('页面没有选中工作状态。');
    return;
  }

  const projects = readProjects(document);
  const totalPercent = projects.reduce(
    (sum, project) => sum + Number(project.percent || 0),
    0
  );
  if (!projects.length || totalPercent !== 100) {
    fail(`项目时间占比合计为 ${totalPercent}%，请先在页面中调整为 100%。`);
    return;
  }

  const savedWork = window.localStorage.getItem('oa-attendance-last-work') || '';
  const initialWork = workInput.value.trim() || savedWork;
  const work = window.prompt('请输入本次考勤的工作概要：', initialWork);
  if (work === null) return;
  if (!work.trim()) {
    fail('工作概要不能为空。');
    return;
  }

  const memo = window.prompt('备注（可留空）：', memoInput ? memoInput.value : '');
  if (memo === null) return;

  const projectSummary = projects
    .map((project) => `${project.name} ${project.percent}%`)
    .join('、');
  const place = [selectedText(provinceSelect), selectedText(citySelect)]
    .filter(Boolean)
    .join(' / ');
  const lodging = [selectedText(lodgingSelect), selectedText(dormSelect)]
    .filter(Boolean)
    .join(' / ');
  const statusLabel = document.querySelector(`label[for="${statusInput.id}"]`);
  const status = statusLabel ? statusLabel.textContent.trim() : statusInput.value;

  const confirmation = [
    '即将写入一条考勤记录：',
    '',
    `日期：${dateLabel}`,
    `工作状态：${status}`,
    `工作概要：${work.trim()}`,
    `项目：${projectSummary}`,
    `地区：${place || '未读取到'}`,
    `住宿：${lodging || '未读取到'}`,
    `备注：${memo.trim() || '无'}`,
    '',
    '确认后将调用 OA 原有“保存”功能。'
  ].join('\n');

  if (!window.confirm(confirmation)) return;

  workInput.value = work.trim();
  workInput.dispatchEvent(new Event('input', { bubbles: true }));
  workInput.dispatchEvent(new Event('change', { bubbles: true }));
  if (memoInput) {
    memoInput.value = memo.trim();
    memoInput.dispatchEvent(new Event('input', { bubbles: true }));
    memoInput.dispatchEvent(new Event('change', { bubbles: true }));
  }

  window.localStorage.setItem('oa-attendance-last-work', work.trim());
  saveButton.click();
})();

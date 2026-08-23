const OA_HOST = 'oa.huawangsoft.com';

chrome.action.onClicked.addListener(async (tab) => {
  if (!tab.id || !tab.url) return;

  let isOaPage = false;
  try {
    isOaPage = new URL(tab.url).hostname === OA_HOST;
  } catch (_) {
    return;
  }

  if (!isOaPage) return;

  try {
    const results = await chrome.scripting.executeScript({
      target: { tabId: tab.id, allFrames: true },
      func: () => {
        const hasAttendanceForm = Boolean(
          document.querySelector('#btnSave') &&
          document.querySelector('#ddlDate') &&
          document.querySelector('#txtWork')
        );

        if (hasAttendanceForm) {
          const launcher = document.querySelector('#hwa-oa-attendance-launcher');
          if (launcher) {
            launcher.click();
          } else {
            window.dispatchEvent(new CustomEvent('hwa-oa-attendance-open'));
          }
        }

        return hasAttendanceForm;
      }
    });

    if (!results.some((result) => result.result === true)) {
      await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        func: () => {
          window.alert('请先进入“个人办公 → 考勤申报 → 考勤录入”，再点击扩展图标。');
        }
      });
    }
  } catch (error) {
    console.error('无法打开考勤快捷申报：', error);
  }
});

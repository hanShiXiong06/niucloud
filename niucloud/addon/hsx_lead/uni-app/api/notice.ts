import request from '@/utils/request'

const root = 'hsx_lead/notice'
const quiet = { showErrorMessage: false }
export const getAccountNoticePreferences = () => request.get(`${root}/preferences`, {}, quiet)
export const saveAccountNoticePreferences = (preferences: { event_key: string; channel: string; enabled: boolean }[]) =>
    request.put(`${root}/preferences`, { preferences }, quiet)
// A user's explicit native acceptance wakes only matching pending tasks. This is not an authorization credential.
export const reportAccountNoticeAuthorization = (event_keys: string[]) =>
    request.post(`${root}/authorization`, { event_keys }, quiet)

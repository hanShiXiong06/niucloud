import request from '@/utils/request'

export const readOpeningCheck = (connection_key: string) => request.get('boyuan/opening-check', { params: { connection_key } })
export const previewOpening = (connection_key: string, resource: string, cursor: number) =>
    request.post('boyuan/opening-preview', { connection_key, resource, cursor })
export const applyOpening = (connection_key: string, resource: string, cursor: number, token: string) =>
    request.post('boyuan/opening-apply', { connection_key, resource, cursor, token, confirm: true })

export type AccountChoice = { id: number; local_id: number; type: string }
const accountResource = 'capital_accounts'
export const readAccountOptions = (connection_key: string, cursor: number) =>
    request.get('boyuan/opening-check', { params: { connection_key, resource: accountResource, cursor } })
export const previewAccounts = (connection_key: string, choices: AccountChoice[]) =>
    request.post('boyuan/opening-preview', { connection_key, resource: accountResource, choices })
export const applyAccounts = (connection_key: string, choices: AccountChoice[], token: string) =>
    request.post('boyuan/opening-apply', { connection_key, resource: accountResource, choices, token, confirm: true })

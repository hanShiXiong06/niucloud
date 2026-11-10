import type { Row } from './useLeadUi'

// These are value semantics, not merely allowed keyword spellings. Never let
// server metadata widen a field to a variable whose value WeChat cannot accept.
export const NOTICE_FIELD_PATTERN = /^(thing|character_string|time|date|phrase|number)([1-9]\d{0,2})$/
const safeVariables = ['task_no', 'status_text', 'occurred_at', 'notice_remark', 'demand_summary', 'task_id']
const variablesByType: Record<string, readonly string[]> = {
    thing: safeVariables,
    character_string: ['task_no', 'task_id'],
    phrase: ['status_text'],
    number: ['task_id'],
    time: ['occurred_at'],
    date: ['occurred_at']
}

export function noticeVariableOptions(keyword: unknown, variables: Row[]): Row[] {
    const type = String(keyword || '').match(NOTICE_FIELD_PATTERN)?.[1]
    if (!type) return []
    return variables.filter(variable => {
        if (!variablesByType[type].includes(variable.key)) return false
        // Missing metadata uses the same conservative local contract. Explicit
        // metadata may narrow it; malformed or empty metadata grants nothing.
        return !Object.prototype.hasOwnProperty.call(variable, 'field_types')
            || (Array.isArray(variable.field_types) && variable.field_types.includes(type))
    })
}

export function suggestNoticeVariable(field: Row, variables: Row[]): string {
    const type = String(field.keyword || '').match(NOTICE_FIELD_PATTERN)?.[1], name = String(field.name || field.label || '')
    let candidate = ''
    if (type === 'number') candidate = 'task_id'
    else if (type === 'date' || type === 'time' || /时间|日期/.test(name)) candidate = 'occurred_at'
    else if (/编号|单号/.test(name)) candidate = 'task_no'
    else if (/状态|进度/.test(name)) candidate = 'status_text'
    else if (/需求|项目|内容/.test(name)) candidate = 'demand_summary'
    else if (/提示|备注|说明|结果/.test(name)) candidate = 'notice_remark'
    return noticeVariableOptions(field.keyword, variables).some(variable => variable.key === candidate) ? candidate : ''
}

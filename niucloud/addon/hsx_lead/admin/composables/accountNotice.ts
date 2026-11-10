export type NoticeRow = Record<string, any>;
export const accountNoticeEvents: Record<string, string> = { account_balance_changed: '余额变动', account_money_changed: '可提现余额变动', account_commission_changed: '佣金变动', cooperation_acceptance_reminder: '接单倒计时提醒', cooperation_response_overdue: '门店尚未接单提醒', cooperation_assignment_accepted: '门店接单成功提醒' };
export const accountNoticeChannels: Record<string, string> = { weapp: '微信小程序', wechat: '微信公众号', sms: '短信' };
export const accountNoticeStatuses: Record<string, string> = { pending: '等待发送', dispatching: '提交中，待核实', accepted: '渠道已受理', unknown: '结果待核实', pending_configuration: '待配置模板', pending_recipient: '接收人待绑定', pending_authorization: '待用户订阅', paused: '规则或偏好已关闭', retry_wait: '等待自动重试', rejected: '渠道明确拒绝', expired: '已过期', exhausted: '已达尝试上限', obsolete: '交付已变更' };
export const accountNoticeVariables: Record<string, string> = { account_name: '账户类型', change_direction: '变动方向', change_amount: '变动金额', account_balance: '变动后余额', occurred_at: '变动时间', record_no: '账目编号' };
export const cooperationNoticeVariables: Record<string, string> = { task_no: '线索编号', status_text: '处理状态', notice_remark: '操作提示', occurred_at: '发生时间', response_deadline: '接单提醒时间', remaining_minutes: '剩余分钟', store_name: '相关门店' };
export const ACCOUNT_NOTICE_FIELD_PATTERN = /^(thing|character_string|time|date|phrase|number)([1-9]\d{0,2})$/;
export function noticeVariablesForEvent(event?: NoticeRow): Record<string, string> {
    return event?.variables || (String(event?.event_key || '').startsWith('cooperation_') ? {} : accountNoticeVariables);
}
export function accountVariableOptions(keyword: unknown, variables: NoticeRow[], event?: NoticeRow): NoticeRow[] {
    const type = String(keyword || '').match(/^(thing|character_string|time|date|phrase|number|keyword)([1-9]\d{0,2})$/)?.[1];
    if (!type)
        return [];
    const catalog = noticeVariablesForEvent(event);
    const allowed = type === 'number' ? ['remaining_minutes'] : ['time', 'date'].includes(type) ? ['occurred_at', 'response_deadline'] : type === 'phrase' ? ['change_direction', 'status_text'] : type === 'character_string' ? ['record_no', 'task_no'] : Object.keys(catalog);
    const narrowed = event?.field_types ? event.field_types[type] || (type === 'keyword' ? event.field_types.thing : []) : allowed;
    return variables.filter(item => allowed.includes(item.key) && Object.prototype.hasOwnProperty.call(catalog, item.key) && Array.isArray(narrowed) && narrowed.includes(item.key));
}
export function suggestAccountVariable(field: NoticeRow, variables: NoticeRow[], event?: NoticeRow): string {
    const label = String(field.name || field.label || ''), keyword = String(field.keyword || '');
    const cooperation = String(event?.event_key || '').startsWith('cooperation_');
    const candidate = /余额|结余/.test(label) ? 'account_balance' : /金额|收入|支出/.test(label) ? 'change_amount' : /剩余|分钟/.test(label) ? 'remaining_minutes' : /截止|提醒时间/.test(label) ? 'response_deadline' : /时间|日期/.test(label) ? 'occurred_at' : /编号|流水号/.test(label) ? cooperation ? 'task_no' : 'record_no' : /门店|商家/.test(label) ? 'store_name' : /状态|进度/.test(label) ? 'status_text' : /备注|提示|说明/.test(label) ? 'notice_remark' : /账户|类型/.test(label) ? 'account_name' : /方向/.test(label) ? 'change_direction' : '';
    return accountVariableOptions(keyword, variables, event).some(item => item.key === candidate) ? candidate : '';
}
export function normalizeResponseReminderMinutes(value: unknown): number[] {
    if (!Array.isArray(value) || value.length > 8 || value.some(item => !['number', 'string'].includes(typeof item) || !/^\d{1,4}$/.test(String(item)) || !Number.isInteger(Number(item)) || Number(item) < 1 || Number(item) > 1440)) throw Error('接单提醒节点请设置1—1440分钟内的整数，最多8项');
    return [...new Set(value.map(Number))].sort((left, right) => right - left);
}
export function parseAccountNoticeSettings(value: any): NoticeRow {
    if (!value || typeof value.enabled !== 'boolean' || typeof value.installed !== 'boolean' || !Number.isInteger(value.ttl_seconds) || value.ttl_seconds < 300 || value.ttl_seconds > 86400 || !Number.isInteger(value.max_attempts) || value.max_attempts < 1 || value.max_attempts > 5 || !Array.isArray(value.events) || !value.events.length || value.events.length > 32)
        throw Error('通知规则返回不完整，请核对配套后端后重新读取');
    const seen = new Set<string>();
    for (const event of value.events) {
        if (!event || !/^[a-z][a-z0-9_]{0,63}$/.test(event.event_key) || seen.has(event.event_key) || typeof event.name !== 'string' || !event.name.trim() || typeof event.enabled !== 'boolean' || !event.channels || !event.readiness)
            throw Error('通知场景返回不完整，请重新读取');
        if (String(event.event_key).startsWith('cooperation_') && (!event.variables || !event.field_types)) throw Error('协作通知变量未完整返回，请重新读取');
        if (event.variables && (typeof event.variables !== 'object' || Array.isArray(event.variables) || !Object.keys(event.variables).length || Object.entries(event.variables).some(([key,label]) => !Object.prototype.hasOwnProperty.call({ ...accountNoticeVariables, ...cooperationNoticeVariables },key) || typeof label !== 'string' || !label.trim()))) throw Error('通知变量目录格式不完整');
        if (event.field_types && (typeof event.field_types !== 'object' || Array.isArray(event.field_types) || Object.values(event.field_types).some(types => !Array.isArray(types) || types.some(key => !Object.prototype.hasOwnProperty.call(event.variables || accountNoticeVariables,key))))) throw Error('通知字段类型目录格式不完整');
        seen.add(event.event_key);
        for (const channel of Object.keys(accountNoticeChannels))
            if (typeof event.channels[channel] !== 'boolean' || !['ready', 'disabled', 'pending_configuration'].includes(event.readiness[channel]?.status))
                throw Error('通知渠道状态返回不完整，请重新读取');
    }
    if (value.events.some((event: NoticeRow) => String(event.event_key).startsWith('cooperation_'))) normalizeResponseReminderMinutes(value.response_reminder_minutes);
    return JSON.parse(JSON.stringify(value));
}
export function accountTemplatePayload(channel: string, draft: NoticeRow): NoticeRow {
    const fields = (draft.fields || []).map((field: NoticeRow) => ({ keyword: String(field.keyword || '').trim(), variable: String(field.variable || ''), label: String(field.label || '').trim() }));
    const id = String(channel === 'weapp' ? draft.tid || '' : draft.temp_key || '').trim();
    if (!id)
        return {};
    return channel === 'weapp' ? { tid: id, fields, scene_desc: String(draft.scene_desc || '').trim(), kid_list: fields.map((field: NoticeRow) => Number(field.keyword.match(/(\d+)$/)?.[1])), confirmed: draft.confirmed === true } : { temp_key: id, fields, confirmed: draft.confirmed === true };
}
export function accountTemplateError(channel: string, draft: NoticeRow, event?: NoticeRow): string {
    const data = accountTemplatePayload(channel, draft), fields = data.fields || [];
    if (!Object.keys(data).length)
        return (draft.fields || []).length || String(draft.scene_desc || '').trim() ? '模板编号为空；请填写真实编号，或使用“清空当前模板草稿”明确移除' : '';
    if (!(channel === 'weapp' ? /^[1-9]\d{0,19}$/ : /^[A-Za-z0-9_-]{1,64}$/).test(String(data.tid || data.temp_key)))
        return channel === 'weapp' ? '请填写真实公共模板编号，不是小程序的长模板 ID' : '请填写本站公众号实际模板编号';
    if (fields.length < 1 || fields.length > 10)
        return '请核对官方模板的 1—10 个真实字段';
    const kids = new Set<number>(), vars = new Set<string>(), catalog = Object.entries(noticeVariablesForEvent(event)).map(([key, label]) => ({ key, label }));
    for (const field of fields) {
        const matched = field.keyword.match(channel === 'weapp' ? ACCOUNT_NOTICE_FIELD_PATTERN : /^(thing|character_string|time|date|phrase|number|keyword)([1-9]\d{0,2})$/);
        if (!matched || !field.label || field.label.length > 30 || kids.has(Number(matched[2])) || vars.has(field.variable) || !accountVariableOptions(field.keyword, catalog, event).some(item => item.key === field.variable))
            return '字段格式、类型或通知变量不匹配；每个关键词编号和变量只能使用一次';
        kids.add(Number(matched[2]));
        vars.add(field.variable);
    }
    if (channel === 'weapp' && (!data.scene_desc || data.scene_desc.length > 100))
        return '请填写已核验的订阅场景（最多100字）';
    return data.confirmed ? '' : '请先核对本站官方模板与字段，再勾选确认';
}
export function accountRetryAllowed(row: NoticeRow): boolean {
    return row.can_retry === true && !['accepted', 'unknown', 'dispatching', 'expired', 'obsolete'].includes(String(row.status));
}
export const accountNoticeStatusTone = (status: string): 'success' | 'warning' | 'danger' | 'info' => status === 'accepted' ? 'success' : ['rejected', 'exhausted'].includes(status) ? 'danger' : ['unknown', 'dispatching', 'pending_authorization', 'pending_configuration', 'pending_recipient'].includes(status) ? 'warning' : 'info';

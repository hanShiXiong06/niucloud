// 查询失败与“查询成功但无人参与”必须分开，不能用空数组掩盖后端未更新。
type DataObject = Record<string, any>
const object = (value: any): value is DataObject => value !== null && typeof value === 'object' && !Array.isArray(value)
const count = (value: any): boolean => (typeof value === 'number' || typeof value === 'string') && String(value).trim() !== '' && Number.isFinite(Number(value)) && Number(value) >= 0
const recordPage = (value: any): boolean => object(value) && Array.isArray(value.data) && value.data.every(object) && count(value.total)
const deploymentHint = '请确认营销插件后端已更新、PHP 缓存已刷新，并检查请求中的 section 参数。'

function responseData(response: any): DataObject {
    if (!object(response) || !object(response.data)) throw new Error(`参与查询未返回有效数据。${deploymentHint}`)
    return response.data
}

export function checkParticipationList(response: any): void {
    const data = responseData(response)
    if ('id' in data && 'title' in data && !('records' in data)) {
        throw new Error(`接口返回了活动配置，不是参与记录。${deploymentHint}`)
    }
    const valid = object(data.campaign) && object(data.summary) && recordPage(data.records)
        && ['record_count', 'member_count', 'reached_count', 'attention_count'].every(key => count(data.summary[key]))
        && object(data.claim_statuses) && object(data.reward_statuses)
        && data.records.data.every((row: DataObject) => object(row.rewards) && Array.isArray(row.rewards.statuses) && row.rewards.statuses.every(object))
    if (!valid) throw new Error(`参与记录返回不完整，暂时无法展示查询结果。${deploymentHint}`)
}

export function checkParticipationDetail(response: any): void {
    const data = responseData(response)
    if (!object(data.campaign) || !object(data.claim) || !recordPage(data.facts) || !recordPage(data.rewards)) {
        throw new Error(`参与明细返回不完整，暂时无法展示查询结果。${deploymentHint}`)
    }
}

const HOME = 'DIY_HSX_LEAD_INDEX',
    BLUE_WHITE = 'hsx_lead_index'
type PresetApi = {
    getDecoratePage: (value: any) => Promise<any>
    getDiyPageInfo: (id: number) => Promise<any>
    getDiyTemplatePages: (value: any) => Promise<any>
    copyDiy: (value: any) => Promise<any>
    editDiyPage: (value: any) => Promise<any>
}
type PresetContext = {
    assertCurrent: () => void
    stage?: (text: string) => void
    backup?: (id: number) => void
    beforeWrite?: (id: number, value: string) => void
}
const idOf = (value: unknown) => (Number.isSafeInteger(Number(value)) && Number(value) > 0 ? Number(value) : 0)
export function diyPageSource(value: unknown): string {
    let data: any = value
    try {
        if (typeof value === 'string') data = JSON.parse(value)
    } catch {
        throw Error('当前首页数据无法读取，未执行替换')
    }
    if (
        !data ||
        typeof data !== 'object' ||
        Array.isArray(data) ||
        !data.global ||
        typeof data.global !== 'object' ||
        !Array.isArray(data.value)
    )
        throw Error('当前首页结构不完整，未执行替换')
    return JSON.stringify(data)
}
/** Orchestrates existing framework APIs. Never changes the active page selection or deletes a source/backup. */
export async function applyBlueWhiteHome(api: PresetApi, context: PresetContext) {
    const step = (text: string) => {
        context.assertCurrent()
        context.stage?.(text)
    }
    const read = async (method: keyof PresetApi, value: any) => {
        context.assertCurrent()
        const res = await api[method](value)
        context.assertCurrent()
        if (res?.code !== undefined && res.code !== 1) throw Error(res.msg || '框架操作未成功，请核对')
        return res?.data
    }
    step('正在读取当前首页')
    const active = await read('getDecoratePage', { type: HOME }),
        id = idOf(active?.use_template?.id)
    if (!id) throw Error('当前还没有已保存首页，请先在装修器保存，再使用备份应用')
    const current = await read('getDiyPageInfo', id)
    if (idOf(current?.id) !== id || current.name !== HOME || current.type !== HOME || Number(current.is_default) !== 1)
        throw Error('当前使用的不是本插件已保存首页，未执行替换')
    const original = diyPageSource(current.value)
    const templates = await read('getDiyTemplatePages', { type: HOME, mode: 'diy' }),
        preset = templates?.[BLUE_WHITE],
        next = diyPageSource(preset?.data)
    const pieces = preset?.data?.value || []
    if (
        preset?.mode !== 'diy' ||
        pieces.map((row: any) => row.componentName).join(',') !==
            'LeadCooperationHero,LeadRoleEntry,LeadRoleEntry,LeadBusinessNavigation' ||
        pieces[1]?.role !== 'stores' ||
        pieces[2]?.role !== 'customers'
    )
        throw Error('蓝白独立卡片模板尚未同步，请先更新源码与装修字典')
    step('正在备份当前首页')
    const copied = await read('copyDiy', { id }),
        backupId = idOf(copied?.id)
    if (!backupId || backupId === id) throw Error('未取得独立备份编号，未执行替换')
    const backup = await read('getDiyPageInfo', backupId)
    if (
        idOf(backup?.id) !== backupId ||
        Number(backup.is_default) !== 0 ||
        backup.name !== current.name ||
        backup.type !== current.type ||
        diyPageSource(backup.value) !== original
    )
        throw Error(`备份#${backupId}尚未通过回读核对，未执行替换`)
    context.backup?.(backupId)
    step('备份已核对，正在复核当前首页')
    const activeAfter = await read('getDecoratePage', { type: HOME }),
        latest = await read('getDiyPageInfo', id)
    if (
        idOf(activeAfter?.use_template?.id) !== id ||
        Number(latest.is_default) !== 1 ||
        diyPageSource(latest.value) !== original
    )
        throw Error('当前首页已被其他操作修改，已保留备份，未执行替换')
    step('正在应用蓝白独立卡片预设')
    const payload = {
        id,
        name: current.name,
        page_title: current.page_title,
        title: preset.data.global.title || current.title,
        template: BLUE_WHITE,
        mode: current.mode || 'diy',
        is_change: 1,
        value: next
    }
    context.beforeWrite?.(id, next)
    await read('editDiyPage', payload)
    step('正在核对保存结果')
    const saved = await read('getDiyPageInfo', id),
        savedActive = await read('getDecoratePage', { type: HOME })
    if (
        idOf(savedActive?.use_template?.id) !== id ||
        idOf(saved?.id) !== id ||
        saved.name !== HOME ||
        saved.type !== HOME ||
        Number(saved.is_default) !== 1 ||
        saved.template !== BLUE_WHITE ||
        diyPageSource(saved.value) !== next
    )
        throw Error('保存结果尚未核对，请先查看当前首页；原备份仍保留，不要重复覆盖')
    return { id, backupId, value: next }
}

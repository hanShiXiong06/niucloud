/**
 * 串号/设备全链路追踪 —— 全局命令式唤起。
 *
 * 设计目标: 任意组件里一行代码即可弹出"设备全链路"抽屉, 且【完全不入侵框架】——
 *   - 不改 App.vue / layout / main.ts / 路由;
 *   - 全部代码只在 addon/hsx_erp 内;
 *   - 复用主应用的 appContext(ElementPlus、Pinia、全局指令/组件都在), 抽屉挂到 body。
 *
 * 用法(在任意组件的 setup 中):
 *   import { useDeviceTrace } from '@/addon/hsx_erp/composables/useDeviceTrace'
 *   const { openTrace } = useDeviceTrace()
 *   openTrace({ deviceId })      // 或 { assetId }
 *
 * 说明: trace-detail.vue 本就是 v-model 的 el-drawer, 按 assetId/deviceId 在 @open 时拉详情;
 *       这里只是把它"挂一次、全局复用", 不修改该组件本身。
 */
import { reactive, h, render, createVNode, nextTick, getCurrentInstance, type AppContext } from 'vue'
import TraceDetail from '@/addon/hsx_erp/views/device_trace/trace-detail.vue'

interface OpenParams {
    assetId?: number
    deviceId?: number
}

// 单例状态(整个前端共享一个抽屉实例)
const state = reactive<{ visible: boolean; assetId: number; deviceId: number }>({
    visible: false,
    assetId: 0,
    deviceId: 0,
})

let mounted = false
let appContext: AppContext | null = null

// 宿主组件: 把单例状态绑到 trace-detail; trace-detail 自身 append-to-body, 不占当前页面 DOM 树
const TraceHost = {
    name: 'ErpDeviceTraceHost',
    setup() {
        return () =>
            h(TraceDetail, {
                modelValue: state.visible,
                assetId: state.assetId || undefined,
                deviceId: state.deviceId || undefined,
                'onUpdate:modelValue': (v: boolean) => {
                    state.visible = v
                },
            })
    },
}

function ensureMounted() {
    if (mounted) return
    const container = document.createElement('div')
    container.setAttribute('data-erp-device-trace-host', '')
    document.body.appendChild(container)
    const vnode = createVNode(TraceHost)
    // 复用主应用上下文, 否则 ElementPlus / Pinia 解析不到
    if (appContext) vnode.appContext = appContext
    render(vnode, container)
    mounted = true
}

async function show(params: OpenParams) {
    ensureMounted()
    const assetId = Number(params.assetId) || 0
    const deviceId = Number(params.deviceId) || 0
    // 已打开且换了设备: 先关再开, 触发 @open 重新拉取详情
    if (state.visible && (assetId !== state.assetId || deviceId !== state.deviceId)) {
        state.visible = false
        await nextTick()
    }
    state.assetId = assetId
    state.deviceId = deviceId
    state.visible = true
}

export function useDeviceTrace() {
    // 捕获一次主应用上下文(在组件 setup 内调用即可拿到, 不需要碰框架入口)
    const inst = getCurrentInstance()
    if (inst && !appContext) appContext = inst.appContext

    return {
        /** 弹出设备全链路抽屉。传 assetId 或 deviceId 之一 */
        openTrace: (params: OpenParams = {}) => show(params),
        /** 关闭抽屉 */
        closeTrace: () => {
            state.visible = false
        },
    }
}

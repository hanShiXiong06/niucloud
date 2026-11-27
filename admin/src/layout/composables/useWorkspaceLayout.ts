import { onBeforeUnmount } from 'vue'
import useSystemStore from '@/stores/modules/system'

export type WorkspaceSidebarMode = 'expanded' | 'collapsed'

/**
 * 页面级工作区布局控制。
 *
 * 页面只声明自己需要的侧栏模式，不直接修改全局宽度；组件卸载时会自动释放，
 * 避免一个数据大屏离开后仍把其他页面锁在紧凑模式。
 */
export function useWorkspaceLayout(source: string) {
    const systemStore = useSystemStore()

    const requestSidebar = (mode: WorkspaceSidebarMode) => {
        systemStore.requestWorkspaceSidebar(source, mode)
    }

    const releaseSidebar = () => {
        systemStore.releaseWorkspaceSidebar(source)
    }

    onBeforeUnmount(releaseSidebar)

    return {
        requestSidebar,
        releaseSidebar
    }
}

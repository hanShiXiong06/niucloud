import { ref } from 'vue'
import type { Ref } from 'vue'

/**
 * 通用「提交守卫」——对齐 admin/src/addon/hsx_recycle/hooks/useSubmit.ts。
 *
 * 设计目标：
 * 1. 防重复提交：提交进行中再次触发会被忽略，避免慢网络下连点造成重复打款 / 重复入库。
 * 2. 统一加载态：暴露 loading ref，供按钮绑定 :loading / :disabled。
 * 3. 不重复弹错：请求层(utils/request.ts)已对业务错误(code!=1)与网络异常自动 toast，
 *    这里默认不再二次弹错，避免「真实错误 + 通用错误」两个提示同时出现。
 *
 * @example
 * const submit = useRecycleSubmit()
 * const onConfirm = () => submit.run(async () => {
 *   await api.save(form)
 *   close()
 * }, { success: '保存成功' })
 * // 模板：<u-button :loading="submit.loading" @click="onConfirm">保存</u-button>
 */

export interface RunOptions {
  /** 成功后的提示文案，传入则在成功后弹一次 success toast。 */
  success?: string
  /**
   * 兜底错误文案。仅当 task 抛出的不是被请求层处理过的业务/网络错误时才使用。
   * 绝大多数场景无需传入——请求层已经弹过真实错误信息。
   */
  errorMessage?: string
  /** 是否把原始错误继续抛出（默认 false：吞掉，仅返回 undefined）。 */
  rethrow?: boolean
}

export interface UseRecycleSubmit {
  /** 提交进行中状态，可直接绑定到按钮 :loading / :disabled。 */
  loading: Ref<boolean>
  /** 包裹一次异步提交，自带防重入 + 加载态 + 成功提示。 */
  run: <T>(task: () => Promise<T>, options?: RunOptions) => Promise<T | undefined>
}

/** 创建一个提交守卫。可传入外部 loading ref 以复用既有状态。 */
export function useRecycleSubmit(externalLoading?: Ref<boolean>): UseRecycleSubmit {
  const loading = externalLoading ?? ref(false)

  async function run<T>(task: () => Promise<T>, options: RunOptions = {}): Promise<T | undefined> {
    if (loading.value) return undefined // 防重入：上一次还没结束，忽略本次触发
    loading.value = true
    try {
      const result = await task()
      if (options.success) {
        uni.showToast({ title: options.success, icon: 'success' })
      }
      return result
    } catch (error: any) {
      // 请求层已弹过错误；仅在调用方显式要求兜底时再提示
      if (options.errorMessage) {
        uni.showToast({ title: options.errorMessage, icon: 'none' })
      }
      if (options.rethrow) throw error
      return undefined
    } finally {
      loading.value = false
    }
  }

  return { loading, run }
}

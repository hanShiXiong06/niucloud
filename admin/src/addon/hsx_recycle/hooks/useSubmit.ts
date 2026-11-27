import { ref } from 'vue'
import type { Ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'

/**
 * 通用「提交守卫」与「危险操作确认」工具。
 *
 * 设计目标：
 * 1. 防重复提交：提交进行中再次触发会被忽略，避免慢网络下连点造成重复打款 / 重复入库。
 * 2. 统一加载态：暴露 loading ref，供按钮绑定 :loading / :disabled。
 * 3. 不重复弹错：请求拦截器(utils/request.ts)已对业务错误(code!=1)与网络异常自动弹出提示，
 *    这里默认不再二次弹错，避免「真实错误 + 通用错误」两个 toast 同时出现。
 */

/** 用户主动取消弹窗（ElMessageBox cancel/close）时的标识，不应按错误处理。 */
export function isDialogCanceled(error: any): boolean {
  return (
    error === 'cancel' ||
    error === 'close' ||
    error?.action === 'cancel' ||
    error?.action === 'close'
  )
}

export interface RunOptions {
  /** 成功后的提示文案，传入则在成功后弹一次 success。 */
  success?: string
  /**
   * 兜底错误文案。仅当 task 抛出的不是被拦截器处理过的业务/网络错误时才使用。
   * 绝大多数场景无需传入——拦截器已经弹过真实错误信息。
   */
  errorMessage?: string
  /** 是否把原始错误继续抛出（默认 false：吞掉，仅返回 undefined）。 */
  rethrow?: boolean
}

export interface UseSubmit {
  /** 提交进行中状态，可直接绑定到按钮 :loading / :disabled。 */
  loading: Ref<boolean>
  /** 包裹一次异步提交，自带防重入 + 加载态 + 成功提示。 */
  run: <T>(task: () => Promise<T>, options?: RunOptions) => Promise<T | undefined>
}

/**
 * 创建一个提交守卫。可传入外部 loading ref 以复用既有状态。
 *
 * @example
 * const submit = useSubmit()
 * const onConfirm = () => submit.run(async () => {
 *   await api.save(form)
 *   close()
 * }, { success: '保存成功' })
 * // 模板：<el-button :loading="submit.loading" @click="onConfirm">保存</el-button>
 */
export function useSubmit(externalLoading?: Ref<boolean>): UseSubmit {
  const loading = externalLoading ?? ref(false)

  async function run<T>(task: () => Promise<T>, options: RunOptions = {}): Promise<T | undefined> {
    if (loading.value) return undefined // 防重入：上一次还没结束，忽略本次触发
    loading.value = true
    try {
      const result = await task()
      if (options.success) ElMessage.success(options.success)
      return result
    } catch (error: any) {
      if (isDialogCanceled(error)) return undefined
      // 拦截器已弹过错误；仅在调用方显式要求兜底时再提示
      if (options.errorMessage) ElMessage.error(options.errorMessage)
      if (options.rethrow) throw error
      return undefined
    } finally {
      loading.value = false
    }
  }

  return { loading, run }
}

export interface ConfirmDangerOptions {
  title?: string
  confirmText?: string
  cancelText?: string
  /** 消息是否按 HTML 渲染，便于高亮金额 / 客户名等关键信息。 */
  html?: boolean
}

/**
 * 危险/不可逆操作的统一二次确认。返回用户是否确认。
 *
 * @example
 * if (!(await confirmDanger(`将打款 <b>¥${amount}</b> 给客户「${name}」，不可撤销。`,
 *   { title: '确认打款', confirmText: '确认打款', html: true }))) return
 */
export async function confirmDanger(message: string, options: ConfirmDangerOptions = {}): Promise<boolean> {
  try {
    await ElMessageBox.confirm(message, options.title ?? '操作确认', {
      confirmButtonText: options.confirmText ?? '确定',
      cancelButtonText: options.cancelText ?? '取消',
      type: 'warning',
      dangerouslyUseHTMLString: options.html ?? false
    })
    return true
  } catch {
    return false // 取消或关闭
  }
}

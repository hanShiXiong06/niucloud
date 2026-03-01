import { computed, ref, type Ref } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/utils/request'

interface UseCameraUploadOptions {
  checkImages: Ref<string>
  maxCheckImageCount?: number
}

export function useCameraUpload(options: UseCameraUploadOptions) {
  const { checkImages, maxCheckImageCount = 6 } = options

  const cameraUploading = ref(false)
  const cameraInputRef = ref<HTMLInputElement | null>(null)

  const getCheckImages = (): string[] => {
    return (checkImages.value || '')
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean)
  }

  const setCheckImages = (images: string[]) => {
    checkImages.value = images.join(',')
  }

  const checkImageCount = computed(() => getCheckImages().length)

  const uploadSingleImage = async (file: File): Promise<string> => {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('cate_id', '0')

    const response: any = await request.post('sys/image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    const imageUrl = response?.data?.url
    if (!imageUrl) throw new Error('上传成功但未返回图片地址')
    return imageUrl
  }

  const openCameraCapture = () => {
    if (cameraUploading.value) return
    if (checkImageCount.value >= maxCheckImageCount) {
      ElMessage.warning(`最多上传${maxCheckImageCount}张质检图片`)
      return
    }
    cameraInputRef.value?.click()
  }

  const handleCameraFilesChange = async (event: Event) => {
    const input = event.target as HTMLInputElement
    const files = input.files ? Array.from(input.files) : []
    if (!files.length) return

    const remainCount = maxCheckImageCount - getCheckImages().length
    if (remainCount <= 0) {
      ElMessage.warning(`最多上传${maxCheckImageCount}张质检图片`)
      input.value = ''
      return
    }

    const filesToUpload = files.slice(0, remainCount)
    cameraUploading.value = true
    try {
      const newUrls: string[] = []
      for (const file of filesToUpload) {
        const imageUrl = await uploadSingleImage(file)
        newUrls.push(imageUrl)
      }

      if (newUrls.length) {
        setCheckImages([...getCheckImages(), ...newUrls].slice(0, maxCheckImageCount))
        ElMessage.success(`成功上传${newUrls.length}张图片`)
      }

      if (files.length > filesToUpload.length) {
        ElMessage.warning(`超出上限，已忽略${files.length - filesToUpload.length}张图片`)
      }
    } catch (error: any) {
      console.error('拍照上传失败:', error)
      ElMessage.error(error?.message || '拍照上传失败，请重试')
    } finally {
      cameraUploading.value = false
      input.value = ''
    }
  }

  return {
    cameraUploading,
    cameraInputRef,
    maxCheckImageCount,
    checkImageCount,
    openCameraCapture,
    handleCameraFilesChange
  }
}

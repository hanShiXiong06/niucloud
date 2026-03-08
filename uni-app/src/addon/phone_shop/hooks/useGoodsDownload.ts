import { useDownloadConfig, type DownloadConfig } from './useDownloadConfig'

/**
 * 商品图片下载 Hook
 * 支持 Android/iOS 权限处理、加载进度提示、下载配置管理
 * 仅限 phone_shop 插件使用
 */
export function useGoodsDownload() {
    const { getStoredConfig, saveConfig, buildShareText } = useDownloadConfig()

    /**
     * 请求相册权限（Android 需要）
     */
    const requestAlbumPermission = (): Promise<boolean> => {
        return new Promise((resolve) => {
            // #ifdef APP-PLUS
            const platform = uni.getSystemInfoSync().platform
            if (platform === 'android') {
                // Android 需要请求存储权限
                uni.authorize({
                    scope: 'scope.writePhotosAlbum',
                    success: () => resolve(true),
                    fail: () => {
                        // 权限被拒绝，引导用户打开设置
                        uni.showModal({
                            title: '需要相册权限',
                            content: '需要您授权相册权限才能保存图片',
                            confirmText: '去设置',
                            success: (res) => {
                                if (res.confirm) {
                                    uni.openSetting({
                                        success: (settingRes) => {
                                            resolve(settingRes.authSetting['scope.writePhotosAlbum'] === true)
                                        },
                                        fail: () => resolve(false)
                                    })
                                } else {
                                    resolve(false)
                                }
                            }
                        })
                    }
                })
            } else {
                // iOS 直接返回 true，会在保存时自动请求权限
                resolve(true)
            }
            // #endif

            // #ifndef APP-PLUS
            // 小程序环境
            uni.authorize({
                scope: 'scope.writePhotosAlbum',
                success: () => resolve(true),
                fail: () => {
                    uni.showModal({
                        title: '需要相册权限',
                        content: '需要您授权相册权限才能保存图片',
                        confirmText: '去设置',
                        success: (res) => {
                            if (res.confirm) {
                                uni.openSetting({
                                    success: (settingRes) => {
                                        resolve(settingRes.authSetting['scope.writePhotosAlbum'] === true)
                                    },
                                    fail: () => resolve(false)
                                })
                            } else {
                                resolve(false)
                            }
                        }
                    })
                }
            })
            // #endif
        })
    }

    /**
     * 下载单张图片
     * @param url 图片 URL（下载原图）
     * @param onProgress 进度回调
     */
    const downloadSingleImage = (
        url: string,
        onProgress?: (progress: number) => void
    ): Promise<string> => {
        return new Promise((resolve, reject) => {
            console.log('开始下载图片:', url)

            // 从URL提取扩展名
            let ext = '.jpg' // 默认扩展名
            const urlMatch = url.match(/\.(jpg|jpeg|png|gif|webp)(\?|$)/i)
            if (urlMatch) {
                ext = '.' + urlMatch[1].toLowerCase()
                if (ext === '.jpeg') ext = '.jpg'
            }
            console.log('从URL提取的扩展名:', ext)

            // 生成带扩展名的临时文件名
            const timestamp = Date.now()
            const random = Math.floor(Math.random() * 10000)
            const fileName = `temp_${timestamp}_${random}${ext}`

            // 使用 wx.env.USER_DATA_PATH（小程序用户数据目录）
            // #ifdef MP-WEIXIN
            const filePath = `${wx.env.USER_DATA_PATH}/${fileName}`
            console.log('目标文件路径:', filePath)
            // #endif

            // #ifndef MP-WEIXIN
            const filePath = fileName
            // #endif

            // 下载图片到指定路径
            const downloadTask = uni.downloadFile({
                url,
                filePath: filePath,
                success: (res) => {
                    console.log('下载成功 statusCode:', res.statusCode)
                    console.log('响应头 Content-Type:', res.header?.['content-type'] || res.header?.['Content-Type'])
                    console.log('响应头 Content-Disposition:', res.header?.['content-disposition'] || res.header?.['Content-Disposition'])

                    if (res.statusCode === 200) {
                        // 当指定了 filePath 时，返回的 tempFilePath 可能是 undefined
                        // 在这种情况下，文件已保存到我们指定的 filePath
                        let finalPath = res.tempFilePath

                        if (!finalPath) {
                            // 如果 tempFilePath 为空，使用我们指定的 filePath
                            finalPath = filePath
                            console.log('tempFilePath为空，使用指定的filePath:', finalPath)
                        } else {
                            console.log('使用返回的tempFilePath:', finalPath)
                        }

                        // 验证文件路径
                        if (!finalPath || finalPath === 'undefined') {
                            reject(new Error('文件路径无效'))
                            return
                        }

                        console.log('最终文件路径:', finalPath)
                        resolve(finalPath)
                    } else {
                        const error = new Error(`下载失败: HTTP ${res.statusCode}`)
                        console.error(error)
                        reject(error)
                    }
                },
                fail: (err) => {
                    console.error('下载失败:', err)
                    reject(err)
                }
            })

            // 监听下载进度
            if (onProgress) {
                downloadTask.onProgressUpdate((res) => {
                    onProgress(res.progress)
                })
            }
        })
    }

    /**
     * 保存图片到相册
     */
    const saveToAlbum = (filePath: string): Promise<void> => {
        return new Promise((resolve, reject) => {
            console.log('准备保存图片到相册:', filePath)
            uni.saveImageToPhotosAlbum({
                filePath,
                success: () => {
                    console.log('图片保存成功')
                    resolve()
                },
                fail: (err) => {
                    console.error('保存到相册失败:', err)
                    reject(err)
                }
            })
        })
    }

    /**
     * 下载多张图片并保存到相册
     * @param urls 图片 URL 数组
     * @param onProgress 总体进度回调 (当前索引, 总数)
     */
    const downloadImages = async (
        urls: string[],
        onProgress?: (current: number, total: number) => void
    ): Promise<boolean> => {
        try {
            // 检查权限
            const hasPermission = await requestAlbumPermission()
            if (!hasPermission) {
                uni.showToast({ title: '未获得相册权限', icon: 'none' })
                return false
            }

            const total = urls.length
            let successCount = 0
            let firstError: any = null

            // 显示加载提示
            uni.showLoading({
                title: `下载中 0/${total}`,
                mask: true
            })

            // 逐个下载并保存
            for (let i = 0; i < urls.length; i++) {
                try {
                    console.log(`开始下载第 ${i + 1}/${total} 张图片:`, urls[i])
                    const tempFilePath = await downloadSingleImage(urls[i])
                    console.log(`图片 ${i + 1} 下载成功，开始保存到相册`)
                    await saveToAlbum(tempFilePath)
                    console.log(`图片 ${i + 1} 保存成功`)
                    successCount++

                    // 更新进度
                    if (onProgress) {
                        onProgress(i + 1, total)
                    }

                    uni.showLoading({
                        title: `下载中 ${i + 1}/${total}`,
                        mask: true
                    })
                } catch (error) {
                    console.error(`图片 ${i + 1} 下载失败:`, error)
                    // 保存第一个错误信息
                    if (!firstError) {
                        firstError = error
                    }
                }
            }

            uni.hideLoading()

            if (successCount === total) {
                return true
            } else if (successCount > 0) {
                uni.showToast({
                    title: `成功保存 ${successCount}/${total} 张图片`,
                    icon: 'none',
                    duration: 2000
                })
                return true
            } else {
                // 所有图片都失败，显示详细错误信息
                console.error('所有图片下载失败，第一个错误:', firstError)

                // 根据错误类型给出更具体的提示
                let errorMsg = '下载失败'
                if (firstError) {
                    const errMsg = firstError.errMsg || firstError.message || ''
                    if (errMsg.includes('domain')) {
                        errorMsg = '下载失败：请在小程序后台配置下载域名'
                    } else if (errMsg.includes('https')) {
                        errorMsg = '下载失败：图片地址必须是HTTPS'
                    } else if (errMsg.includes('timeout')) {
                        errorMsg = '下载失败：网络超时'
                    } else if (errMsg.includes('fail')) {
                        errorMsg = `下载失败：${errMsg}`
                    }
                }

                throw new Error(errorMsg)
            }
        } catch (error) {
            uni.hideLoading()
            console.error('下载失败:', error)
            return false
        }
    }

    /**
     * 复制文案到剪贴板
     */
    const copyText = (text: string): Promise<boolean> => {
        return new Promise((resolve) => {
            console.log('准备复制文案:', text)
            uni.setClipboardData({
                data: text,
                success: () => {
                    console.log('文案复制成功')
                    resolve(true)
                },
                fail: (err) => {
                    console.error('文案复制失败:', err)
                    resolve(false)
                }
            })
        })
    }

    /**
     * 下载商品图片并复制文案（简化版，直接传入文案）
     * @param images 图片 URL 数组
     * @param copyContent 要复制的文案
     * @param showToast 是否显示提示
     */
    const downloadGoodsImages = async (
        images: string[],
        copyContent: string,
        showToast: boolean = true
    ): Promise<boolean> => {
        try {
            // 下载图片
            const downloadSuccess = await downloadImages(images)

            if (!downloadSuccess) {
                if (showToast) {
                    uni.showToast({ title: '图片下载失败', icon: 'none' })
                }
                return false
            }

            // 复制文案
            const copySuccess = await copyText(copyContent)

            if (showToast) {
                if (copySuccess) {
                    uni.showToast({
                        title: '图片下载及文案复制成功',
                        icon: 'success',
                        duration: 2000
                    })
                } else {
                    uni.showToast({
                        title: '图片已保存，文案复制失败',
                        icon: 'none',
                        duration: 2000
                    })
                }
            }

            return true
        } catch (error) {
            console.error('下载失败:', error)
            if (showToast) {
                uni.showToast({ title: '下载失败', icon: 'none' })
            }
            return false
        }
    }

    /**
     * 下载商品图片并复制文案（带配置支持）
     * @param images 图片 URL 数组
     * @param item 商品数据
     * @param config 下载配置（可选，不传则使用存储的配置）
     * @param userId 用户ID（可选，用于生成分享链接）
     * @param showToast 是否显示提示
     */
    const downloadGoodsImagesWithConfig = async (
        images: string[],
        item: any,
        config?: DownloadConfig,
        userId?: string | number,
        showToast: boolean = true
    ): Promise<boolean> => {
        try {
            // 如果没有传入配置，使用存储的配置
            const finalConfig = config || getStoredConfig()

            // 构建文案（根据配置）- 注意这里是异步的
            const copyContent = await buildShareText(item, finalConfig, userId)

            // 下载图片
            const downloadSuccess = await downloadImages(images)

            if (!downloadSuccess) {
                if (showToast) {
                    uni.showToast({ title: '图片下载失败', icon: 'none' })
                }
                return false
            }

            // 复制文案
            const copySuccess = await copyText(copyContent)

            if (showToast) {
                if (copySuccess) {
                    uni.showToast({
                        title: '图片下载及文案复制成功',
                        icon: 'success',
                        duration: 2000
                    })
                } else {
                    uni.showToast({
                        title: '图片已保存，文案复制失败',
                        icon: 'none',
                        duration: 2000
                    })
                }
            }

            return true
        } catch (error: any) {
            console.error('下载失败:', error)
            if (showToast) {
                const errorMsg = error.message || '下载失败'
                uni.showToast({ title: errorMsg, icon: 'none', duration: 3000 })
            }
            return false
        }
    }

    /**
     * 检查是否需要显示配置弹窗
     */
    const needShowConfigDialog = (): boolean => {
        const config = getStoredConfig()
        return !config.configured
    }

    return {
        requestAlbumPermission,
        downloadSingleImage,
        downloadImages,
        copyText,
        downloadGoodsImages,
        downloadGoodsImagesWithConfig,
        needShowConfigDialog,
        getStoredConfig,
        saveConfig
    }
}

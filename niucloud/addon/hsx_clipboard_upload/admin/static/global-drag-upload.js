
import { getToken, isUrl } from '@/utils/common'
import storage from '@/utils/storage'

/**
 * 全局拖拽上传功能
 * 可以在任何页面引入使用
 */
(function(window) {
    'use strict';

    // 全局拖拽上传类

    class GlobalDragUpload {
        constructor(options = {}) {
            this.isUploading = false;
            this.uploadProgress = 0;
            this.currentFileName = '';
            this.isDragActive = false; // 是否正在拖拽
            this.dragCounter = 0; // 拖拽计数器，防止频繁触发
            
            // 默认配置
            this.config = {
                baseURL: '',
                imgDomain: '',
                headers: {},
                debug: false, // 禁用调试模式
                // 拖拽相关配置
                enableFullScreenDrop: true, // 是否启用全屏拖拽
                allowedTypes: [
                    // 图片类型
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    // 视频类型
                    'video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo', 
                    'video/x-matroska', 'video/webm', 'video/3gpp', 'video/x-flv'
                ], // 允许的文件类型
                maxImageFileSize: 10 * 1024 * 1024, // 图片最大文件大小 10MB
                maxVideoFileSize: 50 * 1024 * 1024, // 视频最大文件大小 50MB (降低限制避免413错误)
                maxFiles: 5, // 最大文件数量
                ...options // 合并用户配置
            };
            
            // 定时器
            this.timers = {
                dragLeave: null,
                autoHide: null
            };
            
            // DOM 元素
            this.elements = {};
            
            this.init();
        }

        // 调试日志输出 (复用剪贴板组件的日志方法)
        log(message, type = 'info', data = null) {
            if (!this.config.debug) return;
            
            const timestamp = new Date().toLocaleTimeString();
            const prefix = `[全局拖拽上传 ${timestamp}]`;
            
            switch (type) {
                case 'success':
                    console.log(`%c${prefix} ✅ ${message}`, 'color: #10b981; font-weight: bold;', data || '');
                    break;
                case 'error':
                    console.error(`%c${prefix} ❌ ${message}`, 'color: #ef4444; font-weight: bold;', data || '');
                    break;
                case 'warning':
                    console.warn(`%c${prefix} ⚠️ ${message}`, 'color: #f59e0b; font-weight: bold;', data || '');
                    break;
                case 'debug':
                    console.log(`%c${prefix} 🔍 ${message}`, 'color: #8b5cf6; font-weight: bold;', data || '');
                    break;
                case 'event':
                    console.log(`%c${prefix} 🎯 ${message}`, 'color: #06b6d4; font-weight: bold;', data || '');
                    break;
                default:
                    console.log(`%c${prefix} 💡 ${message}`, 'color: #3b82f6; font-weight: bold;', data || '');
            }
        }

        // 初始化
        init() {
            this.log('开始初始化拖拽上传组件', 'info');
            this.initConfig();
            this.createUI();
            this.setupEventListeners();
            // this.showWelcomeMessage();
            this.log('拖拽上传组件初始化完成', 'success');

        }

        // 初始化配置 (复用剪贴板组件的配置逻辑)
        initConfig() {
            // 尝试获取正确的API地址
            let baseURL = '';
            
            // 方法1：尝试从环境变量获取（Vite）
            try {
                if (typeof window !== 'undefined' && window.import && window.import.meta && window.import.meta.env) {
                    baseURL = window.import.meta.env.VITE_APP_BASE_URL || '';
                    this.log('从Vite环境变量获取API地址', 'debug', { baseURL });
                }
            } catch (e) {
                // 忽略错误
            }
            
            // 方法2：尝试从全局变量获取
            if (!baseURL && window.VITE_APP_BASE_URL) {
                baseURL = window.VITE_APP_BASE_URL;
                this.log('从全局变量获取API地址', 'debug', { baseURL });
            }
            
            // 方法3：根据当前URL智能判断
            if (!baseURL) {
                const protocol = window.location.protocol;
                const hostname = window.location.hostname;
                
                if (window.location.port && ['5173', '3000', '8080', '8000'].includes(window.location.port)) {
                    baseURL = `${protocol}//${hostname}/adminapi/`;
                } else {
                    baseURL = `${protocol}//${window.location.host}/adminapi/`;
                }
            }
            
            // 确保API地址以/结尾
            if (baseURL && !baseURL.endsWith('/')) {
                baseURL += '/';
            }
            
            this.config.baseURL = baseURL;
            
            // 设置图片域名
            let imgDomain = '';
            try {
                if (typeof window !== 'undefined' && window.import && window.import.meta && window.import.meta.env) {
                    imgDomain = window.import.meta.env.VITE_IMG_DOMAIN || '';
                }
            } catch (e) {
                // 忽略错误
            }
            
            if (!imgDomain && window.VITE_IMG_DOMAIN) {
                imgDomain = window.VITE_IMG_DOMAIN;
            }
            if (!imgDomain) {
                const protocol = window.location.protocol;
                const hostname = window.location.hostname;
                imgDomain = window.location.port && ['5173', '3000', '8080', '8000'].includes(window.location.port)
                    ? `${protocol}//${hostname}/`
                    : `${protocol}//${window.location.host}/`;
            }
            this.config.imgDomain = imgDomain;
            
            this.config.headers = {
                'token': getToken(),
                'Site-Id': storage.get('siteId') || 0
            };
            
            this.log('配置初始化完成', 'success', this.config);
        }

        // 创建UI
        createUI() {
            this.log('开始创建UI组件', 'debug');
            
            // 创建容器
            const container = document.createElement('div');
            container.id = 'global-drag-upload';
            container.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                pointer-events: none;
                z-index: 99999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            `;
            
            document.body.appendChild(container);
            this.elements.container = container;
            
            this.log('UI容器创建完成', 'success');
        }

        // 获取认证token
        getAuthToken() {
            return getToken();
        }

        // 获取站点ID
        getSiteId() {
            return storage.get('siteId') || 0;
        }

        // 设置事件监听器
        setupEventListeners() {
            this.log('开始设置拖拽事件监听器', 'debug');
            
            // 阻止默认的拖拽行为
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                document.addEventListener(eventName, this.preventDefaults.bind(this), false);
            });
            
            // 拖拽进入
            document.addEventListener('dragenter', this.handleDragEnter.bind(this), false);
            
            // 拖拽悬停
            document.addEventListener('dragover', this.handleDragOver.bind(this), false);
            
            // 拖拽离开
            document.addEventListener('dragleave', this.handleDragLeave.bind(this), false);
            
            // 文件放置
            document.addEventListener('drop', this.handleDrop.bind(this), false);
            
            this.log('拖拽事件监听器设置完成', 'success');
        }

        // 阻止默认行为
        preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // 处理拖拽进入
        handleDragEnter(e) {
            this.dragCounter++;
            
            // 检查是否包含文件
            if (this.hasFiles(e)) {
                this.log('检测到拖拽文件进入', 'event');
                this.showDragOverlay();
            }
        }

        // 处理拖拽悬停
        handleDragOver(e) {
            if (this.hasFiles(e)) {
                this.isDragActive = true;
                // 设置拖拽效果
                e.dataTransfer.dropEffect = 'copy';
            }
        }

        // 处理拖拽离开
        handleDragLeave(e) {
            this.dragCounter--;
            
            // 清除定时器
            if (this.timers.dragLeave) {
                clearTimeout(this.timers.dragLeave);
            }
            
            // 延迟隐藏，避免快速进出时闪烁
            this.timers.dragLeave = setTimeout(() => {
                if (this.dragCounter <= 0) {
                    this.log('拖拽离开页面', 'event');
                    this.hideDragOverlay();
                    this.isDragActive = false;
                    this.dragCounter = 0;
                }
            }, 50);
        }

        // 处理文件放置
        handleDrop(e) {
            this.log('文件放置事件触发', 'event');
            
            this.dragCounter = 0;
            this.isDragActive = false;
            this.hideDragOverlay();
            
            const files = Array.from(e.dataTransfer.files);
            this.log('放置的文件数量：' + files.length, 'info', files.map(f => ({ name: f.name, type: f.type, size: f.size })));
            
            if (files.length > 0) {
                this.handleFiles(files);
            }
        }

        // 检查是否包含文件
        hasFiles(e) {
            if (!e.dataTransfer) return false;
            if (!e.dataTransfer.types) return false;
            
            return e.dataTransfer.types.includes('Files');
        }

        // 显示拖拽覆盖层
        showDragOverlay() {
            if (this.elements.dragOverlay) return; // 避免重复创建
            
            this.log('显示拖拽覆盖层', 'debug');
            
            const overlay = document.createElement('div');
            overlay.className = 'drag-upload-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(59, 130, 246, 0.1);
                backdrop-filter: blur(2px);
                z-index: 99998;
                pointer-events: none;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.2s ease;
            `;
            
            overlay.innerHTML = `
                <div style="
                    background: rgba(59, 130, 246, 0.95);
                    color: white;
                    padding: 40px;
                    border-radius: 20px;
                    text-align: center;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
                    transform: scale(1.1);
                    animation: bounceIn 0.3s ease;
                ">
                    <div style="font-size: 48px; margin-bottom: 16px;">📁</div>
                    <div style="font-size: 20px; font-weight: 600; margin-bottom: 8px;">释放文件开始上传</div>
                    <div style="font-size: 14px; opacity: 0.9;">支持拖拽图片和视频文件</div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            this.elements.dragOverlay = overlay;
        }

        // 隐藏拖拽覆盖层
        hideDragOverlay() {
            if (this.elements.dragOverlay) {
                this.log('隐藏拖拽覆盖层', 'debug');
                this.elements.dragOverlay.remove();
                this.elements.dragOverlay = null;
            }
        }

        // 处理文件
        handleFiles(files) {
            this.log('开始处理文件', 'info', { fileCount: files.length });
            
            // 过滤和验证文件
            const validFiles = this.validateFiles(files);
            
            if (validFiles.length === 0) {
                this.createResultModal('error', '没有有效文件', '请拖拽图片文件（支持 JPG、PNG、GIF、WebP）');
                return;
            }
            
            // 检查认证状态
            if (!this.checkAuthStatus()) {
                return;
            }
            
            // 开始上传
            this.uploadFiles(validFiles);
        }

        // 验证文件
        validateFiles(files) {
            const validFiles = [];
            const errors = [];
            
            for (const file of files) {
                // 检查文件类型
                if (!this.config.allowedTypes.includes(file.type)) {
                    errors.push(`${file.name}: 不支持的文件类型 (${file.type})`);
                    continue;
                }
                
                // 根据文件类型检查文件大小
                const isVideo = file.type.startsWith('video/');
                const maxSize = isVideo ? this.config.maxVideoFileSize : this.config.maxImageFileSize;
                
                if (file.size > maxSize) {
                    const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                    const maxSizeInMB = (maxSize / (1024 * 1024)).toFixed(2);
                    const fileType = isVideo ? '视频' : '图片';
                    errors.push(`${file.name}: ${fileType}文件过大 (${sizeInMB}MB > ${maxSizeInMB}MB)`);
                    continue;
                }
                
                validFiles.push(file);
            }
            
            // 检查文件数量
            if (validFiles.length > this.config.maxFiles) {
                errors.push(`一次最多只能上传 ${this.config.maxFiles} 个文件`);
                validFiles.splice(this.config.maxFiles);
            }
            
            // 显示错误信息
            if (errors.length > 0) {
                this.createResultModal('warning', '部分文件无法上传', errors.join('<br>'));
            }
            
            this.log('文件验证完成', 'success', { 
                totalFiles: files.length, 
                validFiles: validFiles.length, 
                errors: errors.length 
            });
            
            return validFiles;
        }

        // 检查认证状态 (复用剪贴板组件的方法)
        checkAuthStatus() {
            const token = this.getAuthToken();
            const siteId = this.getSiteId();
            
            if (!token) {
                this.createResultModal('warning', '需要登录', '请先登录系统后再使用拖拽上传功能');
                return false;
            }
            
            return true;
        }

        // 上传文件
        async uploadFiles(files) {
            this.log('开始批量上传文件', 'info', { fileCount: files.length });
            this.isUploading = true;
            
            // 创建批量上传进度模态框
            const progressModal = this.createBatchProgressModal(files);
            
            const results = [];
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                this.log(`开始上传第 ${i + 1} 个文件: ${file.name}`, 'info');
                
                try {
                    const result = await this.uploadSingleFile(file, i, files.length, progressModal);
                    results.push({ file, result, success: true });
                    this.log(`文件 ${file.name} 上传成功`, 'success');
                } catch (error) {
                    results.push({ file, error, success: false });
                    this.log(`文件 ${file.name} 上传失败: ${error.message}`, 'error');
                }
            }
            
            // 移除进度模态框
            progressModal.remove();
            
            // 显示批量上传结果
            this.showBatchUploadResults(results);
            
            this.isUploading = false;
            this.log('批量上传完成', 'success');
        }

        // 创建批量上传进度模态框
        createBatchProgressModal(files) {
            this.log('创建批量上传进度模态框', 'debug');
            
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
                min-width: 400px;
                max-width: 500px;
                pointer-events: auto;
                z-index: 100001;
                animation: slideIn 0.3s ease;
                text-align: center;
            `;
            
            modal.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 20px;">
                    <span style="font-size: 28px;">📤</span>
                    <span style="font-size: 18px; font-weight: 600; color: #1f2937;">批量上传进度</span>
                </div>
                <div id="batch-upload-info" style="font-size: 14px; color: #6b7280; margin-bottom: 16px;">
                    准备上传 ${files.length} 个文件...
                </div>
                <div style="width: 100%; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
                    <div id="batch-progress-fill" style="height: 100%; background: linear-gradient(90deg, #3b82f6, #10b981); transition: width 0.3s ease; width: 0%;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #6b7280;">
                    <span id="batch-progress-text">0%</span>
                    <span id="batch-current-file">等待开始...</span>
                </div>
                <div id="batch-file-list" style="margin-top: 16px; max-height: 200px; overflow-y: auto; text-align: left;">
                    ${files.map((file, index) => `
                        <div id="file-item-${index}" style="display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 12px; color: #6b7280;">
                            <span class="file-status" style="width: 16px;">⏳</span>
                            <span class="file-name" style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${file.name}</span>
                            <span class="file-size" style="font-size: 10px; color: #9ca3af;">${this.formatFileSize(file.size)}</span>
                        </div>
                    `).join('')}
                </div>
            `;
            
            this.elements.container.appendChild(modal);
            return modal;
        }

        // 上传单个文件
        async uploadSingleFile(file, index, totalFiles, progressModal) {
            this.log(`开始上传文件: ${file.name}`, 'debug');
            
            // 更新UI
            this.updateBatchProgress(progressModal, index, totalFiles, file.name);
            this.updateFileStatus(progressModal, index, '🔄', '#3b82f6');
            
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('cate_id', '0');
                
                const xhr = new XMLHttpRequest();
                
                // 上传进度
                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        const progress = Math.round((event.loaded / event.total) * 100);
                        // 这里可以添加单个文件的进度显示
                    }
                });
                
                xhr.onload = () => {
                    if (xhr.status === 200) {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            this.log(`文件 ${file.name} 上传响应`, 'debug', result);
                            
                            if (result.code >= 1) {
                                this.updateFileStatus(progressModal, index, '✅', '#10b981');
                                resolve(result);
                            } else {
                                this.updateFileStatus(progressModal, index, '❌', '#ef4444');
                                reject(new Error(result.msg || '上传失败'));
                            }
                        } catch (e) {
                            this.updateFileStatus(progressModal, index, '❌', '#ef4444');
                            reject(new Error('响应格式错误'));
                        }
                    } else {
                        this.updateFileStatus(progressModal, index, '❌', '#ef4444');
                        reject(new Error(`HTTP ${xhr.status}`));
                    }
                };
                
                xhr.onerror = () => {
                    this.updateFileStatus(progressModal, index, '❌', '#ef4444');
                    reject(new Error('网络错误'));
                };
                
                xhr.onreadystatechange = () => {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 413) {
                            this.updateFileStatus(progressModal, index, '❌', '#ef4444');
                            reject(new Error('文件过大，服务器拒绝上传'));
                        } else if (xhr.status === 0) {
                            this.updateFileStatus(progressModal, index, '❌', '#ef4444');
                            reject(new Error('网络连接错误或服务器无响应'));
                        }
                    }
                };
                
                // 根据文件类型选择上传接口
                const isVideo = file.type.startsWith('video/');
                const uploadEndpoint = isVideo ? 'sys/video' : 'sys/image';
                
                xhr.open('POST', `${this.config.baseURL}${uploadEndpoint}`);
                
                // 设置请求头
                const currentToken = this.getAuthToken();
                const currentSiteId = this.getSiteId();
                
                if (currentToken) {
                    xhr.setRequestHeader('token', currentToken);
                }
                xhr.setRequestHeader('Site-Id', currentSiteId);
                
                xhr.send(formData);
            });
        }

        // 更新批量上传进度
        updateBatchProgress(modal, currentIndex, totalFiles, currentFileName) {
            const progress = Math.round(((currentIndex + 1) / totalFiles) * 100);
            
            const progressFill = modal.querySelector('#batch-progress-fill');
            const progressText = modal.querySelector('#batch-progress-text');
            const currentFile = modal.querySelector('#batch-current-file');
            const info = modal.querySelector('#batch-upload-info');
            
            if (progressFill) progressFill.style.width = progress + '%';
            if (progressText) progressText.textContent = progress + '%';
            if (currentFile) currentFile.textContent = currentFileName;
            if (info) info.textContent = `正在上传第 ${currentIndex + 1} 个文件，共 ${totalFiles} 个`;
        }

        // 更新文件状态
        updateFileStatus(modal, index, icon, color) {
            const fileItem = modal.querySelector(`#file-item-${index}`);
            if (fileItem) {
                const statusIcon = fileItem.querySelector('.file-status');
                if (statusIcon) {
                    statusIcon.textContent = icon;
                    statusIcon.style.color = color;
                }
            }
        }

        // 显示批量上传结果
        showBatchUploadResults(results) {
            this.log('显示批量上传结果', 'debug', results);
            
            const successCount = results.filter(r => r.success).length;
            const failCount = results.length - successCount;
            
            // 检查是否有413错误
            const has413Error = results.some(r => 
                !r.success && r.error && r.error.message.includes('文件过大')
            );
            
            if (successCount > 0 && failCount === 0) {
                // 全部成功
                this.createBatchResultModal('success', '上传完成', 
                    `成功上传 ${successCount} 个文件`, results);
            } else if (successCount === 0) {
                // 全部失败
                let errorMessage = `${failCount} 个文件上传失败`;
                if (has413Error) {
                    errorMessage += '<br><span style="color: #f59e0b;" title="文件大小超过服务器限制，建议压缩后再试">💡 提示：部分文件可能过大，建议压缩到50MB以下再试</span>';
                }
                this.createBatchResultModal('error', '上传失败', errorMessage, results);
            } else {
                // 部分成功
                let warningMessage = `成功 ${successCount} 个，失败 ${failCount} 个`;
                if (has413Error) {
                    warningMessage += '<br><span style="color: #f59e0b;" title="文件大小超过服务器限制，建议压缩后再试">💡 提示：部分文件可能过大，建议压缩到50MB以下再试</span>';
                }
                this.createBatchResultModal('warning', '部分上传成功', warningMessage, results);
            }
        }

        // 创建批量结果模态框
        createBatchResultModal(type, title, summary, results) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: white;
                border-radius: 16px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
                max-width: 400px;
                max-height: 500px;
                pointer-events: auto;
                z-index: 100000;
                animation: slideInRight 0.3s ease;
                border-left: 4px solid ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#f59e0b'};
                overflow: hidden;
            `;
            
            const successResults = results.filter(r => r.success);
            const failResults = results.filter(r => !r.success);
            
            let content = `
                <div style="padding: 16px 20px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <span style="font-size: 20px;">${type === 'success' ? '✅' : type === 'error' ? '❌' : '⚠️'}</span>
                        <span style="flex: 1; font-size: 16px; font-weight: 600; color: #1f2937;">${title}</span>
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" style="background: none; border: none; color: #6b7280; cursor: pointer; font-size: 16px; padding: 0; width: 20px; height: 20px;">✕</button>
                    </div>
                    <div style="color: #6b7280; font-size: 14px; margin-bottom: 12px;">${summary}</div>
            `;
            
            if (successResults.length > 0) {
                content += `
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 12px; font-weight: 600; color: #10b981; margin-bottom: 6px;">✅ 上传成功 (${successResults.length})</div>
                        <div style="max-height: 150px; overflow-y: auto;">
                            ${successResults.map(r => {
                                const isVideo = r.file.type.startsWith('video/');
                                const copyFunction = isVideo ? 'copyVideoUrl' : 'copyImageUrl';
                                return `
                                    <div style="display: flex; align-items: center; gap: 8px; padding: 4px 8px; margin: 2px 0; background: #f0fdf4; border-radius: 6px; font-size: 11px;">
                                        <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #15803d;">${r.file.name}</span>
                                        <button onclick="window.globalDragUpload.${copyFunction}('${r.result.data.url}')" style="background: #dcfce7; color: #15803d; border: none; border-radius: 4px; padding: 2px 6px; cursor: pointer; font-size: 10px;">复制</button>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            }
            
            if (failResults.length > 0) {
                content += `
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 12px; font-weight: 600; color: #ef4444; margin-bottom: 6px;">❌ 上传失败 (${failResults.length})</div>
                        <div style="max-height: 100px; overflow-y: auto;">
                            ${failResults.map(r => `
                                <div style="display: flex; align-items: center; gap: 8px; padding: 4px 8px; margin: 2px 0; background: #fef2f2; border-radius: 6px; font-size: 11px;">
                                    <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #dc2626;">${r.file.name}</span>
                                    <span style="font-size: 10px; color: #991b1b;" title="${r.error.message}">失败</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            content += '</div>';
            modal.innerHTML = content;
            this.elements.container.appendChild(modal);
            
            // 自动关闭
            setTimeout(() => {
                if (modal.parentElement) {
                    modal.remove();
                }
            }, 10000);
            
            return modal;
        }

        // 复制图片URL
        async copyImageUrl(url) {
            try {
                const fullUrl = this.getImageUrl(url);
                this.copyToClipboard(fullUrl);
            } catch (error) {
                this.log('复制链接失败', 'error', error);
                this.createTip('❌ 复制失败', 'error');
            }
        }

        // 复制视频URL
        async copyVideoUrl(url) {
            try {
                const fullUrl = this.getVideoUrl(url);
                this.copyToClipboard(fullUrl);
            } catch (error) {
                this.log('复制视频链接失败', 'error', error);
                this.createTip('❌ 复制失败', 'error');
            }
        }

        // 复制到剪贴板 (复用剪贴板组件的稳健方法)
        async copyToClipboard(text) {
            try {
                // 创建临时文本区域来复制文本
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                textArea.style.top = '-9999px';
                document.body.appendChild(textArea);
                textArea.select();
                textArea.setSelectionRange(0, 99999); // 移动设备兼容
                
                // 执行复制命令
                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                
                if (successful) {
                    this.createTip('📋 链接已复制到剪贴板', 'success');
                } else {
                    throw new Error('复制失败');
                }
            } catch (error) {
                // 降级到现代API
                try {
                    await navigator.clipboard.writeText(text);
                    this.createTip('📋 链接已复制到剪贴板', 'success');
                } catch (fallbackError) {
                    console.error('复制失败:', fallbackError);
                    this.createTip('❌ 复制失败，请手动复制', 'error');
                }
            }
        }

        // 获取图片URL
        getImageUrl(url) {
            if (!url) return '';
            if (url.startsWith('http')) return url;
            return `${this.config.imgDomain}${url}`;
        }

        // 获取视频URL
        getVideoUrl(url) {
            if (!url) return '';
            if (url.startsWith('http')) return url;
            return `${this.config.imgDomain}${url}`;
        }

        // 格式化文件大小
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // 显示欢迎消息
        // showWelcomeMessage() {
        //     this.log('显示欢迎消息', 'debug');
        //     setTimeout(() => {
        //         this.createTip('🎯 拖拽上传已启用，可以直接拖拽图片或视频到页面上传', 'info');
        //     }, 1000);
        // }

        // 创建提示框 (复用并简化剪贴板组件的方法)
        createTip(content, type = 'info', position = 'top-right') {
            const tip = document.createElement('div');
            tip.className = `drag-upload-tip drag-upload-tip-${type}`;
            
            const positions = {
                'top-right': 'top: 80px; right: 20px;',
                'top-left': 'top: 80px; left: 20px;',
                'bottom-right': 'bottom: 20px; right: 20px;',
                'bottom-left': 'bottom: 20px; left: 20px;',
                'center': 'top: 50%; left: 50%; transform: translate(-50%, -50%);'
            };
            
            tip.style.cssText = `
                position: fixed;
                ${positions[position]}
                background: ${type === 'error' ? '#ef4444' : type === 'success' ? '#10b98199' : '#3b82f6a6'};
                color: white;
                padding: 12px 16px;
                border-radius: 12px;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                pointer-events: auto;
                max-width: 350px;
                z-index: 100000;
                animation: slideInRight 0.3s ease;
                font-size: 14px;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            
            tip.innerHTML = content;
            
            // 添加关闭按钮
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '✕';
            closeBtn.style.cssText = `
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                font-size: 16px;
                padding: 0;
                width: 20px;
                height: 20px;
                opacity: 0.8;
                margin-left: auto;
            `;
            closeBtn.onclick = () => tip.remove();
            tip.appendChild(closeBtn);
            
            this.elements.container.appendChild(tip);
            
            // 自动移除
            setTimeout(() => {
                if (tip.parentElement) {
                    tip.remove();
                }
            }, 5000);
            
            return tip;
        }

        // 创建结果模态框 (复用剪贴板组件的方法)
        createResultModal(type, title, message, imageUrl = '') {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: white;
                border-radius: 16px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
                max-width: 350px;
                pointer-events: auto;
                z-index: 100000;
                animation: slideInRight 0.3s ease;
                border-left: 4px solid ${type === 'success' ? '#10b981' : '#ef4444'};
            `;
            
            let content = `
                <div style="padding: 16px 20px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <span style="font-size: 20px;">${type === 'success' ? '✅' : '❌'}</span>
                        <span style="flex: 1; font-size: 16px; font-weight: 600; color: #1f2937;">${title}</span>
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" style="background: none; border: none; color: #6b7280; cursor: pointer; font-size: 16px; padding: 0; width: 20px; height: 20px;">✕</button>
                    </div>
                    <div style="color: #6b7280; font-size: 14px; line-height: 1.5;">${message}</div>
                </div>
            `;
            
            modal.innerHTML = content;
            this.elements.container.appendChild(modal);
            
            // 自动关闭
            const autoCloseTime = type === 'success' ? 8000 : 5000;
            setTimeout(() => {
                if (modal.parentElement) {
                    modal.remove();
                }
            }, autoCloseTime);
            
            return modal;
        }

        // 销毁实例
        destroy() {
            this.log('开始销毁拖拽上传实例', 'info');
            
            // 清除定时器
            Object.values(this.timers).forEach(timer => {
                if (timer) clearTimeout(timer);
            });
            
            // 移除事件监听器
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                document.removeEventListener(eventName, this.preventDefaults);
            });
            
            // 移除DOM
            if (this.elements.container) {
                this.elements.container.remove();
            }
            if (this.elements.dragOverlay) {
                this.elements.dragOverlay.remove();
            }
            
            this.log('拖拽上传实例销毁完成', 'success');
        }
    }

    // 添加必要的CSS动画
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translate(-50%, -60%) scale(0.9); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .drag-upload-tip:hover {
            transform: scale(1.02);
            transition: transform 0.2s ease;
        }
        
        /* 拖拽覆盖层样式 */
        .drag-upload-overlay {
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }
        
        /* 批量上传进度条样式 */
        #batch-progress-fill {
            transition: width 0.3s ease;
        }
        
        /* 文件列表悬停效果 */
        #batch-file-list [id^="file-item-"]:hover {
            background: rgba(59, 130, 246, 0.05);
            transition: background 0.2s ease;
        }
    `;
    document.head.appendChild(style);

    // 创建全局实例
    window.globalDragUpload = new GlobalDragUpload();
    
    // 导出类
    window.GlobalDragUpload = GlobalDragUpload;
    


    // 在全局对象上添加调试方法快捷访问
    window.dragUploadDebug = {
        enable: () => window.globalDragUpload.config.debug = true,
        disable: () => window.globalDragUpload.config.debug = false,
        info: () => ({
            isUploading: window.globalDragUpload.isUploading,
            isDragActive: window.globalDragUpload.isDragActive,
            dragCounter: window.globalDragUpload.dragCounter,
            config: window.globalDragUpload.config
        }),
        destroy: () => window.globalDragUpload.destroy(),
    };

})(window);
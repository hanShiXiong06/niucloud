import { getToken, isUrl } from '@/utils/common'
import storage from '@/utils/storage'
/**
 * 全局剪贴板上传功能
 * 可以在任何页面引入使用
 */
(function(window) {
    'use strict';

    // 全局剪贴板上传类
    class GlobalClipboardUpload {
        constructor() {
            this.isUploading = false;
            this.uploadProgress = 0;
            this.currentFileName = '';
            
            // 配置
            this.config = {
                baseURL: '',
                imgDomain: '',
                headers: {},
                debug: true // 启用调试模式
            };
            
            // 定时器
            this.timers = {
                tip: null,
                result: null,
                help: null,
                clipboard: null
            };
            
            // DOM 元素
            this.elements = {};
            
            this.init();
        }

        // 调试日志输出
        log(message, type = 'info', data = null) {
            if (!this.config.debug) return;
            
            const timestamp = new Date().toLocaleTimeString();
            const prefix = `[全局剪贴板 ${timestamp}]`;
            
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

            this.initConfig();
            this.createUI();
            this.setupEventListeners();
            this.startClipboardMonitor();
            this.showWelcomeMessage();

        }

        // 初始化配置
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
                
                // 如果当前是开发端口（:5173, :3000等），使用localhost作为后端
                if (window.location.port && ['5173', '3000', '8080', '8000'].includes(window.location.port)) {
                    baseURL = `${protocol}//${hostname}/adminapi/`;
                   
                } else {
                    // 否则使用当前域名
                    baseURL = `${protocol}//${window.location.host}/adminapi/`;
                    this.log('使用当前域名作为后端', 'debug', { baseURL });
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
                'Site-Id':  0
            };
            
            
        }

        // 获取认证token
        getAuthToken() {
            // 方法1：使用系统的token存储方式（带前缀）
            let token = getToken();
            return token;
        }

        // 获取站点ID
        getSiteId() {
            return  storage.get('siteId') || 0;
        }

        // 从cookie获取值
        getCookieValue(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
            return null;
        }

        // 检查当前登录状态
        checkAuthStatus() {

            
            const token = this.getAuthToken();
            const siteId = this.getSiteId();
            
           

            if (!token) {
                this.createResultModal('warning', '需要登录', '请先登录系统后再使用剪贴板上传功能');
                return false;
            }

            return true;
        }

        // 创建UI
        createUI() {

            // 创建容器
            const container = document.createElement('div');
            container.id = 'global-clipboard-upload';
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

        }

        // 创建提示框
        createTip(content, type = 'info', position = 'top-right') {

            
            const tip = document.createElement('div');
            tip.className = `clipboard-tip clipboard-tip-${type}`;
            
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
            closeBtn.onclick = () => {
                // 如果是剪贴板检测提示，点击关闭时清空剪贴板
                if (content.includes('检测到剪贴板图片')) {
                    this.clearClipboard();
                    this.log('用户主动关闭剪贴板提示，已清空剪贴板', 'info');
                }
                tip.remove();
            };
            tip.appendChild(closeBtn);
            
            this.elements.container.appendChild(tip);

            
            return tip;
        }

        // 创建进度框
        createProgressModal() {

            
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border-radius: 16px;
                padding: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
                min-width: 320px;
                pointer-events: auto;
                z-index: 100001;
                animation: slideIn 0.3s ease;
                text-align: center;
            `;
            
            modal.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 15px;">
                    <span style="font-size: 24px;">📤</span>
                    <span style="font-size: 16px; font-weight: 600; color: #1f2937;">正在上传图片...</span>
                </div>
                <div style="width: 100%; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin-bottom: 10px;">
                    <div id="progress-fill" style="height: 100%; background: linear-gradient(90deg, #3b82f6, #10b981); transition: width 0.3s ease; width: 0%;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #6b7280;">
                    <span id="progress-text">0%</span>
                    <span id="progress-file" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                </div>
            `;
            
            this.elements.container.appendChild(modal);

            return modal;
        }

        // 创建结果框
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
            `;
            
            if (type === 'success' && imageUrl) {
                const fullUrl = this.getImageUrl(imageUrl);

                
                content += `
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="border-radius: 8px; overflow: hidden; max-height: 150px;">
                            <img src="${fullUrl}" alt="上传的图片" style="width: 100%; height: auto; max-height: 150px; object-fit: cover;" />
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button onclick="window.globalClipboardUpload.copyToClipboard('${fullUrl}')" style="flex: 1; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 500; background: #e0f2fe; color: #0369a1; transition: all 0.2s ease;">
                                📋 复制链接
                            </button>
                            <button onclick="window.open('${fullUrl}', '_blank')" style="flex: 1; padding: 8px 12px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 500; background: #f0fdf4; color: #15803d; transition: all 0.2s ease;">
                                👁️ 预览
                            </button>
                        </div>
                    </div>
                `;
            } else {
                content += `<div style="color: #6b7280; font-size: 14px; line-height: 1.5;">${message}</div>`;
            }
            
            content += '</div>';
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

        // 设置事件监听
        setupEventListeners() {


            // 全局键盘事件
            document.addEventListener('keydown', (event) => {
                // Ctrl+V 或 Cmd+V
                if ((event.ctrlKey || event.metaKey) && event.key === 'v' && !event.shiftKey) {

                    
                    // 检查是否在输入框中
                    const activeElement = document.activeElement;
                    const isInInput = activeElement && (
                        activeElement.tagName === 'INPUT' ||
                        activeElement.tagName === 'TEXTAREA' ||
                        activeElement.contentEditable === 'true'
                    );
                    

                    
                    if (!isInInput) {

                        event.preventDefault();
                        this.handlePasteFromKeyboard();
                    } 
                }
                
                // Ctrl+Shift+V 显示帮助
                if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === 'V') {

                    event.preventDefault();
                    this.showHelp();
                }
            }, true);
            
            // 全局粘贴事件
            document.addEventListener('paste', (event) => {

                
                const activeElement = document.activeElement;
                const isInInput = activeElement && (
                    activeElement.tagName === 'INPUT' ||
                    activeElement.tagName === 'TEXTAREA' ||
                    activeElement.contentEditable === 'true'
                );
                

                
                if (isInInput) {

                    return;
                }
                
                const items = event.clipboardData?.items;
                if (!items) {

                    return;
                }

                
                for (const item of items) {
                    if (item.type.indexOf('image') !== -1) {

                        event.preventDefault();
                        const file = item.getAsFile();
                        if (file) {

                            this.uploadImage(file);
                        } else {

                        }
                        break;
                    }
                }
                
                const hasImage = Array.from(items).some(item => item.type.indexOf('image') !== -1);

            }, true);
            

        }

        // 剪贴板监控
        startClipboardMonitor() {

            this.timers.clipboard = setInterval(() => {
                // 只在文档有焦点时检查剪贴板
                if (document.hasFocus()) {
                    this.checkClipboardForImages();
                }
            }, 3000);
        }

        // 检查剪贴板
        async checkClipboardForImages() {

            
            try {
                if (!navigator.clipboard || !navigator.clipboard.read) {

                    return;
                }

                // 再次确认文档焦点
                if (!document.hasFocus()) {

                    return;
                }
                
                const items = await navigator.clipboard.read();

                
                for (const item of items) {
                    if (item.types.some(type => type.startsWith('image/'))) {

                        
                        // 检测到图片，显示提示
                        if (!this.isUploading && !document.querySelector('.clipboard-tip')) {

                            this.showUploadTip();
                        } else {

                        }
                        break;
                    }
                }
            } catch (error) {

            }
        }

        // 显示上传提示
        showUploadTip() {

            const tip = this.createTip('📋 检测到剪贴板图片，按 Ctrl+V 上传', 'info');
            
            setTimeout(() => {
                if (tip.parentElement) {

                    tip.remove();
                }
            }, 3000);
        }

        // 从键盘快捷键处理粘贴
        async handlePasteFromKeyboard() {

            
            try {
                if (!navigator.clipboard || !navigator.clipboard.read) {

                    this.createResultModal('error', '浏览器不支持', '请使用现代浏览器并确保是HTTPS环境');
                    return;
                }

                // 检查文档焦点
                if (!document.hasFocus()) {

                    this.createResultModal('error', '请点击页面', '需要先点击页面获得焦点才能读取剪贴板内容');
                    return;
                }
                
                const items = await navigator.clipboard.read();

                
                for (const item of items) {
                    const imageType = item.types.find(type => type.startsWith('image/'));
                    if (imageType) {

                        
                        const blob = await item.getType(imageType);
                        const file = new File([blob], `clipboard-${Date.now()}.png`, { type: imageType });
                        

                        
                        this.uploadImage(file);
                        return; // 成功处理，直接返回
                    }
                }
                
                // 如果没有找到图片

                this.createResultModal('error', '没有图片', '剪贴板中没有发现图片内容，请先复制一张图片');
                
            } catch (error) {

                
                if (error.message.includes('Document is not focused')) {
                    this.createResultModal('error', '页面失去焦点', '请先点击页面获得焦点，然后再按 Ctrl+V');
                } else if (error.message.includes('denied')) {
                    this.createResultModal('error', '权限被拒绝', '请在浏览器中允许剪贴板访问权限');
                } else {
                    this.createResultModal('error', '获取剪贴板失败', '请确保已授权剪贴板访问权限：' + error.message);
                }
            }
        }

        // 上传图片
        async uploadImage(file) {
            if (this.isUploading) {

                this.createTip('❌ 正在上传中，请稍候...', 'error');
                return;
            }

            // 检查认证状态
            if (!this.checkAuthStatus()) {

                return;
            }



            this.isUploading = true;
            this.uploadProgress = 0;
            this.currentFileName = file.name;

            // 创建进度模态框
            const progressModal = this.createProgressModal();
            const progressFill = progressModal.querySelector('#progress-fill');
            const progressText = progressModal.querySelector('#progress-text');
            const progressFile = progressModal.querySelector('#progress-file');

            progressFile.textContent = this.currentFileName;

            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('cate_id', '0');


                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        this.uploadProgress = Math.round((event.loaded / event.total) * 100);
                        progressFill.style.width = this.uploadProgress + '%';
                        progressText.textContent = this.uploadProgress + '%';
                        
                    }
                });

                const response = await new Promise((resolve, reject) => {
                    xhr.onload = () => {
                        
                        if (xhr.status === 200) {
                            try {
                                const result = JSON.parse(xhr.responseText);

                                resolve(result);
                            } catch (e) {

                                reject(new Error('响应格式错误'));
                            }
                        } else {

                            reject(new Error(`HTTP ${xhr.status}`));
                        }
                    };
                    xhr.onerror = () => {

                        reject(new Error('网络错误'));
                    };
                    
                    xhr.open('POST', `${this.config.baseURL}sys/image`);
                    
                    // 刷新token并设置请求头
                    const currentToken = this.getAuthToken();
                    const currentSiteId = this.getSiteId();
                    
                    if (currentToken) {
                        xhr.setRequestHeader('token', currentToken);
                    }
                    
                    xhr.setRequestHeader('Site-Id', currentSiteId);

                    
                    xhr.send(formData);
                });

                // 移除进度模态框
                progressModal.remove();

                if (response.code >= 1) {
                  
                    this.createResultModal('success', '上传成功', '', response.data.url);
                    
                    // 清除剪贴板，避免重复上传
                    this.clearClipboard();
                } else {
                    
                    if (response.code === 401) {
                        this.createResultModal('error', '认证失败', '登录已过期，请重新登录后再试');
                    } else {
                        this.createResultModal('error', '上传失败', response.msg || '未知错误');
                    }
                }
            } catch (error) {

                progressModal.remove();
                this.createResultModal('error', '上传失败', error.message || '网络连接错误');
            } finally {
                this.isUploading = false;
                this.uploadProgress = 0;
                this.currentFileName = '';

            }
        }

        // 复制到剪贴板
        async copyToClipboard(text) {

            
            try {
                await navigator.clipboard.writeText(text);

            } catch (error) {

            }
        }

        // 获取图片URL
        getImageUrl(url) {

            
            if (!url) return '';
            if (url.startsWith('http')) {

                return url;
            }
            
            const fullUrl = `${this.config.imgDomain}${url}`;

            return fullUrl;
        }

        // 显示帮助
        showHelp() {

            
            const helpContent = `
                <div style="background:rgba(31, 41, 55, 0.42); color: white; border-radius: 12px; padding: 16px; min-width: 250px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #374151;">
                        <span style="font-size: 18px;">💡</span>
                        <span style="flex: 1; font-size: 14px; font-weight: 600;">全局上传快捷键</span>
                        <button onclick="this.closest('.clipboard-tip').remove()" style="background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 16px; padding: 0; width: 20px; height: 20px;">✕</button>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="font-size: 12px; color: #d1d5db; display: flex; align-items: center; gap: 4px;">
                            <kbd style="background: #374151; border: 1px solid #4b5563; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-family: monospace; color: #f9fafb;">Ctrl</kbd> + 
                            <kbd style="background: #374151; border: 1px solid #4b5563; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-family: monospace; color: #f9fafb;">V</kbd> - 上传剪贴板图片
                        </div>
                        <div style="font-size: 12px; color: #d1d5db; display: flex; align-items: center; gap: 4px;">
                            <kbd style="background: #374151; border: 1px solid #4b5563; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-family: monospace; color: #f9fafb;">Ctrl</kbd> + 
                            <kbd style="background: #374151; border: 1px solid #4b5563; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-family: monospace; color: #f9fafb;">Shift</kbd> + 
                            <kbd style="background: #374151; border: 1px solid #4b5563; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-family: monospace; color: #f9fafb;">V</kbd> - 显示/隐藏此提示
                        </div>
                    </div>
                </div>
            `;
            
            const helpTip = document.createElement('div');
            helpTip.className = 'clipboard-tip';
            helpTip.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                pointer-events: auto;
                z-index: 100000;
                animation: slideInUp 0.3s ease;
            `;
            helpTip.innerHTML = helpContent;
            
            this.elements.container.appendChild(helpTip);
            
            setTimeout(() => {
                if (helpTip.parentElement) {
                    helpTip.remove();
                }
            }, 30000);
        }

        // 显示欢迎消息
        showWelcomeMessage() {

            setTimeout(() => {

                
                // 检查当前状态并给出相应提示
                if (!document.hasFocus()) {
                    this.createTip('⚠️ 页面需要焦点才能使用剪贴板功能，请先点击页面', 'warning');
                } else if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                    this.createTip('⚠️ 剪贴板功能需要HTTPS环境才能完全正常工作', 'warning');
                } else {

                }
            }, 1000);
        }

        // 销毁实例
        destroy() {

            
            // 清除定时器
            Object.values(this.timers).forEach(timer => {
                if (timer) clearInterval(timer);
            });
            
            // 移除DOM
            if (this.elements.container) {
                this.elements.container.remove();
            }
            
        }

        // 调试控制方法
        enableDebug() {
            this.config.debug = true;
        }

        disableDebug() {
            this.config.debug = false;
        }

        // 获取调试信息
        getDebugInfo() {
            const debugInfo = {
                isUploading: this.isUploading,
                uploadProgress: this.uploadProgress,
                currentFileName: this.currentFileName,
                config: this.config,
                timers: Object.keys(this.timers).map(key => ({
                    name: key,
                    active: !!this.timers[key]
                })),
                hasContainer: !!this.elements.container,
                clipboardSupport: {
                    hasClipboard: !!navigator.clipboard,
                    hasRead: !!(navigator.clipboard && navigator.clipboard.read),
                    hasWrite: !!(navigator.clipboard && navigator.clipboard.writeText)
                },
                eventListeners: {
                    keydownAttached: true, // 我们知道已经附加
                    pasteAttached: true
                },
                browserInfo: {
                    userAgent: navigator.userAgent,
                    isHTTPS: location.protocol === 'https:',
                    origin: location.origin,
                    hasFocus: document.hasFocus(),  // 添加焦点状态
                    documentVisibility: document.visibilityState,  // 添加页面可见性
                    activeElement: document.activeElement ? {
                        tagName: document.activeElement.tagName,
                        type: document.activeElement.type || 'none',
                        id: document.activeElement.id || 'none',
                        className: document.activeElement.className || 'none',
                        contentEditable: document.activeElement.contentEditable
                    } : null
                },
                domElements: {
                    hasContainer: !!this.elements.container,
                    containerInDOM: !!(this.elements.container && document.body.contains(this.elements.container)),
                    existingTips: document.querySelectorAll('.clipboard-tip').length
                },
                // 添加问题诊断信息
                diagnostics: {
                    canAccessClipboard: navigator.clipboard && document.hasFocus(),
                    possibleIssues: this.diagnosePossibleIssues()
                }
            };
            
            return debugInfo;
        }

        // 诊断可能的问题
        diagnosePossibleIssues() {
            const issues = [];
            
            if (!navigator.clipboard) {
                issues.push('浏览器不支持剪贴板API');
            }
            
            if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                issues.push('非HTTPS环境，剪贴板功能受限');
            }
            
            if (!document.hasFocus()) {
                issues.push('页面失去焦点，无法访问剪贴板');
            }
            
            if (document.visibilityState !== 'visible') {
                issues.push('页面不可见，可能影响剪贴板访问');
            }
            
            const activeElement = document.activeElement;
            if (activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.contentEditable === 'true'
            )) {
                issues.push('当前焦点在输入框内，Ctrl+V会被拦截');
            }
            
            if (issues.length === 0) {
                issues.push('没有发现明显问题');
            }
            
            return issues;
        }

        // 强制触发测试事件
        testKeyboardEvent() {
            const event = new KeyboardEvent('keydown', {
                key: 'v',
                ctrlKey: true,
                bubbles: true,
                cancelable: true
            });
            document.dispatchEvent(event);
        }

        // 测试剪贴板访问
        async testClipboardAccess() {
            


            if (!navigator.clipboard) {
                this.log('浏览器不支持剪贴板API', 'error');
                return;
            }

            if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                this.log('非HTTPS环境，剪贴板功能受限', 'warning');
            }

            if (!document.hasFocus()) {
                this.log('文档当前没有焦点，可能影响剪贴板访问', 'warning');
                this.createResultModal('warning', '页面没有焦点', '请先点击页面，然后重新测试剪贴板功能');
            }
            
            try {
                // 测试写入权限
                if (navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText('剪贴板测试文本 - ' + new Date().toLocaleTimeString());
                } else {
                    this.log('浏览器不支持剪贴板写入API', 'error');
                }

                // 测试读取权限
                if (navigator.clipboard.read) {
                    const items = await navigator.clipboard.read();
                    this.log('剪贴板读取测试成功', 'success', {
                        itemsCount: items.length,
                        itemTypes: items.map(item => item.types),
                        hasImages: items.some(item => item.types.some(type => type.startsWith('image/'))),
                        hasText: items.some(item => item.types.some(type => type.includes('text')))
                    });

                    // 检查是否有图片内容
                    const hasImages = items.some(item => item.types.some(type => type.startsWith('image/')));
                    if (hasImages) {
                        this.createResultModal('success', '测试成功', '剪贴板功能正常，当前剪贴板中有图片内容');
                    } else {
                        this.log('剪贴板中没有图片内容', 'info');
                        this.createResultModal('info', '测试完成', '剪贴板功能正常，但当前没有图片内容。请复制一张图片后再试试 Ctrl+V');
                    }
                } else {
                    this.log('浏览器不支持剪贴板读取API', 'error');
                }
                
            } catch (error) {
                this.log('剪贴板访问测试失败', 'error', {
                    error: error.message,
                    name: error.name,
                    hasFocus: document.hasFocus()
                });

                if (error.message.includes('Document is not focused')) {
                    this.createResultModal('error', '页面失去焦点', '请先点击页面获得焦点，剪贴板API需要页面处于活跃状态');
                } else if (error.message.includes('denied')) {
                    this.createResultModal('error', '权限被拒绝', '浏览器拒绝了剪贴板访问权限，请在地址栏点击锁图标允许剪贴板访问');
                } else {
                    this.createResultModal('error', '测试失败', '剪贴板访问测试失败：' + error.message);
                }
            }
        }

        // 详细分析剪贴板内容
        async analyzeClipboardContent() {
            
            try {
                if (!navigator.clipboard || !navigator.clipboard.read) {
                    this.log('浏览器不支持剪贴板读取', 'error');
                    return;
                }

                if (!document.hasFocus()) {
                    this.log('页面没有焦点，无法读取剪贴板', 'warning');
                    return;
                }

                const items = await navigator.clipboard.read();
                this.log('剪贴板项目数量：' + items.length, 'info');

                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    
                    // 详细检查每种类型
                    for (const type of item.types) {
                        try {
                            if (type.startsWith('image/')) {
                                const blob = await item.getType(type);

                            } else if (type.startsWith('text/')) {
                                const text = await item.getType(type);
                                const textContent = await text.text();

                            } else {
                                const blob = await item.getType(type);
                                this.log(`📄 其他类型 (${type}):`, 'info', {
                                    size: blob.size,
                                    type: blob.type
                                });
                            }
                        } catch (error) {
                            this.log(`❌ 无法读取类型 ${type}:`, 'error', error.message);
                        }
                    }
                }

                // 检查是否有旧版本的剪贴板API
                if (navigator.clipboard.readText) {
                    try {
                        const text = await navigator.clipboard.readText();
                    } catch (error) {
                        this.log('readText() 失败:', 'debug', error.message);
                    }
                }

            } catch (error) {
                this.log('剪贴板分析失败', 'error', {
                    error: error.message,
                    name: error.name
                });
            }
        }

        // 清除剪贴板
        async clearClipboard() {
            try {
                if (!navigator.clipboard || !navigator.clipboard.writeText) {
                    this.log('浏览器不支持剪贴板写入API', 'warning');
                    return;
                }

                // 检查文档焦点
                if (!document.hasFocus()) {
                    this.log('文档没有焦点，无法清除剪贴板', 'warning');
                    return;
                }

                // 写入空文本清除剪贴板
                await navigator.clipboard.writeText('');
                this.log('剪贴板已清除', 'success');
                
                // 可选：显示一个简短的提示
                setTimeout(() => {
                    this.createTip('🗑️ 剪贴板已清空', 'info');
                }, 500);
                
            } catch (error) {
                this.log('清除剪贴板失败', 'error', {
                    error: error.message,
                    name: error.name,
                    hasFocus: document.hasFocus()
                });
                
                // 如果清除失败，不影响用户体验，只记录日志
                if (error.message.includes('Document is not focused')) {
                    this.log('页面失去焦点，无法清除剪贴板', 'debug');
                } else if (error.message.includes('denied')) {
                    this.log('权限被拒绝，无法清除剪贴板', 'debug');
                }
            }
        }
    }

    // 添加必要的CSS动画
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(100%); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        
        .clipboard-tip:hover {
            transform: scale(1.02);
            transition: transform 0.2s ease;
        }
    `;
    document.head.appendChild(style);

    // 创建全局实例
    window.globalClipboardUpload = new GlobalClipboardUpload();
    
    // 导出类
    window.GlobalClipboardUpload = GlobalClipboardUpload;
    
    // 在全局对象上添加调试方法快捷访问
    window.clipboardDebug = {
        enable: () => window.globalClipboardUpload.enableDebug(),
        disable: () => window.globalClipboardUpload.disableDebug(),
        info: () => window.globalClipboardUpload.getDebugInfo(),
        test: () => window.globalClipboardUpload.testKeyboardEvent(),
        testClipboard: () => window.globalClipboardUpload.testClipboardAccess(),
        analyze: () => window.globalClipboardUpload.analyzeClipboardContent(),
        checkAuth: () => window.globalClipboardUpload.checkAuthStatus(),
        // 显示所有localStorage中的键值
        showStorage: () => {
            const allKeys = Object.keys(localStorage);
            const adminKeys = allKeys.filter(key => key.startsWith('admin.'));
            console.log('%c📦 所有localStorage键:', 'color: #3b82f6; font-weight: bold;', allKeys);
            console.log('%c🔧 admin前缀的键:', 'color: #10b981; font-weight: bold;', adminKeys);
            return { allKeys, adminKeys };
        }
    };



})(window); 
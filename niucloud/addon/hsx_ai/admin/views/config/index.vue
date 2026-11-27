<template>
    <div class="main-container ai-config-page">
        <div class="page-header">
            <div>
                <h1 class="text-page-title">{{ pageTitle }}</h1>
                <div class="header-status">
                    <el-tag v-if="section !== 'logs'" :type="config.enabled ? 'success' : 'info'" effect="plain">
                        {{ config.enabled ? '已启用' : '未启用' }}
                    </el-tag>
                    <span v-if="section === 'model'">{{ enabledProviders }} 个通道 · {{ modelCount }} 个模型 · {{ enabledScenes }} 个场景</span>
                    <span v-else>{{ pageDescription }}</span>
                </div>
            </div>
            <div v-if="canSave" class="header-actions">
                <el-switch v-if="section === 'model'" v-model="config.enabled" :active-value="1" :inactive-value="0" inline-prompt active-text="启用" inactive-text="停用" />
                <el-button type="primary" :loading="saving" @click="saveConfig">保存</el-button>
            </div>
        </div>

        <div>
            <template v-if="section === 'model'">
                <h2 class="section-title">模型通道</h2>
                <div class="toolbar">
                    <div class="toolbar-fields">
                        <el-select v-model="config.default_provider_id" placeholder="默认通道" @change="config.default_model = ''">
                            <el-option v-for="item in config.providers" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                        <el-select v-model="config.default_model" filterable placeholder="全局默认模型">
                            <el-option v-for="item in defaultProviderModels" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                    </div>
                    <el-button :icon="Plus" @click="addProvider">新增通道</el-button>
                </div>

                <div v-loading="loading" class="provider-list">
                    <section v-for="(provider, index) in config.providers" :key="provider.local_key" class="provider-panel">
                        <header class="provider-header">
                            <div class="provider-title">
                                <el-switch v-model="provider.enabled" :active-value="1" :inactive-value="0" />
                                <strong>{{ provider.name || '未命名通道' }}</strong>
                                <el-tag size="small" effect="plain">{{ provider.driver }}</el-tag>
                                <span>{{ provider.models.length }} 个模型</span>
                            </div>
                            <div>
                                <el-button :icon="Connection" :loading="provider.testing" @click="testProvider(provider)">测试</el-button>
                                <el-button :icon="Refresh" :loading="provider.syncing" @click="syncModels(provider)">同步模型</el-button>
                                <el-button v-if="config.providers.length > 1" :icon="Delete" type="danger" link @click="removeProvider(index)" />
                            </div>
                        </header>
                        <el-form :model="provider" label-width="110px" class="provider-form">
                            <div class="form-grid">
                                <el-form-item label="通道名称">
                                    <el-input v-model.trim="provider.name" placeholder="例如 云雾 API" />
                                </el-form-item>
                                <el-form-item label="通道标识">
                                    <el-input v-model.trim="provider.id" :disabled="Boolean(provider.persisted_id)" placeholder="例如 yunwu" />
                                </el-form-item>
                                <el-form-item label="协议类型">
                                    <el-select v-model="provider.driver">
                                        <el-option label="OpenAI Compatible" value="openai_compatible" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="请求超时">
                                    <el-input-number v-model="provider.timeout" :min="5" :max="180" controls-position="right" />
                                    <span class="unit">秒</span>
                                </el-form-item>
                                <el-form-item label="连接超时">
                                    <el-input-number v-model="provider.connect_timeout" :min="5" :max="90" controls-position="right" />
                                    <span class="unit">秒</span>
                                </el-form-item>
                                <el-form-item label="IP 协议">
                                    <el-select v-model="provider.ip_version">
                                        <el-option label="IPv4（推荐）" value="ipv4" />
                                        <el-option label="自动选择" value="auto" />
                                        <el-option label="IPv6" value="ipv6" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="HTTP 协议">
                                    <el-select v-model="provider.http_version">
                                        <el-option label="HTTP/1.1（推荐）" value="1.1" />
                                        <el-option label="自动协商" value="auto" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="Base URL" class="grid-span-2">
                                    <el-input v-model.trim="provider.base_url" placeholder="https://yunwu.ai" />
                                </el-form-item>
                                <el-form-item label="API Key" class="grid-span-2">
                                    <div class="secret-field">
                                        <el-input v-model.trim="provider.api_key" type="password" show-password autocomplete="new-password" placeholder="填写云雾控制台生成的 Token，无需输入 Bearer" />
                                        <span v-if="provider.api_key_configured" class="secondary-text">已保存 · 密钥指纹 {{ provider.api_key_fingerprint }}</span>
                                        <span v-else class="secret-warning">尚未保存有效 Token，请重新填写</span>
                                    </div>
                                </el-form-item>
                                <el-form-item label="模型接口">
                                    <el-input v-model.trim="provider.models_path" placeholder="/v1/models" />
                                </el-form-item>
                                <el-form-item label="对话接口">
                                    <el-input v-model.trim="provider.chat_path" placeholder="/v1/chat/completions" />
                                </el-form-item>
                                <el-form-item label="默认模型" class="grid-span-2">
                                    <el-select v-model="provider.default_model" filterable clearable placeholder="同步模型后选择">
                                        <el-option v-for="model in provider.models" :key="model.id" :label="model.name" :value="model.id" />
                                    </el-select>
                                </el-form-item>
                            </div>
                        </el-form>
                    </section>
                </div>
            </template>

            <template v-if="section === 'model'">
                <h2 class="section-title section-gap">业务场景</h2>
                <div class="toolbar">
                    <div class="toolbar-fields compact">
                        <el-switch v-model="config.redact_sensitive" :active-value="1" :inactive-value="0" />
                        <span>发送前脱敏</span>
                        <el-switch v-model="config.log_content" :active-value="1" :inactive-value="0" />
                        <span>保存问答内容</span>
                    </div>
                    <el-button :icon="Plus" @click="openScene()">新增场景</el-button>
                </div>
                <el-table :data="config.scenes" row-key="local_key">
                    <el-table-column label="场景" min-width="220">
                        <template #default="{ row }">
                            <div class="primary-text">{{ row.name }}</div>
                            <div class="secondary-text">{{ row.key }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="模型通道" min-width="170">
                        <template #default="{ row }">{{ providerName(row.provider_id) }}</template>
                    </el-table-column>
                    <el-table-column label="模型" min-width="220" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.model || '跟随通道默认' }}</template>
                    </el-table-column>
                    <el-table-column label="输出" width="100">
                        <template #default="{ row }"><el-tag effect="plain">{{ row.response_mode === 'json' ? 'JSON' : '文本' }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="状态" width="90" align="center">
                        <template #default="{ row }"><el-switch v-model="row.enabled" :active-value="1" :inactive-value="0" /></template>
                    </el-table-column>
                    <el-table-column label="操作" width="130" align="right">
                        <template #default="{ row, $index }">
                            <el-button type="primary" link @click="openScene(row, $index)">编辑</el-button>
                            <el-button v-if="row.key !== 'general'" type="danger" link @click="removeScene($index)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </template>

            <template v-if="section === 'integration'">
                <el-alert
                    class="integration-alert"
                    type="info"
                    :closable="false"
                    show-icon
                    title="只有开启接入后，AI 才能与对应业务插件交换数据或调用工具；关闭不会影响业务插件自身运行。"
                />
                <div v-loading="loading" class="integration-list">
                    <section v-for="integration in config.integrations" :key="integration.key" class="integration-panel">
                        <div class="integration-copy">
                            <div class="integration-title">
                                <strong>{{ integration.name }}</strong>
                                <el-tag size="small" effect="plain">{{ integration.source_plugin }}</el-tag>
                                <el-tag size="small" :type="integration.enabled ? (config.enabled ? 'success' : 'warning') : 'info'" effect="plain">
                                    {{ integration.enabled ? (config.enabled ? '已连接' : '已授权，AI 未启用') : '已锁定' }}
                                </el-tag>
                            </div>
                            <p>{{ integration.description || '该插件已声明 AI 接入能力。' }}</p>
                            <div v-if="integration.capabilities.length" class="integration-capabilities">
                                <el-tag v-for="item in integration.capabilities" :key="item" size="small" type="info" effect="plain">{{ item }}</el-tag>
                            </div>
                            <div v-if="integration.scenes.length" class="integration-scenes">
                                关联场景：{{ integration.scenes.map(sceneName).join('、') }}
                            </div>
                        </div>
                        <div class="integration-switch">
                            <span>{{ integration.enabled ? '允许接入' : '禁止接入' }}</span>
                            <el-switch v-model="integration.enabled" :active-value="1" :inactive-value="0" />
                        </div>
                    </section>
                    <el-empty v-if="!config.integrations.length" description="暂未发现支持 AI 接入的业务插件" :image-size="72" />
                </div>
            </template>

            <template v-if="section === 'speech'">
                <div class="speech-panel">
                    <header class="speech-header">
                        <div>
                            <strong>语音输入与回复朗读</strong>
                            <p>密钥仅保存在服务端。商城不会直接连接第三方语音服务。</p>
                        </div>
                        <div class="speech-actions">
                            <el-button :loading="speechTesting" @click="runSpeechTest">测试并试听</el-button>
                            <el-switch v-model="config.speech.enabled" :active-value="1" :inactive-value="0" inline-prompt active-text="启用" inactive-text="停用" />
                        </div>
                    </header>
                    <el-alert type="info" :closable="false" show-icon title="百度智能云与腾讯云均由服务端转发；测试会真实调用当前服务商并生成一段试听语音。" />
                    <el-form label-width="120px" class="speech-form">
                        <div class="form-grid">
                            <el-form-item label="服务商">
                                <el-select v-model="config.speech.provider" @change="onSpeechProviderChange">
                                    <el-option label="百度智能云" value="baidu" />
                                    <el-option label="腾讯云" value="tencent" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="能力">
                                <el-checkbox v-model="config.speech.stt_enabled" :true-value="1" :false-value="0">语音转文字</el-checkbox>
                                <el-checkbox v-model="config.speech.tts_enabled" :true-value="1" :false-value="0">回复朗读</el-checkbox>
                            </el-form-item>
                            <el-form-item label="前台自动朗读">
                                <div class="speech-default-setting">
                                    <el-switch v-model="config.speech.auto_read_default" :active-value="1" :inactive-value="0" :disabled="!config.speech.tts_enabled" />
                                    <span class="secondary-text">仅作为商城聊天页默认值，用户本次会话仍可关闭；开启会产生语音合成费用。</span>
                                </div>
                            </el-form-item>
                            <el-form-item v-if="config.speech.provider === 'baidu'" label="API Key" class="grid-span-2">
                                <div class="secret-field">
                                    <el-input v-model.trim="config.speech.api_key" type="password" show-password autocomplete="new-password" placeholder="支持 bce-v3 API Key 或旧语音应用 API Key" />
                                    <span class="secondary-text">{{ baiduDirectAuth ? '已识别为新版 API Key 直连鉴权，无需 Secret Key' : '旧应用鉴权需要同时填写 Secret Key' }}</span>
                                </div>
                            </el-form-item>
                            <el-form-item v-else label="SecretId" class="grid-span-2">
                                <el-input v-model.trim="config.speech.secret_id" type="password" show-password autocomplete="new-password" placeholder="腾讯云 API 密钥 SecretId" />
                            </el-form-item>
                            <el-form-item v-if="config.speech.provider === 'tencent' || !baiduDirectAuth" label="Secret Key" class="grid-span-2">
                                <el-input v-model.trim="config.speech.secret_key" type="password" show-password autocomplete="new-password" :placeholder="config.speech.provider === 'tencent' ? '腾讯云 API 密钥 SecretKey' : '百度智能云应用 Secret Key'" />
                            </el-form-item>
                            <el-form-item v-if="config.speech.provider === 'tencent'" label="地域">
                                <el-select v-model="config.speech.region">
                                    <el-option label="上海" value="ap-shanghai" />
                                    <el-option label="广州" value="ap-guangzhou" />
                                    <el-option label="北京" value="ap-beijing" />
                                </el-select>
                            </el-form-item>
                            <el-form-item v-if="config.speech.provider === 'tencent'" label="识别引擎">
                                <el-select v-model="config.speech.stt_engine">
                                    <el-option label="中文通用 16k" value="16k_zh" />
                                    <el-option label="中英粤 16k" value="16k_zh-PY" />
                                    <el-option label="粤语 16k" value="16k_yue" />
                                </el-select>
                            </el-form-item>
                            <el-form-item v-if="config.speech.provider === 'baidu'" label="发音人">
                                <el-select v-model="config.speech.voice">
                                    <el-option label="度小美" :value="0" />
                                    <el-option label="度小宇" :value="1" />
                                    <el-option label="度逍遥" :value="3" />
                                    <el-option label="度丫丫" :value="4" />
                                </el-select>
                            </el-form-item>
                            <el-form-item v-else label="发音人">
                                <el-select v-model="config.speech.tencent_voice" filterable allow-create>
                                    <el-option label="智瑜女声（1001）" :value="1001" />
                                    <el-option label="智聆女声（1002）" :value="1002" />
                                    <el-option label="智美男声（1003）" :value="1003" />
                                </el-select>
                            </el-form-item>
                            <el-form-item v-if="config.speech.provider === 'baidu'" label="语速">
                                <el-slider v-model="config.speech.speed" :min="0" :max="15" show-input />
                            </el-form-item>
                            <el-form-item v-else label="语速">
                                <el-slider v-model="config.speech.tencent_speed" :min="-2" :max="6" :step="0.1" show-input />
                            </el-form-item>
                        </div>
                    </el-form>
                    <div v-if="speechTestAudio" class="speech-test-result">
                        <span>{{ speechTestMessage }}</span>
                        <audio :src="speechTestAudio" controls preload="none" />
                    </div>
                </div>
            </template>

            <template v-if="section === 'playground' || section === 'assistant'">
                <div v-loading="loading" class="playground-workspace">
                    <div class="playground-conversation-bar">
                        <div>
                            <strong>{{ isAssistant ? '经营助手' : '直接对话' }}</strong>
                            <span>{{ activeConversationLabel }}</span>
                        </div>
                        <div class="conversation-actions">
                            <el-button v-if="isAssistant" text :icon="Refresh" :disabled="executing" @click="startNewAssistantConversation">新对话</el-button>
                            <el-button text :icon="Setting" @click="showAdvanced = !showAdvanced">
                                {{ showAdvanced ? '收起设置' : '对话设置' }}
                            </el-button>
                        </div>
                    </div>
                    <div v-show="showAdvanced" class="playground-toolbar" :class="{ 'assistant-toolbar': isAssistant }">
                        <label v-if="!isAssistant" class="playground-field">
                            <span>业务场景</span>
                            <el-select v-model="testForm.scene_key" @change="applyScene">
                                <el-option v-for="scene in config.scenes" :key="scene.key" :label="scene.name" :value="scene.key" />
                            </el-select>
                        </label>
                        <label class="playground-field">
                            <span>模型通道</span>
                            <el-select v-model="testForm.provider_id" @change="testForm.model = ''">
                                <el-option v-for="provider in enabledProviderOptions" :key="provider.id" :label="provider.name" :value="provider.id" />
                            </el-select>
                        </label>
                        <label class="playground-field model-field">
                            <span>模型</span>
                            <el-select v-model="testForm.model" filterable>
                                <el-option v-for="model in providerModels(testForm.provider_id)" :key="model.id" :label="model.name" :value="model.id" />
                            </el-select>
                        </label>
                        <label v-if="!isAssistant" class="playground-field mode-field">
                            <span>输出</span>
                            <el-segmented v-model="testForm.response_mode" :options="[{ label: '对话', value: 'text' }, { label: 'JSON', value: 'json' }]" />
                        </label>
                        <label v-if="!isAssistant" class="playground-field mode-field">
                            <span>响应</span>
                            <el-segmented v-model="testForm.stream" :options="[{ label: '流式', value: true }, { label: '完整', value: false }]" />
                        </label>
                    </div>

                    <div v-if="isAssistant" class="assistant-shortcuts">
                        <span>快捷提问</span>
                        <el-button v-for="prompt in assistantPrompts" :key="prompt" size="small" :disabled="executing" @click="runQuickPrompt(prompt)">
                            {{ prompt }}
                        </el-button>
                    </div>

                    <el-alert v-if="jsonReasoningWarning" class="test-warning" type="warning" :closable="false" show-icon title="推理模型可能在 JSON 前后附加说明；业务场景仍会执行严格 JSON 校验。" />

                    <div class="playground-stage">
                        <section class="playground-pane prompt-pane">
                            <header class="pane-header">
                                <div>
                                    <strong>{{ isAssistant ? '向经营助手提问' : '输入' }}</strong>
                                    <span>{{ promptCount }}/2000</span>
                                </div>
                                <el-tag v-if="speechCapability.stt" type="success" effect="plain" size="small">
                                    {{ speechCapability.provider_name }}语音识别
                                </el-tag>
                                <el-tag v-else type="info" effect="plain" size="small">语音输入未启用</el-tag>
                            </header>
                            <el-input
                                v-model="testForm.prompt"
                                class="prompt-input"
                                type="textarea"
                                :maxlength="2000"
                                resize="none"
                                :placeholder="isAssistant ? '例如：今天还有谁的款没有打？' : '输入消息，或点击麦克风说话'"
                                @keydown.meta.enter.prevent="runTest"
                                @keydown.ctrl.enter.prevent="runTest"
                            />
                            <footer class="prompt-footer">
                                <div class="speech-input-status">
                                    <el-tooltip :content="recording ? '结束录音并识别' : '开始语音输入'" placement="top">
                                        <el-button
                                            class="voice-button"
                                            :class="{ 'is-recording': recording }"
                                            :icon="recording ? VideoPause : Microphone"
                                            circle
                                            :disabled="!speechCapability.stt || recognizing || executing"
                                            :loading="recognizing"
                                            @click="toggleRecording"
                                        />
                                    </el-tooltip>
                                    <span :class="{ recording: recording }">{{ speechInputStatus }}</span>
                                </div>
                                <el-button type="primary" :icon="VideoPlay" :loading="executing" :disabled="recording || recognizing" @click="runTest">
                                    发送
                                </el-button>
                            </footer>
                        </section>

                        <section class="playground-pane response-pane">
                            <header class="pane-header response-header">
                                <div>
                                    <strong>{{ isAssistant ? '经营助手回复' : 'AI 回复' }}</strong>
                                    <span v-if="testResult.model">
                                        {{ testResult.model }} · 首字 {{ testResult.first_token_ms || '—' }}ms · {{ testResult.latency_ms || '—' }}ms · {{ testResult.usage?.total_tokens || 0 }} tokens
                                    </span>
                                </div>
                                <div class="response-voice-actions">
                                    <el-checkbox v-if="testResult.reasoning_content" v-model="showReasoning">调试推理</el-checkbox>
                                    <el-checkbox v-if="speechCapability.tts" v-model="autoRead" @change="handleAutoReadChange">自动朗读</el-checkbox>
                                    <el-tooltip :content="speaking ? '停止播放' : '朗读回复'" placement="top">
                                        <el-button
                                            :icon="speaking ? VideoPause : Headset"
                                            circle
                                            :loading="synthesizing"
                                            :disabled="!speechCapability.tts || !testResult.content || executing"
                                            @click="speakAnswer()"
                                        />
                                    </el-tooltip>
                                </div>
                            </header>
                            <div ref="assistantScroller" class="response-content" :class="{ 'assistant-timeline': isAssistant }">
                                <template v-if="isAssistant">
                                    <div v-if="assistantMessages.length === 0" class="assistant-welcome">
                                        <el-icon><ChatDotRound /></el-icon>
                                        <strong>可以连续追问经营数据</strong>
                                        <span>例如先问“还有哪些应付款”，再问“把刚才的明细展开”。</span>
                                    </div>
                                    <article v-for="message in assistantMessages" :key="message.local_id" class="assistant-message" :class="message.role">
                                        <div class="assistant-message-head">
                                            <strong>{{ message.role === 'user' ? '我' : '经营助手' }}</strong>
                                            <span v-if="message.create_at">{{ formatMessageTime(message.create_at) }}</span>
                                        </div>
                                        <div class="assistant-bubble">
                                            <details v-if="showReasoning && message.reasoning" class="reasoning-result" open>
                                                <summary>模型推理内容</summary>
                                                <pre>{{ message.reasoning }}</pre>
                                            </details>
                                            <pre v-if="message.content">{{ message.content }}</pre>
                                            <div v-else-if="message.status === 'streaming'" class="assistant-thinking">
                                                <i /><i /><i /><span>{{ message.tool_status || '正在理解并查询业务数据' }}</span>
                                            </div>
                                            <el-alert v-if="message.error" type="error" :closable="false" :title="message.error" />
                                        </div>
                                        <div v-if="message.tool_results.length" class="assistant-tool-results">
                                            <section v-for="(tool, toolIndex) in message.tool_results" :key="`${tool.tool_key}_${toolIndex}`" class="assistant-tool-result">
                                                <header>
                                                    <div>
                                                        <strong>{{ tool.label || toolLabel(tool.tool_key) }}</strong>
                                                        <span>{{ toolResultHint(tool) }}</span>
                                                    </div>
                                                    <el-tag :type="tool.ok ? 'success' : 'danger'" effect="plain" size="small">{{ tool.ok ? '查询成功' : '查询失败' }}</el-tag>
                                                </header>
                                                <el-alert v-if="tool.error" type="error" :closable="false" :title="tool.error" />
                                                <div v-if="tool.ok && toolScalarEntries(tool).length" class="tool-summary-grid">
                                                    <div v-for="item in toolScalarEntries(tool)" :key="item.key">
                                                        <span>{{ fieldLabel(item.key) }}</span>
                                                        <strong>{{ formatToolValue(item.key, item.value) }}</strong>
                                                    </div>
                                                </div>
                                                <el-table v-if="toolRows(tool).length" :data="toolRows(tool)" size="small" max-height="320" class="tool-detail-table">
                                                    <el-table-column v-for="column in toolColumns(tool)" :key="column" :label="fieldLabel(column)" :min-width="toolColumnWidth(column)">
                                                        <template #default="{ row }">{{ formatToolValue(column, row[column]) }}</template>
                                                    </el-table-column>
                                                </el-table>
                                                <details v-if="tool.ok" class="tool-raw-detail">
                                                    <summary>查看原始查询结果</summary>
                                                    <pre>{{ prettyToolData(tool) }}</pre>
                                                </details>
                                            </section>
                                        </div>
                                    </article>
                                </template>
                                <template v-else>
                                    <details v-if="showReasoning && testResult.reasoning_content" class="reasoning-result" open>
                                        <summary>模型推理内容</summary>
                                        <pre>{{ testResult.reasoning_content }}</pre>
                                    </details>
                                    <el-alert v-if="validationWarning" class="result-error" type="warning" :closable="false" :title="validationWarning" />
                                    <el-alert v-if="testResult.error" class="result-error" type="error" :closable="false" :title="testResult.error" />
                                    <pre v-if="testResult.content">{{ testResult.content }}</pre>
                                    <div v-else class="response-empty">
                                        <el-icon><ChatDotRound /></el-icon>
                                        <span>{{ responseEmptyText }}</span>
                                    </div>
                                </template>
                            </div>
                            <footer class="response-footer">
                                <span v-if="speechCapability.tts">{{ speechOutputStatus }}</span>
                                <span v-else>回复朗读未启用</span>
                            </footer>
                        </section>
                    </div>
                </div>
            </template>

            <template v-if="section === 'logs'">
                <div class="toolbar">
                    <div class="toolbar-fields">
                        <el-input v-model="logQuery.keyword" clearable placeholder="请求ID、模型、来源或错误" :prefix-icon="Search" @keyup.enter="loadLogs" />
                        <el-select v-model="logQuery.status" clearable placeholder="全部状态" @change="loadLogs">
                            <el-option label="成功" value="success" />
                            <el-option label="失败" value="failed" />
                            <el-option label="处理中" value="processing" />
                        </el-select>
                        <el-button type="primary" @click="loadLogs">查询</el-button>
                    </div>
                    <el-button :icon="Refresh" @click="loadLogs" />
                </div>
                <el-table :data="logs" v-loading="logsLoading" row-key="id">
                    <el-table-column label="调用" min-width="230">
                        <template #default="{ row }">
                            <div class="primary-text">{{ sceneName(row.scene_key) }}</div>
                            <div class="secondary-text">{{ row.request_id }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="模型" min-width="210">
                        <template #default="{ row }">
                            <div>{{ row.model }}</div>
                            <div class="secondary-text">{{ providerName(row.provider_id) }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="来源" min-width="160">
                        <template #default="{ row }">{{ row.source_plugin || '后台测试' }}<span v-if="row.source_type"> · {{ row.source_type }}</span></template>
                    </el-table-column>
                    <el-table-column label="用量" width="130">
                        <template #default="{ row }">{{ row.total_tokens }} tokens</template>
                    </el-table-column>
                    <el-table-column label="耗时" width="100">
                        <template #default="{ row }">{{ row.latency_ms }}ms</template>
                    </el-table-column>
                    <el-table-column label="状态" width="90" align="center">
                        <template #default="{ row }"><el-tag :type="statusType(row.status)" effect="plain">{{ statusLabel(row.status) }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="结果" min-width="220" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.error_message || row.response_meta?.finish_reason || '—' }}</template>
                    </el-table-column>
                    <el-table-column label="时间" width="170">
                        <template #default="{ row }">{{ formatTime(row.create_at) }}</template>
                    </el-table-column>
                </el-table>
                <div class="pagination-row">
                    <el-pagination v-model:current-page="logQuery.page" v-model:page-size="logQuery.limit" layout="total, prev, pager, next" :total="logTotal" @current-change="loadLogs" />
                </div>
            </template>
        </div>

        <el-dialog v-model="sceneDialog.visible" :title="sceneDialog.index < 0 ? '新增业务场景' : '编辑业务场景'" width="680px" destroy-on-close>
            <el-form :model="sceneDialog.form" label-width="100px">
                <div class="dialog-grid">
                    <el-form-item label="场景名称"><el-input v-model.trim="sceneDialog.form.name" /></el-form-item>
                    <el-form-item label="场景标识"><el-input v-model.trim="sceneDialog.form.key" :disabled="sceneDialog.index >= 0" /></el-form-item>
                    <el-form-item label="模型通道">
                        <el-select v-model="sceneDialog.form.provider_id" @change="sceneDialog.form.model = ''">
                            <el-option v-for="provider in config.providers" :key="provider.id" :label="provider.name" :value="provider.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="模型">
                        <el-select v-model="sceneDialog.form.model" filterable clearable placeholder="跟随通道默认">
                            <el-option v-for="model in providerModels(sceneDialog.form.provider_id)" :key="model.id" :label="model.name" :value="model.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="输出格式">
                        <el-radio-group v-model="sceneDialog.form.response_mode">
                            <el-radio-button value="text">文本</el-radio-button>
                            <el-radio-button value="json">JSON</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="启用"><el-switch v-model="sceneDialog.form.enabled" :active-value="1" :inactive-value="0" /></el-form-item>
                    <el-form-item label="Temperature">
                        <el-input-number v-model="sceneDialog.form.temperature" :min="0" :max="2" :step="0.1" />
                    </el-form-item>
                    <el-form-item label="最大输出">
                        <el-input-number v-model="sceneDialog.form.max_tokens" :min="0" :max="128000" :step="100" />
                    </el-form-item>
                    <el-form-item label="系统提示词" class="dialog-span-2">
                        <el-input v-model="sceneDialog.form.system_prompt" type="textarea" :rows="6" />
                    </el-form-item>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="sceneDialog.visible = false">取消</el-button>
                <el-button type="primary" @click="saveScene">确定</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ChatDotRound, Connection, Delete, Headset, Microphone, Plus, Refresh, Search, Setting, VideoPause, VideoPlay } from '@element-plus/icons-vue'
import { executeAi, getAiAssistantConversation, getAiLogs, getAiSection, recognizePlaygroundSpeech, saveAiSection, streamAi, syncAiModels, synthesizePlaygroundSpeech, testAiProvider, testAiSpeech } from '../../api'

type AiModel = { id: string; name: string; owned_by: string; enabled: number }
type AiProvider = {
    local_key: string; id: string; name: string; driver: string; base_url: string; api_key: string;
    models_path: string; chat_path: string; timeout: number; connect_timeout: number; ip_version: string;
    http_version: string; enabled: number; default_model: string;
    models: AiModel[]; api_key_configured?: number; api_key_fingerprint?: string;
    persisted_id?: string; testing?: boolean; syncing?: boolean
}
type AiScene = {
    local_key: string; key: string; name: string; enabled: number; provider_id: string; model: string;
    system_prompt: string; temperature: number; max_tokens: number; response_mode: string
}
type AiSpeech = {
    enabled: number; provider: string; stt_enabled: number; tts_enabled: number; auto_read_default: number;
    api_key: string; secret_id: string; secret_key: string; region: string; stt_engine: string;
    voice: number; speed: number; pitch: number; volume: number; tencent_voice: number; tencent_speed: number; tencent_volume: number;
    baidu_auth_mode?: string;
    api_key_configured?: number; secret_id_configured?: number; secret_key_configured?: number
}
type AiIntegration = {
    key: string; name: string; description: string; source_plugin: string; enabled: number;
    scenes: string[]; capabilities: string[]
}
type AiSpeechCapability = { enabled: boolean; provider: string; provider_name: string; stt: boolean; tts: boolean }
type AssistantToolResult = {
    tool_key: string; label?: string; arguments?: Record<string, any>; ok: boolean;
    data?: Record<string, any>; error?: string; requires_confirmation?: boolean
}
type AssistantMessage = {
    local_id: string; id?: number; request_id?: string; role: 'user' | 'assistant'; content: string;
    reasoning: string; tool_results: AssistantToolResult[]; tool_status: string; status: string;
    error: string; model?: string; latency_ms?: number; total_tokens?: number; create_at: number
}

const uid = () => `${Date.now()}_${Math.random().toString(36).slice(2)}`
const props = withDefaults(defineProps<{ section?: 'model' | 'integration' | 'speech' | 'playground' | 'assistant' | 'logs' }>(), { section: 'model' })
const section = computed(() => props.section)
const isAssistant = computed(() => section.value === 'assistant')
const canSave = computed(() => ['model', 'integration', 'speech'].includes(section.value))
const pageTitleMap: Record<string, string> = {
    model: '模型与场景', integration: '业务接入', speech: '语音服务', playground: '在线测试', assistant: '经营助手', logs: '调用日志'
}
const pageDescriptionMap: Record<string, string> = {
    integration: '控制 AI 与业务插件之间的数据和工具通信',
    speech: '配置语音识别与回复朗读服务商',
    playground: '使用已保存的模型与场景进行调试',
    assistant: '查询本人待办、回收进度、库存及应收应付，结果严格遵循当前账号权限',
    logs: '查看模型调用、耗时、Token 与错误记录'
}
const pageTitle = computed(() => pageTitleMap[section.value] || 'AI 能力中心')
const pageDescription = computed(() => pageDescriptionMap[section.value] || '')
const emptyProvider = (): AiProvider => ({
    local_key: uid(), id: `provider_${Date.now().toString().slice(-6)}`, name: '新模型通道',
    driver: 'openai_compatible', base_url: '', api_key: '', models_path: '/v1/models',
    chat_path: '/v1/chat/completions', timeout: 60, connect_timeout: 30, ip_version: 'ipv4',
    http_version: '1.1', enabled: 1, default_model: '', models: []
})
const emptyScene = (): AiScene => ({
    local_key: uid(), key: '', name: '', enabled: 1, provider_id: '', model: '',
    system_prompt: '', temperature: 0.2, max_tokens: 0, response_mode: 'text'
})
const config = reactive<{ enabled: number; default_provider_id: string; default_model: string; redact_sensitive: number; log_content: number; speech: AiSpeech; speech_capability: AiSpeechCapability; providers: AiProvider[]; scenes: AiScene[]; integrations: AiIntegration[] }>({
    enabled: 0, default_provider_id: '', default_model: '', redact_sensitive: 1, log_content: 0,
    speech: { enabled: 0, provider: 'baidu', stt_enabled: 1, tts_enabled: 1, auto_read_default: 0, api_key: '', secret_id: '', secret_key: '', region: 'ap-shanghai', stt_engine: '16k_zh', voice: 0, speed: 5, pitch: 5, volume: 5, tencent_voice: 1001, tencent_speed: 0, tencent_volume: 0 },
    speech_capability: { enabled: false, provider: 'baidu', provider_name: '百度智能云', stt: false, tts: false },
    providers: [], scenes: [], integrations: []
})
const loading = ref(false)
const saving = ref(false)
const enabledProviders = computed(() => config.providers.filter((item) => item.enabled).length)
const modelCount = computed(() => config.providers.reduce((total, item) => total + item.models.length, 0))
const enabledScenes = computed(() => config.scenes.filter((item) => item.enabled).length)
const enabledProviderOptions = computed(() => config.providers.filter((item) => item.enabled))
const providerModels = (id: string) => config.providers.find((item) => item.id === id)?.models.filter((item) => item.enabled) || []
const defaultProviderModels = computed(() => providerModels(config.default_provider_id))
const baiduDirectAuth = computed(() => config.speech.provider === 'baidu' && (
    config.speech.api_key.startsWith('bce-v3/')
    || (config.speech.api_key === '******' && config.speech.baidu_auth_mode === 'api_key')
))
const speechCapability = computed(() => config.speech_capability)

const normalizeClientConfig = (data: any) => {
    Object.assign(config, data || {})
    config.speech = Object.assign({ enabled: 0, provider: 'baidu', stt_enabled: 1, tts_enabled: 1, auto_read_default: 0, api_key: '', secret_id: '', secret_key: '', region: 'ap-shanghai', stt_engine: '16k_zh', voice: 0, speed: 5, pitch: 5, volume: 5, tencent_voice: 1001, tencent_speed: 0, tencent_volume: 0 }, data?.speech || {})
    config.speech_capability = Object.assign({ enabled: false, provider: 'baidu', provider_name: '百度智能云', stt: false, tts: false }, data?.speech_capability || {})
    config.providers = (config.providers || []).map((item: any) => ({ ...item, local_key: uid(), persisted_id: item.id, models: item.models || [], testing: false, syncing: false }))
    config.scenes = (config.scenes || []).map((item: any) => ({ ...item, local_key: uid() }))
    config.integrations = (data?.integrations || []).map((item: any) => ({ ...item, scenes: item.scenes || [], capabilities: item.capabilities || [] }))
}
const loadConfig = async () => {
    loading.value = true
    try { normalizeClientConfig((await getAiSection(isAssistant.value ? 'playground' : section.value)).data || {}) } finally { loading.value = false }
}
const payload = () => ({
    enabled: config.enabled,
    default_provider_id: config.default_provider_id,
    default_model: config.default_model,
    redact_sensitive: config.redact_sensitive,
    log_content: config.log_content,
    integrations: config.integrations.map(({ key, enabled }) => ({ key, enabled })),
    speech: Object.fromEntries(Object.entries(config.speech).filter(([key]) => !key.endsWith('_configured'))),
    providers: config.providers.map(({ local_key, persisted_id, testing, syncing, api_key_configured, api_key_fingerprint, ...item }) => item),
    scenes: config.scenes.map(({ local_key, ...item }) => item)
})
const saveConfig = async (showMessage = true) => {
    saving.value = true
    try {
        normalizeClientConfig((await saveAiSection(section.value, payload())).data || {})
        if (showMessage) ElMessage.success('AI配置已保存')
        return true
    } finally { saving.value = false }
}
const addProvider = () => config.providers.push(emptyProvider())
const removeProvider = async (index: number) => {
    await ElMessageBox.confirm('删除后，使用该通道的场景需要重新选择模型通道。', '删除模型通道', { type: 'warning' })
    const [removed] = config.providers.splice(index, 1)
    if (config.default_provider_id === removed.id) {
        config.default_provider_id = config.providers[0]?.id || ''
        config.default_model = ''
    }
    config.scenes.forEach((scene) => {
        if (scene.provider_id === removed.id) {
            scene.provider_id = config.default_provider_id
            scene.model = ''
        }
    })
}
const testProvider = async (provider: AiProvider) => {
    provider.testing = true
    try {
        const data: any = (await testAiProvider(provider)).data || {}
        ElMessage.success(`连接成功，发现 ${data.model_count || 0} 个模型`)
    } finally { provider.testing = false }
}
const syncModels = async (provider: AiProvider) => {
    provider.syncing = true
    try {
        const data: any = (await syncAiModels(provider)).data || {}
        provider.models = data.models || []
        if (!provider.default_model && provider.models.length) provider.default_model = provider.models[0].id
        if (provider.id === config.default_provider_id && !config.default_model && provider.models.length) config.default_model = provider.default_model
        ElMessage.success(`已同步 ${data.total || 0} 个模型`)
    } finally { provider.syncing = false }
}
const providerName = (id: string) => config.providers.find((item) => item.id === id)?.name || id || '默认通道'
const sceneName = (key: string) => config.scenes.find((item) => item.key === key)?.name || key

const sceneDialog = reactive<{ visible: boolean; index: number; form: AiScene }>({ visible: false, index: -1, form: emptyScene() })
const openScene = (row?: AiScene, index = -1) => {
    sceneDialog.index = index
    sceneDialog.form = row ? JSON.parse(JSON.stringify(row)) : { ...emptyScene(), provider_id: config.default_provider_id }
    sceneDialog.visible = true
}
const saveScene = () => {
    const scene = sceneDialog.form
    if (!scene.name.trim() || !/^[a-z][a-z0-9_.]{1,99}$/.test(scene.key)) {
        ElMessage.warning('请填写场景名称；场景标识需以字母开头，只能包含小写字母、数字、点和下划线')
        return
    }
    const duplicated = config.scenes.some((item, index) => item.key === scene.key && index !== sceneDialog.index)
    if (duplicated) { ElMessage.warning('场景标识不能重复'); return }
    if (sceneDialog.index >= 0) config.scenes.splice(sceneDialog.index, 1, { ...scene, local_key: config.scenes[sceneDialog.index].local_key })
    else config.scenes.push({ ...scene, local_key: uid() })
    sceneDialog.visible = false
}
const removeScene = async (index: number) => {
    await ElMessageBox.confirm('业务插件继续调用该场景时将返回“场景未注册”。', '删除业务场景', { type: 'warning' })
    config.scenes.splice(index, 1)
}

const testForm = reactive({ scene_key: 'general', provider_id: '', model: '', prompt: '', response_mode: 'text', stream: true })
const assistantPrompts = ['回收业务现在有哪些待处理工作？', '当前库存情况怎么样？', '还有哪些应付款没处理？', '还有哪些应收款没收回？']
const testResult = reactive<any>({})
const assistantMessages = ref<AssistantMessage[]>([])
const assistantConversationId = ref(0)
const assistantConversationTitle = ref('')
const assistantScroller = ref<HTMLElement | null>(null)
const assistantConversationStorageKey = 'hsx_ai_admin_assistant_conversation_id'
const executing = ref(false)
const recognizing = ref(false)
const recording = ref(false)
const recordingSeconds = ref(0)
const synthesizing = ref(false)
const speaking = ref(false)
const autoRead = ref(false)
const showReasoning = ref(false)
const showAdvanced = ref(false)
const validationWarning = ref('')
const promptCount = computed(() => testForm.prompt.length)
const responseEmptyText = computed(() => {
    if (!executing.value) return '等待发送消息'
    const reasoningLength = String(testResult.reasoning_content || '').length
    return reasoningLength > 0 ? `AI 正在思考，已持续收到 ${reasoningLength} 个字符` : 'AI 正在连接模型'
})
const activeConversationLabel = computed(() => {
    if (isAssistant.value && assistantConversationTitle.value) return assistantConversationTitle.value
    const scene = config.scenes.find((item) => item.key === testForm.scene_key)?.name || '通用对话'
    const provider = config.providers.find((item) => item.id === testForm.provider_id)?.name || '默认通道'
    return `${scene} · ${provider} · ${testForm.model || '默认模型'}`
})

const normalizeToolResults = (rows: any): AssistantToolResult[] => Array.isArray(rows)
    ? rows.filter((row) => row && typeof row === 'object').map((row) => ({
        tool_key: String(row.tool_key || ''), label: String(row.label || ''), arguments: row.arguments || {},
        ok: Boolean(row.ok), data: row.data || {}, error: String(row.error || ''),
        requires_confirmation: Boolean(row.requires_confirmation)
    }))
    : []
const toAssistantMessage = (row: any): AssistantMessage => ({
    local_id: String(row.id || row.local_id || uid()), id: Number(row.id || 0), request_id: String(row.request_id || ''),
    role: row.role === 'user' ? 'user' : 'assistant', content: String(row.content || ''),
    reasoning: String(row.reasoning || row.reasoning_content || ''), tool_results: normalizeToolResults(row.tool_results),
    tool_status: '', status: String(row.status || 'success'), error: String(row.error || ''), model: String(row.model || ''),
    latency_ms: Number(row.latency_ms || 0), total_tokens: Number(row.total_tokens || 0), create_at: Number(row.create_at || Math.floor(Date.now() / 1000))
})
const scrollAssistantToBottom = async () => {
    await nextTick()
    if (assistantScroller.value) assistantScroller.value.scrollTop = assistantScroller.value.scrollHeight
}
const rememberAssistantConversation = (id: number) => {
    assistantConversationId.value = Math.max(0, Number(id || 0))
    try {
        if (assistantConversationId.value) sessionStorage.setItem(assistantConversationStorageKey, String(assistantConversationId.value))
        else sessionStorage.removeItem(assistantConversationStorageKey)
    } catch (_) {}
}
const startNewAssistantConversation = () => {
    if (executing.value) return
    rememberAssistantConversation(0)
    assistantConversationTitle.value = ''
    assistantMessages.value = []
    Object.keys(testResult).forEach((key) => delete testResult[key])
}
const loadAssistantConversation = async () => {
    let conversationId = 0
    try { conversationId = Number(sessionStorage.getItem(assistantConversationStorageKey) || 0) } catch (_) {}
    if (!conversationId) return
    try {
        const data: any = (await getAiAssistantConversation(conversationId)).data || {}
        rememberAssistantConversation(Number(data.conversation?.id || 0))
        assistantConversationTitle.value = String(data.conversation?.title || '')
        assistantMessages.value = (data.messages || []).map(toAssistantMessage)
        const latest = [...assistantMessages.value].reverse().find((message) => message.role === 'assistant' && message.content)
        if (latest) Object.assign(testResult, { content: latest.content, model: latest.model, latency_ms: latest.latency_ms })
        await scrollAssistantToBottom()
    } catch (_) {
        startNewAssistantConversation()
    }
}

const toolLabel = (key: string) => ({
    'hsx_erp.stock.summary': '库存总览', 'hsx_erp.payable.summary': '应付总览',
    'hsx_erp.payable.search': '应付款明细', 'hsx_erp.receivable.summary': '应收总览',
    'hsx_erp.receivable.search': '应收款明细'
}[key] || key || '业务查询')
const toolData = (tool: AssistantToolResult) => tool.data && typeof tool.data === 'object' ? tool.data : {}
const toolRows = (tool: AssistantToolResult): Record<string, any>[] => {
    const data: any = toolData(tool)
    for (const key of ['items', 'warehouses', 'rows', 'data']) {
        if (Array.isArray(data[key])) return data[key].filter((row: any) => row && typeof row === 'object').slice(0, 20)
    }
    return []
}
const preferredToolColumns = ['party_name', 'payable_no', 'receivable_no', 'source_no', 'amount', 'settled_amount', 'remain_amount', 'finance_status_name', 'task_assignee_name', 'occurred_at_text', 'warehouse_name', 'stock_count', 'stock_cost']
const toolColumns = (tool: AssistantToolResult) => {
    const rows = toolRows(tool)
    if (!rows.length) return []
    const keys = Array.from(new Set(rows.flatMap((row) => Object.keys(row))))
    const preferred = preferredToolColumns.filter((key) => keys.includes(key))
    return (preferred.length ? preferred : keys.filter((key) => !['id', 'party_id', 'source_id', 'web_path', 'miniapp_path'].includes(key))).slice(0, 8)
}
const toolScalarEntries = (tool: AssistantToolResult) => Object.entries(toolData(tool))
    .filter(([key, value]) => !['items', 'warehouses', 'rows', 'data', 'entry', 'generated_at', 'number_field', 'side', 'scope'].includes(key) && !Array.isArray(value) && (value === null || typeof value !== 'object'))
    .slice(0, 8).map(([key, value]) => ({ key, value }))
const fieldLabel = (key: string) => ({
    open_count: '待处理笔数', original_amount: '账款原额', settled_amount: '已结算', remaining_amount: '剩余金额',
    total_returned: '返回明细', stock_count: '库存数量', stock_cost: '库存成本', warehouse_name: '仓库',
    party_name: '往来单位', payable_no: '应付单号', receivable_no: '应收单号', source_no: '来源单号',
    amount: '账款金额', remain_amount: '剩余金额', finance_status_name: '状态', task_assignee_name: '负责人',
    occurred_at_text: '发生时间', category_name: '业务类型', business_reason: '业务原因'
}[key] || key)
const moneyFields = new Set(['original_amount', 'settled_amount', 'remaining_amount', 'stock_cost', 'amount', 'remain_amount'])
const formatToolValue = (key: string, value: any) => {
    if (value === null || value === undefined || value === '') return '—'
    if (moneyFields.has(key)) return `¥${Number(value || 0).toFixed(2)}`
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}
const toolColumnWidth = (key: string) => ['party_name', 'business_reason'].includes(key) ? 150 : (['payable_no', 'receivable_no', 'source_no', 'occurred_at_text'].includes(key) ? 140 : 110)
const toolResultHint = (tool: AssistantToolResult) => {
    const rows = toolRows(tool)
    return rows.length ? `已返回 ${rows.length} 条业务明细` : '本次查询的结构化业务结果'
}
const prettyToolData = (tool: AssistantToolResult) => JSON.stringify(toolData(tool), null, 2)
const formatMessageTime = (value: number) => value ? new Date(value * 1000).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' }) : ''
const speechInputStatus = computed(() => {
    if (recognizing.value) return '正在识别语音'
    if (recording.value) return `录音中 ${String(Math.floor(recordingSeconds.value / 60)).padStart(2, '0')}:${String(recordingSeconds.value % 60).padStart(2, '0')}`
    return speechCapability.value.stt ? '点击麦克风开始说话' : '请先在语音服务中启用识别'
})
const speechOutputStatus = computed(() => {
    if (synthesizing.value) return '正在生成语音'
    if (speaking.value) return '正在播放 AI 回复'
    return '可朗读当前回复'
})
const jsonReasoningWarning = computed(() => testForm.response_mode === 'json' && /(?:^|[-_])r1(?:$|[-_])/i.test(testForm.model))
const applyScene = () => {
    const scene = config.scenes.find((item) => item.key === testForm.scene_key)
    testForm.provider_id = scene?.provider_id || config.default_provider_id
    const provider = config.providers.find((item) => item.id === testForm.provider_id)
    testForm.model = scene?.model || provider?.default_model || config.default_model || ''
}
const runAssistantTest = async () => {
    const prompt = testForm.prompt.trim()
    const userMessage = toAssistantMessage({ role: 'user', content: prompt, status: 'success' })
    const assistantMessage = toAssistantMessage({ role: 'assistant', content: '', status: 'streaming' })
    assistantMessages.value.push(userMessage, assistantMessage)
    testForm.prompt = ''
    await scrollAssistantToBottom()
    Object.keys(testResult).forEach((key) => delete testResult[key])
    validationWarning.value = ''
    showReasoning.value = false
    stopAnswerAudio()
    if (autoRead.value) void unlockAnswerAudio()
    executing.value = true
    let completed = false
    const appendToolResult = (tool: any) => {
        const normalized = normalizeToolResults([tool])[0]
        if (!normalized) return
        const signature = `${normalized.tool_key}:${JSON.stringify(normalized.arguments || {})}:${JSON.stringify(normalized.data || {})}`
        const exists = assistantMessage.tool_results.some((item) => `${item.tool_key}:${JSON.stringify(item.arguments || {})}:${JSON.stringify(item.data || {})}` === signature)
        if (!exists) assistantMessage.tool_results.push(normalized)
    }
    try {
        await streamAi({
            ...testForm,
            prompt,
            conversation_id: assistantConversationId.value
        }, (event) => {
            if (event.type === 'conversation') {
                rememberAssistantConversation(Number(event.conversation_id || 0))
                assistantConversationTitle.value = String(event.conversation_title || assistantConversationTitle.value)
            }
            if (event.type === 'meta') {
                assistantMessage.model = String(event.model || '')
                Object.assign(testResult, event)
            }
            if (event.type === 'tool') {
                const label = toolLabel(String(event.tool_key || ''))
                assistantMessage.tool_status = event.status === 'running' ? `正在${label}` : `${label}${event.status === 'success' ? '完成' : '失败'}`
            }
            if (event.type === 'tool_result') appendToolResult(event.item)
            if (event.type === 'reasoning') {
                assistantMessage.reasoning += String(event.delta || '')
                testResult.reasoning_content = assistantMessage.reasoning
            }
            if (event.type === 'content') {
                assistantMessage.content += String(event.delta || '')
                testResult.content = assistantMessage.content
                void scrollAssistantToBottom()
            }
            if (event.type === 'done') {
                rememberAssistantConversation(Number(event.conversation_id || assistantConversationId.value))
                assistantConversationTitle.value = String(event.conversation_title || assistantConversationTitle.value)
                normalizeToolResults(event.tool_results).forEach(appendToolResult)
                assistantMessage.id = Number(event.message_id || 0)
                assistantMessage.model = String(event.model || assistantMessage.model || '')
                assistantMessage.latency_ms = Number(event.latency_ms || 0)
                assistantMessage.total_tokens = Number(event.usage?.total_tokens || 0)
                assistantMessage.status = 'success'
                assistantMessage.tool_status = ''
                Object.assign(testResult, event, { content: assistantMessage.content, reasoning_content: assistantMessage.reasoning })
            }
            if (event.type === 'error') {
                assistantMessage.error = String(event.message || '经营助手调用失败')
                assistantMessage.status = 'failed'
                testResult.error = assistantMessage.error
            }
        })
        completed = Boolean(assistantMessage.content)
    } catch (error: any) {
        assistantMessage.error = error?.message || error?.msg || '经营助手调用失败'
        assistantMessage.status = 'failed'
        testResult.error = assistantMessage.error
        ElMessage.error(assistantMessage.error)
    } finally {
        if (assistantMessage.status === 'streaming') assistantMessage.status = completed ? 'success' : 'failed'
        executing.value = false
        await scrollAssistantToBottom()
    }
    if (completed && autoRead.value && speechCapability.value.tts) void speakAnswer(true)
}
const runTest = async () => {
    if (!testForm.prompt.trim()) { ElMessage.warning('请输入测试消息'); return }
    if (!testForm.model) { ElMessage.warning('请先在对话设置中选择模型'); return }
    if (isAssistant.value) { await runAssistantTest(); return }
    if (autoRead.value) void unlockAnswerAudio()
    executing.value = true
    stopAnswerAudio()
    let completed = false
    try {
        Object.keys(testResult).forEach((key) => delete testResult[key])
        validationWarning.value = ''
        showReasoning.value = false
        if (testForm.stream) {
            await streamAi(testForm, (event) => {
                if (event.type === 'meta') Object.assign(testResult, event)
                if (event.type === 'reasoning') testResult.reasoning_content = (testResult.reasoning_content || '') + (event.delta || '')
                if (event.type === 'content') testResult.content = (testResult.content || '') + (event.delta || '')
                if (event.type === 'done') Object.assign(testResult, event)
                if (event.type === 'error') testResult.error = event.message || '模型流式调用失败'
            })
        } else {
            Object.assign(testResult, (await executeAi(testForm)).data || {})
        }
        completed = Boolean(testResult.content)
    } catch (error: any) {
        const message = error?.message || error?.msg || '模型调用失败'
        if (testForm.response_mode === 'json' && testResult.content && message.includes('模型未按要求返回有效JSON')) {
            delete testResult.error
            validationWarning.value = '模型已返回文本，但未满足 JSON 格式要求；回答内容已为你保留。'
            completed = true
            ElMessage.warning('回答不是有效 JSON，已按文本保留')
        } else {
            testResult.error = message
            ElMessage.error(testResult.error)
        }
    } finally { executing.value = false }
    if (completed && autoRead.value && speechCapability.value.tts) void speakAnswer(true)
}
const runQuickPrompt = async (prompt: string) => {
    if (executing.value) return
    testForm.prompt = prompt
    await runTest()
}

let recorderContext: AudioContext | null = null
let recorderSource: MediaStreamAudioSourceNode | null = null
let recorderProcessor: ScriptProcessorNode | null = null
let recorderStream: MediaStream | null = null
let recorderTimer: number | null = null
let recorderChunks: Float32Array[] = []
let recorderSampleRate = 16000

const mergeAudioChunks = (chunks: Float32Array[]) => {
    const result = new Float32Array(chunks.reduce((total, chunk) => total + chunk.length, 0))
    let offset = 0
    chunks.forEach((chunk) => { result.set(chunk, offset); offset += chunk.length })
    return result
}
const resampleAudio = (input: Float32Array, inputRate: number, outputRate = 16000) => {
    if (inputRate === outputRate) return input
    const ratio = inputRate / outputRate
    const result = new Float32Array(Math.max(1, Math.round(input.length / ratio)))
    for (let index = 0; index < result.length; index++) {
        const start = Math.floor(index * ratio)
        const end = Math.min(input.length, Math.floor((index + 1) * ratio))
        let total = 0
        for (let cursor = start; cursor < end; cursor++) total += input[cursor]
        result[index] = total / Math.max(1, end - start)
    }
    return result
}
const encodeWav = (samples: Float32Array, sampleRate = 16000) => {
    const buffer = new ArrayBuffer(44 + samples.length * 2)
    const view = new DataView(buffer)
    const writeText = (offset: number, text: string) => {
        for (let index = 0; index < text.length; index++) view.setUint8(offset + index, text.charCodeAt(index))
    }
    writeText(0, 'RIFF')
    view.setUint32(4, 36 + samples.length * 2, true)
    writeText(8, 'WAVE')
    writeText(12, 'fmt ')
    view.setUint32(16, 16, true)
    view.setUint16(20, 1, true)
    view.setUint16(22, 1, true)
    view.setUint32(24, sampleRate, true)
    view.setUint32(28, sampleRate * 2, true)
    view.setUint16(32, 2, true)
    view.setUint16(34, 16, true)
    writeText(36, 'data')
    view.setUint32(40, samples.length * 2, true)
    let offset = 44
    samples.forEach((sample) => {
        const value = Math.max(-1, Math.min(1, sample))
        view.setInt16(offset, value < 0 ? value * 0x8000 : value * 0x7fff, true)
        offset += 2
    })
    return new Blob([buffer], { type: 'audio/wav' })
}
const releaseRecorder = async () => {
    if (recorderTimer !== null) window.clearInterval(recorderTimer)
    recorderTimer = null
    recorderProcessor?.disconnect()
    recorderSource?.disconnect()
    recorderStream?.getTracks().forEach((track) => track.stop())
    if (recorderContext && recorderContext.state !== 'closed') await recorderContext.close()
    recorderProcessor = null
    recorderSource = null
    recorderStream = null
    recorderContext = null
}
const startRecording = async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
        ElMessage.warning('当前浏览器不支持麦克风录音')
        return
    }
    // 麦克风按钮属于用户手势，顺便提前解锁稍后异步返回的自动朗读。
    void unlockAnswerAudio()
    try {
        recorderStream = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true } })
        recorderContext = new AudioContext()
        if (recorderContext.state === 'suspended') await recorderContext.resume()
        recorderSampleRate = recorderContext.sampleRate
        recorderChunks = []
        recorderSource = recorderContext.createMediaStreamSource(recorderStream)
        recorderProcessor = recorderContext.createScriptProcessor(4096, 1, 1)
        recorderProcessor.onaudioprocess = (event) => recorderChunks.push(new Float32Array(event.inputBuffer.getChannelData(0)))
        recorderSource.connect(recorderProcessor)
        recorderProcessor.connect(recorderContext.destination)
        recordingSeconds.value = 0
        recording.value = true
        recorderTimer = window.setInterval(() => {
            recordingSeconds.value += 1
            if (recordingSeconds.value >= 60) void stopRecording()
        }, 1000)
    } catch (error: any) {
        await releaseRecorder()
        ElMessage.error(error?.name === 'NotAllowedError' ? '请允许浏览器使用麦克风' : '麦克风启动失败')
    }
}
const stopRecording = async () => {
    if (!recording.value) return
    recording.value = false
    const chunks = recorderChunks.slice()
    const sampleRate = recorderSampleRate
    await releaseRecorder()
    if (!chunks.length || recordingSeconds.value < 1) {
        ElMessage.warning('录音时间太短，请重新录制')
        return
    }
    recognizing.value = true
    let shouldSend = false
    try {
        const wav = encodeWav(resampleAudio(mergeAudioChunks(chunks), sampleRate))
        const data: any = (await recognizePlaygroundSpeech(wav)).data || {}
        testForm.prompt = String(data.text || '').trim()
        if (!testForm.prompt) throw new Error('没有识别到有效内容')
        shouldSend = true
    } catch (error: any) {
        ElMessage.error(error?.msg || error?.message || '语音识别失败')
    } finally { recognizing.value = false }
    if (shouldSend) await runTest()
}
const toggleRecording = () => recording.value ? stopRecording() : startRecording()

let playbackContext: AudioContext | null = null
let answerAudioBuffer: AudioBuffer | null = null
let answerAudioSource: AudioBufferSourceNode | null = null
let answerAudioText = ''
const stopAnswerAudio = () => {
    if (answerAudioSource) {
        try { answerAudioSource.stop() } catch (_) {}
        answerAudioSource.disconnect()
        answerAudioSource = null
    }
    speaking.value = false
}
const unlockAnswerAudio = async () => {
    if (!playbackContext || playbackContext.state === 'closed') playbackContext = new AudioContext()
    if (playbackContext.state === 'suspended') await playbackContext.resume()
    return playbackContext
}
const handleAutoReadChange = (enabled: boolean | string | number) => {
    if (Boolean(enabled)) void unlockAnswerAudio()
}
const base64AudioBuffer = (base64: string) => {
    const binary = window.atob(base64)
    const bytes = new Uint8Array(binary.length)
    for (let index = 0; index < binary.length; index++) bytes[index] = binary.charCodeAt(index)
    return bytes.buffer
}
const readableAnswer = () => String(testResult.content || '')
    .replace(/```[\s\S]*?```/g, ' ')
    .replace(/[#*_>`~\[\]{}]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
const playAnswerAudio = async () => {
    if (!answerAudioBuffer) return
    try {
        const context = await unlockAnswerAudio()
        stopAnswerAudio()
        const source = context.createBufferSource()
        source.buffer = answerAudioBuffer
        source.connect(context.destination)
        answerAudioSource = source
        source.onended = () => {
            if (answerAudioSource === source) answerAudioSource = null
            speaking.value = false
        }
        source.start(0)
        speaking.value = true
    } catch (error: any) {
        speaking.value = false
        throw error
    }
}
const speakAnswer = async (automatic = false) => {
    const text = readableAnswer()
    if (!text || !speechCapability.value.tts) return
    if (speaking.value) { stopAnswerAudio(); return }
    if (answerAudioBuffer && answerAudioText === text) { await playAnswerAudio(); return }
    synthesizing.value = true
    try {
        const data: any = (await synthesizePlaygroundSpeech(text)).data || {}
        if (!data.audio_base64) throw new Error('语音服务未返回音频')
        const context = await unlockAnswerAudio()
        answerAudioBuffer = await context.decodeAudioData(base64AudioBuffer(String(data.audio_base64)))
        answerAudioText = text
        await playAnswerAudio()
    } catch (error: any) {
        ElMessage.error(error?.msg || error?.message || (automatic ? '自动朗读失败，可点击耳机按钮重试' : '语音合成失败'))
    } finally { synthesizing.value = false }
}

const logsLoading = ref(false)
const logs = ref<any[]>([])
const logTotal = ref(0)
const logQuery = reactive({ keyword: '', status: '', page: 1, limit: 15 })
const loadLogs = async () => {
    logsLoading.value = true
    try {
        const data: any = (await getAiLogs(logQuery)).data || {}
        logs.value = data.data || []
        logTotal.value = data.total || 0
    } finally { logsLoading.value = false }
}
const statusLabel = (status: string) => ({ success: '成功', failed: '失败', processing: '处理中', pending: '等待中' }[status] || status)
const statusType = (status: string) => ({ success: 'success', failed: 'danger', processing: 'warning', pending: 'info' }[status] || 'info') as any
const formatTime = (value: any) => {
    const timestamp = Number(value || 0)
    return timestamp ? new Date(timestamp * 1000).toLocaleString('zh-CN', { hour12: false }) : '—'
}
const speechTesting = ref(false)
const speechTestAudio = ref('')
const speechTestMessage = ref('')
const runSpeechTest = async () => {
    speechTesting.value = true
    speechTestAudio.value = ''
    try {
        const data: any = (await testAiSpeech(payload().speech)).data || {}
        speechTestAudio.value = data.audio_base64 ? `data:${data.mime_type || 'audio/mpeg'};base64,${data.audio_base64}` : ''
        speechTestMessage.value = `${data.message || '连接成功'} · ${data.latency_ms || 0}ms`
        ElMessage.success(data.message || '语音服务连接成功')
    } finally { speechTesting.value = false }
}
const onSpeechProviderChange = () => {
    config.speech.api_key = ''
    config.speech.secret_id = ''
    config.speech.secret_key = ''
    config.speech.api_key_configured = 0
    config.speech.secret_id_configured = 0
    config.speech.secret_key_configured = 0
    speechTestAudio.value = ''
}

onMounted(async () => {
    if (section.value === 'logs') { await loadLogs(); return }
    await loadConfig()
    if (isAssistant.value) {
        testForm.scene_key = 'business.admin_assistant'
        testForm.response_mode = 'text'
        testForm.stream = true
    }
    if (section.value === 'playground' || isAssistant.value) applyScene()
    if (isAssistant.value) await loadAssistantConversation()
})
onBeforeUnmount(() => {
    recording.value = false
    void releaseRecorder()
    stopAnswerAudio()
    if (playbackContext && playbackContext.state !== 'closed') void playbackContext.close()
    playbackContext = null
    answerAudioBuffer = null
})
</script>

<style lang="scss" scoped>
.ai-config-page { min-height: 100%; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.section-title { margin: 0 0 14px; font-size: 16px; font-weight: 600; }.section-gap { margin-top: 28px; padding-top: 22px; border-top: 1px solid var(--el-border-color-lighter); }
.header-status { display: flex; align-items: center; gap: 10px; margin-top: 8px; color: var(--el-text-color-secondary); font-size: 13px; }
.header-actions, .provider-title, .toolbar, .toolbar-fields, .conversation-actions { display: flex; align-items: center; gap: 10px; }
.toolbar { justify-content: space-between; min-height: 48px; margin-bottom: 14px; padding: 10px 12px; background: var(--el-fill-color-lighter); border-radius: 6px; }
.toolbar-fields .el-input { width: 300px; }.toolbar-fields .el-select { width: 220px; }.toolbar-fields.compact { color: var(--el-text-color-regular); font-size: 13px; }
.provider-list { display: grid; gap: 14px; }
.integration-alert { margin-bottom: 14px; }
.integration-list { display: grid; gap: 12px; max-width: 980px; }
.integration-panel { display: flex; align-items: center; justify-content: space-between; gap: 24px; min-height: 112px; padding: 18px; border: 1px solid var(--el-border-color-light); border-radius: 6px; background: var(--el-bg-color); }
.integration-copy { min-width: 0; }.integration-title, .integration-capabilities, .integration-switch { display: flex; align-items: center; gap: 8px; }
.integration-copy p { margin: 8px 0; color: var(--el-text-color-regular); font-size: 13px; line-height: 1.6; }
.integration-capabilities { flex-wrap: wrap; }.integration-scenes { margin-top: 9px; color: var(--el-text-color-secondary); font-size: 12px; }
.integration-switch { flex: 0 0 auto; color: var(--el-text-color-secondary); font-size: 13px; }
.speech-panel { max-width: 860px; padding: 18px; border: 1px solid var(--el-border-color-light); border-radius: 6px; }
.speech-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }.speech-actions { display: flex; align-items: center; gap: 10px; }
.speech-header p { margin: 6px 0 0; color: var(--el-text-color-secondary); font-size: 13px; }
.speech-form { margin-top: 22px; }.speech-form :deep(.el-select), .speech-form :deep(.el-slider) { width: 100%; }
.speech-default-setting { display: flex; min-width: 0; align-items: center; gap: 10px; }
.speech-test-result { display: flex; align-items: center; gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--el-border-color-lighter); color: var(--el-color-success); font-size: 13px; }.speech-test-result audio { width: 320px; height: 36px; }
.provider-panel { border: 1px solid var(--el-border-color-light); border-radius: 6px; background: var(--el-bg-color); }
.provider-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 54px; padding: 0 16px; border-bottom: 1px solid var(--el-border-color-lighter); }
.provider-title span:last-child { color: var(--el-text-color-secondary); font-size: 13px; }
.provider-form { padding: 18px 18px 2px; }.form-grid, .dialog-grid, .test-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 18px; }
.grid-span-2, .dialog-span-2 { grid-column: 1 / -1; }.provider-form :deep(.el-select), .provider-form :deep(.el-input-number) { width: 100%; }
.unit { margin-left: 8px; color: var(--el-text-color-secondary); }
.secret-field { width: 100%; }.secret-warning { display: block; margin-top: 5px; color: var(--el-color-warning); font-size: 12px; }
.primary-text { color: var(--el-text-color-primary); font-weight: 500; }.secondary-text { margin-top: 3px; color: var(--el-text-color-secondary); font-size: 12px; }
.playground-workspace { border: 1px solid var(--el-border-color-light); border-radius: 6px; overflow: hidden; background: var(--el-bg-color); }
.playground-conversation-bar { display: flex; min-height: 58px; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 16px; padding: 10px 16px; border-bottom: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-extra-light); }
.playground-conversation-bar > div { display: flex; min-width: 0; flex-direction: column; gap: 4px; }.playground-conversation-bar strong { font-size: 14px; }.playground-conversation-bar span { overflow: hidden; color: var(--el-text-color-secondary); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.playground-toolbar { display: grid; grid-template-columns: minmax(150px, .8fr) minmax(150px, .8fr) minmax(210px, 1.2fr) 180px 180px; gap: 14px; padding: 14px 16px; border-bottom: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-extra-light); }
.playground-toolbar.assistant-toolbar { grid-template-columns: minmax(180px, 240px) minmax(240px, 360px); }
.assistant-shortcuts { display: flex; min-height: 52px; box-sizing: border-box; align-items: center; flex-wrap: wrap; gap: 8px; padding: 10px 16px; border-bottom: 1px solid var(--el-border-color-lighter); }
.assistant-shortcuts > span { margin-right: 4px; color: var(--el-text-color-secondary); font-size: 12px; }
.playground-field { display: flex; min-width: 0; flex-direction: column; gap: 6px; }.playground-field > span { color: var(--el-text-color-secondary); font-size: 12px; }.playground-field :deep(.el-select), .playground-field :deep(.el-segmented) { width: 100%; }
.test-warning { margin: 14px 16px 0; width: auto; }.result-error { margin-bottom: 14px; }
.playground-stage { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); min-height: 540px; }
.playground-pane { display: flex; min-width: 0; min-height: 540px; flex-direction: column; }.response-pane { border-left: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-blank); }
.pane-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; min-height: 60px; box-sizing: border-box; padding: 12px 16px; border-bottom: 1px solid var(--el-border-color-lighter); }
.pane-header > div:first-child { display: flex; min-width: 0; flex-direction: column; gap: 4px; }.pane-header strong { font-size: 15px; }.pane-header span { overflow: hidden; color: var(--el-text-color-secondary); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
.prompt-input { flex: 1; }.prompt-input :deep(.el-textarea), .prompt-input :deep(.el-textarea__inner) { height: 100%; }.prompt-input :deep(.el-textarea__inner) { min-height: 100% !important; padding: 18px; border: 0; box-shadow: none; font-size: 14px; line-height: 1.8; }
.prompt-footer, .response-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 64px; box-sizing: border-box; padding: 11px 16px; border-top: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-extra-light); }
.speech-input-status, .response-voice-actions { display: flex; align-items: center; gap: 10px; min-width: 0; }.speech-input-status span, .response-footer { color: var(--el-text-color-secondary); font-size: 12px; }.speech-input-status span.recording { color: var(--el-color-danger); }
.voice-button.is-recording { border-color: var(--el-color-danger); color: #fff; background: var(--el-color-danger); }
.response-content { flex: 1; min-height: 0; padding: 18px; overflow: auto; }.response-content > pre, .reasoning-result pre { margin: 0; overflow: auto; white-space: pre-wrap; word-break: break-word; font-family: inherit; font-size: 14px; line-height: 1.8; }
.assistant-timeline { display: flex; flex-direction: column; gap: 18px; background: var(--el-fill-color-extra-light); scroll-behavior: smooth; }
.assistant-welcome { display: flex; min-height: 360px; align-items: center; justify-content: center; flex-direction: column; gap: 10px; color: var(--el-text-color-secondary); text-align: center; }.assistant-welcome .el-icon { font-size: 34px; color: var(--el-color-primary); }.assistant-welcome strong { color: var(--el-text-color-primary); font-size: 15px; }.assistant-welcome span { max-width: 360px; font-size: 13px; line-height: 1.7; }
.assistant-message { display: flex; max-width: 94%; flex-direction: column; gap: 7px; align-self: flex-start; }.assistant-message.user { max-width: 78%; align-self: flex-end; }.assistant-message-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 2px; }.assistant-message-head strong { font-size: 12px; }.assistant-message-head span { color: var(--el-text-color-placeholder); font-size: 11px; }
.assistant-bubble { min-width: 80px; padding: 12px 14px; border: 1px solid var(--el-border-color-lighter); border-radius: 6px; color: var(--el-text-color-primary); background: var(--el-bg-color); }.assistant-message.user .assistant-bubble { border-color: var(--el-color-primary-light-7); background: var(--el-color-primary-light-9); }.assistant-bubble > pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-family: inherit; font-size: 14px; line-height: 1.75; }
.assistant-thinking { display: flex; min-height: 24px; align-items: center; gap: 5px; color: var(--el-text-color-secondary); font-size: 12px; }.assistant-thinking i { width: 5px; height: 5px; border-radius: 50%; background: var(--el-color-primary); animation: assistant-pulse 1.2s infinite ease-in-out; }.assistant-thinking i:nth-child(2) { animation-delay: .15s; }.assistant-thinking i:nth-child(3) { animation-delay: .3s; }.assistant-thinking span { margin-left: 5px; }
.assistant-tool-results { display: grid; gap: 8px; }.assistant-tool-result { overflow: hidden; border: 1px solid var(--el-border-color-light); border-radius: 6px; background: var(--el-bg-color); }.assistant-tool-result > header { display: flex; min-height: 50px; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 12px; border-bottom: 1px solid var(--el-border-color-lighter); }.assistant-tool-result > header > div { display: flex; min-width: 0; flex-direction: column; gap: 3px; }.assistant-tool-result > header strong { font-size: 13px; }.assistant-tool-result > header span { overflow: hidden; color: var(--el-text-color-secondary); font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.tool-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px; background: var(--el-border-color-lighter); }.tool-summary-grid > div { display: flex; min-height: 60px; box-sizing: border-box; justify-content: center; flex-direction: column; gap: 4px; padding: 10px 12px; background: var(--el-bg-color); }.tool-summary-grid span { color: var(--el-text-color-secondary); font-size: 11px; }.tool-summary-grid strong { overflow: hidden; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }.tool-detail-table { width: 100%; }.tool-raw-detail { padding: 9px 12px; border-top: 1px solid var(--el-border-color-lighter); color: var(--el-text-color-secondary); font-size: 11px; }.tool-raw-detail summary { cursor: pointer; }.tool-raw-detail pre { max-height: 240px; margin: 10px 0 0; overflow: auto; white-space: pre-wrap; word-break: break-word; font-size: 11px; line-height: 1.6; }
@keyframes assistant-pulse { 0%, 70%, 100% { opacity: .28; transform: translateY(0); } 35% { opacity: 1; transform: translateY(-2px); } }
.reasoning-result { margin-bottom: 16px; padding: 12px 14px; border: 1px solid var(--el-border-color-lighter); border-radius: 4px; color: var(--el-text-color-secondary); background: var(--el-fill-color-extra-light); }.reasoning-result summary { cursor: pointer; font-size: 13px; }.reasoning-result pre { max-height: 220px; margin-top: 12px; color: var(--el-text-color-secondary); font-size: 12px; }
.response-empty { display: flex; height: 100%; min-height: 360px; align-items: center; justify-content: center; flex-direction: column; gap: 12px; color: var(--el-text-color-placeholder); }.response-empty .el-icon { font-size: 34px; }.response-empty span { font-size: 13px; }
.response-footer { justify-content: flex-start; }.response-header > div:first-child { flex: 1; }.response-voice-actions { flex: 0 0 auto; }
.pagination-row { display: flex; justify-content: flex-end; margin-top: 16px; }
@media (max-width: 1100px) {
    .playground-toolbar { grid-template-columns: repeat(3, minmax(0, 1fr)); }.playground-stage { grid-template-columns: 1fr; }.response-pane { border-top: 1px solid var(--el-border-color-lighter); border-left: 0; }
    .tool-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
    .page-header, .provider-header, .toolbar, .integration-panel { align-items: stretch; flex-direction: column; }.form-grid, .dialog-grid, .test-grid { grid-template-columns: 1fr; }
    .grid-span-2, .dialog-span-2 { grid-column: auto; }.toolbar-fields { align-items: stretch; flex-direction: column; }.toolbar-fields .el-input, .toolbar-fields .el-select { width: 100%; }
    .playground-toolbar { grid-template-columns: 1fr 1fr; }.model-field { grid-column: 1 / -1; }.playground-stage, .playground-pane { min-height: 460px; }.pane-header { align-items: flex-start; }.response-header { flex-direction: column; }.response-voice-actions { width: 100%; justify-content: space-between; }
}
</style>

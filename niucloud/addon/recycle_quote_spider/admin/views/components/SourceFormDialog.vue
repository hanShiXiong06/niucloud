<template>
    <el-dialog
        v-model="sourceDialog.visible"
        :title="sourceDialog.form.id ? '编辑报价源' : '新增报价源'"
        width="780px"
        class="qs-scope quote-spider-dialog"
        append-to-body
    >
        <el-alert
            class="mb-[14px]"
            type="info"
            :closable="false"
            title="报价源只负责连接第三方报价接口。保存后点“同步”，系统会把分类、报价项和行价格写入本地数据表。"
        />
        <el-form :model="sourceDialog.form" label-width="116px">
            <el-form-item label="curl解析">
                <el-input
                    v-model="sourceDialog.curlText"
                    type="textarea"
                    :rows="5"
                    placeholder="把浏览器复制出来的 curl 粘贴到这里，点击一键解析"
                />
                <div class="source-parse-actions">
                    <el-button type="primary" @click="parseSourceCurl">一键解析</el-button>
                    <el-button @click="sourceDialog.curlText = ''">清空</el-button>
                </div>
                <div class="form-tip">只解析请求地址、URL 参数、Header 和 Cookie。解析后仍需确认名称、源标识和列表/详情接口是否符合当前 SaaS 站点。</div>
            </el-form-item>
            <el-form-item label="源标识">
                <el-input v-model="sourceDialog.form.source_key" placeholder="例如 dongxu，建议使用英文、数字或下划线" />
                <div class="form-tip">同一个站点内不能重复。后续同步日志、数据归属都会使用这个标识。</div>
            </el-form-item>
            <el-form-item label="名称" required>
                <el-input v-model="sourceDialog.form.source_name" placeholder="例如 东旭报价爬虫" />
                <div class="form-tip">显示给后台用户看的名称。</div>
            </el-form-item>
            <el-form-item label="服务商">
                <el-input v-model="sourceDialog.form.provider" placeholder="例如 dongxu / chaoniu" />
                <div class="form-tip">用于区分不同供应商，建议和真实来源保持一致。</div>
            </el-form-item>
            <el-form-item label="基础地址">
                <el-input v-model="sourceDialog.form.base_url" placeholder="例如 https://example.com" />
                <div class="form-tip">只填域名和协议，不要把接口路径一起填进来。</div>
            </el-form-item>
            <el-form-item label="列表接口">
                <el-input v-model="sourceDialog.form.list_path" placeholder="例如 /index.php/Api/index/newimage" />
                <div class="form-tip">用于抓取分类和报价项列表，通常是相对路径。</div>
            </el-form-item>
            <el-form-item label="详情接口">
                <el-input v-model="sourceDialog.form.detail_path" placeholder="例如 /index.php/Api/index/bj" />
                <div class="form-tip">用于抓取某个报价项下的型号、等级列和价格。</div>
            </el-form-item>
            <el-form-item label="自动同步">
                <el-switch v-model="sourceDialog.form.sync_enabled" :active-value="1" :inactive-value="0" />
                <div class="form-tip">开启后需要队列/定时任务正常运行。手动同步不受这个开关影响。</div>
            </el-form-item>
            <el-form-item label="同步间隔">
                <el-select v-model="sourceDialog.form.sync_interval" class="w-[220px]">
                    <el-option label="每 6 小时" :value="21600" />
                    <el-option label="每 12 小时" :value="43200" />
                    <el-option label="每天一次" :value="86400" />
                    <el-option label="每 3 天" :value="259200" />
                    <el-option label="每 7 天" :value="604800" />
                </el-select>
                <div class="form-tip">系统计划任务每小时检查一次。这里控制同一个报价源两次自动同步之间的最短间隔，手动同步不受限制。</div>
            </el-form-item>
            <el-form-item label="请求配置JSON">
                <el-input
                    v-model="sourceDialog.requestConfigText"
                    type="textarea"
                    :rows="9"
                    placeholder='{"query":{},"headers":{},"cookies":{}}'
                />
                <div class="form-tip">需要 token、cookie、固定参数时填这里。必须是合法 JSON；不需要时保留空对象即可。</div>
                <pre class="json-example">{
  "query": { "openid": "xxx" },
  "headers": { "Authorization": "Bearer xxx" },
  "cookies": {}
}</pre>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="sourceDialog.visible = false">取消</el-button>
            <el-button type="primary" @click="saveSource">保存</el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const { sourceDialog, parseSourceCurl, saveSource } = useQuoteSpider()
</script>

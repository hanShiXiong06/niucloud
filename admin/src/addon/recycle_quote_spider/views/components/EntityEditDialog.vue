<template>
    <el-dialog
        v-model="editDialog.visible"
        :title="editDialogTitle"
        :width="editDialog.type === 'row' ? '920px' : '680px'"
        class="qs-scope quote-spider-dialog"
        append-to-body
    >
        <el-alert
            v-if="editDialog.type === 'item'"
            class="mb-[14px]"
            type="info"
            :closable="false"
            title="分类是有父子节点的层级关系；分组是第三方报价里同步过来的平铺标签，更适合作为筛选属性。"
        />
        <el-alert
            v-if="editDialog.type === 'row'"
            class="mb-[14px]"
            type="warning"
            :closable="false"
            title="跟随爬虫开启时，最终价会按源价格和调价规则计算；关闭后可保留人工价格，后续同步不主动覆盖。"
        />
        <el-form :model="editDialog.form" label-width="106px">
            <!-- 分类 -->
            <template v-if="editDialog.type === 'category'">
                <el-form-item v-if="editDialog.mode === 'create'" label="报价源">
                    <el-select v-model="editDialog.form.source_id" class="w-full" placeholder="请选择报价源">
                        <el-option v-for="item in sourceOptions" :key="item.id" :label="item.source_name" :value="item.id" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="editDialog.mode === 'create'" label="父级分类">
                    <el-tree-select
                        v-model="editDialog.form.parent_id"
                        clearable
                        check-strictly
                        node-key="id"
                        :data="categoryOptions"
                        :props="{ label: 'name', value: 'id', children: 'children' }"
                        placeholder="不选则创建一级分类"
                        class="w-full"
                    />
                    <div class="form-tip">默认创建一级分类；要建子分类时在这里选父级，或在左侧分类行点“+ 子分类”。</div>
                </el-form-item>
                <el-form-item label="分类名称" required>
                    <el-input v-model="editDialog.form.name" placeholder="例如 苹果 / 华为 / 平板" @keyup.enter="submitEdit(editDialog.keepOpen && editDialog.mode === 'create')" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="editDialog.form.sort" :min="0" />
                    <div class="form-tip">数字越小越靠前，只影响本地后台展示。</div>
                </el-form-item>
                <el-form-item label="显示">
                    <el-switch v-model="editDialog.form.is_show" :active-value="1" :inactive-value="0" />
                    <div class="form-tip">关闭后前台或接口可隐藏该分类，本地数据仍保留。</div>
                </el-form-item>
                <el-form-item label="热门">
                    <el-switch v-model="editDialog.form.is_hot" :active-value="1" :inactive-value="0" />
                    <div class="form-tip">用于标记常用分类，具体展示取决于调用方。</div>
                </el-form-item>
            </template>

            <!-- 报价项 -->
            <template v-if="editDialog.type === 'item'">
                <el-form-item label="报价模式">
                    <el-segmented v-model="editDialog.form.is_image_quote" :options="quoteModeOptions" />
                    <div class="form-tip">结构化报价维护行价格；图片报价直接上传一张报价图。</div>
                </el-form-item>
                <el-form-item label="所属分类">
                    <el-tree-select
                        v-model="editDialog.form.category_id"
                        :disabled="editDialog.mode !== 'create'"
                        check-strictly
                        node-key="id"
                        :data="categoryOptions"
                        :props="{ label: 'name', value: 'id', children: 'children' }"
                        placeholder="来源分类"
                        class="w-full"
                    />
                    <div class="form-tip">这里展示的是有层级的分类节点。为避免同步关系混乱，当前暂不在弹窗内移动分类。</div>
                </el-form-item>
                <el-form-item label="报价项" required>
                    <el-input v-model="editDialog.form.name" placeholder="例如 iPhone 15 Pro Max" />
                    <div class="form-tip">报价项名称用于列表检索和展示。</div>
                </el-form-item>
                <el-form-item label="品牌">
                    <el-input v-model="editDialog.form.brand" placeholder="例如 苹果 / 华为 / 荣耀" />
                    <div class="form-tip">品牌是筛选属性，建议保持统一写法。</div>
                </el-form-item>
                <el-form-item label="分组">
                    <el-input v-model="editDialog.form.tab" placeholder="例如 国行 / 外版 / 靓机 / 花机" />
                    <div class="form-tip">分组不是层级节点，是第三方报价里的标签属性，可用于筛选和归类。</div>
                </el-form-item>
                <el-form-item label="报价类型">
                    <el-input v-model="editDialog.form.quote_type" :disabled="editDialog.mode !== 'create'" placeholder="例如 manual / manual_excel" />
                    <div class="form-tip">手工维护建议使用 manual；Excel 导入会写为 manual_excel。</div>
                </el-form-item>
                <el-form-item v-if="editDialog.mode !== 'create'" label="上级名称">
                    <el-input v-model="editDialog.form.parent_name" disabled placeholder="来源上级名称" />
                    <div class="form-tip">第三方来源返回的上级名称，用于辅助识别，不等同于本地分类节点。</div>
                </el-form-item>
                <el-form-item label="导航图标">
                    <upload-image v-model="editDialog.form.icon" :limit="1" />
                    <div class="form-tip">用于低代码图文导航和后台报价项缩略图。手动上传后优先展示，不填则回退到来源图片。</div>
                </el-form-item>
                <el-form-item v-if="editDialog.form.is_image_quote === 1" label="报价图片">
                    <upload-image v-model="editDialog.form.image" :limit="1" />
                    <div class="form-tip">图片报价不会生成行价格，适合第三方只给报价图或运营直接维护图片。</div>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model="editDialog.form.keywords" placeholder="多个关键词可用逗号分隔" />
                    <div class="form-tip">用于搜索命中，不会改变第三方原始数据。</div>
                </el-form-item>
                <el-form-item label="报价提示">
                    <el-input
                        v-model="editDialog.form.notice_text"
                        type="textarea"
                        :rows="4"
                        placeholder="温馨提示：报价仅供参考，最终价格以质检结果为准"
                    />
                    <div class="form-tip">展示在移动端报价详情顶部的提示卡片里。Excel 导入时也可以通过“报价提示”列写入，换行会按多行展示。</div>
                </el-form-item>
                <el-form-item label="显示">
                    <el-switch v-model="editDialog.form.is_show" :active-value="1" :inactive-value="0" />
                    <div class="form-tip">关闭后该报价项可在前台隐藏。</div>
                </el-form-item>
                <el-form-item label="热门">
                    <el-switch v-model="editDialog.form.is_hot" :active-value="1" :inactive-value="0" />
                    <div class="form-tip">用于运营侧标记常用报价项。</div>
                </el-form-item>
                <el-form-item label="跟随爬虫">
                    <el-switch v-model="editDialog.form.follow_source" :active-value="1" :inactive-value="0" />
                    <div class="form-tip">开启时后续同步会继续使用第三方源价格；关闭后适合做本地人工维护。</div>
                </el-form-item>
            </template>

            <!-- 行价格 -->
            <template v-if="editDialog.type === 'row'">
                <div class="row-edit-layout">
                    <section class="row-edit-section">
                        <div class="section-title">基础信息</div>
                        <div class="row-edit-grid">
                            <el-form-item label="型号" required>
                                <el-input v-model="editDialog.form.model_name" placeholder="例如 iPhone 15 Pro Max 256G" />
                                <div class="form-tip">行价格的主名称，通常对应具体型号、容量或报价行。</div>
                            </el-form-item>
                            <el-form-item label="品牌">
                                <el-input v-model="editDialog.form.brand" placeholder="例如 苹果" />
                                <div class="form-tip">用于筛选和检索，建议和报价项品牌保持一致。</div>
                            </el-form-item>
                            <el-form-item label="分组">
                                <el-input v-model="editDialog.form.tab" placeholder="例如 国行 / 外版 / 靓机 / 花机" />
                                <div class="form-tip">行级分组是平铺属性，不是树形节点。</div>
                            </el-form-item>
                            <el-form-item label="维护方式">
                                <el-segmented v-model="editDialog.form.follow_source" :options="followSourceOptions" @change="handleRowModeChange" />
                                <div class="form-tip">{{ rowEditModeTip }}</div>
                            </el-form-item>
                            <el-form-item label="排序">
                                <el-input-number v-model="editDialog.form.sort" :min="0" />
                                <div class="form-tip">数字越小越靠前。</div>
                            </el-form-item>
                            <el-form-item label="显示">
                                <el-switch v-model="editDialog.form.is_show" :active-value="1" :inactive-value="0" />
                                <div class="form-tip">关闭后前台接口不会返回这一行价格。</div>
                            </el-form-item>
                            <el-form-item label="热门">
                                <el-switch v-model="editDialog.form.is_hot" :active-value="1" :inactive-value="0" />
                                <div class="form-tip">标记该型号为热门，可用于前台“只看热门”和排序。</div>
                            </el-form-item>
                        </div>
                        <el-form-item label="备注">
                            <el-input v-model="editDialog.form.remark" type="textarea" :rows="3" placeholder="例如 有锁、仅参考、特殊报价说明" />
                            <div class="form-tip">备注会跟随行价格一起展示，适合放运营说明。</div>
                        </el-form-item>
                    </section>

                    <section class="row-edit-section">
                        <div class="section-title">
                            价格明细
                            <el-tag size="small" :type="editDialog.form.follow_source === 1 ? 'success' : 'warning'">
                                {{ editDialog.form.follow_source === 1 ? '按源价计算' : '人工维护' }}
                            </el-tag>
                        </div>
                        <div v-if="editDialog.mode === 'create'" class="create-price-columns">
                            <el-input v-model="editDialog.columnsText" type="textarea" :rows="2" placeholder="价格列，用逗号分隔，例如 靓机,小花,内爆" />
                            <div class="form-tip">新增行时先定义价格列，再在下方填写对应人工价格。</div>
                        </div>
                        <el-table :data="rowPriceTable" border size="large" class="row-price-table">
                            <el-table-column prop="column" label="价格列" min-width="150" />
                            <el-table-column label="源价格" min-width="120">
                                <template #default="{ row }">{{ formatMoney(row.source_price) }}</template>
                            </el-table-column>
                            <el-table-column label="当前最终价" min-width="120">
                                <template #default="{ row }">
                                    <span class="final-price">{{ formatMoney(row.final_price) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="人工价格" min-width="170">
                                <template #default="{ $index }">
                                    <el-input-number
                                        v-model="editDialog.form.manual_prices[$index]"
                                        :controls="false"
                                        :disabled="editDialog.form.follow_source === 1"
                                        placeholder="留空则不覆盖"
                                        class="manual-price-input"
                                    />
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="form-tip">
                            人工维护时可直接填写每一列价格；跟随爬虫时人工价格输入会锁定，最终价由源价格和调价规则计算。
                        </div>
                    </section>

                    <section class="row-edit-section">
                        <div class="section-title">调价规则</div>
                        <div class="row-edit-grid">
                            <el-form-item label="调价方式">
                                <el-select v-model="editDialog.form.adjust_type" class="w-full" :disabled="editDialog.form.follow_source === 0">
                                    <el-option label="无" :value="0" />
                                    <el-option label="固定调整" :value="1" />
                                    <el-option label="比例调整" :value="2" />
                                </el-select>
                                <div class="form-tip">固定调整适合统一加减金额；比例调整适合按倍率处理价格。</div>
                            </el-form-item>
                            <el-form-item label="调价值">
                                <el-input-number v-model="editDialog.form.adjust_value" :controls="false" :disabled="editDialog.form.follow_source === 0 || editDialog.form.adjust_type !== 1" />
                                <div class="form-tip">固定调整时生效。正数加价，负数扣价。</div>
                            </el-form-item>
                            <el-form-item label="调价比例">
                                <el-input-number v-model="editDialog.form.adjust_ratio" :controls="false" :step="0.01" :disabled="editDialog.form.follow_source === 0 || editDialog.form.adjust_type !== 2" />
                                <div class="form-tip">比例调整时生效。1 不变，0.95 打 95 折，1.05 上浮 5%。</div>
                            </el-form-item>
                        </div>
                    </section>
                </div>
            </template>
        </el-form>
        <template #footer>
            <el-button @click="editDialog.visible = false">取消</el-button>
            <el-button
                v-if="editDialog.mode === 'create' && editDialog.type === 'category'"
                :loading="editDialog.submitting"
                @click="submitEdit(true)"
            >保存并继续</el-button>
            <el-button type="primary" :loading="editDialog.submitting" @click="submitEdit(false)">保存</el-button>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { useQuoteSpider } from '@/addon/recycle_quote_spider/composables/useQuoteSpider'

const {
    editDialog,
    editDialogTitle,
    sourceOptions,
    categoryOptions,
    quoteModeOptions,
    followSourceOptions,
    rowEditModeTip,
    rowPriceTable,
    formatMoney,
    handleRowModeChange,
    submitEdit
} = useQuoteSpider()
</script>

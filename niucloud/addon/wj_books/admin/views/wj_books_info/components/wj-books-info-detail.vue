<template>
    <el-dialog v-model="showDialog" :title="'图书详情'" width="60%" class="diy-dialog-wrap" :destroy-on-close="true">
        <div class="book-detail-container" v-loading="loading">
            <div class="book-header">
                <div class="book-cover">
                    <el-image v-if="detailData.img" :src="img(detailData.img)" fit="cover" class="cover-image" />
                    <div v-else class="no-image">
                        <el-icon><Picture /></el-icon>
                    </div>
                </div>
                <div class="book-basic-info">
                    <h2 class="book-title">{{ detailData.title }}</h2>
                    <div class="book-meta">
                        <div class="meta-item">
                            <span class="label">作者:</span>
                            <span class="value">{{ detailData.author }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">出版社:</span>
                            <span class="value">{{ detailData.publisher }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">出版日期:</span>
                            <span class="value">{{ detailData.pub_date }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">ISBN:</span>
                            <span class="value">{{ detailData.isbn }}</span>
                        </div>
                        <div class="meta-item" v-if="detailData.isbn10">
                            <span class="label">ISBN10:</span>
                            <span class="value">{{ detailData.isbn10 }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">定价:</span>
                            <span class="value">¥{{ detailData.price }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">回收价格:</span>
                            <span class="value">¥{{ detailData.recycle_price }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="label">是否可回收:</span>
                            <span class="value">
                                <el-tag :type="detailData.can_recycle === 1 ? 'success' : 'danger'">
                                    {{ detailData.can_recycle === 1 ? '可回收' : '不可回收' }}
                                </el-tag>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="label">回收次数:</span>
                            <span class="value">{{ detailData.recycle_count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <el-divider content-position="left"><span class="section-title">图书详细信息</span></el-divider>
            
            <el-row :gutter="20">
                <el-col :span="24">
                    <el-descriptions :column="3" border>
                        <el-descriptions-item label="装帧信息">{{ detailData.binding || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="页数">{{ detailData.page || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="版次">{{ detailData.edition || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="API查询时间">{{ detailData.api_query_time || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="创建时间">{{ detailData.create_time || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="更新时间">{{ detailData.update_time || '-' }}</el-descriptions-item>
                    </el-descriptions>
                </el-col>
            </el-row>
            
            <el-divider content-position="left"><span class="section-title">内容简介</span></el-divider>
            
            <div class="description-content">
                {{ detailData.gist || '暂无简介' }}
            </div>
            
            <el-divider content-position="left" v-if="detailData.api_json">
                <span class="section-title">API返回数据</span>
            </el-divider>
            
            <div class="api-response" v-if="detailData.api_json">
                <el-collapse>
                    <el-collapse-item title="查看API原始数据">
                        <pre class="json-content">{{ formatJSON(detailData.api_json) }}</pre>
                    </el-collapse-item>
                </el-collapse>
            </div>
        </div>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">关闭</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive } from 'vue'
import { t } from '@/lang'
import { img } from '@/utils/common'
import { getWjBooksInfoInfo } from '@/addon/wj_books/api/wj_books_info'
import { Picture } from '@element-plus/icons-vue'

let showDialog = ref(false)
const loading = ref(false)
const detailData = reactive({
    id: '',
    isbn: '',
    isbn10: '',
    title: '',
    author: '',
    publisher: '',
    pub_date: '',
    price: '',
    binding: '',
    page: '',
    edition: '',
    img: '',
    small_img: '',
    gist: '',
    recycle_price: '',
    can_recycle: '',
    recycle_count: '',
    api_json: '',
    api_query_time: '',
    create_time: '',
    update_time: ''
})

// 格式化JSON数据
const formatJSON = (jsonString: string) => {
    try {
        const jsonObj = JSON.parse(jsonString);
        return JSON.stringify(jsonObj, null, 2);
    } catch (e) {
        return jsonString;
    }
}

// 设置详情数据
const setDetailData = async (row: any = null) => {
    if (!row || !row.id) return;
    
    loading.value = true;
    
    try {
        // 清空旧数据
        Object.keys(detailData).forEach(key => {
            detailData[key] = '';
        });
        
        // 先填充列表页面的数据
        Object.keys(row).forEach(key => {
            if (detailData.hasOwnProperty(key)) {
                detailData[key] = row[key];
            }
        });
        
        // 获取完整数据
        const response = await getWjBooksInfoInfo(row.id);
        const data = response.data;
        
        if (data) {
            Object.keys(data).forEach(key => {
                if (detailData.hasOwnProperty(key)) {
                    detailData[key] = data[key];
                }
            });
        }
    } catch (error) {
        console.error('获取图书详情失败', error);
    } finally {
        loading.value = false;
    }
}

defineExpose({
    showDialog,
    setDetailData
})
</script>

<style lang="scss" scoped>
.book-detail-container {
    padding: 0 20px;
}

.book-header {
    display: flex;
    margin-bottom: 20px;
}

.book-cover {
    width: 120px;
    height: 160px;
    margin-right: 20px;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.cover-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    width: 100%;
    height: 100%;
    background-color: #f5f7fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    color: #c0c4cc;
}

.book-basic-info {
    flex: 1;
}

.book-title {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 20px;
    color: #303133;
}

.book-meta {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.meta-item {
    display: flex;
    align-items: center;
}

.label {
    color: #606266;
    margin-right: 8px;
    font-weight: 500;
    min-width: 80px;
}

.value {
    color: #303133;
}

.section-title {
    font-weight: bold;
    font-size: 16px;
    color: #303133;
}

.description-content {
    line-height: 1.6;
    color: #606266;
    white-space: pre-line;
    background-color: #f5f7fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.json-content {
    background-color: #f5f7fa;
    padding: 15px;
    border-radius: 4px;
    overflow: auto;
    max-height: 300px;
    font-family: monospace;
    font-size: 14px;
    line-height: 1.5;
}
</style> 
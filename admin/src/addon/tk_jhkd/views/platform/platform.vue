<template>
  <!--平台设置-->
  <div class="main-container">
    <el-card class="box-card !border-none" shadow="never">
      <div style="width: 840px" class="mb-2">
        <el-alert type="info" title="提示：请正确配置并启用相关三方平台，启用多个平台将会返回价格最低的平台给用户;定期核查所在平台回掉地址是否正常" :closable="false"
          show-icon />
      </div>
      <div class="mt-[20px]">
        <el-table :data="platformTableData.data" size="large" v-loading="platformTableData.loading">
          <template #empty>
            <span>{{ !platformTableData.loading ? t("emptyData") : "" }}</span>
          </template>

          <el-table-column prop="name" label="平台名称" min-width="80" :show-overflow-tooltip="true" />
          <el-table-column prop="balance" label="平台余额" min-width="80" :show-overflow-tooltip="true" />
          <el-table-column label="是否使用" min-width="100" align="center">
            <template #default="{ row }">
              <el-tag class="ml-2" type="success" v-if="row.is_use == 1">{{
                t("statusNormal")
              }}</el-tag>
              <el-tag class="ml-2" type="error" v-if="row.is_use == 0">{{
                t("statusDeactivate")
              }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="callback" label="回调地址" min-width="140" :show-overflow-tooltip="true">
            <template #default="{ row }">
              <div class="flex items-center">
                <span class="mr-2">{{ row.callback }}</span>
                <el-button type="primary" link circle @click="copyCallback(row.callback)">
                  <el-icon>
                    <CopyDocument />
                  </el-icon>
                </el-button>
              </div>
            </template>
          </el-table-column>
          <el-table-column :label="t('operation')" align="right" fixed="right" width="100">
            <template #default="{ row, $index }">
              <el-button type="primary" link @click="editEvent(row, $index)">设置</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <template v-for="(item, index) in platformTableData.data">
        <component :is="item.component" :ref="(el) => setPlatformTypeRefs(el, index)" v-if="item.component"
          @complete="loadPlatformList()" />
      </template>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { defineAsyncComponent, reactive, ref } from "vue";
import { t } from "@/lang";
import { getPlatformList } from "@/addon/tk_jhkd/api/platform";
import { useRoute } from "vue-router";
import {
  getJhkdConfig,
} from "@/addon/tk_jhkd/api/tkjhkd";
import { ElMessage } from "element-plus";
import { CopyDocument } from "@element-plus/icons-vue";
const config = ref()
const getData = async () => {
  const data = await getJhkdConfig({});
  config.value = data.data.value
};
getData()
const route = useRoute();
const pageName = route.meta.title;
const platformTypeRefs = ref([]);

const platformTableData = reactive({
  loading: true,
  data: [],
});

const modules: any = import.meta.glob("@/**/*.vue");
/**
 * 获取配置信息
 */
const loadPlatformList = () => {
  platformTableData.loading = true;
  getPlatformList()
    .then(({ data }) => {
      Object.keys(data).forEach((key: string) => {
        data[key].component &&
          (data[key].component = defineAsyncComponent(
            modules[data[key].component]
          ));
      });
      platformTableData.data = data;
      platformTableData.loading = false;
    })
    .catch(() => {
      platformTableData.loading = false;
    });
};

const setPlatformTypeRefs = (el, index) => {
  platformTypeRefs.value[index] = el;
};

loadPlatformList();
const editEvent = (data: any, index: number) => {
  platformTypeRefs.value[index].setFormData(data);
  platformTypeRefs.value[index].showDialog = true;
};

// 复制回调地址
const copyCallback = (text: string) => {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text)
      .then(() => {
        ElMessage.success('复制成功');
      })
      .catch(() => {
        ElMessage.error('复制失败');
      });
  } else {
    // 兼容不支持 Clipboard API 的浏览器
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      ElMessage.success('复制成功');
    } catch (err) {
      ElMessage.error('复制失败');
    }
    document.body.removeChild(textarea);
  }
};
</script>

<style lang="scss" scoped></style>

<template>
  <div class="main-container">
    <el-card class="box-card !border-none" shadow="never">
      <div class="flex justify-between items-center">
        <span class="text-lg">{{ pageName }}</span>
        <el-button type="primary" @click="addEvent">
          {{ t("addBusinessMember") }}
        </el-button>
      </div>

      <el-card
        class="box-card !border-none my-[10px] table-search-wrap"
        shadow="never"
      >
        <el-form
          :inline="true"
          :model="businessMemberTable.searchParam"
          ref="searchFormRef"
        >
          <el-form-item :label="t('businessId')" prop="business_id">
            <el-select
              class="w-[280px]"
              v-model="businessMemberTable.searchParam.business_id"
              clearable
              :placeholder="t('businessIdPlaceholder')"
            >
              <el-option
                v-for="(item, index) in businessIdList"
                :key="index"
                :label="item['name']"
                :value="item['id']"
              />
            </el-select>
          </el-form-item>

          <el-form-item label="会员" prop="member_id">
            <el-select
              class="input-width"
              v-model="businessMemberTable.searchParam.member_id"
              clearable
              :placeholder="t('memberIdPlaceholder')"
            >
              <div class="mt-2 mb-2 ml-4">
                <el-input
                  @change="change"
                  v-model="keyword"
                  style="width: 200px"
                  placeholder="搜索会员支持昵称/会员名"
                >
                  <template #append>搜索 </template></el-input
                >
              </div>
              <el-option label="请选择" value=""></el-option>
              <el-option
                v-for="(item, index) in memberIdList"
                :key="index"
                :label="item['nickname']"
                :value="item['member_id']"
              />
            </el-select>
          </el-form-item>

          <el-form-item :label="t('level')" prop="level">
            <el-input
              v-model="businessMemberTable.searchParam.level"
              :placeholder="t('levelPlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('balance')" prop="balance">
            <el-input
              v-model="businessMemberTable.searchParam.balance"
              :placeholder="t('balancePlaceholder')"
            />
          </el-form-item>
          <el-form-item :label="t('createTime')" prop="create_time">
            <el-date-picker
              v-model="businessMemberTable.searchParam.create_time"
              type="datetimerange"
              format="YYYY-MM-DD hh:mm:ss"
              :start-placeholder="t('startDate')"
              :end-placeholder="t('endDate')"
            />
          </el-form-item>

          <el-form-item>
            <el-button type="primary" @click="loadBusinessMemberList()">{{
              t("search")
            }}</el-button>
            <el-button @click="resetForm(searchFormRef)">{{
              t("reset")
            }}</el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <div class="mt-[10px]">
        <el-table
          :data="businessMemberTable.data"
          size="large"
          v-loading="businessMemberTable.loading"
        >
          <template #empty>
            <span>{{
              !businessMemberTable.loading ? t("emptyData") : ""
            }}</span>
          </template>
          <el-table-column
            prop="business_id_name"
            :label="t('businessId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="member_id_name"
            :label="t('memberId')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="level"
            :label="t('level')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            prop="balance"
            :label="t('balance')"
            min-width="120"
            :show-overflow-tooltip="true"
          />

          <el-table-column
            :label="t('operation')"
            fixed="right"
            min-width="120"
          >
            <template #default="{ row }">
              <el-button type="primary" link @click="editEvent(row)">{{
                t("edit")
              }}</el-button>
              <el-button type="primary" link @click="deleteEvent(row.id)">{{
                t("delete")
              }}</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-[16px] flex justify-end">
          <el-pagination
            v-model:current-page="businessMemberTable.page"
            v-model:page-size="businessMemberTable.limit"
            layout="total, sizes, prev, pager, next, jumper"
            :total="businessMemberTable.total"
            @size-change="loadBusinessMemberList()"
            @current-change="loadBusinessMemberList"
          />
        </div>
      </div>

      <edit ref="editBusinessMemberDialog" @complete="loadBusinessMemberList" />
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from "vue";
import { t } from "@/lang";
import { useDictionary } from "@/app/api/dict";
import {
  getBusinessMemberList,
  deleteBusinessMember,
  getWithBusinessList,
  getWithMemberList,
} from "@/addon/fast_pay/api/businessmember";
import { img } from "@/utils/common";
import { ElMessageBox, FormInstance } from "element-plus";
import Edit from "@/addon/fast_pay/views/businessmember/components/businessmember-edit.vue";
import { useRoute } from "vue-router";
const route = useRoute();
const pageName = route.meta.title;

let businessMemberTable = reactive({
  page: 1,
  limit: 10,
  total: 0,
  loading: true,
  data: [],
  searchParam: {
    business_id: "",
    member_id: "",
    level: "",
    balance: "",
    create_time: [],
  },
});

const searchFormRef = ref<FormInstance>();

// 选中数据
const selectData = ref<any[]>([]);

// 字典数据

/**
 * 获取商户会员列表
 */
const loadBusinessMemberList = (page: number = 1) => {
  businessMemberTable.loading = true;
  businessMemberTable.page = page;

  getBusinessMemberList({
    page: businessMemberTable.page,
    limit: businessMemberTable.limit,
    ...businessMemberTable.searchParam,
  })
    .then((res) => {
      businessMemberTable.loading = false;
      businessMemberTable.data = res.data.data;
      businessMemberTable.total = res.data.total;
    })
    .catch(() => {
      businessMemberTable.loading = false;
    });
};
loadBusinessMemberList();

const editBusinessMemberDialog: Record<string, any> | null = ref(null);

/**
 * 添加商户会员
 */
const addEvent = () => {
  editBusinessMemberDialog.value.setFormData();
  editBusinessMemberDialog.value.showDialog = true;
};

/**
 * 编辑商户会员
 * @param data
 */
const editEvent = (data: any) => {
  editBusinessMemberDialog.value.setFormData(data);
  editBusinessMemberDialog.value.showDialog = true;
};

/**
 * 删除商户会员
 */
const deleteEvent = (id: number) => {
  ElMessageBox.confirm(t("businessMemberDeleteTips"), t("warning"), {
    confirmButtonText: t("confirm"),
    cancelButtonText: t("cancel"),
    type: "warning",
  }).then(() => {
    deleteBusinessMember(id)
      .then(() => {
        loadBusinessMemberList();
      })
      .catch(() => {});
  });
};

const businessIdList = ref([]);
const setBusinessIdList = async () => {
  businessIdList.value = await (await getWithBusinessList({})).data;
};
setBusinessIdList();
const change = () => {
  setMemberIdList();
};
const keyword = ref();
const memberIdList = ref([]);
const setMemberIdList = async () => {
  memberIdList.value = await (
    await getWithMemberList({ keyword: keyword.value })
  ).data.data;
};
setMemberIdList();

const resetForm = (formEl: FormInstance | undefined) => {
  if (!formEl) return;
  formEl.resetFields();
  loadBusinessMemberList();
};
</script>

<style lang="scss" scoped>
/* 多行超出隐藏 */
.multi-hidden {
  word-break: break-all;
  text-overflow: ellipsis;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
</style>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { settingApi } from '@/api'

const GROUPS = [
  { name: 'base', label: '门店信息' },
  { name: 'point', label: '记分牌与赠金' },
  { name: 'order', label: '订单规则' },
  { name: 'wine', label: '存酒规则' },
]

const LABELS: Record<string, string> = {
  shop_name: '门店名称',
  shop_phone: '门店电话',
  shop_address: '门店地址',
  shop_notice: '门店公告',
  business_hours: '营业时间',
  point_to_gift_rate: '记分牌兑换赠金比例',
  gift_default_days: '赠金默认有效天数',
  auto_cancel_minutes: '未支付订单自动取消(分钟)',
  consume_point_rate: '每消费1元赠送记分牌',
  gift_pay_enabled: '允许赠金参与点单支付(1开/0关)',
  default_expire_days: '存酒默认保存天数',
  take_code_expire_min: '取酒码有效分钟数',
}

const active = ref('base')
const forms = ref<Record<string, Record<string, string>>>({})
const loading = ref(false)

onMounted(() => loadGroup('base'))

async function loadGroup(group: string) {
  loading.value = true
  try {
    forms.value[group] = await settingApi.get(group)
  } finally {
    loading.value = false
  }
}

function onTabChange(name: string) {
  if (!forms.value[name]) loadGroup(name)
}

async function save(group: string) {
  await settingApi.save(group, forms.value[group])
  ElMessage.success('保存成功')
}
</script>

<template>
  <div class="page">
    <el-tabs v-model="active" @tab-change="onTabChange as any">
      <el-tab-pane v-for="group in GROUPS" :key="group.name" :label="group.label" :name="group.name">
        <el-card v-loading="loading">
          <el-form label-width="220px" v-if="forms[group.name]">
            <el-form-item v-for="(_, key) in forms[group.name]" :key="key" :label="LABELS[key] || key">
              <el-input v-model="forms[group.name][key]" style="max-width: 420px" />
            </el-form-item>
            <el-button type="primary" @click="save(group.name)">保存</el-button>
          </el-form>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

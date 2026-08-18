<script setup lang="ts">
import { rechargePackageApi } from '@/api'
import CrudTable, { type CrudColumn, type CrudField } from '@/components/CrudTable.vue'

const columns: CrudColumn[] = [
  { prop: 'title', label: '套餐名称' },
  { prop: 'amount', label: '充值金额', width: 120, type: 'money' },
  { prop: 'gift_amount', label: '赠送赠金', width: 120, type: 'money' },
  { prop: 'gift_point', label: '赠送记分牌', width: 120 },
  { prop: 'gift_expire_days', label: '赠金有效天数', width: 130 },
  { prop: 'status', label: '状态', width: 100, type: 'status' },
]

const fields: CrudField[] = [
  { prop: 'title', label: '套餐名称', required: true, placeholder: '如 充1000送200' },
  { prop: 'amount', label: '充值金额', type: 'money', required: true },
  { prop: 'gift_amount', label: '赠送赠金', type: 'money', default: 0 },
  { prop: 'gift_point', label: '赠送记分牌', type: 'number', default: 0 },
  { prop: 'gift_expire_days', label: '赠金有效天数', type: 'number', default: 0, tip: '0 表示永久有效' },
  { prop: 'sort', label: '排序', type: 'number', default: 0 },
  { prop: 'status', label: '启用', type: 'switch', default: 1 },
]
</script>

<template>
  <CrudTable :api="rechargePackageApi" :columns="columns" :fields="fields" title="套餐" />
</template>

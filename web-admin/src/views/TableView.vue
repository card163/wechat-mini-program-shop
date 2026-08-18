<script setup lang="ts">
import { tableApi } from '@/api'
import CrudTable, { type CrudColumn, type CrudField } from '@/components/CrudTable.vue'

const columns: CrudColumn[] = [
  { prop: 'name', label: '桌号名称' },
  { prop: 'sort', label: '排序', width: 90 },
  { prop: 'status', label: '状态', width: 100, type: 'status' },
]

const fields: CrudField[] = [
  { prop: 'name', label: '桌号名称', required: true, placeholder: '如 大桌 / 小桌 / 一楼' },
  { prop: 'sort', label: '排序', type: 'number', default: 0 },
  { prop: 'status', label: '启用', type: 'switch', default: 1 },
]
</script>

<template>
  <CrudTable :api="tableApi" :columns="columns" :fields="fields" title="桌号" />
</template>

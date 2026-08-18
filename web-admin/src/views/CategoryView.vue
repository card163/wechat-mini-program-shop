<script setup lang="ts">
import { categoryApi } from '@/api'
import CrudTable, { type CrudColumn, type CrudField } from '@/components/CrudTable.vue'

const columns: CrudColumn[] = [
  { prop: 'name', label: '分类名称' },
  { prop: 'sort', label: '排序', width: 90 },
  { prop: 'status', label: '状态', width: 100, type: 'status' },
  { prop: 'created_at', label: '创建时间', width: 180 },
]

const fields: CrudField[] = [
  { prop: 'name', label: '分类名称', required: true },
  { prop: 'icon', label: '分类图标', type: 'image' },
  { prop: 'sort', label: '排序', type: 'number', default: 0, tip: '值越小越靠前' },
  { prop: 'status', label: '启用', type: 'switch', default: 1 },
]
</script>

<template>
  <CrudTable :api="categoryApi" :columns="columns" :fields="fields" title="分类" />
</template>

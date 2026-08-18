<script setup lang="ts">
import { bannerApi } from '@/api'
import CrudTable, { type CrudColumn, type CrudField } from '@/components/CrudTable.vue'

const columns: CrudColumn[] = [
  { prop: 'image', label: '图片', width: 120, type: 'image' },
  { prop: 'title', label: '标题' },
  { prop: 'link', label: '跳转路径' },
  { prop: 'sort', label: '排序', width: 90 },
  { prop: 'status', label: '状态', width: 100, type: 'status' },
]

const fields: CrudField[] = [
  { prop: 'title', label: '标题', required: true },
  { prop: 'image', label: '图片', type: 'image' },
  { prop: 'link', label: '跳转路径', placeholder: '如 /pages/recharge/index' },
  { prop: 'sort', label: '排序', type: 'number', default: 0 },
  { prop: 'status', label: '显示', type: 'switch', default: 1 },
]
</script>

<template>
  <CrudTable :api="bannerApi" :columns="columns" :fields="fields" title="轮播图" />
</template>

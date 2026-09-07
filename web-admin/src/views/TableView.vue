<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { tableApi, tableZoneApi } from '@/api'
import CrudTable, { type CrudColumn, type CrudField } from '@/components/CrudTable.vue'

const zones = ref<any[]>([])

onMounted(async () => {
  const zoneRes = await tableZoneApi.list({ page: 1, page_size: 100 })
  zones.value = zoneRes.list
})

const columns = computed<CrudColumn[]>(() => [
  { prop: 'zone_name', label: '所属分区', width: 140 },
  { prop: 'name', label: '桌号名称' },
  { prop: 'sort', label: '排序', width: 90 },
  { prop: 'status', label: '状态', width: 100, type: 'status' },
])

const fields = computed<CrudField[]>(() => {
  if (!zones.value.length) return []

  return [
    {
      prop: 'zone_id',
      label: '所属分区',
      type: 'select',
      required: true,
      options: zones.value.map((item: any) => ({ label: item.name, value: item.id })),
      tip: '请先在“桌号分区”里创建分区，再为分区添加具体桌号',
    },
    { prop: 'name', label: '桌号名称', required: true, placeholder: '如 A01' },
    { prop: 'sort', label: '排序', type: 'number', default: 0 },
    { prop: 'status', label: '启用', type: 'switch', default: 1 },
  ]
})
</script>

<template>
  <CrudTable :api="tableApi" :columns="columns" :fields="fields" title="桌号" />
</template>


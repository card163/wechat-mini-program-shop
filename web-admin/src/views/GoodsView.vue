<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { categoryApi, goodsApi } from '@/api'
import CrudTable, { type CrudColumn, type CrudField } from '@/components/CrudTable.vue'

const columns: CrudColumn[] = [
  { prop: 'cover', label: '封面', width: 90, type: 'image' },
  { prop: 'name', label: '商品名称' },
  { prop: 'price', label: '售价', width: 110, type: 'money' },
  { prop: 'stock', label: '库存', width: 90 },
  { prop: 'sales', label: '销量', width: 90 },
  { prop: 'status', label: '状态', width: 100, type: 'status' },
]

const fields = ref<CrudField[]>([])

onMounted(async () => {
  const categories = await categoryApi.list({ page: 1, page_size: 100 })

  fields.value = [
    { prop: 'name', label: '商品名称', required: true },
    { prop: 'subtitle', label: '副标题/规格' },
    {
      prop: 'category_id',
      label: '所属分类',
      type: 'select',
      required: true,
      options: categories.list.map((item: any) => ({ label: item.name, value: item.id })),
    },
    { prop: 'cover', label: '封面图', type: 'image' },
    { prop: 'price', label: '售价', type: 'money', required: true },
    { prop: 'origin_price', label: '划线价', type: 'money' },
    { prop: 'unit', label: '单位', default: '份' },
    { prop: 'stock', label: '库存', type: 'number', default: -1, tip: '-1 表示不限库存' },
    { prop: 'gift_payable', label: '可用赠金支付', type: 'switch', default: 1, tip: '关闭后该商品结算时不参与赠金抵扣' },
    { prop: 'sort', label: '排序', type: 'number', default: 0 },
    { prop: 'status', label: '上架', type: 'switch', default: 1 },
    { prop: 'description', label: '商品详情', type: 'textarea' },
  ]
})
</script>

<template>
  <CrudTable v-if="fields.length" :api="goodsApi" :columns="columns" :fields="fields" title="商品" />
</template>

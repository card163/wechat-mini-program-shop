<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { exchangeGoodsApi } from '@/api'
import CrudTable, { type CrudColumn, type CrudField } from '@/components/CrudTable.vue'
import { giftConfig, loadGiftConfig } from '@/utils/gift'

onMounted(loadGiftConfig)

const columns = computed<CrudColumn[]>(() => [
  { prop: 'name', label: '名称' },
  { prop: 'point', label: '所需记分牌', width: 130 },
  { prop: 'gift_amount', label: `兑换${giftConfig.displayName}`, width: 120, type: 'giftAmount' },
  { prop: 'stock', label: '库存', width: 90 },
  { prop: 'exchanged', label: '已兑换', width: 90 },
  { prop: 'status', label: '状态', width: 100, type: 'status' },
])

const fields = computed<CrudField[]>(() => [
  { prop: 'name', label: '名称', required: true },
  {
    prop: 'type',
    label: '兑换类型',
    type: 'select',
    default: 1,
    options: [
      { label: '实物/酒水（需店员核销）', value: 1 },
      { label: `${giftConfig.displayName}（即时到账）`, value: 2 },
    ],
  },
  { prop: 'cover', label: '封面图', type: 'image' },
  { prop: 'point', label: '所需记分牌', type: 'number', required: true, default: 0 },
  { prop: 'gift_amount', label: `兑换${giftConfig.displayName}`, type: 'giftAmount', default: 0, tip: `仅${giftConfig.displayName}类型有效` },
  { prop: 'gift_expire_days', label: `${giftConfig.displayName}有效天数`, type: 'number', default: 0, tip: '0 表示永久有效' },
  { prop: 'stock', label: '库存', type: 'number', default: -1, tip: '-1 表示不限量' },
  { prop: 'description', label: '兑换说明', type: 'textarea' },
  { prop: 'sort', label: '排序', type: 'number', default: 0 },
  { prop: 'status', label: '上架', type: 'switch', default: 1 },
])
</script>

<template>
  <CrudTable :api="exchangeGoodsApi" :columns="columns" :fields="fields" title="兑换商品" />
</template>

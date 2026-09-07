<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { categoryApi, goodsApi } from '@/api'
import CrudTable, { type CrudColumn, type CrudField } from '@/components/CrudTable.vue'
import { giftConfig, loadGiftConfig } from '@/utils/gift'
import { drinkCardConfig, loadDrinkCardConfig } from '@/utils/drinkCard'

const columns = computed<CrudColumn[]>(() => [
  { prop: 'cover', label: '封面', width: 90, type: 'image' },
  { prop: 'name', label: '商品名称' },
  { prop: 'price', label: '售价', width: 110, type: 'money' },
  { prop: 'stock', label: '库存', width: 90 },
  { prop: 'sales', label: '销量', width: 90 },
  { prop: 'gift_amount', label: `所需${giftConfig.displayName}`, width: 110, type: 'giftAmount' },
  { prop: 'drink_card_amount', label: `所需${drinkCardConfig.displayName}`, width: 110, type: 'drinkCardAmount' },
  { prop: 'drink_card_gift_amount', label: `购买赠送${drinkCardConfig.displayName}`, width: 130, type: 'drinkCardAmount' },
  { prop: 'status', label: '状态', width: 100, type: 'status' },
])

const categories = ref<any[]>([])

onMounted(async () => {
  const [categoryRes] = await Promise.all([categoryApi.list({ page: 1, page_size: 100 }), loadGiftConfig(), loadDrinkCardConfig()])
  categories.value = categoryRes.list
})

const fields = computed<CrudField[]>(() => {
  if (!categories.value.length) return []

  return [
    { prop: 'name', label: '商品名称', required: true },
    { prop: 'subtitle', label: '副标题/规格' },
    {
      prop: 'category_id',
      label: '所属分类',
      type: 'select',
      required: true,
      options: categories.value.map((item: any) => ({ label: item.name, value: item.id })),
    },
    { prop: 'cover', label: '封面图', type: 'image' },
    { prop: 'price', label: '售价', type: 'money', required: true },
    { prop: 'origin_price', label: '划线价', type: 'money' },
    { prop: 'unit', label: '单位', default: '份' },
    { prop: 'stock', label: '库存', type: 'number', default: -1, tip: '-1 表示不限库存' },
    {
      prop: 'gift_payable',
      label: `可用${giftConfig.displayName}支付`,
      type: 'switch',
      default: 1,
      tip: `关闭后该商品结算时不参与${giftConfig.displayName}抵扣`,
    },
    {
      prop: 'gift_amount',
      label: `使用${giftConfig.displayName}支付需消耗数量`,
      type: 'giftAmount',
      default: 0,
      required: true,
      visibleIf: (form) => form.gift_payable === 1,
      tip: `兑换该商品(单件)固定消耗的${giftConfig.displayName}数量，与商品现金售价无关`,
    },
    {
      prop: 'drink_card_payable',
      label: `可用${drinkCardConfig.displayName}支付`,
      type: 'switch',
      default: 0,
      tip: `关闭后该商品结算时不参与${drinkCardConfig.displayName}抵扣`,
    },
    {
      prop: 'drink_card_amount',
      label: `使用${drinkCardConfig.displayName}支付需消耗数量`,
      type: 'drinkCardAmount',
      default: 0,
      required: true,
      visibleIf: (form) => form.drink_card_payable === 1,
      tip: `兑换该商品(单件)固定消耗的${drinkCardConfig.displayName}数量，与商品现金售价无关`,
    },
    {
      prop: 'drink_card_gift_amount',
      label: `购买赠送${drinkCardConfig.displayName}数量`,
      type: 'drinkCardAmount',
      default: 0,
      tip: `顾客购买该商品(单件)时自动赠送的${drinkCardConfig.displayName}数量，按购买件数累加，0 表示不赠送`,
    },
    {
      prop: 'drink_card_gift_expire_days',
      label: `赠送${drinkCardConfig.displayName}有效天数`,
      type: 'number',
      default: 0,
      visibleIf: (form) => Number(form.drink_card_gift_amount) > 0,
      tip: '0 表示永久有效',
    },
    { prop: 'sort', label: '排序', type: 'number', default: 0 },
    { prop: 'status', label: '上架', type: 'switch', default: 1 },
    { prop: 'description', label: '商品详情', type: 'textarea' },
  ]
})
</script>

<template>
  <CrudTable v-if="fields.length" :api="goodsApi" :columns="columns" :fields="fields" title="商品" />
</template>

<script setup lang="ts">
import { computed, type HTMLAttributes } from 'vue'
import {
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  type AlertDialogEmits,
  type AlertDialogProps,
  AlertDialogTitle,
  AlertDialogTrigger,
} from 'radix-vue'
import { cn } from '@/lib/utils'

const props = defineProps<AlertDialogProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<AlertDialogEmits>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <AlertDialogRoot v-bind="delegatedProps" @update:open="emits('update:open', $event)">
    <slot />
  </AlertDialogRoot>
</template>

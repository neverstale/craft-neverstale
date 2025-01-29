import type { Component } from 'vue'

export interface MenuItem {
  action: Function|string
  label: string
  icon: Component
  blank?: boolean
}

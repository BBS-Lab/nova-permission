import { createPinia } from 'pinia'
import Tool from '@/pages/Tool.vue'
import '../css/tool.css'

window.Nova.booting(app => {
  app.use(createPinia())
  window.Nova.inertia('PermissionBuilder', Tool)
})

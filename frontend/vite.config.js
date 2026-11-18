import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/readerss/', // имя твоего репозитория
  build: {
    outDir: '../docs',
    emptyOutDir: true, // очищает папку docs перед каждой новой сборкой
  }
})
<script setup>
import { ref, computed, onMounted } from 'vue'

const feeds = ref([])
const loading = ref(true)
const error = ref(null)

// Плоский список всех новостей, отсортированный по дате (новые сверху)
const allItems = computed(() => {
  const items = []
  for (const feed of feeds.value) {
    for (const item of feed.items) {
      items.push({
        ...item,
        sourceTitle: feed.title,
        sourceUrl: feed.source,
      })
    }
  }
  return items.sort((a, b) => new Date(b.pubDate) - new Date(a.pubDate))
})

onMounted(async () => {
  try {
    const res = await fetch('./data.json')
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    feeds.value = await res.json()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

function formatDate(str) {
  const d = new Date(str)
  if (isNaN(d)) return str
  return d.toLocaleString('ru-RU', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <main class="app">
    <h1>RSS Reader</h1>

    <p v-if="loading">Загрузка…</p>
    <p v-else-if="error" class="error">Ошибка: {{ error }}</p>
    <p v-else-if="allItems.length === 0">Новостей нет</p>

    <ul v-else class="news">
      <li v-for="item in allItems" :key="item.link" class="news-item">
        <h2>
          <a :href="item.link" target="_blank" rel="noopener">
            {{ item.title }}
          </a>
        </h2>
        <div class="meta">
          <span class="source">{{ item.sourceTitle }}</span>
          <span class="date">{{ formatDate(item.pubDate) }}</span>
        </div>
        <p class="description">{{ item.description }}</p>
      </li>
    </ul>
  </main>
</template>

<style scoped>
.app {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
  font-family: system-ui, sans-serif;
}
h1 {
  margin-bottom: 1.5rem;
}
.news {
  list-style: none;
  padding: 0;
}
.news-item {
  border-bottom: 1px solid #eee;
  padding: 1rem 0;
}
.news-item h2 {
  font-size: 1.1rem;
  margin: 0 0 0.25rem;
}
.news-item a {
  color: #1a5fb4;
  text-decoration: none;
}
.news-item a:hover {
  text-decoration: underline;
}
.meta {
  font-size: 0.85rem;
  color: #666;
  display: flex;
  gap: 1rem;
  margin-bottom: 0.5rem;
}
.description {
  margin: 0;
  color: #333;
  font-size: 0.95rem;
  white-space: pre-line;
}
.error {
  color: #c00;
}
</style>
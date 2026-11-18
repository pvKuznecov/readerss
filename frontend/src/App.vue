<template src="./main.html"></template>
<style src="./style.css"></style>
<script setup>
    import { ref, computed, onMounted } from 'vue'

    const feeds = ref([]);
    const loading = ref(true);
    const error = ref(null);

    // Плоский список всех новостей, отсортированный по дате (новые сверху)
    const allItems = computed(() => {
        const items = [];
        
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
            const res = await fetch('./data.json');
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            feeds.value = await res.json();
        } catch (e) {
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    })

    function formatDate(str) {
        const d = new Date(str);
        if (isNaN(d)) return str;
        
        return d.toLocaleString('ru-RU', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        })
    }
</script>
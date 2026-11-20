<template src="./main.html"></template>
<style src="./style.css"></style>
<script setup>
    import { ref, computed, onMounted, watch } from 'vue'
    import soursesdata from './soursesdata.json'
    import DOMPurify from 'dompurify'

    const feeds = ref([]);
    const loading = ref(true);
    const error = ref(null);
    const SourceTitle = ref(null);
    const ShowFilters = ref(true);
    const SoursSelectors = ref(JSON.parse(localStorage.getItem('selectedSources') || '[]'));
    const readLinks = ref(new Set(JSON.parse(localStorage.getItem('readLinks') || '[]')));

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

    const allItemsFilter = computed(() => {
        const res = allItems.value.filter((val) =>
            SoursSelectors.value.includes(val.sourceTitle)
        );
        return res;
    });

    const SourceTitlesArr = computed(() => {
        const items = {};

        for (const feed of feeds.value) {
            items[feed.title] = feed.source;
        }
        return items;
    });

    function isRead(link) {
        return readLinks.value.has(link);
    }

    function toggleRead(link) {
        if (readLinks.value.has(link)) {
            readLinks.value.delete(link);
        } else {
            readLinks.value.add(link);
        }
        // Set не сериализуется в JSON, поэтому превращаем в массив
        localStorage.setItem('readLinks', JSON.stringify([...readLinks.value]));
    }

    function safeHtml(html) {
        return DOMPurify.sanitize(html, {
            ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'em', 'strong', 'a', 'img', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre'],
            ALLOWED_ATTR: ['href', 'src', 'alt', 'title', 'width', 'height'],
        })
    }

    function select_all() {
        const all = [];
        for (const stype of Object.keys(soursesdata)) {
            for (const url of soursesdata[stype]) {
                all.push(getSourceTitle(url));
            }
        }
        SoursSelectors.value = all;
    }

    function select_null() {
        SoursSelectors.value = [];
    }

    // при любом изменении — сохраняем в localstorage
    watch(SoursSelectors, (val) => {
        localStorage.setItem('selectedSources', JSON.stringify(val));
    }, { deep: true });

    onMounted(async () => {
        try {
            const res = await fetch('./data.json');
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            feeds.value = await res.json();

            // если в localStorage ничего не было — выбираем все источники
            const saved = localStorage.getItem('selectedSources');
            if (!saved) {
                SoursSelectors.value = feeds.value.map((f) => f.title);
            }
        } catch (e) {
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    });

    function getSourceTitle(sourceUrl) {
        const feed = feeds.value.find((f) => f.source === sourceUrl);
        return feed ? feed.title : sourceUrl;
    }

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

    function getTypeTitle(key) {
        const tData = {
            "it": "IT",
            "ithard": "Железо",
            "games": "Игры",
            "sport": "Спорт",
            "news": "Новости",
            "culture": "Культура",
            "science": "Наука/образование",
            "auto": "Авто",
            "video": "Кино/сериалы/аниме"
        };
        return tData[key] ?? 'Прочее';
    }
</script>
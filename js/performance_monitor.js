class LazyAvatarLoader {
    constructor() {
        this.observer = null;
        this.loadedImages = new Set();
        this.initializeLazyLoading();
    }

    initializeLazyLoading() {
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, {
                root: document.getElementById('avatarContainer'),
                rootMargin: '100px' // Start loading 100px before image is visible
            });
        }
    }

    loadImage(img) {
        const dataSrc = img.getAttribute('data-src');
        if (dataSrc && !this.loadedImages.has(img)) {
            img.src = dataSrc;
            img.removeAttribute('data-src');
            img.classList.remove('lazy');
            this.loadedImages.add(img);
            
            img.style.opacity = '0';
            img.onload = () => {
                img.style.transition = 'opacity 0.3s ease';
                img.style.opacity = '1';
            };
        }
    }

    observeImage(img) {
        if (this.observer) {
            this.observer.observe(img);
        } else {
            this.loadImage(img);
        }
    }
}

const lazyLoader = new LazyAvatarLoader();

function renderAvatarsWithLazyLoading() {
    const avatarContainer = document.getElementById('avatarContainer');
    
    const avatars = avatarContainer.querySelectorAll('img.avatar[data-src]');
    
    avatars.forEach(img => {
        img.classList.add('lazy');
        
        img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNTgiIGhlaWdodD0iNTgiIHZpZXdCb3g9IjAgMCA1OCA1OCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjU4IiBoZWlnaHQ9IjU4IiBmaWxsPSIjZjhmOWZhIi8+CjxjaXJjbGUgY3g9IjI5IiBjeT0iMjkiIHI9IjgiIGZpbGw9IiNkZGUiLz4KPC9zdmc+';
        
        lazyLoader.observeImage(img);
    });
}

const lazyLoadingCSS = `
<style>
.avatar.lazy {
    background: linear-gradient(45deg, #f8f9fa 25%, transparent 25%), 
                linear-gradient(-45deg, #f8f9fa 25%, transparent 25%), 
                linear-gradient(45deg, transparent 75%, #f8f9fa 75%), 
                linear-gradient(-45deg, transparent 75%, #f8f9fa 75%);
    background-size: 8px 8px;
    background-position: 0 0, 0 4px, 4px -4px, -4px 0px;
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 0.6; }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', lazyLoadingCSS);



class ImagePreloader {
    constructor() {
        this.preloadQueue = [];
        this.preloadedImages = new Map();
        this.maxConcurrentLoads = 3;
        this.currentLoads = 0;
    }

    preloadImage(src) {
        return new Promise((resolve, reject) => {
            if (this.preloadedImages.has(src)) {
                resolve(this.preloadedImages.get(src));
                return;
            }

            const img = new Image();
            img.onload = () => {
                this.preloadedImages.set(src, img);
                resolve(img);
            };
            img.onerror = reject;
            img.src = src;
        });
    }

    async preloadBatch(imageUrls) {
        const chunks = [];
        for (let i = 0; i < imageUrls.length; i += this.maxConcurrentLoads) {
            chunks.push(imageUrls.slice(i, i + this.maxConcurrentLoads));
        }

        for (const chunk of chunks) {
            await Promise.allSettled(chunk.map(url => this.preloadImage(url)));
        }
    }
}



class VirtualScrollAvatars {
    constructor(container, itemHeight = 70, itemsPerRow = 8) {
        this.container = container;
        this.itemHeight = itemHeight;
        this.itemsPerRow = itemsPerRow;
        this.visibleItems = [];
        this.allItems = [];
        this.scrollTop = 0;
        this.containerHeight = container.clientHeight;
        
        this.init();
    }

    init() {
        this.container.addEventListener('scroll', this.onScroll.bind(this));
        window.addEventListener('resize', this.onResize.bind(this));
    }

    setItems(items) {
        this.allItems = items;
        this.render();
    }

    onScroll() {
        this.scrollTop = this.container.scrollTop;
        this.render();
    }

    onResize() {
        this.containerHeight = this.container.clientHeight;
        this.render();
    }

    render() {
        const startRow = Math.floor(this.scrollTop / this.itemHeight);
        const endRow = Math.min(
            startRow + Math.ceil(this.containerHeight / this.itemHeight) + 1,
            Math.ceil(this.allItems.length / this.itemsPerRow)
        );

        const startIndex = startRow * this.itemsPerRow;
        const endIndex = Math.min(endRow * this.itemsPerRow, this.allItems.length);

        this.visibleItems = this.allItems.slice(startIndex, endIndex);
        
        this.container.innerHTML = '';
        
        // Add spacer for items above viewport
        if (startIndex > 0) {
            const spacer = document.createElement('div');
            spacer.style.height = `${startRow * this.itemHeight}px`;
            this.container.appendChild(spacer);
        }

        // Render visible items
        this.visibleItems.forEach((item, index) => {
            const element = this.createItemElement(item, startIndex + index);
            this.container.appendChild(element);
        });

        // Add spacer for items below viewport
        const remainingItems = this.allItems.length - endIndex;
        if (remainingItems > 0) {
            const spacer = document.createElement('div');
            const remainingRows = Math.ceil(remainingItems / this.itemsPerRow);
            spacer.style.height = `${remainingRows * this.itemHeight}px`;
            this.container.appendChild(spacer);
        }
    }

    createItemElement(item, index) {
        const img = document.createElement('img');
        img.src = item.web_path;
        img.className = 'avatar';
        img.dataset.avatar = item.path;
        img.alt = 'Avatar option';
        img.addEventListener('click', () => this.selectAvatar(item));
        return img;
    }

    selectAvatar(item) {
        debugLog('Selected avatar:', item);
        // Your avatar selection logic here
    }
}



class PerformanceMonitor {
    constructor() {
        this.metrics = {
            imagesLoaded: 0,
            totalLoadTime: 0,
            averageLoadTime: 0,
            failedLoads: 0,
            memoryUsage: 0
        };
        this.startTime = performance.now();
    }

    recordImageLoad(loadTime) {
        this.metrics.imagesLoaded++;
        this.metrics.totalLoadTime += loadTime;
        this.metrics.averageLoadTime = this.metrics.totalLoadTime / this.metrics.imagesLoaded;
        this.updateDisplay();
    }

    recordFailedLoad() {
        this.metrics.failedLoads++;
        this.updateDisplay();
    }

    updateMemoryUsage() {
        if ('memory' in performance) {
            this.metrics.memoryUsage = Math.round(performance.memory.usedJSHeapSize / 1024 / 1024);
        }
    }

    updateDisplay() {
        const displayElement = document.getElementById('performance-stats');
        if (displayElement) {
            displayElement.innerHTML = `
                Images Loaded: ${this.metrics.imagesLoaded} | 
                Avg Load Time: ${Math.round(this.metrics.averageLoadTime)}ms | 
                Failed: ${this.metrics.failedLoads} | 
                Memory: ${this.metrics.memoryUsage}MB
            `;
            
        }
    }

    getReport() {
        return {
            ...this.metrics,
            totalTime: performance.now() - this.startTime,
            successRate: ((this.metrics.imagesLoaded / (this.metrics.imagesLoaded + this.metrics.failedLoads)) * 100).toFixed(2)
        };
    }

    
}



class ImageCache {
    constructor(maxSize = 50) {
        this.cache = new Map();
        this.maxSize = maxSize;
        this.accessOrder = [];
    }

    get(key) {
        if (this.cache.has(key)) {
            // Move to end (most recently used)
            this.accessOrder = this.accessOrder.filter(k => k !== key);
            this.accessOrder.push(key);
            return this.cache.get(key);
        }
        return null;
    }

    set(key, value) {
        if (this.cache.size >= this.maxSize && !this.cache.has(key)) {
            // Remove least recently used
            const lru = this.accessOrder.shift();
            this.cache.delete(lru);
        }

        this.cache.set(key, value);
        this.accessOrder = this.accessOrder.filter(k => k !== key);
        this.accessOrder.push(key);
    }

    clear() {
        this.cache.clear();
        this.accessOrder = [];
    }
}


document.addEventListener('DOMContentLoaded', function() {
    const imageCache = new ImageCache(100);
    const preloader = new ImagePreloader();
    const perfMonitor = new PerformanceMonitor();
    
    class EnhancedLazyLoader extends LazyAvatarLoader {
        loadImage(img) {
            const startTime = performance.now();
            const dataSrc = img.getAttribute('data-src');
            
            if (dataSrc && !this.loadedImages.has(img)) {
                const cached = imageCache.get(dataSrc);
                if (cached) {
                    img.src = dataSrc;
                    img.removeAttribute('data-src');
                    img.classList.remove('lazy');
                    this.loadedImages.add(img);
                    perfMonitor.recordImageLoad(0); // Cached load
                    return;
                }

                img.src = dataSrc;
                img.removeAttribute('data-src');
                img.classList.remove('lazy');
                this.loadedImages.add(img);
                
                img.onload = () => {
                    const loadTime = performance.now() - startTime;
                    perfMonitor.recordImageLoad(loadTime);
                    imageCache.set(dataSrc, img);
                    img.style.transition = 'opacity 0.3s ease';
                    img.style.opacity = '1';
                };
                
                img.onerror = () => {
                    perfMonitor.recordFailedLoad();
                };
            }
        }
    }

    const enhancedLoader = new EnhancedLazyLoader();
    
    setInterval(() => {
        perfMonitor.updateMemoryUsage();
        perfMonitor.updateDisplay();
    }, 5000);
    
    const statsDiv = document.createElement('div');
    statsDiv.id = 'performance-stats';
    statsDiv.style.cssText = 'position: fixed; top: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 1000;';
    document.body.appendChild(statsDiv);
});
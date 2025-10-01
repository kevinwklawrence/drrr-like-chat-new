(function() {
    let cached = 0;
    const avatars = document.querySelectorAll('.avatar');
    const total = avatars.length;
    const progress = document.getElementById('progress');
    const status = document.getElementById('status');
    
    function updateProgress() {
        const pct = (cached / total) * 100;
        progress.style.width = pct + '%';
        status.textContent = cached + ' / ' + total;
        
        if (cached >= total) {
            setTimeout(() => {
                document.getElementById('avatarLoader').classList.add('done');
                setupReveal();
            }, 300);
        }
    }
    
    function cacheImage(img) {
        if (img.complete) {
            cached++;
            updateProgress();
        } else {
            img.onload = () => { cached++; updateProgress(); };
            img.onerror = () => { cached++; updateProgress(); };
        }
    }
    
    function setupReveal() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('show');
                        observer.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '50px' });
            
            avatars.forEach(img => observer.observe(img));
        } else {
            avatars.forEach(img => img.classList.add('show'));
        }
    }
    
    avatars.forEach(cacheImage);
    updateProgress();
})();
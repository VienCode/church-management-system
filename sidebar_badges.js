// 🔁 Live Badge Updater for Promotion & Evangelism
function updateBadges() {
    // Promotion badge (eligible for promotion)
    fetch('get_promotion_count.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('promoBadge');
            if (badge) {
                if (data.count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = data.count;
                    // Optional glow effect when there are eligible members
                    badge.style.boxShadow = '0 0 6px rgba(220,53,69,0.6)';
                } else {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(err => console.error('Promotion badge update failed:', err));

    // Evangelism badge (total non-members)
    fetch('get_evangelism_count.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('evangelismBadge');
            if (badge) {
                if (data.count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = data.count;

                    // Turn green if 10 or more non-members
                    if (data.count >= 10) {
                        badge.classList.add('active');
                    } else {
                        badge.classList.remove('active');
                    }
                } else {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(err => console.error('Evangelism badge update failed:', err));
}

// Run immediately and every 30 seconds
document.addEventListener('DOMContentLoaded', () => {
    updateBadges();
    setInterval(updateBadges, 30000);
});

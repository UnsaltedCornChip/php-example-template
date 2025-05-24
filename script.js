document.addEventListener('DOMContentLoaded', () => {
    // Theme toggle functionality
    const themeToggle = document.querySelector('.theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('light-mode');
            localStorage.setItem('theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
        });
    }

    // Apply saved theme
    if (localStorage.getItem('theme') === 'light') {
        document.body.classList.add('light-mode');
    }

    // Copy song request command functionality
    const copyButtons = document.querySelectorAll('.copy-command-btn');
    copyButtons.forEach(button => {
        button.addEventListener('click', () => {
            const youtubeLink = button.getAttribute('data-youtube-link');
            const command = `!sr ${youtubeLink}`;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(command).then(() => {
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.disabled = true;
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.disabled = false;
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                    alert('Failed to copy command. Please copy manually: ' + command);
                });
            } else {
                // Fallback for non-secure contexts or unsupported browsers
                alert('Copy this command manually: ' + command);
            }
        });
    });

    // Edit button functionality
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const videoId = button.getAttribute('data-video-id');
            window.location.href = `edit_video.php?video_id=${videoId}`;
        });
    });

    // Refresh button functionality
    const refreshButtons = document.querySelectorAll('.refresh-btn');
    refreshButtons.forEach(button => {
        button.addEventListener('click', () => {
            const videoId = button.getAttribute('data-video-id');
            if (confirm('Are you sure you want to refresh the video metadata from YouTube? This will overwrite existing data!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'refresh_video.php';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'video_id';
                input.value = videoId;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});
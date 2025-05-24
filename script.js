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
});
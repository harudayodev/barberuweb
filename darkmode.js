let isDarkMode = localStorage.getItem('darkMode') === 'true'; 

if (isDarkMode) {
    document.body.classList.add('dark-mode');
}

function toggleDarkMode() {
    isDarkMode = !isDarkMode;
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
        localStorage.setItem('darkMode', 'true');
    } else {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('darkMode', 'false');
    }
}

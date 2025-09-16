// Dark Mode Toggle Functionality

class DarkModeToggle {
    constructor() {
        this.init();
    }

    init() {
        console.log('DarkModeToggle: Initializing...');
        
        // Load saved theme or default to light
        this.loadTheme();
        
        // Create toggle button if it doesn't exist
        this.createToggleButton();
        
        // Add event listeners
        this.addEventListeners();
        
        // Update button icon based on current theme
        this.updateButtonIcon();
        
        console.log('DarkModeToggle: Initialization complete');
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Use saved theme, or fall back to system preference, or default to light
        const theme = savedTheme || (prefersDark ? 'dark' : 'light');
        
        this.setTheme(theme);
    }

    setTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        this.currentTheme = theme;
        this.updateButtonIcon();
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    }

    createToggleButton() {
        console.log('DarkModeToggle: Creating toggle button...');
        
        // Check if button already exists in footer
        const existingButton = document.querySelector('.dark-mode-toggle');
        if (existingButton) {
            console.log('DarkModeToggle: Button already exists in footer');
            this.toggleButton = existingButton;
            return;
        }

        // If no button exists in footer, create one (fallback)
        const button = document.createElement('button');
        button.className = 'dark-mode-toggle';
        button.setAttribute('aria-label', 'Toggle dark mode');
        button.setAttribute('title', 'Toggle dark mode');
        
        // Add icon
        const icon = document.createElement('i');
        icon.className = 'fas fa-moon';
        button.appendChild(icon);
        
        // Add to body as fallback
        document.body.appendChild(button);
        
        this.toggleButton = button;
        
        console.log('DarkModeToggle: Fallback button created and added to DOM');
    }

    updateButtonIcon() {
        if (!this.toggleButton) return;
        
        const icon = this.toggleButton.querySelector('i');
        if (this.currentTheme === 'dark') {
            icon.className = 'fas fa-sun';
            this.toggleButton.setAttribute('title', 'Switch to light mode');
        } else {
            icon.className = 'fas fa-moon';
            this.toggleButton.setAttribute('title', 'Switch to dark mode');
        }
    }

    addEventListeners() {
        // Toggle button click
        document.addEventListener('click', (e) => {
            if (e.target.closest('.dark-mode-toggle')) {
                console.log('DarkModeToggle: Button clicked, toggling theme...');
                this.toggleTheme();
            }
        });

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            // Only auto-switch if user hasn't manually set a preference
            if (!localStorage.getItem('theme')) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Keyboard accessibility
        document.addEventListener('keydown', (e) => {
            if (e.target.closest('.dark-mode-toggle') && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }

    // Public method to get current theme
    getCurrentTheme() {
        return this.currentTheme;
    }

    // Public method to set theme programmatically
    setThemePublic(theme) {
        if (theme === 'light' || theme === 'dark') {
            this.setTheme(theme);
        }
    }
}

// Initialize when DOM is ready
console.log('DarkModeToggle: About to initialize, document.readyState =', document.readyState);

// Function to initialize the dark mode toggle
function initializeDarkMode() {
    console.log('DarkModeToggle: Initializing dark mode');
    window.darkModeToggle = new DarkModeToggle();
}

if (document.readyState === 'loading') {
    console.log('DarkModeToggle: Document still loading, adding DOMContentLoaded listener');
    document.addEventListener('DOMContentLoaded', initializeDarkMode);
} else {
    console.log('DarkModeToggle: Document ready, creating instance immediately');
    initializeDarkMode();
}

// Export the class as default export for ES6 modules
export default DarkModeToggle;
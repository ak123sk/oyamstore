// Search functionality
class SearchManager {
    constructor() {
        this.searchQuery = '';
        this.searchResults = [];
        this.init();
    }

    init() {
        const urlParams = new URLSearchParams(window.location.search);
        this.searchQuery = urlParams.get('q') || '';
        
        if (this.searchQuery) {
            document.getElementById('search-input').value = this.searchQuery;
            this.performSearch();
        }
        
        this.setupSearchListeners();
    }

    setupSearchListeners() {
        const searchForm = document.getElementById('search-form');
        const searchInput = document.getElementById('search-input');
        
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query) {
                    window.location.href = `search.html?q=${encodeURIComponent(query)}`;
                }
            });
        }
        
        // Real-time search suggestions
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(() => {
                this.showSuggestions(searchInput.value);
            }, 300));
        }
    }

    async performSearch() {
        const sanitizedQuery = SecurityManager.sanitizeInput(this.searchQuery).toLowerCase();
        
        // Get apps data
        let appsData = CacheManager.get('appsData');
        if (!appsData) {
            appsData = await window.appStore?.appsData;
        }
        
        if (!appsData) {
            this.showNoResults();
            return;
        }
        
        // Search through apps
        this.searchResults = Object.values(appsData).filter(app => {
            return (
                app.name?.toLowerCase().includes(sanitizedQuery) ||
                app.developer?.name?.toLowerCase().includes(sanitizedQuery) ||
                app.description?.toLowerCase().includes(sanitizedQuery) ||
                app.category?.toLowerCase().includes(sanitizedQuery) ||
                app.package_name?.toLowerCase().includes(sanitizedQuery)
            );
        });
        
        this.renderResults();
    }

    renderResults() {
        const container = document.getElementById('search-results');
        const resultCount = document.getElementById('result-count');
        
        if (!container) return;
        
        if (resultCount) {
            resultCount.textContent = `${this.searchResults.length} results found`;
        }
        
        if (this.searchResults.length === 0) {
            container.innerHTML = this.getNoResultsHTML();
            return;
        }
        
        container.innerHTML = this.searchResults
            .map(app => window.appStore?.createAppCard(app) || '')
            .join('');
    }

    showSuggestions(query) {
        if (query.length < 2) {
            this.hideSuggestions();
            return;
        }
        
        const suggestionsContainer = document.getElementById('search-suggestions');
        if (!suggestionsContainer) return;
        
        const appsData = window.appStore?.appsData;
        if (!appsData) return;
        
        const sanitizedQuery = query.toLowerCase();
        const suggestions = Object.values(appsData)
            .filter(app => app.name?.toLowerCase().includes(sanitizedQuery))
            .slice(0, 5);
        
        if (suggestions.length > 0) {
            suggestionsContainer.innerHTML = suggestions
                .map(app => `
                    <div class="suggestion-item" onclick="window.location.href='app.html?package=${app.package_name}'">
                        <img src="${APIService.getLogoURL(app.logo || 'default.png')}" 
                             alt="${app.name}" class="suggestion-icon">
                        <span>${app.name}</span>
                    </div>
                `).join('');
            suggestionsContainer.style.display = 'block';
        } else {
            this.hideSuggestions();
        }
    }

    hideSuggestions() {
        const container = document.getElementById('search-suggestions');
        if (container) {
            container.style.display = 'none';
        }
    }

    getNoResultsHTML() {
        return `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No apps found</h3>
                <p>Try adjusting your search terms or browse categories</p>
                <a href="category.html" class="browse-link">Browse Categories</a>
            </div>
        `;
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new SearchManager();
});

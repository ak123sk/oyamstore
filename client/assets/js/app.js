// Main Application Controller
class AppStore {
    constructor() {
        this.appsData = null;
        this.categoriesData = null;
        this.currentUser = null;
        this.init();
    }

    async init() {
        try {
            await this.loadComponents();
            await this.loadData();
            this.setupEventListeners();
            this.renderHomePage();
        } catch (error) {
            console.error('Initialization error:', error);
            this.showError('Failed to load application');
        }
    }

    async loadComponents() {
        // Load navbar
        const navbarResponse = await fetch('components/navbar.html');
        const navbarHTML = await navbarResponse.text();
        document.getElementById('navbar-container').innerHTML = navbarHTML;

        // Load footer
        const footerResponse = await fetch('components/footer.html');
        const footerHTML = await footerResponse.text();
        document.getElementById('footer-container').innerHTML = footerHTML;
    }

    async loadData() {
        try {
            // Try to get data from cache first
            let appsData = CacheManager.get('appsData');
            
            if (!appsData) {
                const [appsInfo, appsExp, developerInfo] = await Promise.all([
                    APIService.fetchAppsInfo(),
                    APIService.fetchAppsExperience(),
                    APIService.fetchDeveloperInfo()
                ]);
                
                appsData = this.processAppsData(appsInfo, appsExp, developerInfo);
                CacheManager.set('appsData', appsData);
            }
            
            this.appsData = appsData;
            
            // Load categories
            const categoriesResponse = await fetch('data/category.json');
            this.categoriesData = await categoriesResponse.json();
            
        } catch (error) {
            console.error('Data loading error:', error);
            this.showError('Failed to load app data');
        }
    }

    processAppsData(info, experience, developer) {
        const apps = {};
        
        // Process app info
        if (info && info.apps) {
            info.apps.forEach(app => {
                apps[app.package_name] = {
                    ...app,
                    experience: experience.apps.find(e => e.package_name === app.package_name) || {},
                    developer: developer.developers.find(d => d.package_name === app.package_name) || {}
                };
            });
        }
        
        return apps;
    }

    renderHomePage() {
        this.renderFeaturedApps();
        this.renderTrendingApps();
        this.renderCategories();
    }

    renderFeaturedApps() {
        const container = document.getElementById('featured-apps');
        if (!container || !this.appsData) return;
        
        const featuredApps = Object.values(this.appsData)
            .filter(app => app.is_featured)
            .slice(0, 6);
        
        container.innerHTML = featuredApps
            .map(app => this.createAppCard(app))
            .join('');
    }

    renderTrendingApps() {
        const container = document.getElementById('trending-apps');
        if (!container || !this.appsData) return;
        
        const trendingApps = Object.values(this.appsData)
            .sort((a, b) => (b.downloads || 0) - (a.downloads || 0))
            .slice(0, 8);
        
        container.innerHTML = trendingApps
            .map(app => this.createAppCard(app))
            .join('');
    }

    renderCategories() {
        const container = document.getElementById('categories-list');
        if (!container || !this.categoriesData) return;
        
        container.innerHTML = this.categoriesData.categories
            .map(category => this.createCategoryCard(category))
            .join('');
    }

    createAppCard(app) {
        const logoURL = APIService.getLogoURL(app.logo || 'default.png');
        const sanitizedName = SecurityManager.sanitizeInput(app.name || 'Unknown');
        const sanitizedDeveloper = SecurityManager.sanitizeInput(
            app.developer?.name || 'Unknown Developer'
        );
        
        return `
            <div class="app-card" onclick="window.location.href='app.html?package=${app.package_name}'">
                <img src="${logoURL}" alt="${sanitizedName}" class="app-icon" 
                     onerror="this.src='assets/icons/default-app-icon.png'">
                <div class="app-card-info">
                    <h3>${sanitizedName}</h3>
                    <p>${sanitizedDeveloper}</p>
                    <div class="app-card-rating">
                        <span class="stars">${this.generateStars(app.rating || 0)}</span>
                        <span>${app.downloads || 0}+ downloads</span>
                    </div>
                </div>
            </div>
        `;
    }

    createCategoryCard(category) {
        const sanitizedName = SecurityManager.sanitizeInput(category.name);
        return `
            <div class="category-card" onclick="window.location.href='category.html?category=${category.id}'">
                <i class="fas ${category.icon}"></i>
                <span>${sanitizedName}</span>
            </div>
        `;
    }

    generateStars(rating) {
        const fullStars = Math.floor(rating);
        const halfStar = rating % 1 >= 0.5;
        let stars = '';
        
        for (let i = 0; i < fullStars; i++) {
            stars += '<i class="fas fa-star"></i>';
        }
        if (halfStar) {
            stars += '<i class="fas fa-star-half-alt"></i>';
        }
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
        for (let i = 0; i < emptyStars; i++) {
            stars += '<i class="far fa-star"></i>';
        }
        
        return stars;
    }

    setupEventListeners() {
        // Search functionality
        const searchBtn = document.getElementById('search-btn');
        const searchInput = document.getElementById('main-search');
        
        if (searchBtn && searchInput) {
            searchBtn.addEventListener('click', () => {
                const query = SecurityManager.sanitizeInput(searchInput.value.trim());
                if (query) {
                    window.location.href = `search.html?q=${encodeURIComponent(query)}`;
                }
            });
            
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    searchBtn.click();
                }
            });
        }
        
        // Navigation
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-nav]')) {
                e.preventDefault();
                const page = e.target.getAttribute('data-nav');
                this.navigateTo(page);
            }
        });
    }

    navigateTo(page) {
        // Simple SPA-like navigation
        switch(page) {
            case 'home':
                window.location.href = 'index.html';
                break;
            case 'categories':
                window.location.href = 'category.html';
                break;
            case 'search':
                window.location.href = 'search.html';
                break;
            default:
                window.location.href = page;
        }
    }

    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        document.body.prepend(errorDiv);
        
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.appStore = new AppStore();
});

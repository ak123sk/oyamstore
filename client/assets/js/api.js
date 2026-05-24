// API Configuration and Security
const API_CONFIG = {
    baseURL: 'https://oyamstore.great-site.net/devoloper/apk',
    endpoints: {
        apps: '/file',
        info: '/apk_info.json',
        experience: '/apk_exp.json',
        developer: '/developer.json'
    }
};

// Security utilities
class SecurityManager {
    static sanitizeInput(input) {
        if (!input) return '';
        return String(input).replace(/[<>'"]/g, '');
    }

    static validateURL(url) {
        const pattern = /^[a-zA-Z0-9\-_/.]+$/;
        return pattern.test(url);
    }

    static generateCSRFToken() {
        return crypto.randomUUID();
    }

    static encryptData(data) {
        return btoa(JSON.stringify(data));
    }

    static decryptData(encryptedData) {
        try {
            return JSON.parse(atob(encryptedData));
        } catch (error) {
            console.error('Decryption failed:', error);
            return null;
        }
    }
}

// API Service
class APIService {
    static async fetchJSON(url) {
        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    static async fetchAppsInfo() {
        return await this.fetchJSON(
            `${API_CONFIG.baseURL}${API_CONFIG.endpoints.info}`
        );
    }

    static async fetchAppsExperience() {
        return await this.fetchJSON(
            `${API_CONFIG.baseURL}${API_CONFIG.endpoints.experience}`
        );
    }

    static async fetchDeveloperInfo() {
        return await this.fetchJSON(
            `${API_CONFIG.baseURL}${API_CONFIG.endpoints.developer}`
        );
    }

    static getAPKDownloadURL(filename) {
        if (!filename || !SecurityManager.validateURL(filename)) {
            throw new Error('Invalid filename');
        }
        return `${API_CONFIG.baseURL}${API_CONFIG.endpoints.apps}/${filename}`;
    }

    static getLogoURL(filename) {
        if (!filename || !SecurityManager.validateURL(filename)) {
            return 'assets/icons/default-app-icon.svg';
        }
        return `${API_CONFIG.baseURL}/logo/${filename}`;
    }
}

// Cache Manager
class CacheManager {
    static set(key, data, ttl = 3600000) {
        try {
            const item = {
                data: SecurityManager.encryptData(data),
                expiry: Date.now() + ttl
            };
            localStorage.setItem(key, JSON.stringify(item));
        } catch (e) { /* storage not available */ }
    }

    static get(key) {
        try {
            const item = localStorage.getItem(key);
            if (!item) return null;
            const parsed = JSON.parse(item);
            if (Date.now() > parsed.expiry) {
                localStorage.removeItem(key);
                return null;
            }
            return SecurityManager.decryptData(parsed.data);
        } catch (e) { return null; }
    }

    static clear() {
        try { localStorage.clear(); } catch (e) { /* ignore */ }
    }
}

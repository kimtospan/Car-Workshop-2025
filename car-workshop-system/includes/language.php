<?php
/**
 * Language Manager Class
 * Handles multilingual support for the application
 */
class LanguageManager {
    private $currentLanguage;
    private $translations;
    private $availableLanguages = [
        'en' => 'En',
        'el' => 'Ελ'
    ];
    
    public function __construct() {
        $this->initializeLanguage();
        $this->loadTranslations();
    }
    
    /**
     * Initialize language based on user preference or default
     */
    private function initializeLanguage() {
        // Check if session is active before using session variables
        $sessionActive = (session_status() === PHP_SESSION_ACTIVE);
        
        // Priority: URL parameter -> Session -> Cookie -> Browser -> Default
        if (isset($_GET['lang']) && $this->isValidLanguage($_GET['lang'])) {
            $this->currentLanguage = $_GET['lang'];
            if ($sessionActive) {
                $_SESSION['language'] = $this->currentLanguage;
            }
            setcookie('language', $this->currentLanguage, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        } elseif ($sessionActive && isset($_SESSION['language']) && $this->isValidLanguage($_SESSION['language'])) {
            $this->currentLanguage = $_SESSION['language'];
        } elseif (isset($_COOKIE['language']) && $this->isValidLanguage($_COOKIE['language'])) {
            $this->currentLanguage = $_COOKIE['language'];
            if ($sessionActive) {
                $_SESSION['language'] = $this->currentLanguage;
            }
        } else {
            // Detect browser language
            $this->currentLanguage = $this->detectBrowserLanguage();
            if ($sessionActive) {
                $_SESSION['language'] = $this->currentLanguage;
            }
        }
    }
    
    /**
     * Detect browser language preference
     */
    private function detectBrowserLanguage() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($browserLangs as $lang) {
                $lang = substr(trim($lang), 0, 2);
                if ($this->isValidLanguage($lang)) {
                    return $lang;
                }
            }
        }
        return 'el'; // Default fallback
    }
    
    /**
     * Check if language code is valid
     */
    private function isValidLanguage($lang) {
        return array_key_exists($lang, $this->availableLanguages);
    }
    
    /**
     * Load translations for current language
     */
    private function loadTranslations() {
        $languageFile = __DIR__ . '/languages/' . $this->currentLanguage . '.php';
        if (file_exists($languageFile)) {
            $this->translations = include $languageFile;
        } else {
            // Fallback to English
            $this->translations = include __DIR__ . '/languages/en.php';
        }
    }
    
    /**
     * Get translation for a key
     */
    public function get($key, $default = null) {
        return $this->translations[$key] ?? $default ?? $key;
    }
    
    /**
     * Get current language code
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }
    
    /**
     * Get available languages
     */
    public function getAvailableLanguages() {
        return $this->availableLanguages;
    }
    
    /**
     * Get language switcher HTML
     */
    public function getLanguageSwitcher($currentPage = '') {
    $html = '<div class="dropdown">';
    
    $html .= '<button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
        $html .= '<i class="bi bi-globe me-1"></i>' . $this->availableLanguages[$this->currentLanguage];
        $html .= '</button>';
        $html .= '<ul class="dropdown-menu">';
        
        foreach ($this->availableLanguages as $code => $name) {
            $active = ($code === $this->currentLanguage) ? ' active' : '';
            $url = $this->buildLanguageUrl($currentPage, $code);
            $html .= '<li><a class="dropdown-item' . $active . '" href="' . $url . '">' . $name . '</a></li>';
        }
        
        $html .= '</ul></div>';
        return $html;
    }
    
    /**
     * Build URL with language parameter
     */
    private function buildLanguageUrl($currentPage, $langCode) {
        // Get the current file name with .php extension
        $currentFile = basename($_SERVER['PHP_SELF']);
        
        // Use provided page or current file, ensuring .php extension
        if ($currentPage && $currentPage !== '') {
            $page = $currentPage;
            // Add .php extension if not present (compatible with older PHP)
            if (substr($page, -4) !== '.php') {
                $page .= '.php';
            }
        } else {
            $page = $currentFile;
        }
        
        // Remove any leading path to ensure we stay in current directory
        $page = basename($page);
        
        // Parse existing query string and add/update lang parameter
        $queryParams = $_GET;
        $queryParams['lang'] = $langCode;
        
        $queryString = http_build_query($queryParams);
        return $page . ($queryString ? '?' . $queryString : '');
    }
}



// Create global language manager instance
$lang = new LanguageManager();

/**
 * Global translation function
 */
function t($key, $default = null) {
    global $lang;
    return $lang->get($key, $default);
}

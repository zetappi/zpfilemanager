<?php
/**
 * Language Manager
 * Handles loading and retrieving translations for the File Manager
 */

class Language {
    public static $translations = null;
    private static $currentLanguage = null;
    
    /**
     * Load language file
     * 
     * @param string $lang Language code (e.g., 'en', 'it')
     * @return array Translation strings
     */
    public static function load($lang = null) {
        if ($lang === null) {
            $lang = defined('FM_DEFAULT_LANGUAGE') ? FM_DEFAULT_LANGUAGE : 'en';
        }
        
        // Validate language is available
        $availableLanguages = defined('FM_AVAILABLE_LANGUAGES') ? FM_AVAILABLE_LANGUAGES : ['en'];
        if (!in_array($lang, $availableLanguages)) {
            $lang = $availableLanguages[0];
        }
        
        self::$currentLanguage = $lang;
        
        $langFile = __DIR__ . "/{$lang}.php";
        
        if (file_exists($langFile)) {
            self::$translations = require $langFile;
        } else {
            // Fallback to English if language file not found
            $enFile = __DIR__ . '/en.php';
            if (file_exists($enFile)) {
                self::$translations = require $enFile;
            } else {
                self::$translations = [];
            }
        }
        
        return self::$translations;
    }
    
    /**
     * Get translation string
     * 
     * @param string $key Translation key
     * @param array $params Parameters for placeholder replacement
     * @return string Translated string
     */
    public static function get($key, $params = []) {
        if (self::$translations === null) {
            self::load();
        }
        
        $string = self::$translations[$key] ?? $key;
        
        // Replace placeholders
        foreach ($params as $placeholder => $value) {
            $string = str_replace("{{$placeholder}}", $value, $string);
        }
        
        return $string;
    }
    
    /**
     * Get current language
     * 
     * @return string Current language code
     */
    public static function getCurrentLanguage() {
        return self::$currentLanguage;
    }
    
    /**
     * Get available languages
     * 
     * @return array Available language codes
     */
    public static function getAvailableLanguages() {
        return defined('FM_AVAILABLE_LANGUAGES') ? FM_AVAILABLE_LANGUAGES : ['en'];
    }
    
    /**
     * Check if language switching is allowed
     * 
     * @return bool True if language switching is allowed
     */
    public static function canSwitchLanguage() {
        return defined('FM_ALLOW_LANGUAGE_SWITCH') ? FM_ALLOW_LANGUAGE_SWITCH : true;
    }
    
    /**
     * Get language name from code
     * 
     * @param string $code Language code
     * @return string Language name
     */
    public static function getLanguageName($code) {
        $names = [
            'en' => 'English',
            'it' => 'Italiano',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'pt' => 'Português',
            'ru' => 'Русский',
            'zh' => '中文',
            'ja' => '日本語',
            'ko' => '한국어',
        ];
        
        return $names[$code] ?? strtoupper($code);
    }
}

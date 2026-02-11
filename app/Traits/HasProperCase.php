<?php

namespace App\Traits;

trait HasProperCase
{
    /**
     * Boot the HasProperCase trait for a model.
     */
    protected static function bootHasProperCase()
    {
        static::saving(function ($model) {
            // Get the list of attributes that should be converted to proper case
            $properCaseAttributes = $model->properCaseAttributes ?? [];
            
            foreach ($properCaseAttributes as $attribute) {
                if (isset($model->attributes[$attribute]) && is_string($model->attributes[$attribute])) {
                    $model->attributes[$attribute] = self::convertToProperCase($model->attributes[$attribute]);
                }
            }
        });
    }

    /**
     * Convert a string to Proper Case (Title Case)
     * 
     * @param string $string
     * @return string
     */
    protected static function convertToProperCase($string)
    {
        if (empty($string)) {
            return $string;
        }

        // List of words that should remain lowercase (articles, conjunctions, prepositions)
        $lowercaseWords = ['a', 'an', 'the', 'and', 'but', 'or', 'for', 'nor', 'on', 'at', 'to', 'from', 'by', 'of', 'in'];

        // Convert to lowercase first
        $string = mb_strtolower(trim($string));
        
        // Split into words
        $words = preg_split('/\s+/', $string);
        
        // Capitalize each word
        $result = [];
        foreach ($words as $index => $word) {
            // Always capitalize first word and words not in the lowercase list
            if ($index === 0 || !in_array($word, $lowercaseWords)) {
                $result[] = mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
            } else {
                $result[] = $word;
            }
        }
        
        return implode(' ', $result);
    }
}

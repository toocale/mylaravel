import type { Component } from 'vue';

export type ThemeName = 'default' | 'ocean' | 'industrial' | 'minimal';

export interface ThemeColors {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    foreground: string;
}

export interface ChartConfig {
    colors: string[];
    gaugeType: 'radial' | 'hexagonal' | 'outlined';
    trendType: 'area' | 'bar' | 'line';
    downtimeType: 'horizontal' | 'vertical' | 'simple';
}

export interface ThemeConfig {
    name: ThemeName;
    displayName: string;
    description: string;
    cssClass: string;
    navType: 'horizontal' | 'sidebar' | 'minimal';
    layoutType: 'grid' | 'masonry' | 'list';
    colors: ThemeColors;
    chartConfig: ChartConfig;
}

export interface ThemeState {
    currentTheme: ThemeName;
    availableThemes: ThemeConfig[];
}

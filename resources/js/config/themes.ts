import type { ThemeConfig } from '@/types/theme';

export const themes: Record<string, ThemeConfig> = {
    default: {
        name: 'default',
        displayName: 'Default',
        description: 'Classic OEE dashboard theme',
        cssClass: '',
        navType: 'sidebar',
        layoutType: 'grid',
        colors: {
            primary: 'hsl(0 0% 9%)',
            secondary: 'hsl(0 0% 92.1%)',
            accent: 'hsl(0 0% 96.1%)',
            background: 'hsl(0 0% 100%)',
            foreground: 'hsl(0 0% 3.9%)',
        },
        chartConfig: {
            colors: ['#3b82f6', '#0ea5e9', '#06b6d4', '#14b8a6', '#10b981'],
            gaugeType: 'radial',
            trendType: 'line',
            downtimeType: 'horizontal',
        },
    },
    ocean: {
        name: 'ocean',
        displayName: 'Ocean Modern',
        description: 'Clean, professional theme with oceanic blues and glassmorphism',
        cssClass: 'theme-ocean',
        navType: 'horizontal',
        layoutType: 'grid',
        colors: {
            primary: 'hsl(210, 100%, 40%)',
            secondary: 'hsl(180, 65%, 45%)',
            accent: 'hsl(15, 80%, 60%)',
            background: 'hsl(210, 20%, 98%)',
            foreground: 'hsl(210, 10%, 10%)',
        },
        chartConfig: {
            colors: [
                'hsl(210, 100%, 50%)', // Deep Blue
                'hsl(195, 80%, 45%)',  // Ocean Blue
                'hsl(180, 65%, 45%)',  // Teal
                'hsl(170, 60%, 50%)',  // Turquoise
                'hsl(15, 80%, 60%)',   // Coral
            ],
            gaugeType: 'radial',
            trendType: 'area',
            downtimeType: 'horizontal',
        },
    },
    industrial: {
        name: 'industrial',
        displayName: 'Industrial Dark',
        description: 'Bold dark theme with industrial orange accents',
        cssClass: 'theme-industrial',
        navType: 'sidebar',
        layoutType: 'masonry',
        colors: {
            primary: 'hsl(25, 95%, 55%)',
            secondary: 'hsl(210, 10%, 30%)',
            accent: 'hsl(45, 100%, 60%)',
            background: 'hsl(0, 0%, 8%)',
            foreground: 'hsl(0, 0%, 95%)',
        },
        chartConfig: {
            colors: [
                'hsl(25, 95%, 55%)',   // Industrial Orange
                'hsl(35, 90%, 60%)',   // Amber
                'hsl(45, 100%, 60%)',  // Electric Yellow
                'hsl(15, 85%, 50%)',   // Red-Orange
                'hsl(5, 80%, 55%)',    // Red
            ],
            gaugeType: 'hexagonal',
            trendType: 'bar',
            downtimeType: 'vertical',
        },
    },
    minimal: {
        name: 'minimal',
        displayName: 'Minimalist Pro',
        description: 'Ultra-clean design with ample whitespace and subtle elegance',
        cssClass: 'theme-minimal',
        navType: 'minimal',
        layoutType: 'list',
        colors: {
            primary: 'hsl(260, 60%, 60%)',
            secondary: 'hsl(30, 10%, 50%)',
            accent: 'hsl(150, 50%, 55%)',
            background: 'hsl(0, 0%, 100%)',
            foreground: 'hsl(0, 0%, 10%)',
        },
        chartConfig: {
            colors: [
                'hsl(260, 60%, 65%)',  // Soft Purple
                'hsl(150, 50%, 55%)',  // Mint Green
                'hsl(30, 70%, 65%)',   // Peach
                'hsl(200, 60%, 60%)',  // Sky Blue
                'hsl(340, 55%, 65%)',  // Rose
            ],
            gaugeType: 'outlined',
            trendType: 'line',
            downtimeType: 'simple',
        },
    },
};

export const defaultTheme = themes.default;

export function getTheme(name: string): ThemeConfig {
    return themes[name] || defaultTheme;
}

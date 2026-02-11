import { defineStore } from 'pinia';
import axios from 'axios';

interface OeeState {
    // Live Breakdown
    breakdown: Array<{
        id: number;
        name: string;
        type: string;
        oee: number;
        availability: number;
        performance: number;
        quality: number;
    }>;

    // Filters & Cache options
    filters: {
        plantId: number | null;
        lineId: number | null;
        machineId: number | null;
        dateFrom: string;
        dateTo: string;
        mode?: string;
        days?: number;
    };
    loading: boolean;
    metrics: {
        oee: number;
        availability: number;
        performance: number;
        quality: number;
    };
    currentShift: {
        name: string;
        type: string; // 'day' or 'night'
        date: string;
        start: string;
        end: string;
        good_count?: number;
        reject_count?: number;
    } | null;
    trend: Array<{
        date: string;
        oee: number;
        availability?: number;
        performance?: number;
        quality?: number;
        reject_count?: number;
        total_count?: number;
    }>;
    downtime: Array<{ description: string; total_duration: number }>;
    downtimeAnalysis: Array<{ description: string; total_duration: number; count: number }>;
    reliability: {
        mttr: number;
        mtbf: number;
        failures: number;
        total_uptime_hours: number;
        total_downtime_minutes: number;
    } | null;
    shiftAnalysis: Array<{
        name: string;
        production: number;
        good: number;
        reject: number;
    }>;
    materialLoss: {
        total_quantity: number;
        total_cost: number;
        total_count: number;
        by_category: Array<any>;
    } | null;
    materialLossTrend: Array<{ date: string; quantity: number; cost: number; count: number }>;
    worldClassTarget: number;
    target: {
        target_oee: number | null;
        target_availability: number | null;
        target_performance: number | null;
        target_quality: number | null;
    } | null;
    options: Array<{
        id: number;
        name: string;
        lines: Array<{
            id: number;
            name: string;
            machines: Array<{ id: number; name: string }>;
        }>;
    }>;
}

export const useOeeStore = defineStore('oee', {
    state: (): OeeState => ({
        filters: {
            plantId: null,
            lineId: null,
            machineId: null,
            dateFrom: '', // default to 30 days ago in component
            dateTo: '',
        },
        loading: false,
        metrics: {
            oee: 0,
            availability: 0,
            performance: 0,
            quality: 0,
        },
        breakdown: [],
        currentShift: null,
        trend: [],
        downtime: [],
        downtimeAnalysis: [],
        reliability: null,
        shiftAnalysis: [],
        materialLoss: null,
        materialLossTrend: [],
        worldClassTarget: 85.0,
        target: null,
        options: [],
    }),

    actions: {
        async fetchOptions() {
            try {
                const response = await axios.get('/api/v1/dashboard/options');
                this.options = response.data;
            } catch (error) {
                console.error('Failed to fetch OEE options', error);
            }
        },

        async fetchDashboardData() {
            this.loading = true;
            const startTime = Date.now();

            try {
                const params: any = { ...this.filters };
                // Snake case conversion if needed by API, but my API logic checked request->plant_id.
                // Axios usually keeps keys as is.
                // API expects snake_case keys: plant_id, line_id...

                const apiParams: any = {
                    plant_id: this.filters.plantId,
                    line_id: this.filters.lineId,
                    machine_id: this.filters.machineId,
                    date_from: this.filters.dateFrom,
                    date_to: this.filters.dateTo,
                };

                // Add mode and days if present
                if (this.filters.mode) {
                    apiParams.mode = this.filters.mode;
                }
                if (this.filters.days) {
                    apiParams.days = this.filters.days;
                }

                const [metricsRes, downtimeRes] = await Promise.all([
                    axios.get('/api/v1/dashboard/metrics', { params: apiParams }),
                    axios.get('/api/v1/dashboard/downtime', { params: apiParams })
                ]);

                this.metrics = metricsRes.data.overview;
                this.currentShift = metricsRes.data.current_shift || null;
                this.breakdown = metricsRes.data.breakdown || [];
                this.trend = metricsRes.data.trend;
                this.materialLoss = metricsRes.data.material_loss || null;
                this.materialLossTrend = metricsRes.data.material_loss_trend || [];
                this.downtimeAnalysis = metricsRes.data.downtime_analysis || [];
                this.reliability = metricsRes.data.reliability || null;
                this.shiftAnalysis = metricsRes.data.shift_analysis || [];

                // Store both worldClassTarget and the full target object
                if (typeof metricsRes.data.target === 'object' && metricsRes.data.target !== null) {
                    this.target = metricsRes.data.target;
                    this.worldClassTarget = metricsRes.data.target.target_oee || 85.0;
                } else {
                    this.worldClassTarget = metricsRes.data.target || 85.0;
                    this.target = null;
                }

                this.downtime = downtimeRes.data;

            } catch (error) {
                console.error('Failed to fetch OEE data', error);
            } finally {
                // Ensure loading indicator shows for at least 500ms
                const elapsed = Date.now() - startTime;
                const minLoadingTime = 500;

                if (elapsed < minLoadingTime) {
                    await new Promise(resolve => setTimeout(resolve, minLoadingTime - elapsed));
                }

                this.loading = false;
            }
        },

        setFilters(filters: Partial<OeeState['filters']>) {
            this.filters = { ...this.filters, ...filters };
            this.fetchDashboardData();
        },

        // Operator Interface Actions
        async fetchActiveShift(machineId: number) {
            try {
                const response = await axios.get(`/api/v1/production-shifts/${machineId}`);
                if (response.data.active_shift) {
                    // Normalize to currentShift format or store separately
                    // For now, let's keep it simple, though types might mismatch slightly
                    // ideally we align the interfaces.
                    const as = response.data.active_shift;
                    this.currentShift = {
                        name: as.shift_name || 'Manual Shift',
                        type: 'day', // Default or derive from shift
                        date: as.started_at.split('T')[0],
                        start: as.started_at,
                        end: '', // Not ended
                        good_count: 0, // Should be fetched from production logs agg
                        reject_count: 0
                    };
                    return as;
                }
                return null;
            } catch (error) {
                console.error('Failed to fetch active shift', error);
                return null;
            }
        },

        async startShift(machineId: number, data: { product_id: number; shift_id: number; batch_number?: string }) {
            try {
                await axios.post(`/api/v1/production-shifts/${machineId}/start`, data);
                await this.fetchActiveShift(machineId);
                return true;
            } catch (error) {
                console.error('Failed to start shift', error);
                throw error;
            }
        },

        async endShift(machineId: number) {
            try {
                await axios.post(`/api/v1/production-shifts/${machineId}/end`);
                this.currentShift = null;
                return true;
            } catch (error) {
                console.error('Failed to end shift', error);
                throw error;
            }
        },

        async logProduction(data: { machine_id: number; product_id: number; good_count: number; reject_count: number }) {
            try {
                await axios.post('/api/v1/ingest/production', data);
                // Optimistically update or refetch
                if (this.currentShift) {
                    this.currentShift.good_count = (this.currentShift.good_count || 0) + data.good_count;
                    this.currentShift.reject_count = (this.currentShift.reject_count || 0) + data.reject_count;
                }
                // Also trigger full refresh to get accurate OEE
                this.fetchDashboardData();
            } catch (error) {
                console.error('Failed to log production', error);
                throw error;
            }
        },

        async logDowntime(data: { machine_id: number; reason_code_id: number; duration_seconds?: number, start_time: string }) {
            try {
                await axios.post('/api/v1/ingest/downtime', data);
                this.fetchDashboardData();
            } catch (error) {
                console.error('Failed to log downtime', error);
                throw error;
            }
        }
    }
});

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Bell } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';

const notifications = ref<any[]>([]);
const showDropdown = ref(false);
const loading = ref(false);

const unreadCount = computed(() => notifications.value.filter(n => !n.read).length);

const fetchNotifications = async () => {
    loading.value = true;
    try {
        const response = await fetch('/api/notifications', {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });
        const data = await response.json();
        notifications.value = data.notifications;
    } catch (error) {
        console.error('Failed to fetch notifications:', error);
    } finally {
        loading.value = false;
    }
};

const markAsRead = async (notification: any) => {
    try {
        await fetch(`/api/notifications/mark-read/${notification.id}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        });
        notification.read = true;
        
        // Navigate if there's a URL
        if (notification.data?.url) {
            showDropdown.value = false;
            router.visit(notification.data.url);
        }
    } catch (error) {
        console.error('Failed to mark notification as read:', error);
    }
};

const markAllAsRead = async () => {
    try {
        await fetch('/api/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        });
        notifications.value.forEach(n => n.read = true);
    } catch (error) {
        console.error('Failed to mark all as read:', error);
    }
};

const getNotificationIcon = (type: string) => {
    const icons: any = {
        ticket_assigned: '🎫',
        ticket_message: '💬',
        ticket_status_changed: '🔄',
        maintenance_reminder: '🔧',
        overdue: '⚠️',
        upcoming: '📅',
        low_stock: '📦',
    };
    return icons[type] || '🔔';
};

const formatTime = (date: string) => {
    const d = new Date(date);
    const now = new Date();
    const diff = now.getTime() - d.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return d.toLocaleDateString();
};

onMounted(() => {
    fetchNotifications();
    // Poll for new notifications every 30 seconds
    setInterval(fetchNotifications, 30000);
});
</script>

<template>
    <div class="relative">
        <Button 
            variant="ghost" 
            size="icon"
            @click="showDropdown = !showDropdown"
            class="relative"
        >
            <Bell class="w-5 h-5" />
            <Badge 
                v-if="unreadCount > 0" 
                class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center p-0 text-xs"
                variant="destructive"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </Badge>
        </Button>

        <!-- Dropdown -->
        <div 
            v-if="showDropdown"
            class="fixed sm:absolute right-0 left-0 sm:left-auto mt-0 sm:mt-2 w-full sm:w-96 max-w-md sm:max-w-none bg-white dark:bg-neutral-900 rounded-none sm:rounded-lg shadow-lg border z-50 max-h-[100vh] sm:max-h-none overflow-hidden"
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-3 sm:p-4 border-b">
                <h3 class="font-semibold text-sm sm:text-base">Notifications</h3>
                <Button 
                    v-if="unreadCount > 0"
                    variant="ghost" 
                    size="sm"
                    @click="markAllAsRead"
                    class="text-xs h-7 px-2"
                >
                    Mark all as read
                </Button>
            </div>

            <!-- Notifications List -->
            <div class="max-h-[calc(100vh-60px)] sm:max-h-96 overflow-y-auto">
                <div v-if="loading" class="p-8 text-center text-muted-foreground">
                    Loading...
                </div>

                <div v-else-if="notifications.length === 0" class="p-8 text-center text-muted-foreground">
                    <Bell class="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>No notifications</p>
                </div>

                <div v-else>
                    <button
                        v-for="notification in notifications"
                        :key="notification.id"
                        @click="markAsRead(notification)"
                        class="w-full text-left p-3 sm:p-4 hover:bg-gray-50 dark:hover:bg-neutral-800 transition-colors border-b last:border-b-0"
                        :class="!notification.read ? 'bg-blue-50 dark:bg-blue-950' : ''"
                    >
                        <div class="flex gap-2 sm:gap-3">
                            <div class="text-xl sm:text-2xl flex-shrink-0">{{ getNotificationIcon(notification.type) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="font-semibold text-xs sm:text-sm line-clamp-1">{{ notification.title }}</h4>
                                    <span 
                                        v-if="!notification.read"
                                        class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-1"
                                    ></span>
                                </div>
                                <p class="text-xs sm:text-sm text-muted-foreground mt-1 line-clamp-2">
                                    {{ notification.message }}
                                </p>
                                <span class="text-[10px] sm:text-xs text-muted-foreground mt-1 block">
                                    {{ formatTime(notification.created_at) }}
                                </span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Backdrop -->
        <div 
            v-if="showDropdown"
            class="fixed inset-0 z-40"
            @click="showDropdown = false"
        ></div>
    </div>
</template>

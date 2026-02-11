<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
  ArrowRight, 
  BarChart3, 
  Cpu, 
  ShieldCheck, 
  Zap, 
  Activity,
  CheckCircle2,
  Menu,
  X
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

const page = usePage();
const siteName = computed(() => (page.props.site as any)?.site_name || 'Dawaoee');
const siteLogo = computed(() => (page.props.site as any)?.site_logo || null);
const isMobileMenuOpen = ref(false);

const features = [
  {
    icon: BarChart3,
    title: 'Real-time OEE',
    description: 'Track Overall Equipment Effectiveness instantly with millisecond precision.'
  },
  {
    icon: Cpu,
    title: 'IoT Integration',
    description: 'Seamlessly connect with PLC, SCADA, and IoT sensors for automated data collection.'
  },
  {
    icon: Activity,
    title: 'Predictive Health',
    description: 'AI-driven analysis to predict machine failures before they impact production.'
  },
  {
    icon: ShieldCheck,
    title: 'Enterprise Security',
    description: 'Bank-grade encryption and role-based access control for your sensitive operational data.'
  }
];
</script>

<template>
  <Head :title="`${siteName} — Asset Performance`" />

  <div class="min-h-screen bg-slate-950 text-slate-50 font-sans selection:bg-blue-500/30">
    <!-- Navbar -->
    <nav class="fixed top-0 left-0 right-0 z-50 border-b border-white/10 bg-slate-950/80 backdrop-blur-md">
      <div class="container mx-auto px-6 h-16 flex items-center justify-between">
        <!-- Logo -->
        <Link href="/" class="flex items-center space-x-2">
          <template v-if="siteLogo">
            <img :src="siteLogo" :alt="siteName" class="h-8 w-auto max-w-[150px] object-contain" />
            <span class="text-xl font-bold tracking-tight">{{ siteName }}</span>
          </template>
          <template v-else>
            <div class="h-8 w-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
              <Zap class="h-5 w-5 text-white" fill="currentColor" />
            </div>
            <span class="text-xl font-bold tracking-tight">{{ siteName }}</span>
          </template>
        </Link>

        <!-- Desktop Nav -->
        <div class="hidden md:flex items-center space-x-8">
          <a href="#features" class="text-sm font-medium text-slate-300 hover:text-white transition-colors">Features</a>
          <a href="#solutions" class="text-sm font-medium text-slate-300 hover:text-white transition-colors">Solutions</a>

        </div>

        <!-- Auth Buttons -->
        <div class="hidden md:flex items-center space-x-4">
          <Link href="/login" class="text-sm font-medium text-slate-300 hover:text-white transition-colors">
            Log in
          </Link>
          <Button as-child class="bg-blue-600 hover:bg-blue-500 text-white rounded-full px-6">
            <Link href="/register">Get Started</Link>
          </Button>
        </div>

        <!-- Mobile Menu Toggle -->
        <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="md:hidden text-slate-300">
          <Menu v-if="!isMobileMenuOpen" />
          <X v-else />
        </button>
      </div>

      <!-- Mobile Menu -->
      <div v-if="isMobileMenuOpen" class="md:hidden border-t border-white/10 bg-slate-950 p-4 space-y-4">
        <a href="#features" class="block text-slate-300 hover:text-white">Features</a>
        <a href="#solutions" class="block text-slate-300 hover:text-white">Solutions</a>
        <div class="border-t border-white/10 pt-4 flex flex-col space-y-3">
          <Link href="/login" class="text-slate-300">Log in</Link>
          <Link href="/register" class="text-blue-400 font-bold">Get Started</Link>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-32 pb-20 overflow-hidden">
      <!-- Background Gradients -->
      <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-blue-600/20 rounded-full blur-[120px] -z-10 opacity-50"></div>
      
      <div class="container mx-auto px-6 text-center">
        <Badge variant="outline" class="mb-6 border-blue-500/30 text-blue-400 bg-blue-500/10 px-4 py-1.5 rounded-full">
          v2.0 is now live — Experience the future
        </Badge>
        
        <h1 class="text-5xl md:text-7xl font-bold tracking-tight mb-6 bg-gradient-to-b from-white to-slate-400 bg-clip-text text-transparent">
          Asset Performance <br />
          <span class="text-blue-500">Reimagined.</span>
        </h1>
        
        <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto mb-10 leading-relaxed">
          Unlock the full potential of your manufacturing lines with AI-driven insights, 
          real-time OEE tracking, and predictive maintenance.
        </p>
        
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">

          <Button as-child variant="outline" size="lg" class="border-slate-700 hover:bg-slate-800 text-slate-300 rounded-full h-12 px-8 text-base">
            <Link href="/contact-us">Book a Demo</Link>
          </Button>
        </div>

        <!-- Dashboard Preview -->
        <div class="mt-20 relative mx-auto max-w-5xl group">
             <div class="absolute -inset-1 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl blur opacity-25 group-hover:opacity-50 transition duration-1000"></div>
             <div class="relative bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-2xl">
                 <div class="bg-slate-950/50 p-4 border-b border-white/5 flex items-center space-x-2">
                     <div class="flex space-x-1.5">
                         <div class="w-3 h-3 rounded-full bg-red-500/20 border border-red-500/50"></div>
                         <div class="w-3 h-3 rounded-full bg-yellow-500/20 border border-yellow-500/50"></div>
                         <div class="w-3 h-3 rounded-full bg-green-500/20 border border-green-500/50"></div>
                     </div>
                     <div class="h-6 w-64 bg-slate-800/50 rounded-md mx-auto text-[10px] flex items-center justify-center text-slate-500 font-mono">
                         {{ siteName.toLowerCase().replace(/\s+/g, '') }}.app/dashboard
                     </div>
                 </div>
                 <!-- Simplified Dashboard Mockup Content -->
                 <div class="p-6 grid grid-cols-12 gap-6 bg-slate-900/50">
                    <!-- Sidebar Mockup -->
                    <div class="col-span-12 md:col-span-2 space-y-4 hidden md:block">
                        <div class="h-8 w-24 bg-slate-800 rounded mb-6"></div>
                        <div class="h-4 w-full bg-slate-800/50 rounded"></div>
                        <div class="h-4 w-3/4 bg-slate-800/50 rounded"></div>
                        <div class="h-4 w-5/6 bg-slate-800/50 rounded"></div>
                    </div>
                    <!-- Main Content Mockup -->
                    <div class="col-span-12 md:col-span-10 grid gap-4">
                        <div class="flex justify-between">
                            <div class="h-8 w-48 bg-slate-800 rounded"></div>
                            <div class="h-8 w-32 bg-blue-600/20 rounded"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mt-4">
                             <div class="h-32 bg-slate-800/30 border border-white/5 rounded-lg p-4">
                                <div class="h-8 w-8 bg-blue-500/20 rounded mb-2"></div>
                                <div class="h-4 w-20 bg-slate-700/50 rounded"></div>
                                <div class="h-8 w-16 bg-slate-600/50 rounded mt-2"></div>
                             </div>
                             <div class="h-32 bg-slate-800/30 border border-white/5 rounded-lg p-4">
                                <div class="h-8 w-8 bg-green-500/20 rounded mb-2"></div>
                                <div class="h-4 w-20 bg-slate-700/50 rounded"></div>
                                <div class="h-8 w-16 bg-slate-600/50 rounded mt-2"></div>
                             </div>
                             <div class="h-32 bg-slate-800/30 border border-white/5 rounded-lg p-4">
                                <div class="h-8 w-8 bg-purple-500/20 rounded mb-2"></div>
                                <div class="h-4 w-20 bg-slate-700/50 rounded"></div>
                                <div class="h-8 w-16 bg-slate-600/50 rounded mt-2"></div>
                             </div>
                        </div>
                    </div>
                 </div>
             </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-slate-900/50 relative">
      <div class="container mx-auto px-6">
        <div class="text-center mb-16">
          <h2 class="text-3xl md:text-5xl font-bold mb-6">Engineered for Performance</h2>
          <p class="text-slate-400 max-w-2xl mx-auto">Everything you need to monitor, analyze, and optimize your manufacturing assets.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
          <div v-for="feature in features" :key="feature.title" class="p-6 rounded-2xl bg-slate-950 border border-white/5 hover:border-blue-500/30 transition-all duration-300 hover:-translate-y-1">
            <component :is="feature.icon" class="h-10 w-10 text-blue-500 mb-4" />
            <h3 class="text-xl font-semibold mb-2">{{ feature.title }}</h3>
            <p class="text-slate-400 text-sm leading-relaxed">{{ feature.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 relative overflow-hidden">
      <div class="absolute inset-0 bg-blue-600/5 -z-10"></div>
      <div class="container mx-auto px-6 text-center">
        <h2 class="text-4xl font-bold mb-6">Ready to optimize your line?</h2>
        <p class="text-slate-400 mb-10 max-w-xl mx-auto">Join industry leaders who are using {{ siteName }} to reduce downtime and increase shift efficiency.</p>
        <Link href="/register">
             <Button size="lg" class="bg-white text-slate-950 hover:bg-slate-200 rounded-full h-12 px-8 text-base font-semibold">
                Get Started Now
             </Button>
        </Link>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-white/10 bg-slate-950 py-12">
      <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center">
        <div class="flex items-center space-x-2 mb-4 md:mb-0">
           <template v-if="siteLogo">
             <img :src="siteLogo" :alt="siteName" class="h-6 w-auto max-w-[120px] object-contain" />
             <span class="font-bold text-lg">{{ siteName }}</span>
           </template>
           <template v-else>
             <Zap class="h-5 w-5 text-blue-500" />
             <span class="font-bold text-lg">{{ siteName }}</span>
           </template>
        </div>
        <div class="text-slate-500 text-sm">
          &copy; {{ new Date().getFullYear() }} {{ siteName }} Inc. All rights reserved.
        </div>
      </div>
    </footer>
  </div>
</template>


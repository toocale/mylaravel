<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const name = ref('');
const email = ref('');
const message = ref('');
const status = ref('');

const submit = async () => {
  status.value = 'sending';
  try {
    await fetch('/contact', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
      body: JSON.stringify({ name: name.value, email: email.value, message: message.value }),
    });
    status.value = 'ok';
  } catch (e) {
    status.value = 'error';
  }
};
</script>

<template>
  <AppLayout>
    <Head title="Contact — Dawaoee">
      <meta name="description" content="Get in touch with Dawaoee for asset performance optimization and IoT solutions." />
    </Head>
    <div class="container mx-auto px-6 lg:px-0 max-w-2xl py-16">
      <h1 class="text-3xl font-semibold mb-4">Contact Us</h1>
      <p class="mb-6 text-slate-600">Questions about Dawaoee, pricing or integrations? Send us a message.</p>

      <form @submit.prevent="submit" class="grid gap-4">
        <input v-model="name" placeholder="Your name" class="p-3 border rounded" required />
        <input v-model="email" type="email" placeholder="Email" class="p-3 border rounded" required />
        <textarea v-model="message" rows="6" placeholder="Message" class="p-3 border rounded" required />
        <div class="flex gap-4">
          <button type="submit" class="bg-slate-900 text-white px-5 py-2 rounded">Send</button>
          <div class="text-sm text-slate-600 self-center">
            <span v-if="status === 'sending'">Sending…</span>
            <span v-if="status === 'ok'">Thanks — we'll be in touch.</span>
            <span v-if="status === 'error'">There was an error.</span>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

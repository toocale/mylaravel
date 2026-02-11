Dawaoee — Landing page prototype
=================================

This workspace was extended to include a lightweight landing site named **Dawaoee** inspired by the structure and content of driftopex.com.

Quick start
-----------

- Install JS deps: `npm install`
- Run dev server: `npm run dev` and `php artisan serve` (or `php artisan serve` for backend)
- Run tests: `php artisan test`

Files added
-----------
- `resources/js/pages/Dawaoee/Home.vue` — Landing homepage
- `resources/js/pages/Dawaoee/Contact.vue` — Contact page + form
- `app/Http/Controllers/ContactController.php` — handles contact submissions
- `public/images/*` — placeholder logo and report images

Next steps
----------
- Add real content and images
- Wire up real mail delivery and persistence for contact submissions
- Improve accessibility and SEO

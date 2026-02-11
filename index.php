<?php
/**
 * Laravel Application Entry Point (for cPanel / shared hosting)
 * 
 * This file redirects to the public folder if accessed from the project root.
 * For best performance, configure your domain to point directly to the 'public' folder.
 */

// Redirect to the public folder
header('Location: public/');
exit;

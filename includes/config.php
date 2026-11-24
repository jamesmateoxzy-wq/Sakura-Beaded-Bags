<?php
/**
 * Site settings. Edit the values below.
 *
 * GOOGLE_CLIENT_ID  : from Google Cloud Console > Credentials > OAuth client ID (Web application).
 *                     It is public. This login flow has NO client secret, so there is nothing secret to leak.
 * ALLOWED_ADMIN_EMAILS : only these Google accounts can enter the admin side.
 *                     (Backend: later, replace with a lookup in an `admins` table.)
 * DEMO_MODE         : true shows a "Preview without Google" link on the login page.
 *                     Use only for the local prototype.
 */
const GOOGLE_CLIENT_ID = 'PASTE-ID';

const ALLOWED_ADMIN_EMAILS = [
    'owner',
    // 'groupmate@gmail.com',
];

const DEMO_MODE = false;
const USE_SAMPLE_DATA = false;
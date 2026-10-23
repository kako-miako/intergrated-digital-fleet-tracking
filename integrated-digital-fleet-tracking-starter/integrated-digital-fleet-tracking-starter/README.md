# Integrated Digital Fleet GPS Tracking System — Starter

This starter contains the first screen: a responsive, monochrome login interface.

## Files
- `index.html` — login page structure
- `css/style.css` — layout and styling
- `js/login.js` — client-side field validation and show/hide password interaction

## Run locally
Open `index.html` in a browser. For a better local development workflow, use VS Code Live Server or another local web server.

## Current limitation
This is a frontend login prototype only. It does not authenticate users or create a secure session yet. The next stage is a backend login endpoint with password hashing, database lookup, session management and role-based authorization. Do not use real credentials with this prototype.

## Flowchart mapping
1. Start / open login page
2. Enter email and password
3. Check that fields are complete and the email is valid
4. If not, show validation messages and allow another attempt
5. If valid, send credentials to backend (to be implemented)
6. Backend verifies credentials and role
7. On failure, show a generic login error
8. On success, create a server-side session and show the permitted dashboard

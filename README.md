# Kail’s Landscaping Website & Quote Management System

Custom PHP/MySQL website and quote management system for Kail’s Landscaping, a local outdoor-services business serving the DeForest, Windsor, and Sun Prairie area.

The project includes a public service website, quote request form, customer request follow-up pages, admin CRM tools, editable website content, service card management, theme controls, document tools, and business identity settings.

---

## Project Overview

Kail’s Landscaping needed a website that could present services clearly, provide business contact information, collect quote requests, and support follow-up after a customer submits a request.

The site includes:

- Public homepage for services, contact details, and quote requests
- Quote request form with validation and spam reduction
- Customer request confirmation page
- Customer request update page
- Admin tools for managing clients, quote requests, request statuses, and website content
- Editable service cards, images, button links, theme colors, and business information
- Document and print tools for customer-facing business workflows

---

## Public Website Features

- Local service-business homepage
- Service area and business contact information
- Service cards for landscaping and outdoor services
- Mobile-friendly layout
- Quote request form
- Existing request lookup and update flow
- Customer-facing request confirmation page

---

## Quote Request Workflow

- Server-side form validation
- CSRF protection
- Honeypot spam field
- Preferred contact method capture
- Service selection and project detail fields
- Customer record creation/update
- Quote request creation
- Request number generation
- Public access key workflow for customer follow-up

---

## Admin CRM Tools

- Admin login and protected admin pages
- Client list and client detail pages
- Quote/request list and detail pages
- Request status management
- Customer comments and internal notes
- Request editing tools
- Account management tools
- System check page for validating required files, tables, and columns

---

## Website Content Editor

The admin website editor allows editable management of:

- Business identity and contact information
- Homepage hero content
- Navigation text
- Quick contact box text
- Service section heading and intro
- Service cards
- About section content
- Contact form labels and helper text
- Footer text
- SEO metadata
- Theme colors
- Website images
- Business logo
- Hero/person image
- Favicon

---

## Image Upload Handling

Image uploads include validation for:

- Allowed file types
- MIME type
- File size
- Image dimensions
- Aspect ratio
- Upload errors
- Safe replacement of existing image variants

Supported image types:

- JPG/JPEG
- PNG
- WebP
- ICO for favicon upload

---

## Business Documents and Print Tools

The project includes tools for:

- Client document editing
- Printable customer documents
- Business card generation
- Receipt-related content settings
- Business identity reuse across documents and printable materials

---

## Tech Stack

- PHP
- MySQL
- HTML
- CSS
- JavaScript
- Bootstrap
- Apache
- XAMPP / local PHP development environment

---

## Project Structure

```text
.
├── config/
│   ├── database.php
│   ├── environment.php
│   └── initialize.php
├── config.example/
├── public/
│   ├── index.php
│   ├── request-confirmation.php
│   ├── request-update.php
│   ├── admin.php
│   ├── admin_clients.php
│   ├── admin_client_detail.php
│   ├── admin_client_edit.php
│   ├── admin_requests.php
│   ├── admin_request_detail.php
│   ├── admin_request_edit.php
│   ├── admin_request_status_settings.php
│   ├── admin_website.php
│   ├── admin_system_check.php
│   ├── admin_account.php
│   ├── admin_document_edit.php
│   ├── document_print.php
│   ├── business_cards.php
│   └── assets/
│       ├── css/
│       └── images/
├── src/
│   ├── Admin/
│   ├── Content/
│   ├── Database/
│   ├── Layout/
│   └── Session/
├── storage/
├── vendor/
├── README.md
└── .gitignore
````

---

## Security Notes

The project includes:

* Prepared database statements
* Admin authentication
* Password hashing
* Session handling
* CSRF token validation
* Honeypot spam protection
* Public request access keys
* Upload validation
* Restricted upload file types

Production secrets should not be committed to the repository.

Private local files should include:

```text
config/database.php
config/environment.php
config/initialize.php
.env
```

Safe public examples can include:

```text
config/database.example.php
config/environment.example.php
config/initialize.example.php
.env.example
```

If live credentials were committed, rotate the affected credentials and remove the secrets from Git history.

---

## Local Setup

### 1. Clone the repository

```bash
git clone https://github.com/kniraven-llc/kailslandscaping.com.git
cd kailslandscaping.com
```

### 2. Install dependencies

If Composer dependencies are used:

```bash
composer install
```

### 3. Create local configuration files

Copy the example config files and update them for the local environment.

```bash
cp config/database.example.php config/database.php
cp config/environment.example.php config/environment.php
cp config/initialize.example.php config/initialize.php
```

Update the database host, database name, username, password, and environment settings as needed.

### 4. Create the database

Create a MySQL database for the project.

A schema or migration file should be added so the database can be rebuilt from the repository.

### 5. Run locally

Place the project in a local Apache/PHP environment such as XAMPP and point the local virtual host or document root to:

```text
public/
```

The public folder should be the web root.

---

## Production Setup Notes

For production, the web server should serve only the `public/` directory.

Private folders such as `config/`, `src/`, `storage/`, and `vendor/` should not be directly web-accessible.

Recommended structure:

```text
project-root/
├── config/
├── public/       <- web root
├── src/
├── storage/
└── vendor/
```

---

## Admin Pages

Key admin routes include:

```text
/admin.php
/admin_clients.php
/admin_client_detail.php
/admin_client_edit.php
/admin_requests.php
/admin_request_detail.php
/admin_request_edit.php
/admin_request_status_settings.php
/admin_website.php
/admin_account.php
/admin_system_check.php
```

Admin and sensitive business routes should require authentication before showing private customer, request, or business data.

---

## Implementation Scope

This project includes work in the following areas:

* Custom PHP/MySQL application development
* Small-business website implementation
* Quote request and customer follow-up workflows
* Admin dashboard and CRM-style tooling
* Editable website content management
* Server-side form handling
* File upload validation
* Business document and print workflow support

---

## Author

Built by Nickolas Patino.

Portfolio:

```text
https://nickolaspatino.com
```

GitHub:

```text
https://github.com/kniraven-llc
```

```
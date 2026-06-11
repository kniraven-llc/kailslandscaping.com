<?php
declare(strict_types=1);

/*
    Website content helper.

    This file reads editable website text, buttons, services, image paths,
    request/job option lists, document option lists, business identity fields,
    and theme colors from the database.

    The public website keeps its structure in PHP and CSS.
    The database only controls content and theme values.

    This file also cleans rich text before it is shown on the website.
*/

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function getDefaultSiteContentRows(): array
{
    return [
        'site_meta_description' => [
            'label' => 'SEO: Homepage Description',
            'type' => 'textarea',
            'value' => "Kail's Landscaping provides yard clean up, residential mowing, power washing, and gutter services in DeForest, Windsor, and Sun Prairie, Wisconsin.",
            'sort_order' => 10,
        ],

        'navbar_logo_src' => [
            'label' => 'Internal Image: Website Logo',
            'type' => 'image_path',
            'value' => '/assets/images/kails-logo.png',
            'sort_order' => 20,
        ],
        'navbar_brand_text' => [
            'label' => 'Navigation: Business Name',
            'type' => 'text',
            'value' => "Kail's Landscaping",
            'sort_order' => 30,
        ],
        'nav_home_text' => [
            'label' => 'Navigation: Home Link Text',
            'type' => 'text',
            'value' => 'Home',
            'sort_order' => 40,
        ],
        'nav_services_text' => [
            'label' => 'Navigation: Services Link Text',
            'type' => 'text',
            'value' => 'Services',
            'sort_order' => 50,
        ],
        'nav_about_text' => [
            'label' => 'Navigation: About Link Text',
            'type' => 'text',
            'value' => 'About',
            'sort_order' => 60,
        ],
        'nav_contact_text' => [
            'label' => 'Navigation: Contact Link Text',
            'type' => 'text',
            'value' => 'Contact',
            'sort_order' => 70,
        ],

        'hero_eyebrow' => [
            'label' => 'Hero: Small Text Above Main Headline',
            'type' => 'text',
            'value' => 'Local Outdoor Services in DeForest, Windsor & Sun Prairie',
            'sort_order' => 100,
        ],
        'hero_title' => [
            'label' => 'Hero: Main Headline',
            'type' => 'textarea',
            'value' => 'Reliable Lawn Care, Yard Clean Up, Power Washing & Gutter Services',
            'sort_order' => 110,
        ],
        'hero_lead' => [
            'label' => 'Hero: Short Intro Paragraph',
            'type' => 'richtext',
            'value' => "<p>Kail's Landscaping helps homeowners in DeForest, Windsor, and Sun Prairie with practical outdoor services done one property at a time. Kail is here to help the local community keep their yards, homes, and outdoor spaces cleaner, safer, and easier to maintain.</p>",
            'sort_order' => 120,
        ],
        'hero_selfie_src' => [
            'label' => 'Internal Image: Hero Person Image',
            'type' => 'image_path',
            'value' => '/assets/images/kail-selfie.png',
            'sort_order' => 150,
        ],
        'hero_selfie_alt' => [
            'label' => 'Hero: Person Image Description',
            'type' => 'text',
            'value' => "Kail wearing Kail's Landscaping branded workwear",
            'sort_order' => 160,
        ],
        'hero_logo_src' => [
            'label' => 'Internal Image: Hero Logo Image',
            'type' => 'image_path',
            'value' => '/assets/images/kails-logo.png',
            'sort_order' => 170,
        ],
        'hero_logo_alt' => [
            'label' => 'Hero: Logo Image Description',
            'type' => 'text',
            'value' => "Kail's Landscaping logo",
            'sort_order' => 180,
        ],

        'quick_contact_title' => [
            'label' => 'Quick Contact: Box Title',
            'type' => 'text',
            'value' => 'Quick Contact',
            'sort_order' => 200,
        ],
        'quick_contact_body' => [
            'label' => 'Quick Contact: Main Contact Text',
            'type' => 'richtext',
            'value' => '<p><strong>Call or text:</strong> <a href="tel:6084458182">608-445-8182</a></p><p><strong>Email:</strong> <a href="mailto:mikailman7@icloud.com">mikailman7@icloud.com</a></p><p><strong>Service area:</strong> DeForest, Windsor, and Sun Prairie, WI</p><p><strong>Hours:</strong> 8am to 8pm</p>',
            'sort_order' => 210,
        ],
        'quick_contact_footer' => [
            'label' => 'Quick Contact: Bold Closing Line',
            'type' => 'text',
            'value' => 'Here to help the community, one property at a time.',
            'sort_order' => 220,
        ],

        'services_eyebrow' => [
            'label' => 'Services: Small Text Above Section Title',
            'type' => 'text',
            'value' => 'What We Do',
            'sort_order' => 300,
        ],
        'services_title' => [
            'label' => 'Services: Section Title',
            'type' => 'text',
            'value' => 'Services',
            'sort_order' => 310,
        ],
        'services_intro' => [
            'label' => 'Services: Short Intro Text',
            'type' => 'richtext',
            'value' => '<p>Kail focuses on straightforward outdoor jobs that make a real difference for local homeowners. Choose the service that best fits what you need, or explain the situation in the quote request form.</p>',
            'sort_order' => 320,
        ],

        'about_eyebrow' => [
            'label' => 'About: Small Text Above Section Title',
            'type' => 'text',
            'value' => "About Kail's Landscaping",
            'sort_order' => 400,
        ],
        'about_title' => [
            'label' => 'About: Main Title',
            'type' => 'textarea',
            'value' => 'Helping the Local Community One Property at a Time',
            'sort_order' => 410,
        ],
        'about_body_1' => [
            'label' => 'About: First Paragraph',
            'type' => 'richtext',
            'value' => "<p>Kail's Landscaping is a single-member LLC owned and operated by Kail. The business is built around helping people in the local community with practical outdoor work that keeps properties clean, maintained, and easier to enjoy.</p>",
            'sort_order' => 420,
        ],
        'about_body_2' => [
            'label' => 'About: Second Paragraph',
            'type' => 'richtext',
            'value' => '<p>Kail services DeForest, Windsor, and Sun Prairie, Wisconsin. The goal is not to overcomplicate the process: tell Kail what you need help with, provide a way to contact you, and he will follow up so the work can be handled one person and one property at a time.</p>',
            'sort_order' => 430,
        ],
        'why_choose_title' => [
            'label' => 'Why Choose Us: Box Title',
            'type' => 'text',
            'value' => "Why Choose Kail's Landscaping?",
            'sort_order' => 440,
        ],
        'why_choose_item_1' => [
            'label' => 'Why Choose Us: Bullet 1',
            'type' => 'text',
            'value' => 'Local, one-person business',
            'sort_order' => 450,
        ],
        'why_choose_item_2' => [
            'label' => 'Why Choose Us: Bullet 2',
            'type' => 'text',
            'value' => 'Focused on helping the community',
            'sort_order' => 460,
        ],
        'why_choose_item_3' => [
            'label' => 'Why Choose Us: Bullet 3',
            'type' => 'text',
            'value' => 'Serving DeForest, Windsor, and Sun Prairie',
            'sort_order' => 470,
        ],
        'why_choose_item_4' => [
            'label' => 'Why Choose Us: Bullet 4',
            'type' => 'text',
            'value' => 'Simple quote request and follow-up process',
            'sort_order' => 480,
        ],

        'contact_eyebrow' => [
            'label' => 'Contact Form: Small Text Above Section Title',
            'type' => 'text',
            'value' => 'Ready to Get Started?',
            'sort_order' => 500,
        ],
        'contact_title' => [
            'label' => 'Contact Form: Section Title',
            'type' => 'text',
            'value' => 'Request a Quote',
            'sort_order' => 510,
        ],
        'contact_intro' => [
            'label' => 'Contact Form: Short Intro Text',
            'type' => 'richtext',
            'value' => '<p>Need help with yard clean up, mowing, power washing, or gutter services? Send the details below. Please include either a phone number or an email address so Kail can follow up.</p>',
            'sort_order' => 520,
        ],
        'form_name_label' => [
            'label' => 'Contact Form: Name Field Label',
            'type' => 'text',
            'value' => 'Name',
            'sort_order' => 530,
        ],
        'form_phone_label' => [
            'label' => 'Contact Form: Phone Field Label',
            'type' => 'text',
            'value' => 'Phone',
            'sort_order' => 540,
        ],
        'form_email_label' => [
            'label' => 'Contact Form: Email Field Label',
            'type' => 'text',
            'value' => 'Email',
            'sort_order' => 550,
        ],
        'form_service_label' => [
            'label' => 'Contact Form: Service Dropdown Label',
            'type' => 'text',
            'value' => 'Service Needed',
            'sort_order' => 560,
        ],
        'form_service_placeholder' => [
            'label' => 'Contact Form: Service Dropdown Placeholder',
            'type' => 'text',
            'value' => 'Choose a service...',
            'sort_order' => 570,
        ],
        'form_message_label' => [
            'label' => 'Contact Form: Message Field Label',
            'type' => 'text',
            'value' => 'Project Details',
            'sort_order' => 620,
        ],
        'form_submit_text' => [
            'label' => 'Contact Form: Submit Button Text',
            'type' => 'text',
            'value' => 'Submit Quote Request',
            'sort_order' => 630,
        ],
        'quote_form_success_message' => [
            'label' => 'Contact Form: Success Message',
            'type' => 'richtext',
            'value' => '<p>Thank you. Your request has been saved. Kail will follow up with you soon.</p>',
            'sort_order' => 640,
        ],
        'quote_form_error_message' => [
            'label' => 'Contact Form: Error Message',
            'type' => 'richtext',
            'value' => '<p>Please check the form and try again. Make sure you provide either a phone number or an email address.</p>',
            'sort_order' => 650,
        ],

        'footer_copyright_suffix' => [
            'label' => 'Footer: Copyright Ending Text',
            'type' => 'text',
            'value' => 'All rights reserved.',
            'sort_order' => 700,
        ],
        'footer_tagline' => [
            'label' => 'Footer: Short Tagline',
            'type' => 'text',
            'value' => 'Helping the local community one property at a time.',
            'sort_order' => 710,
        ],

        'business_name' => [
            'label' => 'Business Identity: Business Name',
            'type' => 'text',
            'value' => "Kail's Landscaping",
            'sort_order' => 800,
        ],
        'business_owner_display_name' => [
            'label' => 'Business Identity: Owner Display Name',
            'type' => 'text',
            'value' => 'Kail',
            'sort_order' => 810,
        ],
        'business_legal_owner_name' => [
            'label' => 'Business Identity: Legal Owner Name',
            'type' => 'text',
            'value' => 'Mikail Taylor Alexander',
            'sort_order' => 820,
        ],
        'business_phone_display' => [
            'label' => 'Business Contact: Phone Number Display',
            'type' => 'text',
            'value' => '608-445-8182',
            'sort_order' => 830,
        ],
        'business_phone_digits' => [
            'label' => 'Business Contact: Phone Number Digits',
            'type' => 'text',
            'value' => '6084458182',
            'sort_order' => 840,
        ],
        'business_email' => [
            'label' => 'Business Contact: Email Address',
            'type' => 'text',
            'value' => 'mikailman7@icloud.com',
            'sort_order' => 850,
        ],
        'business_website' => [
            'label' => 'Business Contact: Website',
            'type' => 'text',
            'value' => 'https://kailslandscaping.com',
            'sort_order' => 860,
        ],
        'business_service_area' => [
            'label' => 'Business Contact: Service Area',
            'type' => 'text',
            'value' => 'DeForest, Windsor, and Sun Prairie, WI',
            'sort_order' => 870,
        ],
        'business_hours' => [
            'label' => 'Business Contact: Business Hours',
            'type' => 'text',
            'value' => '8am to 8pm',
            'sort_order' => 880,
        ],
        'business_tagline' => [
            'label' => 'Business Identity: Tagline',
            'type' => 'text',
            'value' => 'Helping the local community one property at a time.',
            'sort_order' => 890,
        ],
        'business_logo_src' => [
            'label' => 'Business Identity: Logo Image',
            'type' => 'image_path',
            'value' => '/assets/images/kails-logo.png',
            'sort_order' => 900,
        ],
        'business_person_image_src' => [
            'label' => 'Business Identity: Person Image',
            'type' => 'image_path',
            'value' => '/assets/images/kail-selfie.png',
            'sort_order' => 910,
        ],
        'business_person_image_alt' => [
            'label' => 'Business Identity: Person Image Description',
            'type' => 'text',
            'value' => "Kail wearing Kail's Landscaping branded workwear",
            'sort_order' => 920,
        ],

        'business_card_headline' => [
            'label' => 'Business Card: Headline',
            'type' => 'text',
            'value' => "Kail's Landscaping",
            'sort_order' => 930,
        ],
        'business_card_tagline' => [
            'label' => 'Business Card: Tagline',
            'type' => 'text',
            'value' => 'Helping the local community one property at a time.',
            'sort_order' => 940,
        ],
        'business_card_services_line' => [
            'label' => 'Business Card: Services Line',
            'type' => 'text',
            'value' => 'Yard Clean Up • Residential Mowing • Power Washing • Gutter Services',
            'sort_order' => 950,
        ],
        'business_card_qr_url' => [
            'label' => 'Business Card: QR Code Link',
            'type' => 'text',
            'value' => 'https://kailslandscaping.com',
            'sort_order' => 960,
        ],

        'receipt_number_prefix' => [
            'label' => 'Receipts: Receipt Number Prefix',
            'type' => 'text',
            'value' => 'KL-',
            'sort_order' => 970,
        ],
        'receipt_default_title' => [
            'label' => 'Receipts: Default Receipt Title',
            'type' => 'text',
            'value' => 'Receipt',
            'sort_order' => 980,
        ],
        'receipt_footer_note' => [
            'label' => 'Receipts: Footer Note',
            'type' => 'richtext',
            'value' => "<p>Thank you for supporting a local, single-member LLC. Kail's Landscaping is here to help the community one property at a time.</p>",
            'sort_order' => 990,
        ],
        'receipt_payment_note' => [
            'label' => 'Receipts: Payment Note',
            'type' => 'richtext',
            'value' => '<p>Payment is due upon completion unless otherwise agreed.</p>',
            'sort_order' => 1000,
        ],

        'quote_number_prefix' => [
            'label' => 'Quotes: Quote Number Prefix',
            'type' => 'text',
            'value' => 'KQ-',
            'sort_order' => 1010,
        ],
        'quote_default_title' => [
            'label' => 'Quotes: Default Quote Title',
            'type' => 'text',
            'value' => 'Quote',
            'sort_order' => 1020,
        ],
        'quote_footer_note' => [
            'label' => 'Quotes: Footer Note',
            'type' => 'richtext',
            'value' => "<p>Thank you for considering Kail's Landscaping. This quote is an estimate based on the information available at the time it was prepared.</p>",
            'sort_order' => 1030,
        ],
        'quote_payment_note' => [
            'label' => 'Quotes: Quote Note',
            'type' => 'richtext',
            'value' => '<p>This quote is not a receipt. Final pricing may change if the job scope changes.</p>',
            'sort_order' => 1040,
        ],
    ];
}

function getDefaultThemeColorRows(): array
{
    return [
        'kails_black' => [
            'css_variable' => '--kails-black',
            'label' => 'Main Background Color',
            'value' => '#060707',
            'sort_order' => 10,
        ],
        'kails_white' => [
            'css_variable' => '--kails-white',
            'label' => 'Main Text Color',
            'value' => '#F0F2ED',
            'sort_order' => 20,
        ],
        'kails_yellow' => [
            'css_variable' => '--kails-yellow',
            'label' => 'Primary Action Color',
            'value' => '#F2CC16',
            'sort_order' => 30,
        ],
        'kails_green' => [
            'css_variable' => '--kails-green',
            'label' => 'Brand Accent Color',
            'value' => '#97B62A',
            'sort_order' => 40,
        ],
        'kails_dark_green' => [
            'css_variable' => '--kails-dark-green',
            'label' => 'Deep Accent Color',
            'value' => '#365123',
            'sort_order' => 50,
        ],
        'kails_charcoal' => [
            'css_variable' => '--kails-charcoal',
            'label' => 'Dark Surface Color',
            'value' => '#0d0f0d',
            'sort_order' => 60,
        ],
        'kails_panel' => [
            'css_variable' => '--kails-panel',
            'label' => 'Card / Panel Background Color',
            'value' => '#11150f',
            'sort_order' => 70,
        ],
        'kails_panel_light' => [
            'css_variable' => '--kails-panel-light',
            'label' => 'Secondary Panel Background Color',
            'value' => '#171f13',
            'sort_order' => 80,
        ],
    ];
}

function getDefaultButtonRows(): array
{
    return [
        'hero_primary' => [
            'label' => 'Hero: Main Button',
            'help' => 'This is the main button near the top of the homepage. The button text and destination must match. Example: if the text says "Request a Quote", the destination should usually be "#contact".',
            'text' => 'Request a Quote',
            'href' => '#contact',
            'sort_order' => 100,
        ],
        'hero_secondary' => [
            'label' => 'Hero: Second Button',
            'help' => 'This is the secondary button near the top of the homepage. It should send visitors to more information before they contact the business.',
            'text' => 'View Services',
            'href' => '#services',
            'sort_order' => 110,
        ],
        'services_cta' => [
            'label' => 'Services: Button Below Service Cards',
            'help' => 'This button appears below the service cards. It should usually send visitors to the quote/contact form.',
            'text' => 'Request a Quote',
            'href' => '#contact',
            'sort_order' => 300,
        ],
    ];
}

function getDefaultServiceRows(): array
{
    return [
        [
            'id' => 0,
            'service_title' => 'Yard Clean Up',
            'service_description' => '<p>Clean up leaves, branches, debris, overgrowth, and general outdoor clutter so the property looks cleaner and easier to maintain.</p>',
            'service_image_path' => null,
            'service_image_alt' => 'Cleaned residential yard',
            'is_active' => 1,
            'sort_order' => 10,
        ],
        [
            'id' => 0,
            'service_title' => 'Residential Mowing',
            'service_description' => '<p>Reliable residential mowing for homeowners who want their lawn kept neat, trimmed, and presentable.</p>',
            'service_image_path' => null,
            'service_image_alt' => 'Freshly mowed residential lawn',
            'is_active' => 1,
            'sort_order' => 20,
        ],
        [
            'id' => 0,
            'service_title' => 'Power Washing',
            'service_description' => '<p>Power washing for outdoor surfaces that need dirt, buildup, and grime removed from around the property.</p>',
            'service_image_path' => null,
            'service_image_alt' => 'Power washed outdoor surface',
            'is_active' => 1,
            'sort_order' => 30,
        ],
        [
            'id' => 0,
            'service_title' => 'Gutter Services',
            'service_description' => '<p>Gutter cleaning and related gutter services to help water move properly away from the home.</p>',
            'service_image_path' => null,
            'service_image_alt' => 'Residential gutter service',
            'is_active' => 1,
            'sort_order' => 40,
        ],
    ];
}

function getDefaultRequestStatusOptions(): array
{
    return [
        'New',
        'Contacted',
        'Needs Estimate',
        'Estimate Scheduled',
        'Quoted',
        'Accepted',
        'Scheduled',
        'In Progress',
        'Completed',
        'Cancelled',
        'Archived',
    ];
}

function getDefaultRequestSourceOptions(): array
{
    return [
        'Website Form',
        'Phone Call',
        'Text Message',
        'Email',
        'Facebook',
        'Referral',
        'Other',
    ];
}

function getDefaultQuoteItemTypeOptions(): array
{
    return [
        'Labor',
        'Materials',
        'Equipment',
        'Disposal',
        'Travel',
        'Discount',
        'Other',
    ];
}

function getDefaultDocumentStatusOptions(): array
{
    return [
        'Draft',
        'Ready to Send',
        'Ready to Print',
        'Sent',
        'Printed',
        'Accepted',
        'Declined',
        'Expired',
        'Converted to Receipt',
        'Partially Paid',
        'Paid',
        'Void',
        'Archived',
    ];
}

function getDefaultReceiptStatusOptions(): array
{
    return getDefaultDocumentStatusOptions();
}

function getDefaultPaymentMethodOptions(): array
{
    return [
        'Not Recorded',
        'Cash',
        'Check',
        'Card',
        'Venmo',
        'PayPal',
        'Zelle',
        'Other',
    ];
}

function siteContentHelpText(string $key): string
{
    $help = [
        'site_meta_description' => 'This is the short homepage description search engines may show. Keep it clear and local. Good length: about 140 to 160 characters.',

        'navbar_brand_text' => 'This is the business name shown beside the logo in the top menu.',
        'nav_home_text' => 'This is the text for the Home link in the top menu. It links to the homepage.',
        'nav_services_text' => 'This is the text for the Services link in the top menu. It jumps to the services section.',
        'nav_about_text' => 'This is the text for the About link in the top menu. It jumps to the about section.',
        'nav_contact_text' => 'This is the text for the Contact link in the top menu. It jumps to the contact form.',

        'hero_eyebrow' => 'Small uppercase text above the main headline. Use it to quickly say what the business does and where it works.',
        'hero_title' => 'The biggest headline on the page. This should clearly explain why a visitor should choose the business.',
        'hero_lead' => 'Short paragraph below the headline. Use it to summarize the services, service area, and who they are for. Basic formatting is allowed here.',
        'hero_selfie_alt' => 'Short description of the person image for screen readers. Example: "Kail wearing a Kail’s Landscaping hoodie."',
        'hero_logo_alt' => 'Short description of the logo image for screen readers. Example: "Kail’s Landscaping logo."',

        'quick_contact_title' => 'Title for the contact box near the top of the page.',
        'quick_contact_body' => 'Main contact text. This is a good place for phone number, email, location, business hours, and service area. Basic formatting is allowed here.',
        'quick_contact_footer' => 'Bold closing line in the contact box. Use it for a trust-building phrase or follow-up expectation.',

        'services_eyebrow' => 'Small text above the services section title.',
        'services_title' => 'Main title for the services section.',
        'services_intro' => 'Short explanation shown above the service cards. Basic formatting is allowed here.',

        'about_eyebrow' => 'Small text above the About section title.',
        'about_title' => 'Main headline for the About section.',
        'about_body_1' => 'First paragraph in the About section. Explain who the business is and what customers can expect. Basic formatting is allowed here.',
        'about_body_2' => 'Second paragraph in the About section. Use this for service area, availability, reliability, quality, or local trust. Basic formatting is allowed here.',
        'why_choose_title' => 'Title for the trust-building bullet list.',
        'why_choose_item_1' => 'First trust-building bullet point.',
        'why_choose_item_2' => 'Second trust-building bullet point.',
        'why_choose_item_3' => 'Third trust-building bullet point.',
        'why_choose_item_4' => 'Fourth trust-building bullet point.',

        'contact_eyebrow' => 'Small text above the contact form title.',
        'contact_title' => 'Main title above the contact form.',
        'contact_intro' => 'Short explanation shown above the contact form. Basic formatting is allowed here.',
        'form_name_label' => 'Label for the customer name field.',
        'form_phone_label' => 'Label for the customer phone field. Customers must provide either phone or email.',
        'form_email_label' => 'Label for the customer email field. Customers must provide either phone or email.',
        'form_service_label' => 'Label above the service dropdown.',
        'form_service_placeholder' => 'First dropdown option shown before the customer picks a service.',
        'form_message_label' => 'Label for the project details message box.',
        'form_submit_text' => 'Text on the form submit button.',
        'quote_form_success_message' => 'Message shown after a customer successfully submits a quote request.',
        'quote_form_error_message' => 'General error message shown when the customer needs to fix the form.',

        'footer_copyright_suffix' => 'Text after the business name and year in the footer.',
        'footer_tagline' => 'Short phrase shown on the right side of the footer.',

        'business_name' => 'Official business name used by business cards, quotes, receipts, and printable tools.',
        'business_owner_display_name' => 'Public-facing owner name. Example: Kail.',
        'business_legal_owner_name' => 'Legal owner name for records. This does not need to be displayed everywhere.',
        'business_phone_display' => 'Phone number as customers should see it.',
        'business_phone_digits' => 'Phone number with only digits. This is used for phone links.',
        'business_email' => 'Main customer-facing business email address.',
        'business_website' => 'Website URL used on quotes, receipts, and business cards.',
        'business_service_area' => 'Short service area line.',
        'business_hours' => 'Business hours shown on business materials.',
        'business_tagline' => 'Reusable business tagline for website, quotes, receipts, and business cards.',
        'business_person_image_alt' => 'Short description of the person image for screen readers.',

        'business_card_headline' => 'Main headline printed on the business card.',
        'business_card_tagline' => 'Short tagline printed on the business card.',
        'business_card_services_line' => 'Short services line printed on the business card.',
        'business_card_qr_url' => 'The URL used to generate the QR code on business cards.',

        'receipt_number_prefix' => 'Prefix used before generated receipt numbers. Example: KL-',
        'receipt_default_title' => 'Default title shown at the top of printed receipts.',
        'receipt_footer_note' => 'Message shown near the bottom of printed receipts.',
        'receipt_payment_note' => 'Payment instructions or payment expectation shown on printed receipts.',

        'quote_number_prefix' => 'Prefix used before generated quote numbers. Example: KQ-',
        'quote_default_title' => 'Default title shown at the top of printed quotes.',
        'quote_footer_note' => 'Message shown near the bottom of printed quotes.',
        'quote_payment_note' => 'Quote instructions or quote disclaimer shown on printed quotes.',
    ];

    return $help[$key] ?? 'Editable website content.';
}

function themeColorHelpText(string $key): string
{
    $help = [
        'kails_black' => 'Main page background color. This affects the overall dark base of the website.',
        'kails_white' => 'Main text color used on dark backgrounds.',
        'kails_yellow' => 'Main action color used for important buttons, highlights, and attention-grabbing accents. This does not have to be yellow.',
        'kails_green' => 'Supporting brand accent color used for glows, borders, and secondary visual accents.',
        'kails_dark_green' => 'Deeper accent color used in darker gradients and brand-themed backgrounds.',
        'kails_charcoal' => 'Dark surface color used for subtle background changes.',
        'kails_panel' => 'Card and panel background color.',
        'kails_panel_light' => 'Secondary panel color used when a section needs slightly more contrast.',
    ];

    return $help[$key] ?? 'Editable theme color.';
}

function buttonDestinationHelpText(): string
{
    return 'Use "#contact" to jump to the quote form, "#services" to jump to the services section, "#about" to jump to the about section, "/gallery.php" to link to another page on this site, "https://example.com" to link to another website, "tel:6085551234" for phone calls, or "mailto:name@example.com" for email.';
}

function getEditableSiteContentRows(): array
{
    $rows = getDefaultSiteContentRows();

    try {
        $connection = getDatabaseConnection();
        $result = $connection->query(
            'SELECT content_key, content_label, content_type, content_value, sort_order
             FROM site_content
             ORDER BY sort_order ASC, content_label ASC'
        );

        while ($row = $result->fetch_assoc()) {
            $key = (string)$row['content_key'];
            $existingRow = $rows[$key] ?? [];

            $rows[$key] = [
                'label' => (string)($existingRow['label'] ?? $row['content_label']),
                'type' => (string)($existingRow['type'] ?? $row['content_type']),
                'value' => (string)$row['content_value'],
                'sort_order' => (int)($existingRow['sort_order'] ?? $row['sort_order']),
            ];
        }
    } catch (Throwable $exception) {
        return $rows;
    }

    uasort($rows, function (array $first, array $second): int {
        return ($first['sort_order'] ?? 0) <=> ($second['sort_order'] ?? 0);
    });

    return $rows;
}

function getEditableThemeColorRows(): array
{
    $rows = getDefaultThemeColorRows();

    try {
        $connection = getDatabaseConnection();
        $result = $connection->query(
            'SELECT color_key, css_variable, color_label, color_value, sort_order
             FROM theme_colors
             ORDER BY sort_order ASC, color_label ASC'
        );

        while ($row = $result->fetch_assoc()) {
            $key = (string)$row['color_key'];
            $existingRow = $rows[$key] ?? [];

            $rows[$key] = [
                'css_variable' => (string)($existingRow['css_variable'] ?? $row['css_variable']),
                'label' => (string)($existingRow['label'] ?? $row['color_label']),
                'value' => (string)$row['color_value'],
                'sort_order' => (int)($existingRow['sort_order'] ?? $row['sort_order']),
            ];
        }
    } catch (Throwable $exception) {
        return $rows;
    }

    uasort($rows, function (array $first, array $second): int {
        return ($first['sort_order'] ?? 0) <=> ($second['sort_order'] ?? 0);
    });

    return $rows;
}

function getEditableButtonRows(): array
{
    $rows = getDefaultButtonRows();

    try {
        $connection = getDatabaseConnection();
        $result = $connection->query(
            'SELECT button_key, button_label, button_help, button_text, button_href, sort_order
             FROM site_buttons
             ORDER BY sort_order ASC, button_label ASC'
        );

        while ($row = $result->fetch_assoc()) {
            $key = (string)$row['button_key'];
            $existingRow = $rows[$key] ?? [];

            $rows[$key] = [
                'label' => (string)($existingRow['label'] ?? $row['button_label']),
                'help' => (string)($existingRow['help'] ?? $row['button_help']),
                'text' => (string)$row['button_text'],
                'href' => (string)$row['button_href'],
                'sort_order' => (int)($existingRow['sort_order'] ?? $row['sort_order']),
            ];
        }
    } catch (Throwable $exception) {
        return $rows;
    }

    uasort($rows, function (array $first, array $second): int {
        return ($first['sort_order'] ?? 0) <=> ($second['sort_order'] ?? 0);
    });

    return $rows;
}

function getActiveServiceRows(): array
{
    try {
        $connection = getDatabaseConnection();
        $statement = $connection->prepare(
            'SELECT id, service_title, service_description, service_image_path, service_image_alt, is_active, sort_order
             FROM services
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC
             LIMIT 9'
        );

        $statement->execute();
        $result = $statement->get_result();

        $services = [];

        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }

        if (!empty($services)) {
            return $services;
        }
    } catch (Throwable $exception) {
        return getDefaultServiceRows();
    }

    return getDefaultServiceRows();
}

function getAllServiceRows(): array
{
    try {
        $connection = getDatabaseConnection();
        $result = $connection->query(
            'SELECT id, service_title, service_description, service_image_path, service_image_alt, is_active, sort_order
             FROM services
             ORDER BY sort_order ASC, id ASC
             LIMIT 9'
        );

        $services = [];

        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }

        return $services;
    } catch (Throwable $exception) {
        return getDefaultServiceRows();
    }
}

function getRequestStatusOptions(): array
{
    try {
        $connection = getDatabaseConnection();
        $result = $connection->query(
            'SELECT status_name
             FROM request_status_options
             ORDER BY sort_order ASC, status_name ASC'
        );

        $options = [];

        while ($row = $result->fetch_assoc()) {
            $options[] = (string)$row['status_name'];
        }

        if (!empty($options)) {
            return $options;
        }
    } catch (Throwable $exception) {
        return getDefaultRequestStatusOptions();
    }

    return getDefaultRequestStatusOptions();
}

function getRequestSourceOptions(): array
{
    try {
        $connection = getDatabaseConnection();
        $result = $connection->query(
            'SELECT source_name
             FROM request_source_options
             ORDER BY sort_order ASC, source_name ASC'
        );

        $options = [];

        while ($row = $result->fetch_assoc()) {
            $options[] = (string)$row['source_name'];
        }

        if (!empty($options)) {
            return $options;
        }
    } catch (Throwable $exception) {
        return getDefaultRequestSourceOptions();
    }

    return getDefaultRequestSourceOptions();
}

function getQuoteItemTypeOptions(): array
{
    try {
        $connection = getDatabaseConnection();
        $result = $connection->query(
            'SELECT item_type_name
             FROM quote_item_type_options
             ORDER BY sort_order ASC, item_type_name ASC'
        );

        $options = [];

        while ($row = $result->fetch_assoc()) {
            $options[] = (string)$row['item_type_name'];
        }

        if (!empty($options)) {
            return $options;
        }
    } catch (Throwable $exception) {
        return getDefaultQuoteItemTypeOptions();
    }

    return getDefaultQuoteItemTypeOptions();
}

function getDocumentStatusOptions(): array
{
    try {
        $connection = getDatabaseConnection();

        $result = $connection->query(
            'SELECT status_name
             FROM document_status_options
             ORDER BY sort_order ASC, status_name ASC'
        );

        $options = [];

        while ($row = $result->fetch_assoc()) {
            $options[] = (string)$row['status_name'];
        }

        if (!empty($options)) {
            return $options;
        }
    } catch (Throwable $exception) {
        return getDefaultDocumentStatusOptions();
    }

    return getDefaultDocumentStatusOptions();
}

function getReceiptStatusOptions(): array
{
    return getDocumentStatusOptions();
}

function getPaymentMethodOptions(): array
{
    try {
        $connection = getDatabaseConnection();
        $result = $connection->query(
            'SELECT method_name
             FROM payment_method_options
             ORDER BY sort_order ASC, method_name ASC'
        );

        $options = [];

        while ($row = $result->fetch_assoc()) {
            $options[] = (string)$row['method_name'];
        }

        if (!empty($options)) {
            return $options;
        }
    } catch (Throwable $exception) {
        return getDefaultPaymentMethodOptions();
    }

    return getDefaultPaymentMethodOptions();
}

function siteContentValues(): array
{
    static $values = null;

    if (is_array($values)) {
        return $values;
    }

    $values = [];

    foreach (getEditableSiteContentRows() as $key => $row) {
        $values[$key] = (string)($row['value'] ?? '');
    }

    return $values;
}

function contentValue(string $key): string
{
    $values = siteContentValues();

    return (string)($values[$key] ?? '');
}

function buttonValue(string $buttonKey, string $part): string
{
    $buttons = getEditableButtonRows();

    return (string)($buttons[$buttonKey][$part] ?? '');
}

function isSafeRichTextHref(string $href): bool
{
    $href = trim($href);

    if ($href === '') {
        return false;
    }

    $allowedStarts = [
        '#',
        '/',
        'https://',
        'http://',
        'tel:',
        'mailto:',
    ];

    foreach ($allowedStarts as $allowedStart) {
        if (str_starts_with($href, $allowedStart)) {
            return true;
        }
    }

    return false;
}

function sanitizeRichHtmlNode(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return escapeHtml($node->nodeValue);
    }

    if (!($node instanceof DOMElement)) {
        $html = '';

        foreach ($node->childNodes as $childNode) {
            $html .= sanitizeRichHtmlNode($childNode);
        }

        return $html;
    }

    $tagName = strtolower($node->nodeName);

    if ($tagName === 'script' || $tagName === 'style') {
        return '';
    }

    $childrenHtml = '';

    foreach ($node->childNodes as $childNode) {
        $childrenHtml .= sanitizeRichHtmlNode($childNode);
    }

    if ($tagName === 'div') {
        $tagName = 'p';
    }

    if ($tagName === 'b') {
        $tagName = 'strong';
    }

    if ($tagName === 'i') {
        $tagName = 'em';
    }

    if ($tagName === 'br') {
        return '<br>';
    }

    $allowedTags = [
        'p',
        'strong',
        'em',
        'u',
        'ul',
        'ol',
        'li',
        'a',
    ];

    if (!in_array($tagName, $allowedTags, true)) {
        return $childrenHtml;
    }

    if ($tagName === 'a') {
        $href = trim($node->getAttribute('href'));

        if (!isSafeRichTextHref($href)) {
            return $childrenHtml;
        }

        return '<a href="' . escapeHtml($href) . '">' . $childrenHtml . '</a>';
    }

    return '<' . $tagName . '>' . $childrenHtml . '</' . $tagName . '>';
}

function sanitizeRichHtml(string $html): string
{
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    if ($html === strip_tags($html)) {
        return '<p>' . nl2br(escapeHtml($html), false) . '</p>';
    }

    if (!class_exists('DOMDocument')) {
        return '<p>' . nl2br(escapeHtml(trim(strip_tags($html))), false) . '</p>';
    }

    $document = new DOMDocument();

    libxml_use_internal_errors(true);

    $loaded = $document->loadHTML(
        '<?xml encoding="UTF-8"><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();

    if (!$loaded) {
        return '<p>' . nl2br(escapeHtml(trim(strip_tags($html))), false) . '</p>';
    }

    $wrapper = $document->getElementsByTagName('div')->item(0);

    if (!$wrapper) {
        return '<p>' . nl2br(escapeHtml(trim(strip_tags($html))), false) . '</p>';
    }

    $cleanHtml = '';

    foreach ($wrapper->childNodes as $childNode) {
        $cleanHtml .= sanitizeRichHtmlNode($childNode);
    }

    return trim($cleanHtml);
}

function richContentHtml(string $key): string
{
    return sanitizeRichHtml(contentValue($key));
}

function themeCssVariableOverrides(): string
{
    $cssLines = [];

    foreach (getEditableThemeColorRows() as $row) {
        $cssVariable = (string)($row['css_variable'] ?? '');
        $colorValue = (string)($row['value'] ?? '');

        if (!preg_match('/^--[a-zA-Z0-9-]+$/', $cssVariable)) {
            continue;
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorValue)) {
            continue;
        }

        $cssLines[] = '    ' . $cssVariable . ': ' . $colorValue . ';';
    }

    if (empty($cssLines)) {
        return '';
    }

    return ":root {\n" . implode("\n", $cssLines) . "\n}";
}
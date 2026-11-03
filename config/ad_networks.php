<?php

// Ad network catalogue for the super-admin "Ad Networks" page.
// Each field: [key, label, is_secret, help text].

return [
    'meta' => [
        'name' => 'Meta (Facebook & Instagram)', 'category' => 'Social', 'stage' => 1,
        'console' => 'https://developers.facebook.com/apps',
        'steps' => [
            'Go to developers.facebook.com/apps, click Create App, choose the Business type and link it to your Business Portfolio.',
            'Add the Marketing API product and Facebook Login for Business.',
            'Copy the App ID and App Secret from App settings > Basic.',
            'In Facebook Login for Business > Configurations, create a configuration with ads_read, ads_management and business_management, then copy its Configuration ID.',
            'Paste the Redirect URL below into Facebook Login > Settings > Valid OAuth Redirect URIs.',
            'Complete Business Verification, then request Advanced access for ads_read and ads_management in App Review (needed to manage client accounts).',
        ],
        'fields' => [
            ['app_id', 'App ID', false, 'App settings > Basic > App ID (numbers only).'],
            ['app_secret', 'App Secret', true, 'App settings > Basic > App Secret > Show. Never share it.'],
            ['config_id', 'Login configuration ID', false, 'Facebook Login for Business > Configurations > your configuration > Configuration ID.'],
            ['api_version', 'Graph API version', false, 'The latest version in the Marketing API changelog, written like v23.0.'],
        ],
    ],
    'google_ads' => [
        'name' => 'Google Ads (incl. YouTube)', 'category' => 'Search', 'stage' => 1,
        'console' => 'https://console.cloud.google.com/',
        'steps' => [
            'Create a dedicated Google Cloud project for AdsRanger (Cloud Console > project picker > New project).',
            'APIs & Services > Library: enable the Google Ads API (and the Merchant API for Shopping).',
            'OAuth consent screen: app name AdsRanger, domain adsranger.com, privacy and terms URLs, then complete brand verification.',
            'Credentials > Create credentials > OAuth client ID > Web application. Add the Redirect URL below as an authorised redirect URI.',
            'Google Ads API > Overview in the Cloud Console: apply for Basic access. Developer tokens were retired on 10 Sep 2026; access now belongs to the Cloud project.',
        ],
        'fields' => [
            ['client_id', 'OAuth Client ID', false, 'APIs & Services > Credentials > your Web client. Ends with .apps.googleusercontent.com.'],
            ['client_secret', 'OAuth Client Secret', true, 'Same screen as the Client ID > Client secret.'],
            ['project_id', 'Cloud project ID', false, 'Cloud Console project picker > ID column (not the project name).'],
            ['login_customer_id', 'Manager (MCC) account ID', false, 'Optional. 10 digits without dashes, shown top right in Google Ads when signed in to your manager account.'],
        ],
    ],
    'google_merchant' => [
        'name' => 'Google Merchant Center / Shopping', 'category' => 'Retail', 'stage' => 1,
        'console' => 'https://merchants.google.com/',
        'steps' => [
            'Merchant Center uses the same Google Cloud project and OAuth client as Google Ads.',
            'Enable the Merchant API in APIs & Services > Library on that project.',
            'Leave the fields below empty to reuse the Google Ads keys.',
        ],
        'fields' => [
            ['client_id', 'OAuth Client ID (optional)', false, 'Leave empty to reuse the Google Ads client.'],
            ['client_secret', 'OAuth Client Secret (optional)', true, 'Leave empty to reuse the Google Ads client.'],
        ],
    ],
    'tiktok' => [
        'name' => 'TikTok Ads', 'category' => 'Social', 'stage' => 1,
        'console' => 'https://business-api.tiktok.com/portal',
        'steps' => [
            'Go to business-api.tiktok.com > Become a Developer. Register with your business email and fill in the company name and website.',
            'My Apps > Create App: name AdsRanger, a short description, and the Redirect URL below as the advertiser redirect URL. Tick the Ads Management, Reporting and Audience scopes.',
            'Wait for approval (about 2 to 3 business days), then open the app to copy the App ID and Secret.',
        ],
        'fields' => [
            ['app_id', 'App ID', false, 'My Apps > your app > App ID.'],
            ['app_secret', 'Secret', true, 'My Apps > your app > Secret.'],
        ],
    ],
    'snapchat' => [
        'name' => 'Snapchat Ads', 'category' => 'Social', 'stage' => 1,
        'console' => 'https://business.snapchat.com/',
        'steps' => [
            'You need the Organization Admin role in Snapchat Business Manager.',
            'Business Details > OAuth Apps > accept the developer terms > Create OAuth App.',
            'Enter the app name AdsRanger and the Redirect URL below.',
            'Copy the Client ID and Client Secret. The secret is shown only once.',
        ],
        'fields' => [
            ['client_id', 'Client ID', false, 'Business Details > OAuth Apps > your app > Client ID.'],
            ['client_secret', 'Client Secret', true, 'Shown once when the app is created. Create a new app if it was lost.'],
        ],
    ],
    'microsoft' => [
        'name' => 'Microsoft (Bing) Ads', 'category' => 'Search', 'stage' => 1,
        'console' => 'https://developers.ads.microsoft.com/',
        'steps' => [
            'Sign in to developers.ads.microsoft.com with a Super Admin user of your Microsoft Advertising account and request a Developer Token (third-party tools can wait up to 5 business days).',
            'portal.azure.com > Microsoft Entra ID > App registrations > New registration. Supported accounts: any organizational directory and personal Microsoft accounts. Add the Redirect URL below as a Web redirect.',
            'Copy the Application (client) ID. Certificates & secrets > New client secret > copy the Value straight away.',
            'Build on the REST API: the SOAP API (v13) shuts down on 31 Jan 2027.',
        ],
        'fields' => [
            ['developer_token', 'Developer Token', true, 'Developer portal > Account > Developer Token.'],
            ['client_id', 'Application (client) ID', false, 'Entra ID > App registrations > your app > Overview.'],
            ['client_secret', 'Client secret value', true, 'Certificates & secrets > the Value column, not the Secret ID.'],
            ['tenant', 'Tenant', false, 'Use common unless sign-in is limited to one directory.'],
        ],
    ],
    'linkedin' => [
        'name' => 'LinkedIn Ads', 'category' => 'Social', 'stage' => 1,
        'console' => 'https://www.linkedin.com/developers/apps',
        'steps' => [
            'Create a LinkedIn Company Page for AdsRanger if there is none yet.',
            'linkedin.com/developers/apps > Create app, link it to the Company Page, add the logo and privacy policy URL.',
            'Products tab > request the Advertising API. Apps start on the Development tier (up to 5 ad accounts).',
            'Auth tab: copy the Client ID and Primary Client Secret, and add the Redirect URL below.',
            'When ready, open a ticket at linkedin.com/help/linkedin/ask/dsapi to request the Standard tier.',
        ],
        'fields' => [
            ['client_id', 'Client ID', false, 'Developer app > Auth tab > Client ID.'],
            ['client_secret', 'Primary Client Secret', true, 'Developer app > Auth tab > Primary Client Secret.'],
        ],
    ],
    'x' => [
        'name' => 'X Ads', 'category' => 'Social', 'stage' => 1,
        'console' => 'https://console.x.com/',
        'steps' => [
            'Create a developer account at console.x.com.',
            'Create a Project and App. In User authentication settings, add the Redirect URL below and choose Read and write.',
            'Keys and tokens: copy the API Key and API Key Secret. They are shown once; regenerating them breaks the old ones.',
            'Submit the Ads API access form for this App and choose Standard Access.',
        ],
        'fields' => [
            ['api_key', 'API Key (Consumer Key)', false, 'App > Keys and tokens > Consumer Keys > API Key.'],
            ['api_secret', 'API Key Secret', true, 'App > Keys and tokens > Consumer Keys > API Key Secret.'],
        ],
    ],
];

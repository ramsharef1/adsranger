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
            ['api_version', 'Google Ads API version', false, 'Optional. Leave empty to use v24. Each version works for about a year; use the newest from the Google Ads API release notes, written like v24.'],
        ],
    ],
    'ga4' => [
        'name' => 'Google Analytics 4', 'category' => 'Analytics', 'stage' => 1,
        'console' => 'https://console.cloud.google.com/',
        'steps' => [
            'GA4 uses the same Google Cloud project and OAuth client as Google Ads.',
            'APIs & Services > Library: enable the Google Analytics Admin API and the Google Analytics Data API.',
            'Add the Redirect URL below to the same OAuth client\'s authorised redirect URIs.',
            'Leave the fields below empty to reuse the Google Ads keys.',
        ],
        'fields' => [
            ['client_id', 'OAuth Client ID (optional)', false, 'Leave empty to reuse the Google Ads client.'],
            ['client_secret', 'OAuth Client Secret (optional)', true, 'Leave empty to reuse the Google Ads client.'],
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
            ['api_version', 'API version (optional)', false, 'Format yyyymm, e.g. 202509. Leave empty to use a recent version automatically.'],
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

    // Stage 2 (Wave 3d). Native and DSP networks connect with each agency's own API credentials: no platform keys here.
    'amazon_ads' => [
        'name' => 'Amazon Ads', 'category' => 'Retail', 'stage' => 2,
        'console' => 'https://advertising.amazon.com/API/docs/en-us/guides/onboarding/overview',
        'steps' => [
            'Create a Login with Amazon security profile at developer.amazon.com (Login with Amazon > Create a New Security Profile).',
            'Apply for Amazon Ads API access with that security profile (advertising.amazon.com/API > Apply for access). Approval can take a few weeks.',
            'In the security profile > Web Settings, add the Redirect URL below as an Allowed Return URL. Copy the Client ID and Client Secret.',
            'Region: eu covers the UAE, Saudi Arabia, Egypt and Turkey. Profiles in every region are listed after connecting.',
            'Reporting API v3 shuts off on 30 Jun 2027; the connector moves to Reporting API v1 before then.',
        ],
        'fields' => [
            ['client_id', 'Client ID', false, 'Login with Amazon security profile > Web Settings. Starts with amzn1.application-oa2-client.'],
            ['client_secret', 'Client Secret', true, 'Same screen > Show secret.'],
            ['region', 'Sign-in region', false, 'eu (default, Middle East + Europe), na or fe.'],
        ],
    ],
    'pinterest' => [
        'name' => 'Pinterest Ads', 'category' => 'Social', 'stage' => 2,
        'console' => 'https://developers.pinterest.com/apps/',
        'steps' => [
            'Sign in to developers.pinterest.com with a Pinterest business account and click Connect app.',
            'Fill in the app details (AdsRanger, adsranger.com, privacy policy URL) and submit. The app starts with Trial access (1,000 calls a day).',
            'In the app settings add the Redirect URL below. Copy the App ID and App secret key.',
            'Request Standard access when ready for customers.',
        ],
        'fields' => [
            ['client_id', 'App ID', false, 'developers.pinterest.com > My apps > your app > App ID.'],
            ['client_secret', 'App secret key', true, 'Same screen > App secret key > Show.'],
        ],
    ],
    'reddit' => [
        'name' => 'Reddit Ads', 'category' => 'Social', 'stage' => 2,
        'console' => 'https://ads.reddit.com/',
        'steps' => [
            'In Reddit Ads Manager open Business settings > Developer applications and create an app (or request Ads API access from your Reddit rep).',
            'Add the Redirect URL below as the redirect URI.',
            'Copy the App ID and Secret.',
        ],
        'fields' => [
            ['client_id', 'App ID', false, 'Business settings > Developer applications > your app.'],
            ['client_secret', 'Secret', true, 'Same screen.'],
            ['reddit_username', 'Reddit username of the app owner', false, 'Used in the User-Agent Reddit requires, e.g. adsranger_app (without u/).'],
        ],
    ],
    'taboola' => [
        'name' => 'Taboola', 'category' => 'Native', 'stage' => 2,
        'console' => 'https://developers.taboola.com/backstage-api/reference/welcome',
        'steps' => ['Nothing to set up here. Each agency asks its Taboola account manager for Backstage API credentials and enters them in Networks > Connect.'],
        'fields' => [],
    ],
    'outbrain' => [
        'name' => 'Outbrain', 'category' => 'Native', 'stage' => 2,
        'console' => 'https://developer.outbrain.com/home-page/amplify-api',
        'steps' => ['Nothing to set up here. Each agency asks Outbrain to enable Amplify API access and connects with its own login or token in Networks > Connect.'],
        'fields' => [],
    ],
    'mgid' => [
        'name' => 'MGID', 'category' => 'Native', 'stage' => 2,
        'console' => 'https://help.mgid.com/api-advertisers',
        'steps' => ['Nothing to set up here. Each agency copies its API token from the MGID dashboard (Settings > API) into Networks > Connect.'],
        'fields' => [],
    ],
    'revcontent' => [
        'name' => 'Revcontent', 'category' => 'Native', 'stage' => 2,
        'console' => 'https://help.revcontent.com/knowledge/publisher-advertiser-api-requests',
        'steps' => ['Nothing to set up here. Each agency asks its Revcontent rep to enable API access and enters its Stats API credentials in Networks > Connect.'],
        'fields' => [],
    ],
    'apple_ads' => [
        'name' => 'Apple Ads (App Store)', 'category' => 'Search', 'stage' => 2,
        'console' => 'https://developer.apple.com/documentation/apple-ads-platform-api',
        'steps' => ['Nothing to set up here. Each agency creates an API user in Apple Ads, uploads a public key and enters the IDs and private key in Networks > Connect. Built on the Apple Ads Platform API (Search Ads API v5 shuts off on 26 Jan 2027).'],
        'fields' => [],
    ],
    'yahoo_dsp' => [
        'name' => 'Yahoo DSP (incl. Native)', 'category' => 'Programmatic', 'stage' => 2,
        'console' => 'https://help.yahooinc.com/dsp-api/docs/setup-access-guide',
        'steps' => ['Nothing to set up here. Each agency gets an API client ID and secret for its DSP seat from Yahoo and enters them in Networks > Connect.'],
        'fields' => [],
    ],
];

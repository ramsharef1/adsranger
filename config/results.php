<?php

/*
 | Goal-based results (spec module 2). A campaign's result follows its ad set optimisation goal;
 | if none, the campaign objective. Each entry: [result_type, list of Meta action types, first match wins].
 | Special sources: "@reach", "@impressions", "@thruplay", "@link_clicks" read the insight fields instead of actions.
 */
return [
    'labels' => [
        'purchase' => ['Purchases', 'المشتريات'],
        'lead' => ['Leads', 'العملاء المحتملون'],
        'message' => ['Conversations', 'المحادثات'],
        'call' => ['Calls', 'المكالمات'],
        'link_click' => ['Link clicks', 'نقرات الرابط'],
        'landing_page_view' => ['Landing page views', 'مشاهدات الصفحة'],
        'thruplay' => ['ThruPlays', 'مشاهدات الفيديو'],
        'reach' => ['Reach', 'الوصول'],
        'impressions' => ['Impressions', 'مرات الظهور'],
        'engagement' => ['Engagements', 'التفاعلات'],
        'page_like' => ['Page likes', 'إعجابات الصفحة'],
        'app_install' => ['App installs', 'تثبيتات التطبيق'],
        'registration' => ['Registrations', 'التسجيلات'],
        'conversion' => ['Conversions', 'التحويلات'],
        'profile_visit' => ['Profile visits', 'زيارات الملف'],
    ],

    // Result types that carry purchase value, so their spend counts toward ROAS.
    'roas_types' => ['purchase', 'conversion'],

    'actions' => [
        'purchase' => ['offsite_conversion.fb_pixel_purchase', 'purchase', 'onsite_web_purchase', 'omni_purchase', 'onsite_conversion.purchase'],
        'lead' => ['lead', 'onsite_conversion.lead_grouped', 'offsite_conversion.fb_pixel_lead', 'onsite_web_lead', 'leadgen_grouped'],
        'message' => ['onsite_conversion.messaging_conversation_started_7d', 'onsite_conversion.total_messaging_connection'],
        'call' => ['click_to_call_native_call_placed', 'click_to_call_call_confirm'],
        'call_confirmed' => ['click_to_call_call_confirm'],
        'call_20s' => ['click_to_call_native_20s_call_connect'],
        'landing_page_view' => ['landing_page_view', 'omni_landing_page_view'],
        'engagement' => ['post_engagement', 'page_engagement'],
        'page_like' => ['like', 'onsite_conversion.page_like'],
        'app_install' => ['mobile_app_install', 'omni_app_install', 'app_install'],
        'registration' => ['offsite_conversion.fb_pixel_complete_registration', 'complete_registration', 'omni_complete_registration'],
        'add_to_cart' => ['offsite_conversion.fb_pixel_add_to_cart', 'add_to_cart', 'omni_add_to_cart'],
        'checkout' => ['offsite_conversion.fb_pixel_initiate_checkout', 'initiate_checkout', 'omni_initiated_checkout'],
        'profile_visit' => ['ig_profile_visit', 'onsite_conversion.profile_visit'],
    ],

    // Meta ad set optimization_goal → result type.
    'meta_goals' => [
        'OFFSITE_CONVERSIONS' => 'conversion', 'VALUE' => 'purchase', 'LEAD_GENERATION' => 'lead', 'QUALITY_LEAD' => 'lead',
        'CONVERSATIONS' => 'message', 'QUALITY_CALL' => 'call', 'LINK_CLICKS' => 'link_click',
        'LANDING_PAGE_VIEWS' => 'landing_page_view', 'THRUPLAY' => 'thruplay', 'TWO_SECOND_CONTINUOUS_VIDEO_VIEWS' => 'thruplay',
        'REACH' => 'reach', 'IMPRESSIONS' => 'impressions', 'AD_RECALL_LIFT' => 'reach', 'POST_ENGAGEMENT' => 'engagement',
        'PAGE_LIKES' => 'page_like', 'APP_INSTALLS' => 'app_install', 'PROFILE_VISIT' => 'profile_visit',
        'ENGAGED_USERS' => 'engagement', 'EVENT_RESPONSES' => 'engagement', 'MESSAGING_PURCHASE_CONVERSION' => 'purchase',
    ],

    // Meta campaign objective → result type, used when the ad set has no goal.
    'meta_objectives' => [
        'OUTCOME_SALES' => 'purchase', 'OUTCOME_LEADS' => 'lead', 'OUTCOME_TRAFFIC' => 'link_click', 'OUTCOME_ENGAGEMENT' => 'engagement',
        'OUTCOME_AWARENESS' => 'reach', 'OUTCOME_APP_PROMOTION' => 'app_install', 'CONVERSIONS' => 'conversion', 'LEAD_GENERATION' => 'lead',
        'MESSAGES' => 'message', 'LINK_CLICKS' => 'link_click', 'VIDEO_VIEWS' => 'thruplay', 'REACH' => 'reach', 'BRAND_AWARENESS' => 'reach',
        'POST_ENGAGEMENT' => 'engagement', 'PAGE_LIKES' => 'page_like', 'APP_INSTALLS' => 'app_install',
    ],

    // Conversion goal with a pixel event: promoted_object.custom_event_type → result type.
    'meta_custom_events' => [
        'PURCHASE' => 'purchase', 'LEAD' => 'lead', 'COMPLETE_REGISTRATION' => 'registration', 'ADD_TO_CART' => 'add_to_cart',
        'INITIATED_CHECKOUT' => 'checkout', 'CONTACT' => 'lead', 'SUBMIT_APPLICATION' => 'lead', 'SCHEDULE' => 'lead', 'DONATE' => 'purchase',
    ],

    // Google Ads conversion action category → our counter.
    'google_categories' => [
        'PURCHASE' => 'purchases', 'STORE_SALE' => 'purchases', 'PHONE_CALL_LEAD' => 'calls', 'SUBMIT_LEAD_FORM' => 'leads',
        'CONTACT' => 'leads', 'BOOK_APPOINTMENT' => 'leads', 'REQUEST_QUOTE' => 'leads', 'GET_DIRECTIONS' => 'leads',
        'QUALIFIED_LEAD' => 'leads', 'CONVERTED_LEAD' => 'leads', 'SIGNUP' => 'leads', 'ADD_TO_CART' => 'add_to_cart',
        'BEGIN_CHECKOUT' => 'checkouts', 'PAGE_VIEW' => 'landing_views',
    ],
];

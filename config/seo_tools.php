<?php

/*
 * Free SEO tools (SEO slice 0 step 0.4, PR-015 Part 2). While `public` is false the tool pages answer at their URLs
 * for checks (Product EX check, Growth live check) but send noindex, stay out of the sitemap and the menu, and no
 * page links to them. Rami approves each publish (SEO_Slice0_Spec 0.4 gate) before this is switched on.
 */
return [
    'public' => (bool) env('SEO_TOOLS_PUBLIC', false),
];

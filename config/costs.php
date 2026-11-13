<?php

// A-4 (R2-70): the monthly platform cost cap Rami set (1,000 USD). The /admin cost page compares typed costs against it.
return [
    'monthly_cap_usd' => (float) env('COSTS_MONTHLY_CAP_USD', 1000),
];

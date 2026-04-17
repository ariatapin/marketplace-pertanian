<?php

return [
    'enabled' => (bool) env('RECOMMENDATION_ENABLED', true),

    'consumer' => [
        'rule_key' => env('RECOMMENDATION_CONSUMER_RULE_KEY', 'consumer_spraying_followup'),
        'product_keywords' => array_filter(array_map('trim', explode(',', (string) env('RECOMMENDATION_CONSUMER_KEYWORDS', 'pupuk')))),
        'trigger_days_after_purchase' => (int) env('RECOMMENDATION_CONSUMER_TRIGGER_DAYS', 7),
        'trigger_window_days' => (int) env('RECOMMENDATION_CONSUMER_TRIGGER_WINDOW_DAYS', 7),
        'lookback_days' => (int) env('RECOMMENDATION_CONSUMER_LOOKBACK_DAYS', 45),
        'humidity_min' => (int) env('RECOMMENDATION_CONSUMER_HUMIDITY_MIN', 70),
        'clear_keywords' => array_filter(array_map('trim', explode(',', (string) env('RECOMMENDATION_CONSUMER_CLEAR_KEYWORDS', 'clear,cerah,sunny')))),
        'behavior_lookback_days' => (int) env('RECOMMENDATION_CONSUMER_BEHAVIOR_LOOKBACK_DAYS', 60),
        'behavior_history_limit' => (int) env('RECOMMENDATION_CONSUMER_BEHAVIOR_HISTORY_LIMIT', 300),
        'behavior_min_samples' => (int) env('RECOMMENDATION_CONSUMER_BEHAVIOR_MIN_SAMPLES', 3),
        'behavior_window_hours' => (int) env('RECOMMENDATION_CONSUMER_BEHAVIOR_WINDOW_HOURS', 2),
    ],

    'mitra' => [
        'rule_key' => env('RECOMMENDATION_MITRA_RULE_KEY', 'mitra_demand_forecast_pesticide'),
        'product_keywords' => array_filter(array_map('trim', explode(',', (string) env('RECOMMENDATION_MITRA_KEYWORDS', 'pupuk')))),
        'lookback_days' => (int) env('RECOMMENDATION_MITRA_LOOKBACK_DAYS', 7),
        'min_distinct_buyers' => (int) env('RECOMMENDATION_MITRA_MIN_DISTINCT_BUYERS', 20),
        'target_window_days' => env('RECOMMENDATION_MITRA_TARGET_WINDOW_DAYS', '7-10'),
        'allowed_weather_severities' => array_filter(array_map('trim', explode(',', (string) env('RECOMMENDATION_MITRA_ALLOWED_SEVERITIES', 'green,yellow')))),
        'vegetative_temp_min' => (float) env('RECOMMENDATION_MITRA_VEGETATIVE_TEMP_MIN', 20),
        'vegetative_temp_max' => (float) env('RECOMMENDATION_MITRA_VEGETATIVE_TEMP_MAX', 33),
        'vegetative_humidity_min' => (int) env('RECOMMENDATION_MITRA_VEGETATIVE_HUMIDITY_MIN', 55),
        'vegetative_humidity_max' => (int) env('RECOMMENDATION_MITRA_VEGETATIVE_HUMIDITY_MAX', 95),
        'behavior_lookback_days' => (int) env('RECOMMENDATION_MITRA_BEHAVIOR_LOOKBACK_DAYS', 30),
        'behavior_history_limit' => (int) env('RECOMMENDATION_MITRA_BEHAVIOR_HISTORY_LIMIT', 300),
        'behavior_min_samples' => (int) env('RECOMMENDATION_MITRA_BEHAVIOR_MIN_SAMPLES', 3),
        'behavior_window_hours' => (int) env('RECOMMENDATION_MITRA_BEHAVIOR_WINDOW_HOURS', 2),
    ],

    'seller' => [
        'rule_key' => env('RECOMMENDATION_SELLER_RULE_KEY', 'seller_demand_harvest_ops'),
        'lookback_days' => (int) env('RECOMMENDATION_SELLER_LOOKBACK_DAYS', 7),
        'min_paid_orders' => (int) env('RECOMMENDATION_SELLER_MIN_PAID_ORDERS', 5),
        'min_total_qty' => (int) env('RECOMMENDATION_SELLER_MIN_TOTAL_QTY', 10),
        'target_window_days' => env('RECOMMENDATION_SELLER_TARGET_WINDOW_DAYS', '3-5'),
        'allowed_weather_severities' => array_filter(array_map('trim', explode(',', (string) env('RECOMMENDATION_SELLER_ALLOWED_SEVERITIES', 'green,yellow')))),
        'harvest_temp_min' => (float) env('RECOMMENDATION_SELLER_HARVEST_TEMP_MIN', 20),
        'harvest_temp_max' => (float) env('RECOMMENDATION_SELLER_HARVEST_TEMP_MAX', 34),
        'harvest_humidity_min' => (int) env('RECOMMENDATION_SELLER_HARVEST_HUMIDITY_MIN', 50),
        'harvest_humidity_max' => (int) env('RECOMMENDATION_SELLER_HARVEST_HUMIDITY_MAX', 95),
        'behavior_lookback_days' => (int) env('RECOMMENDATION_SELLER_BEHAVIOR_LOOKBACK_DAYS', 30),
        'behavior_history_limit' => (int) env('RECOMMENDATION_SELLER_BEHAVIOR_HISTORY_LIMIT', 300),
        'behavior_min_samples' => (int) env('RECOMMENDATION_SELLER_BEHAVIOR_MIN_SAMPLES', 3),
        'behavior_window_hours' => (int) env('RECOMMENDATION_SELLER_BEHAVIOR_WINDOW_HOURS', 2),
    ],

    'sync' => [
        'enabled' => (bool) env('RECOMMENDATION_SYNC_ENABLED', true),
        'cron' => env('RECOMMENDATION_SYNC_CRON', '23 * * * *'),
        'chunk' => (int) env('RECOMMENDATION_SYNC_CHUNK', 200),
    ],
];

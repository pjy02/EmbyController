<?php
return [
    'api_key' => env('AI_API_KEY', ''),
    'base_url' => env('AI_BASE_URL', 'https://api.openai.com'),
    'model' => env('AI_MODEL', 'gpt-3.5-turbo'),
]; 
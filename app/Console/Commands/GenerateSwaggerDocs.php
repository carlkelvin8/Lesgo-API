<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateSwaggerDocs extends Command
{
    protected $signature   = 'swagger:generate';
    protected $description = 'Generate Swagger/OpenAPI documentation (suppresses non-fatal warnings)';

    public function handle(): int
    {
        // Set the analyser at runtime (can't store object instances in config:cache)
        config(['l5-swagger.defaults.scanOptions.analyser' => new \OpenApi\Analysers\ReflectionAnalyser([
            new \OpenApi\Analysers\DocBlockAnnotationFactory(),
            new \OpenApi\Analysers\AttributeAnnotationFactory(),
        ])]);

        // Suppress E_USER_WARNING so swagger-php PathItem warnings don't abort generation
        $previous = set_error_handler(function (int $errno, string $errstr) use (&$previous): bool {
            if ($errno === E_USER_WARNING || $errno === E_USER_NOTICE) {
                $this->warn("[swagger-php] {$errstr}");
                return true;
            }
            return $previous ? (bool) ($previous)($errno, $errstr) : false;
        });

        try {
            $this->call('l5-swagger:generate');
            $this->info('Swagger docs generated successfully.');
        } finally {
            restore_error_handler();
        }

        return self::SUCCESS;
    }
}

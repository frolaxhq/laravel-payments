<?php

namespace Frolax\Payment\Services;

class WebhookRouter
{
    protected array $routes = [];

    public function __construct()
    {
        $this->routes = config('payments.webhooks.routes', []);
    }

    /**
     * Register a webhook handler for an event pattern.
     */
    public function route(string $eventPattern, string $handlerClass): void
    {
        $this->routes[$eventPattern] = $handlerClass;
    }

    /**
     * Resolve handler class for a given event type.
     */
    public function resolve(string $eventType): ?string
    {
        // Exact match
        if (isset($this->routes[$eventType])) {
            return $this->routes[$eventType];
        }

        // Wildcard match (e.g., "payment.*" matches "payment.completed")
        foreach ($this->routes as $pattern => $handler) {
            if (str_contains($pattern, '*')) {
                $regex = str_replace('.', '\\.', $pattern);
                $regex = str_replace('*', '.*', $regex);
                if (preg_match("/^{$regex}$/", $eventType)) {
                    return $handler;
                }
            }
        }

        return null;
    }

    /**
     * Get all registered routes.
     */
    public function routes(): array
    {
        return $this->routes;
    }
}

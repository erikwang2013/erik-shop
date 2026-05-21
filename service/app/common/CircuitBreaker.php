<?php
namespace app\common;

class CircuitBreaker {
    private static string $prefix = "erik:cb:";
    private int $threshold;
    private int $timeout;
    private string $name;

    public function __construct(string $name, int $failureThreshold = 5, int $timeoutSeconds = 60) {
        $this->name = $name; $this->threshold = $failureThreshold; $this->timeout = $timeoutSeconds;
    }
    public function isOpen(): bool {
        try { return (int)redis()->get(self::$prefix.$this->name.":open") === 1; }
        catch(\Throwable $e) { return false; }
    }
    public function recordSuccess(): void {
        try { redis()->del(self::$prefix.$this->name.":failures"); redis()->del(self::$prefix.$this->name.":open"); }
        catch(\Throwable $e) {}
    }
    public function recordFailure(): void {
        try {
            $count = redis()->incr(self::$prefix.$this->name.":failures");
            if ($count === 1) redis()->expire(self::$prefix.$this->name.":failures", $this->timeout);
            if ($count >= $this->threshold) {
                redis()->setEx(self::$prefix.$this->name.":open", $this->timeout, "1");
            }
        } catch(\Throwable $e) {}
    }
    public static function quick(string $name, callable $fn, $fallback = null): mixed {
        static $breakers = [];
        if (!isset($breakers[$name])) $breakers[$name] = new self($name);
        $cb = $breakers[$name];
        if ($cb->isOpen()) return $fallback;
        try { $result = $fn(); $cb->recordSuccess(); return $result; }
        catch(\Throwable $e) { $cb->recordFailure(); return $fallback; }
    }
}

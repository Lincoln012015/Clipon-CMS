<?php

class AdminActionRouter
{
    private array $handlers = [];

    public function on(string $action, callable $handler): self
    {
        $this->handlers[$action] = $handler;
        return $this;
    }

    public function dispatch(string $action, ?callable $unknownHandler = null): bool
    {
        if (!isset($this->handlers[$action])) {
            if ($unknownHandler !== null) {
                $unknownHandler($action);
                return true;
            }
            return false;
        }

        ($this->handlers[$action])($action);
        return true;
    }
}

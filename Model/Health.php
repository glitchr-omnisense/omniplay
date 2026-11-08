<?php

namespace Omniplay\Model;

/** What a server says of itself in its heartbeats. */
enum Health: string
{
    /** Started, not yet ready: seated only when no ready server has room (it was started for that). */
    case Starting = 'starting';
    /** Ready, taking players. */
    case Healthy = 'healthy';
    /** Finishing its matches: seats nobody new. */
    case Draining = 'draining';
    /** Stopping or stopped: seats nobody. */
    case Down = 'down';

    /** Whether a player may be seated on a server in this state. */
    public function seats(): bool
    {
        return self::Starting === $this || self::Healthy === $this;
    }
}
